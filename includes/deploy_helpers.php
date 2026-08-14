<?php
/**
 * Fonctions utilitaires pour déploiement WAMP → serveur local.
 * PHP procédural — pas de classes.
 */

function deploy_init_realtime_output() {
    @ini_set('output_buffering', '0');
    @ini_set('implicit_flush', '1');
    @ini_set('zlib.output_compression', '0');
    while (ob_get_level() > 0) {
        @ob_end_flush();
    }
    if (function_exists('ob_implicit_flush')) {
        ob_implicit_flush(true);
    }
}

function deploy_flush_output() {
    if (ob_get_level() > 0) {
        @ob_flush();
    }
    @flush();
}

function deploy_log($msg) {
    echo '[deploy ' . date('H:i:s') . '] ' . $msg . PHP_EOL;
    deploy_flush_output();
}

function deploy_fail($msg, $code = 1) {
    fwrite(STDERR, '[deploy ' . date('H:i:s') . '] ERREUR : ' . $msg . PHP_EOL);
    deploy_flush_output();
    exit($code);
}

function deploy_mask_cmd($cmd) {
    return preg_replace('/(-p|--password=)[^\s]*/', '$1***', $cmd);
}

function deploy_run($cmd, $dry_run = false) {
    deploy_log('$ ' . deploy_mask_cmd($cmd));
    if ($dry_run) {
        return ['code' => 0, 'output' => ['(dry-run)']];
    }
    if (DIRECTORY_SEPARATOR === '\\' && stripos($cmd, 'cmd /c ') !== 0) {
        $cmd = 'cmd /c ' . $cmd;
    }
    $output = [];
    $code = 0;
    exec($cmd . ' 2>&1', $output, $code);
    return ['code' => $code, 'output' => $output];
}

function deploy_run_redirect($cmd, $dry_run = false) {
    deploy_log('$ ' . deploy_mask_cmd($cmd));
    if ($dry_run) {
        return ['code' => 0, 'output' => ['(dry-run)']];
    }
    if (DIRECTORY_SEPARATOR === '\\' && stripos($cmd, 'cmd /c ') !== 0) {
        $cmd = 'cmd /c ' . $cmd;
    }
    $err_file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fouta_stderr_' . getmypid() . '.log';
    $err_q = escapeshellarg(str_replace('\\', '/', $err_file));
    $output = [];
    $code = 0;
    exec($cmd . ' 2>' . $err_q, $output, $code);
    if (is_file($err_file)) {
        $err_lines = file($err_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (is_array($err_lines) && $err_lines !== []) {
            $output = array_merge($output, $err_lines);
        }
        @unlink($err_file);
    }
    return ['code' => $code, 'output' => $output];
}

function deploy_scp_from_server_retry(array $ssh, $remote_path, $local_path, $dry_run, $attempts = 3) {
    $last = ['code' => 1, 'output' => []];
    for ($i = 1; $i <= $attempts; $i++) {
        $last = deploy_scp_from_server($ssh, $remote_path, $local_path, $dry_run);
        if ($last['code'] === 0 && is_file($local_path) && filesize($local_path) >= 500) {
            return $last;
        }
        if ($i < $attempts) {
            deploy_log('SCP échoué (tentative ' . $i . '/' . $attempts . '), nouvel essai dans 3 s...');
            sleep(3);
        }
    }
    return $last;
}

function deploy_remote_file_size(array $server, $remote_path, $dry_run) {
    if ($dry_run) {
        return 0;
    }
    $res = deploy_ssh_run($server, 'wc -c < ' . $remote_path . ' 2>/dev/null || echo 0', false);
    if ($res['code'] !== 0 || empty($res['output'])) {
        return 0;
    }
    return (int) trim((string) ($res['output'][0] ?? '0'));
}

function deploy_write_mysql_cnf(array $db_cfg, $prefix = 'client') {
    $file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fouta_mysql_' . $prefix . '_' . getmypid() . '.cnf';
    $content = "[client]\n"
        . 'host=' . ($db_cfg['host'] ?? 'localhost') . "\n"
        . 'port=' . (int) ($db_cfg['port'] ?? 3306) . "\n"
        . 'user=' . ($db_cfg['user'] ?? '') . "\n"
        . 'password=' . ($db_cfg['pass'] ?? '') . "\n";
    file_put_contents($file, $content);
    if (function_exists('chmod')) {
        @chmod($file, 0600);
    }
    return $file;
}

function deploy_find_mysql_bin($configured = '') {
    $configured = rtrim(str_replace('\\', '/', (string) $configured), '/');
    if ($configured !== '' && is_dir($configured)) {
        return str_replace('/', DIRECTORY_SEPARATOR, $configured);
    }

    $candidates = [
        'C:/wamp64/bin/mysql',
        'C:/wamp/bin/mysql',
    ];
    foreach ($candidates as $base) {
        $base = str_replace('/', DIRECTORY_SEPARATOR, $base);
        if (!is_dir($base)) {
            continue;
        }
        foreach (glob($base . DIRECTORY_SEPARATOR . 'mysql*' . DIRECTORY_SEPARATOR . 'bin') ?: [] as $bin) {
            if (is_file($bin . DIRECTORY_SEPARATOR . 'mysqldump.exe') || is_file($bin . DIRECTORY_SEPARATOR . 'mysqldump')) {
                return $bin;
            }
        }
    }

    return '';
}

function deploy_mysql_tools(array $wamp_cfg) {
    $bin = deploy_find_mysql_bin($wamp_cfg['mysql_bin'] ?? '');
    if ($bin !== '') {
        $ext = (DIRECTORY_SEPARATOR === '\\') ? '.exe' : '';
        return [
            'mysql' => $bin . DIRECTORY_SEPARATOR . 'mysql' . $ext,
            'mysqldump' => $bin . DIRECTORY_SEPARATOR . 'mysqldump' . $ext,
        ];
    }
    return ['mysql' => 'mysql', 'mysqldump' => 'mysqldump'];
}

function deploy_wamp_db_cfg(array $wamp, $project_root) {
    $name = $wamp['db_name'] ?? '';
    $user = $wamp['db_user'] ?? '';
    $pass = $wamp['db_pass'] ?? null;
    if ($name === '' || $user === '' || $pass === null) {
        $conn = $project_root . '/conn/conn.php';
        if (is_file($conn)) {
            $db_host = $db_name = $db_user = $db_pass = null;
            include $conn;
            return [
                'host' => $wamp['db_host'] ?? ($db_host ?? 'localhost'),
                'port' => 3306,
                'name' => $name ?: ($db_name ?? 'fouta3'),
                'user' => $user ?: ($db_user ?? 'root'),
                'pass' => $pass !== null && $pass !== '' ? $pass : ($db_pass ?? ''),
            ];
        }
    }
    return [
        'host' => $wamp['db_host'] ?? 'localhost',
        'port' => 3306,
        'name' => $name,
        'user' => $user,
        'pass' => $pass ?? '',
    ];
}

function deploy_dump_database(array $db_cfg, $mysqldump, $dump_file, $dry_run) {
    $cnf = deploy_write_mysql_cnf($db_cfg, 'dump');
    $cnf_path = str_replace('\\', '/', $cnf);
    $cmd = escapeshellarg($mysqldump)
        . ' --defaults-extra-file=' . escapeshellarg($cnf_path)
        . ' --single-transaction --quick --routines --triggers --set-gtid-purged=OFF '
        . escapeshellarg($db_cfg['name'])
        . ' > ' . escapeshellarg(str_replace('\\', '/', $dump_file));

    $res = deploy_run($cmd, $dry_run);
    @unlink($cnf);

    if ($dry_run) {
        return true;
    }
    if ($res['code'] !== 0 || !is_file($dump_file) || filesize($dump_file) < 500) {
        $err = trim(implode("\n", $res['output'] ?? []));
        if ($err !== '') {
            deploy_log('Dump local échoué : ' . $err);
        } elseif (is_file($dump_file)) {
            deploy_log('Dump local échoué : fichier trop petit (' . filesize($dump_file) . ' octets).');
        } else {
            deploy_log('Dump local échoué : fichier non créé.');
        }
        return false;
    }
    return true;
}

/**
 * Export BDD production via fplserver (contourne MySQL 9 WAMP + mysql_native_password).
 * Flux principal : mysqldump sur stdout via SSH (évite SCP d'un gros fichier).
 */
function deploy_dump_database_via_jump_server(array $server, array $prod_db, $dump_file, $dry_run) {
    $remote_dump = deploy_remote_path('prod_jump') . '.sql';
    $remote_cnf = deploy_remote_path('mysql_jump') . '.cnf';
    $name = preg_replace('/[^a-zA-Z0-9_]/', '', (string) ($prod_db['name'] ?? ''));

    deploy_log('Export via serveur local ' . ($server['host'] ?? '') . ' ...');

    if ($dry_run) {
        deploy_log('(dry-run) mysqldump via fplserver avec fichier .cnf');
        return true;
    }

    $local_cnf = deploy_write_mysql_cnf($prod_db, 'jump');
    $scp_cnf = deploy_scp_to_server($server, $local_cnf, $remote_cnf, false);
    if ($scp_cnf['code'] !== 0) {
        @unlink($local_cnf);
        deploy_log('Impossible d\'envoyer la config MySQL sur fplserver : ' . implode("\n", $scp_cnf['output']));
        return false;
    }

    $remote_dump_cmd = 'chmod 600 ' . $remote_cnf
        . ' && mysqldump --defaults-extra-file=' . $remote_cnf
        . ' --single-transaction --quick --routines --triggers --set-gtid-purged=OFF '
        . $name . '; EC=$?; rm -f ' . $remote_cnf . '; exit $EC';

    deploy_log('Export mysqldump via SSH (flux direct)...');
    deploy_log('Patientez — export BDD production en cours...');
    $dump_path = str_replace('\\', '/', $dump_file);
    $stream_cmd = 'ssh ' . deploy_ssh_port_opts($server) . ' '
        . escapeshellarg(deploy_ssh_target($server)) . ' '
        . escapeshellarg($remote_dump_cmd)
        . ' > ' . escapeshellarg($dump_path);

    $res = deploy_run_redirect($stream_cmd, false);
    if ($res['code'] === 0 && is_file($dump_file) && filesize($dump_file) >= 500) {
        @unlink($local_cnf);
        return true;
    }

    if (!empty($res['output'])) {
        deploy_log('Flux SSH direct échoué : ' . implode("\n", $res['output']));
    } elseif (is_file($dump_file)) {
        deploy_log('Flux SSH direct : dump trop petit (' . filesize($dump_file) . ' octets).');
    }

    deploy_log('Repli : mysqldump sur fplserver puis SCP...');
    $scp_cnf2 = deploy_scp_to_server($server, $local_cnf, $remote_cnf, false);
    @unlink($local_cnf);
    if ($scp_cnf2['code'] !== 0) {
        deploy_log('Re-envoi config MySQL impossible : ' . implode("\n", $scp_cnf2['output']));
        return false;
    }
    $remote_cmd = 'chmod 600 ' . $remote_cnf
        . ' && mysqldump --defaults-extra-file=' . $remote_cnf
        . ' --single-transaction --quick --routines --triggers --set-gtid-purged=OFF '
        . $name . ' > ' . $remote_dump
        . '; EC=$?; rm -f ' . $remote_cnf . '; exit $EC';

    $res = deploy_ssh_run($server, $remote_cmd, false);
    if ($res['code'] !== 0) {
        deploy_log('Dump via fplserver échoué : ' . implode("\n", $res['output']));
        deploy_ssh_run($server, 'rm -f ' . $remote_dump, false);
        return false;
    }

    $remote_size = deploy_remote_file_size($server, $remote_dump, false);
    if ($remote_size < 500) {
        deploy_log('Dump distant invalide (' . $remote_size . ' octets).');
        if (!empty($res['output'])) {
            deploy_log(implode("\n", $res['output']));
        }
        deploy_ssh_run($server, 'rm -f ' . $remote_dump, false);
        return false;
    }
    deploy_log('Dump distant : ' . round($remote_size / 1024 / 1024, 1) . ' Mo — téléchargement SCP...');

    $res2 = deploy_scp_from_server_retry($server, $remote_dump, $dump_file, false);
    deploy_ssh_run($server, 'rm -f ' . $remote_dump, false);

    if ($res2['code'] !== 0 || !is_file($dump_file) || filesize($dump_file) < 500) {
        deploy_log('Dump via fplserver : fichier invalide ou SCP échoué.');
        if (!empty($res2['output'])) {
            deploy_log(implode("\n", $res2['output']));
        }
        return false;
    }
    return true;
}

function deploy_analyze_sql_dump($dump_file) {
    if (!is_file($dump_file) || filesize($dump_file) < 500) {
        return ['ok' => false, 'size_mb' => 0, 'tables' => 0, 'completed' => false];
    }
    $size_mb = round(filesize($dump_file) / 1024 / 1024, 2);
    $sample = file_get_contents($dump_file, false, null, 0, min(filesize($dump_file), 512000));
    $tail = file_get_contents($dump_file, false, null, max(0, filesize($dump_file) - 512));
    $tables = substr_count($sample, 'CREATE TABLE');
    if ($tables === 0) {
        $tables = substr_count(file_get_contents($dump_file), 'CREATE TABLE');
    }
    $completed = stripos($tail, 'Dump completed') !== false
        || (stripos($tail, 'COMMIT') !== false && $tables > 0);
    return [
        'ok' => $completed && $tables > 0,
        'size_mb' => $size_mb,
        'tables' => $tables,
        'completed' => $completed,
    ];
}

function deploy_log_sql_dump_stats($dump_file, $label = 'Dump SQL') {
    $stats = deploy_analyze_sql_dump($dump_file);
    if (!$stats['ok']) {
        deploy_log($label . ' : ' . $stats['size_mb'] . ' Mo — vérification incomplète ('
            . $stats['tables'] . ' tables détectées).');
        return $stats;
    }
    deploy_log($label . ' : ' . $stats['size_mb'] . ' Mo, ' . $stats['tables']
        . ' tables — export complet (pas de limite de taille appliquée).');
    deploy_log('Note : le fichier .sql est compact ; MySQL utilise plus d’espace disque après import (index).');
    return $stats;
}

function deploy_dump_production_via_ssh(array $ssh, array $prod_db, $dump_file, $dry_run) {
    $host = trim((string) ($ssh['host'] ?? ''));
    $user = trim((string) ($ssh['user'] ?? ''));
    if ($host === '' || $user === '') {
        return false;
    }

    $name = preg_replace('/[^a-zA-Z0-9_]/', '', (string) ($prod_db['name'] ?? ''));
    $db_user = (string) ($prod_db['user'] ?? '');
    $db_pass = (string) ($prod_db['pass'] ?? '');
    if ($name === '' || $db_user === '') {
        return false;
    }

    deploy_log('Export BDD production via SSH (' . $user . '@' . $host . ')...');
    if ($dry_run) {
        return true;
    }

    $remote_dump = 'mysqldump -h localhost -u ' . escapeshellarg($db_user)
        . ' -p' . escapeshellarg($db_pass)
        . ' --single-transaction --quick --routines --triggers --set-gtid-purged=OFF '
        . escapeshellarg($name);

    $dump_path = str_replace('\\', '/', $dump_file);
    $cmd = 'ssh ' . deploy_ssh_port_opts($ssh) . ' '
        . escapeshellarg($user . '@' . $host) . ' '
        . escapeshellarg($remote_dump)
        . ' > ' . escapeshellarg($dump_path);

    $res = deploy_run_redirect($cmd, false);
    if ($res['code'] === 0 && is_file($dump_file) && filesize($dump_file) >= 500) {
        return true;
    }
    if (!empty($res['output'])) {
        deploy_log('Dump SSH production échoué : ' . implode("\n", $res['output']));
    }
    return false;
}

function deploy_dump_production(array $prod_db, array $tools, array $server, $dump_file, $dry_run, array $prod_ssh = []) {
    if (deploy_dump_database($prod_db, $tools['mysqldump'], $dump_file, $dry_run)) {
        return true;
    }
    if (!empty($prod_ssh['host']) && !empty($prod_ssh['user'])) {
        deploy_log('Dump MySQL distant impossible — repli SSH production.');
        if (deploy_dump_production_via_ssh($prod_ssh, $prod_db, $dump_file, $dry_run)) {
            return true;
        }
    }
    if (!empty($server['host']) && !empty($server['user'])) {
        deploy_log('Repli : export production via fplserver (MySQL 9 WAMP incompatible mysql_native_password).');
        return deploy_dump_database_via_jump_server($server, $prod_db, $dump_file, $dry_run);
    }
    return false;
}

function deploy_mysql_exec_file($mysql, $cnf, $sql_file, $db_name, $dry_run) {
    $mysql_q = escapeshellarg($mysql);
    $cnf_q = escapeshellarg($cnf);
    $file_q = escapeshellarg($sql_file);

    if (DIRECTORY_SEPARATOR === '\\') {
        // Windows : mysql -e + --defaults-extra-file casse sous cmd /c ; pipe fiable
        $target = $mysql_q . ' --defaults-extra-file=' . $cnf_q;
        if ($db_name !== '') {
            $target .= ' ' . escapeshellarg($db_name);
        }
        $cmd = 'type ' . $file_q . ' | ' . $target;
    } else {
        $cmd = $mysql_q . ' --defaults-extra-file=' . $cnf_q;
        if ($db_name !== '') {
            $cmd .= ' ' . escapeshellarg($db_name);
        }
        $cmd .= ' < ' . $file_q;
    }
    return deploy_run($cmd, $dry_run);
}

function deploy_import_database(array $db_cfg, $mysql, $dump_file, $recreate, $dry_run) {
    $cnf = deploy_write_mysql_cnf($db_cfg, 'import');
    if ($recreate && !$dry_run) {
        $db_name = preg_replace('/[^a-zA-Z0-9_]/', '', (string) ($db_cfg['name'] ?? ''));
        $sql_file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fouta_recreate_' . getmypid() . '.sql';
        file_put_contents(
            $sql_file,
            'DROP DATABASE IF EXISTS `' . $db_name . "`;\n"
            . 'CREATE DATABASE `' . $db_name . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;' . "\n"
        );
        $res = deploy_mysql_exec_file($mysql, $cnf, $sql_file, '', $dry_run);
        @unlink($sql_file);
        if ($res['code'] !== 0) {
            @unlink($cnf);
            return $res;
        }
    }

    if (!$dry_run) {
        deploy_log('Import SQL en cours (' . round(filesize($dump_file) / 1024 / 1024, 1) . ' Mo)...');
    }

    $res = deploy_mysql_exec_file($mysql, $cnf, $dump_file, $db_cfg['name'] ?? '', $dry_run);
    @unlink($cnf);
    return $res;
}

function deploy_ssh_port_opts(array $ssh) {
    $port = (int) ($ssh['port'] ?? 22);
    $opts = '-p ' . $port . ' -o StrictHostKeyChecking=accept-new';
    if (!empty($ssh['identity_file']) && is_readable($ssh['identity_file'])) {
        $opts .= ' -i ' . escapeshellarg($ssh['identity_file']);
    }
    return $opts;
}

function deploy_scp_port_opts(array $ssh) {
    $port = (int) ($ssh['port'] ?? 22);
    $opts = '-P ' . $port . ' -o StrictHostKeyChecking=accept-new';
    if (!empty($ssh['identity_file']) && is_readable($ssh['identity_file'])) {
        $opts .= ' -i ' . escapeshellarg($ssh['identity_file']);
    }
    return $opts;
}

function deploy_ssh_target(array $ssh) {
    return ($ssh['user'] ?? '') . '@' . ($ssh['host'] ?? '');
}

function deploy_scp_remote_spec(array $ssh, $remote_path) {
    return deploy_ssh_target($ssh) . ':' . $remote_path;
}

function deploy_ssh_base(array $ssh) {
    return [
        'target' => deploy_ssh_target($ssh),
        'opts' => deploy_ssh_port_opts($ssh),
        'scp_opts' => deploy_scp_port_opts($ssh),
    ];
}

function deploy_scp_to_server(array $ssh, $local_path, $remote_path, $dry_run, $recursive = false) {
    $base = deploy_ssh_base($ssh);
    $recursive_flag = $recursive ? ' -r' : '';
    $cmd = 'scp ' . $base['scp_opts'] . $recursive_flag . ' '
        . escapeshellarg($local_path) . ' '
        . escapeshellarg(deploy_scp_remote_spec($ssh, $remote_path));
    return deploy_run($cmd, $dry_run);
}

function deploy_scp_from_server(array $ssh, $remote_path, $local_path, $dry_run) {
    $base = deploy_ssh_base($ssh);
    $cmd = 'scp ' . $base['scp_opts'] . ' '
        . escapeshellarg(deploy_scp_remote_spec($ssh, $remote_path)) . ' '
        . escapeshellarg($local_path);
    return deploy_run($cmd, $dry_run);
}

function deploy_ssh_run(array $ssh, $remote_cmd, $dry_run) {
    $target = deploy_ssh_target($ssh);
    // remote_cmd : chemins Linux sans espaces ; guillemets Windows uniquement sur la commande entière
    $cmd = 'ssh ' . deploy_ssh_port_opts($ssh) . ' ' . escapeshellarg($target) . ' ' . escapeshellarg($remote_cmd);
    return deploy_run($cmd, $dry_run);
}

function deploy_remote_path($prefix) {
    return '/tmp/fouta_' . preg_replace('/[^a-zA-Z0-9_]/', '', $prefix) . '_' . getmypid();
}

function deploy_zip_directory($source_dir, $zip_file) {
    if (!class_exists('ZipArchive')) {
        return false;
    }
    $source_dir = rtrim(str_replace('\\', '/', $source_dir), '/');
    if (!is_dir($source_dir)) {
        return false;
    }
    $zip = new ZipArchive();
    if ($zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        return false;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source_dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $item) {
        $path = str_replace('\\', '/', $item->getPathname());
        $relative = substr($path, strlen($source_dir) + 1);
        if ($item->isDir()) {
            $zip->addEmptyDir($relative);
        } else {
            $zip->addFile($item->getPathname(), $relative);
        }
    }
    $zip->close();
    return is_file($zip_file) && filesize($zip_file) > 0;
}

function deploy_ftp_is_configured(array $ftp_cfg) {
    $host = trim((string) ($ftp_cfg['host'] ?? ''));
    $user = trim((string) ($ftp_cfg['user'] ?? ''));
    if ($host === '' || $user === '') {
        return false;
    }
    if (preg_match('/^(VOTRE_|CHANGEZ|TODO|XXX)/i', $user)) {
        return false;
    }
    if (stripos($user, 'VOTRE_') !== false || stripos($user, 'CHANGEZ') !== false) {
        return false;
    }
    return true;
}

function deploy_ftp_connect(array $ftp_cfg) {
    if (!function_exists('ftp_connect')) {
        return null;
    }
    if (!deploy_ftp_is_configured($ftp_cfg)) {
        return null;
    }
    $host = $ftp_cfg['host'] ?? '';
    $port = (int) ($ftp_cfg['port'] ?? 21);
    $user = $ftp_cfg['user'] ?? '';
    $pass = $ftp_cfg['pass'] ?? '';
    $method = strtolower($ftp_cfg['method'] ?? 'ftp');
    if ($method === 'ftps' && function_exists('ftp_ssl_connect')) {
        $conn = @ftp_ssl_connect($host, $port, 30);
    } else {
        $conn = @ftp_connect($host, $port, 30);
    }
    if (!$conn || !@ftp_login($conn, $user, $pass)) {
        return null;
    }
    if (!empty($ftp_cfg['passive'])) {
        @ftp_pasv($conn, true);
    }
    return $conn;
}

function deploy_ftp_is_dir($conn, $path) {
    $current = @ftp_pwd($conn);
    $ok = @ftp_chdir($conn, $path);
    if ($ok) {
        if ($current !== false) {
            @ftp_chdir($conn, $current);
        }
        return true;
    }
    return false;
}

function deploy_ftp_download_recursive($conn, $remote_dir, $local_dir, &$count = 0) {
    $remote_dir = rtrim(str_replace('\\', '/', $remote_dir), '/');
    if (!is_dir($local_dir)) {
        mkdir($local_dir, 0775, true);
    }

    $list = @ftp_mlsd($conn, $remote_dir);
    if (!is_array($list) || $list === []) {
        $names = @ftp_nlist($conn, $remote_dir);
        if (!is_array($names)) {
            return;
        }
        $list = [];
        foreach ($names as $full) {
            $base = basename(str_replace('\\', '/', $full));
            if ($base === '.' || $base === '..') {
                continue;
            }
            $list[] = ['name' => $base, 'type' => 'unknown'];
        }
    }

    foreach ($list as $entry) {
        $name = $entry['name'] ?? '';
        if ($name === '' || $name === '.' || $name === '..') {
            continue;
        }
        $remote_path = $remote_dir . '/' . $name;
        $local_path = $local_dir . DIRECTORY_SEPARATOR . $name;
        $type = strtolower($entry['type'] ?? '');

        if ($type === 'dir' || ($type === 'unknown' && deploy_ftp_is_dir($conn, $remote_path))) {
            deploy_ftp_download_recursive($conn, $remote_path, $local_path, $count);
            continue;
        }

        if (@ftp_get($conn, $local_path, $remote_path, FTP_BINARY)) {
            $count++;
            if ($count === 1) {
                deploy_log('FTP : premier fichier téléchargé.');
            } elseif ($count % 50 === 0) {
                deploy_log('FTP : ' . $count . ' fichiers téléchargés...');
            }
        }
    }
}

function deploy_connect_wamp_db(array $wamp_db) {
    $dsn = 'mysql:host=' . ($wamp_db['host'] ?? 'localhost')
        . ';dbname=' . ($wamp_db['name'] ?? '')
        . ';charset=utf8mb4';
    return new PDO($dsn, $wamp_db['user'] ?? 'root', $wamp_db['pass'] ?? '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function deploy_apply_full_refresh_options(array $opts) {
    if (empty($opts['full_refresh'])) {
        return $opts;
    }
    return array_merge($opts, [
        'pull_from_production' => true,
        'recreate_wamp_database' => true,
        'sync_upload_to_wamp' => true,
        'push_to_local_server' => true,
        'recreate_server_database' => true,
        'sync_upload_to_server' => true,
        'wipe_upload_before_pull' => true,
        'wipe_upload_on_server' => true,
        'configure_sync' => false,
        'run_sync_migrations_on_server' => !empty($opts['run_sync_migrations_on_server']),
    ]);
}

function deploy_wipe_directory($dir, $dry_run = false) {
    $dir = rtrim(str_replace('/', DIRECTORY_SEPARATOR, $dir), DIRECTORY_SEPARATOR);
    if (!is_dir($dir)) {
        return;
    }
    deploy_log('Nettoyage complet : ' . $dir);
    if ($dry_run) {
        return;
    }
    $items = scandir($dir);
    if (!is_array($items)) {
        return;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            deploy_wipe_directory($path, false);
            @rmdir($path);
        } else {
            @unlink($path);
        }
    }
}

function deploy_is_upload_file_path($rel) {
    $rel = trim(str_replace('\\', '/', (string) $rel), '/');
    if ($rel === '' || strpos($rel, '..') !== false) {
        return false;
    }
    $base = basename($rel);
    return strpos($base, '.') !== false;
}

function deploy_collect_upload_paths_from_db(PDO $db) {
    $paths = [];
    $add = function ($raw) use (&$paths) {
        if ($raw === null || $raw === '') {
            return;
        }
        $raw = trim(str_replace('\\', '/', (string) $raw));
        if ($raw === '') {
            return;
        }
        if (strpos($raw, 'upload/') === 0) {
            $raw = substr($raw, 7);
        }
        if (!deploy_is_upload_file_path($raw)) {
            return;
        }
        $paths[$raw] = true;
        $dir = dirname($raw);
        $base = basename($raw);
        if (preg_match('/^(.+)(\.(webp|jpg|jpeg|png|gif))$/i', $base, $m)) {
            foreach (['_md', '_sm'] as $suffix) {
                $paths[($dir !== '.' ? $dir . '/' : '') . $m[1] . $suffix . $m[2]] = true;
            }
        }
    };

    $queries = [
        "SELECT image_principale AS img FROM produits WHERE image_principale IS NOT NULL AND image_principale != ''",
        "SELECT image AS img FROM categories WHERE image IS NOT NULL AND image != ''",
        "SELECT image AS img FROM slider WHERE image IS NOT NULL AND image != ''",
        "SELECT logo AS img FROM logos WHERE logo IS NOT NULL AND logo != ''",
        "SELECT image AS img FROM section4 WHERE image IS NOT NULL AND image != ''",
        "SELECT image AS img FROM trending WHERE image IS NOT NULL AND image != ''",
        "SELECT photo AS img FROM employes WHERE photo IS NOT NULL AND photo != ''",
        "SELECT images FROM produits WHERE images IS NOT NULL AND images != ''",
    ];

    foreach ($queries as $sql) {
        try {
            $stmt = $db->query($sql);
            if (!$stmt) {
                continue;
            }
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (isset($row['images'])) {
                    $dec = json_decode((string) $row['images'], true);
                    if (is_array($dec)) {
                        foreach ($dec as $img) {
                            $add($img);
                        }
                    }
                    continue;
                }
                $add($row['img'] ?? '');
            }
        } catch (Throwable $e) {
            // Table ou colonne absente — ignorer
        }
    }

    return array_keys($paths);
}

function deploy_count_files($dir) {
    $dir = rtrim(str_replace('/', DIRECTORY_SEPARATOR, $dir), DIRECTORY_SEPARATOR);
    if (!is_dir($dir)) {
        return 0;
    }
    $count = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $item) {
        if ($item->isFile()) {
            $count++;
        }
    }
    return $count;
}

function deploy_http_download_file($url, $local_path, $timeout = 60) {
    if (is_dir($local_path)) {
        return false;
    }
    $dir = dirname($local_path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    if (is_file($local_path) && filesize($local_path) > 0) {
        return true;
    }

    if (!function_exists('curl_init')) {
        $ctx = stream_context_create(['http' => ['timeout' => $timeout], 'ssl' => ['verify_peer' => true]]);
        $data = @file_get_contents($url, false, $ctx);
        if ($data === false || $data === '') {
            return false;
        }
        return file_put_contents($local_path, $data) !== false;
    }

    $fp = @fopen($local_path, 'wb');
    if (!$fp) {
        return false;
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FILE => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $ca = dirname(__DIR__) . '/config/cacert.pem';
    if (is_file($ca)) {
        curl_setopt($ch, CURLOPT_CAINFO, $ca);
    }
    $ok = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fp);
    if (!$ok || $code >= 400) {
        @unlink($local_path);
        return false;
    }
    return is_file($local_path) && filesize($local_path) > 0;
}

function deploy_sync_upload_http_from_db($site_url, PDO $db, $local_upload, array $files_cfg, $dry_run) {
    $site_url = rtrim((string) $site_url, '/');
    $paths = deploy_collect_upload_paths_from_db($db);
    deploy_log('Téléchargement HTTP production → WAMP (' . count($paths) . ' fichiers référencés en BDD)...');
    if ($dry_run) {
        return;
    }

    $ok = 0;
    $fail = 0;
    $skip = 0;
    foreach ($paths as $rel) {
        if (!deploy_is_upload_file_path($rel)) {
            $skip++;
            continue;
        }
        $url = $site_url . '/upload/' . ltrim(str_replace('\\', '/', $rel), '/');
        $local = rtrim($local_upload, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (deploy_http_download_file($url, $local)) {
            $ok++;
            if ($ok === 1) {
                deploy_log('HTTP : premier fichier téléchargé.');
            }
        } else {
            $fail++;
        }
        if (($ok + $fail) % 50 === 0 && ($ok + $fail) > 0) {
            deploy_log('HTTP : ' . $ok . ' OK, ' . $fail . ' échecs...');
        }
    }
    $suffix = $skip > 0 ? ' (' . $skip . ' chemins dossier ignorés)' : '';
    deploy_log('HTTP terminé : ' . $ok . ' fichiers, ' . $fail . ' introuvables.' . $suffix);
}

function deploy_sync_upload_ftp(array $files_cfg, $local_upload, $dry_run) {
    if ($dry_run) {
        deploy_log('(dry-run) Téléchargement FTP ' . ($files_cfg['remote_path'] ?? '') . ' → ' . $local_upload);
        return true;
    }
    if (!function_exists('ftp_connect')) {
        deploy_log('Extension PHP ftp absente (activez php_ftp dans WAMP).');
        return false;
    }
    if (!deploy_ftp_is_configured($files_cfg)) {
        deploy_log('FTP non configuré (identifiants placeholder dans deploy_wamp.php).');
        return false;
    }
    deploy_log('Téléchargement FTP production → WAMP (' . ($files_cfg['remote_path'] ?? '') . ')...');
    $conn = deploy_ftp_connect($files_cfg);
    if (!$conn) {
        deploy_log('Connexion FTP production échouée.');
        return false;
    }
    $count = 0;
    deploy_ftp_download_recursive($conn, $files_cfg['remote_path'] ?? '/', $local_upload, $count);
    ftp_close($conn);
    deploy_log('FTP terminé : ' . $count . ' fichiers.');
    return $count > 0;
}

function deploy_sync_upload_rsync(array $ssh, $local_upload, array $opts, $dry_run) {
    $host = trim((string) ($ssh['host'] ?? ''));
    $user = trim((string) ($ssh['user'] ?? ''));
    $remote_upload = rtrim((string) ($ssh['upload_path'] ?? ''), '/');
    if ($host === '' || $user === '' || $remote_upload === '') {
        deploy_log('SSH production incomplet (host, user, upload_path).');
        return false;
    }

    if (!is_dir($local_upload) && !$dry_run) {
        mkdir($local_upload, 0775, true);
    }

    $exclude = '';
    if (!empty($opts['exclude_barcodes'])) {
        $exclude = "--exclude 'barcodes/'";
    }

    $remote = $user . '@' . $host . ':' . $remote_upload . '/';
    $ssh_opts = '-T ' . deploy_ssh_port_opts($ssh);
    $cmd = 'rsync -avz --progress -e ' . escapeshellarg('ssh ' . $ssh_opts) . ' ' . $exclude . ' '
        . escapeshellarg($remote) . ' ' . escapeshellarg(rtrim(str_replace('\\', '/', $local_upload), '/') . '/');

    $run_as = getenv('SUDO_USER') ?: '';
    if ($run_as !== '' && function_exists('posix_geteuid') && posix_geteuid() === 0) {
        $cmd = 'sudo -u ' . escapeshellarg($run_as) . ' ' . $cmd;
    }

    deploy_log('rsync production → ' . $local_upload);
    $res = deploy_run($cmd, $dry_run);
    if ($res['code'] !== 0) {
        deploy_log('rsync échoué : ' . implode("\n", $res['output']));
        return false;
    }
    deploy_log('upload/ copié via rsync.');
    return true;
}

function deploy_sync_upload_from_production(array $files_cfg, $local_upload, array $opts, $dry_run, PDO $db = null, $site_url = '', array $prod_ssh = []) {
    $method = strtolower($files_cfg['method'] ?? 'auto');
    $ssh = $prod_ssh;
    if (empty($ssh['host']) && !empty($files_cfg['ssh_host'])) {
        $ssh = [
            'host' => $files_cfg['ssh_host'] ?? ($files_cfg['host'] ?? ''),
            'user' => $files_cfg['ssh_user'] ?? '',
            'port' => $files_cfg['ssh_port'] ?? 22,
            'identity_file' => $files_cfg['ssh_identity_file'] ?? '',
            'upload_path' => $files_cfg['ssh_upload_path'] ?? ($files_cfg['upload_path'] ?? ''),
        ];
    }
    if (empty($ssh['upload_path']) && !empty($files_cfg['upload_path'])) {
        $ssh['upload_path'] = $files_cfg['upload_path'];
    }

    if (!empty($opts['wipe_upload_before_pull']) && $method !== 'skip') {
        deploy_wipe_directory($local_upload, $dry_run);
    }

    if ($method === 'skip') {
        deploy_log('Images production : ignorées (method=skip). Utilisation upload/ existant.');
        return;
    }

    if ($method === 'auto') {
        if (deploy_sync_upload_ftp($files_cfg, $local_upload, $dry_run)) {
            return;
        }
        if (!empty($ssh['host']) && deploy_sync_upload_rsync($ssh, $local_upload, $opts, $dry_run)) {
            return;
        }
        deploy_log('FTP/rsync indisponibles — repli HTTP (fichiers référencés en BDD)...');
        if (!$db instanceof PDO) {
            deploy_fail('BDD locale requise pour le repli HTTP. Importez la BDD production d’abord.');
        }
        deploy_sync_upload_http_from_db($site_url, $db, $local_upload, $files_cfg, $dry_run);
        return;
    }

    if ($method === 'http_db') {
        if (!$db instanceof PDO) {
            deploy_fail('BDD locale requise pour method=http_db.');
        }
        deploy_sync_upload_http_from_db($site_url, $db, $local_upload, $files_cfg, $dry_run);
        return;
    }

    if ($method === 'ssh' || $method === 'rsync') {
        if (!deploy_sync_upload_rsync($ssh, $local_upload, $opts, $dry_run)) {
            deploy_fail('Copie SSH/rsync de upload/ impossible. Vérifiez production_ssh.');
        }
        return;
    }

    if ($method === 'ftp' || $method === 'ftps') {
        if (!deploy_sync_upload_ftp($files_cfg, $local_upload, $dry_run)) {
            deploy_fail('Connexion FTP production impossible. Vérifiez production_files.');
        }
        return;
    }

    deploy_fail('Méthode production_files inconnue : ' . $method);
}

function deploy_push_upload_zip_to_server(array $server, $upload_dir, $dry_run, $wipe_server_upload = false) {
    $web_root = rtrim($server['web_root'] ?? '/var/www/fouta', '/');
    $zip_local = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fouta_upload_' . date('Ymd_His') . '.zip';
    $zip_remote = '/tmp/fouta_upload_deploy.zip';

    deploy_log('Compression upload/ pour envoi serveur (peut prendre plusieurs minutes)...');
    if (!$dry_run && !deploy_zip_directory($upload_dir, $zip_local)) {
        deploy_fail('Impossible de créer le zip upload/. Activez l’extension PHP zip.');
    }
    if (!$dry_run) {
        deploy_log('Zip : ' . round(filesize($zip_local) / 1024 / 1024, 1) . ' Mo — envoi SCP vers fplserver...');
    } else {
        deploy_log('(dry-run) envoi SCP zip upload/');
    }

    deploy_scp_to_server($server, $zip_local, $zip_remote, $dry_run);

    deploy_log('Décompression upload/ sur le serveur local...');

    $wipe = $wipe_server_upload
        ? 'find ' . escapeshellarg($web_root . '/upload') . ' -mindepth 1 -delete 2>/dev/null || true; '
        : '';

    $remote_cmd = 'set -e; '
        . 'mkdir -p ' . escapeshellarg($web_root . '/upload') . '; '
        . $wipe
        . 'unzip -o ' . escapeshellarg($zip_remote) . ' -d ' . escapeshellarg($web_root . '/upload') . '; '
        . 'rm -f ' . escapeshellarg($zip_remote) . '; '
        . 'sudo chown -R www-data:www-data ' . escapeshellarg($web_root . '/upload') . '; '
        . 'sudo chmod -R 775 ' . escapeshellarg($web_root . '/upload');
    deploy_ssh_run($server, $remote_cmd, $dry_run);

    if (!$dry_run && is_file($zip_local)) {
        @unlink($zip_local);
    }
}

function deploy_push_database_to_server(array $server, $dump_file, $dry_run) {
    $remote_dump = '/tmp/fouta_wamp_deploy.sql';
    $db_name = str_replace('`', '', $server['db_name'] ?? 'fouta_local');
    $db_user = $server['db_user'] ?? 'fouta_user';
    $db_pass = $server['db_pass'] ?? '';

    deploy_scp_to_server($server, $dump_file, $remote_dump, $dry_run);

    $import_cmd = 'mysql -u ' . escapeshellarg($db_user)
        . ' -p' . escapeshellarg($db_pass)
        . ' -e ' . escapeshellarg(
            'DROP DATABASE IF EXISTS `' . $db_name . '`;'
            . ' CREATE DATABASE `' . $db_name . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'
        )
        . ' && mysql -u ' . escapeshellarg($db_user)
        . ' -p' . escapeshellarg($db_pass)
        . ' ' . escapeshellarg($db_name)
        . ' < ' . escapeshellarg($remote_dump)
        . ' && rm -f ' . escapeshellarg($remote_dump);

    $res = deploy_ssh_run($server, $import_cmd, $dry_run);
    if (!$dry_run && $res['code'] !== 0) {
        deploy_fail("Import BDD sur serveur échoué :\n" . implode("\n", $res['output']));
    }
}

function deploy_finalize_server(array $server, $dry_run) {
    $web_root = rtrim($server['web_root'] ?? '/var/www/fouta', '/');
    $site_url = $server['site_url'] ?? 'http://192.168.1.217';
    $db_user = $server['db_user'] ?? 'fouta_user';
    $db_pass = $server['db_pass'] ?? '';
    $db_name = $server['db_name'] ?? 'fouta_local';

    $cmd = 'cd ' . escapeshellarg($web_root)
        . ' && sudo mysql -e ' . escapeshellarg('SET GLOBAL log_bin_trust_function_creators = 1;')
        . ' && php migrations/run_add_sync_columns.php'
        . ' && php migrations/run_assign_sync_uuids.php'
        . ' && mysql -u ' . escapeshellarg($db_user)
        . ' -p' . escapeshellarg($db_pass)
        . ' ' . escapeshellarg($db_name)
        . ' -e ' . escapeshellarg('TRUNCATE TABLE sync_file_queue;')
        . ' && printf %s ' . escapeshellarg("<?php\nreturn ['site_url' => " . var_export($site_url, true) . "];\n")
        . ' > config/site.php';

    deploy_ssh_run($server, $cmd, $dry_run);
}

function deploy_setup_sync_nodes($project_root, array $sync_cfg, array $server, $dry_run, array $scope = []) {
    require_once $project_root . '/includes/sync_config_builder.php';

    $wamp_only = !empty($scope['wamp_only']);
    $server_only = !empty($scope['server_only']);
    $setup_wamp = !$server_only;
    $setup_server = !$wamp_only;

    $remote_url = $sync_cfg['remote_url'] ?? 'https://e.foutapoidslourds.com';
    $token = $sync_cfg['remote_api_token'] ?? '';
    if ($token === '') {
        deploy_log('Sync ignorée : remote_api_token vide dans deploy_wamp.php');
        return;
    }

    deploy_log('');
    deploy_log('--- Phase 3 : Configuration sync incrémentale ---');

    if ($setup_wamp) {
        $wamp_profile = array_merge(
            ['node_id' => 'dev_wamp', 'sync_direction' => 'bidirectional', 'node_priority_on_tie' => false],
            $sync_cfg['wamp'] ?? []
        );
        $wamp_config = sync_config_build($wamp_profile, $remote_url, $token);
        $wamp_sync_path = $project_root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'sync.php';

        if ($dry_run) {
            deploy_log('(dry-run) WAMP config/sync.php → ' . $wamp_profile['node_id'] . ' (' . $wamp_profile['sync_direction'] . ')');
        } else {
            sync_config_write($wamp_sync_path, $wamp_config);
            deploy_log('WAMP config/sync.php → ' . $wamp_profile['node_id'] . ' (' . $wamp_profile['sync_direction'] . ')');
        }
    }

    if ($setup_server) {
        $server_profile = array_merge(
            ['node_id' => 'local_entreprise', 'sync_direction' => 'push_only', 'node_priority_on_tie' => true],
            $sync_cfg['local_server'] ?? []
        );
        $server_config = sync_config_build($server_profile, $remote_url, $token);
        $tmp_sync = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fouta_sync_server_' . getmypid() . '.php';

        if (!$dry_run) {
            sync_config_write($tmp_sync, $server_config);
        }

        $web_root = rtrim($server['web_root'] ?? '/var/www/fouta', '/');
        deploy_log('Serveur ' . $web_root . '/config/sync.php → ' . $server_profile['node_id'] . ' (' . $server_profile['sync_direction'] . ')');
        deploy_scp_to_server($server, $tmp_sync, $web_root . '/config/sync.php', $dry_run);

        if (!empty($sync_cfg['setup_cron'])) {
            $cron_line = '*/30 * * * * www-data /usr/bin/php ' . $web_root . '/scripts/sync_local_to_vps.php >> /var/log/fouta-sync.log 2>&1';
            $cron_cmd = 'grep -qF ' . escapeshellarg('sync_local_to_vps.php') . ' /etc/crontab 2>/dev/null || '
                . 'echo ' . escapeshellarg($cron_line) . ' | sudo tee -a /etc/crontab';
            deploy_ssh_run($server, $cron_cmd, $dry_run);
            deploy_ssh_run($server, 'sudo touch /var/log/fouta-sync.log && sudo chown www-data:www-data /var/log/fouta-sync.log', $dry_run);
            deploy_log('Cron sync serveur local : toutes les 30 min');
        }

        deploy_log('Test connexion sync production depuis le serveur local...');
        deploy_ssh_run($server, 'cd ' . escapeshellarg($web_root) . ' && sudo -u www-data php scripts/sync_test_ping.php', $dry_run);

        if (!$dry_run && is_file($tmp_sync)) {
            @unlink($tmp_sync);
        }
    }

    deploy_log('Sync configurée. WAMP : php scripts/sync_run.php | Serveur : cron auto push_only → production');
}
