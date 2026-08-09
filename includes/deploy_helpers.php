<?php
/**
 * Fonctions utilitaires pour déploiement WAMP → serveur local.
 * PHP procédural — pas de classes.
 */

function deploy_log($msg) {
    echo '[deploy] ' . $msg . "\n";
}

function deploy_fail($msg, $code = 1) {
    fwrite(STDERR, '[deploy] ERREUR : ' . $msg . "\n");
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
        if (!empty($res['output'])) {
            deploy_log('Dump échoué : ' . implode("\n", $res['output']));
        }
        return false;
    }
    return true;
}

function deploy_import_database(array $db_cfg, $mysql, $dump_file, $recreate, $dry_run) {
    $cnf = deploy_write_mysql_cnf($db_cfg, 'import');
    if ($recreate && !$dry_run) {
        $db_name = str_replace('`', '', $db_cfg['name']);
        $sql = 'DROP DATABASE IF EXISTS `' . $db_name . '`;'
            . ' CREATE DATABASE `' . $db_name . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;';
        $res = deploy_run(
            escapeshellarg($mysql) . ' --defaults-extra-file=' . escapeshellarg($cnf) . ' -e ' . escapeshellarg($sql),
            $dry_run
        );
        if ($res['code'] !== 0) {
            @unlink($cnf);
            return $res;
        }
    }

    if (DIRECTORY_SEPARATOR === '\\') {
        $cmd = escapeshellarg($mysql) . ' --defaults-extra-file=' . escapeshellarg($cnf)
            . ' ' . escapeshellarg($db_cfg['name'])
            . ' < ' . escapeshellarg($dump_file);
    } else {
        $cmd = escapeshellarg($mysql) . ' --defaults-extra-file=' . escapeshellarg($cnf)
            . ' ' . escapeshellarg($db_cfg['name'])
            . ' < ' . escapeshellarg($dump_file);
    }
    $res = deploy_run($cmd, $dry_run);
    @unlink($cnf);
    return $res;
}

function deploy_ssh_base(array $ssh) {
    $host = $ssh['host'] ?? '';
    $user = $ssh['user'] ?? '';
    $port = (int) ($ssh['port'] ?? 22);
    $opts = '-p ' . $port . ' -o StrictHostKeyChecking=accept-new';
    if (!empty($ssh['identity_file']) && is_readable($ssh['identity_file'])) {
        $opts .= ' -i ' . escapeshellarg($ssh['identity_file']);
    }
    return [
        'target' => escapeshellarg($user . '@' . $host),
        'opts' => $opts,
    ];
}

function deploy_scp_to_server(array $ssh, $local_path, $remote_path, $dry_run, $recursive = false) {
    $base = deploy_ssh_base($ssh);
    $recursive_flag = $recursive ? ' -r' : '';
    $cmd = 'scp ' . $base['opts'] . $recursive_flag . ' '
        . escapeshellarg($local_path) . ' '
        . $base['target'] . ':' . escapeshellarg($remote_path);
    return deploy_run($cmd, $dry_run);
}

function deploy_ssh_run(array $ssh, $remote_cmd, $dry_run) {
    $base = deploy_ssh_base($ssh);
    $cmd = 'ssh ' . $base['opts'] . ' ' . $base['target'] . ' ' . escapeshellarg($remote_cmd);
    return deploy_run($cmd, $dry_run);
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

function deploy_ftp_connect(array $ftp_cfg) {
    $host = $ftp_cfg['host'] ?? '';
    $port = (int) ($ftp_cfg['port'] ?? 21);
    $user = $ftp_cfg['user'] ?? '';
    $pass = $ftp_cfg['pass'] ?? '';
    $method = strtolower($ftp_cfg['method'] ?? 'ftp');
    if ($host === '' || $user === '') {
        return null;
    }
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
            if ($count % 500 === 0) {
                deploy_log('FTP : ' . $count . ' fichiers téléchargés...');
            }
        }
    }
}

function deploy_sync_upload_from_production(array $files_cfg, $local_upload, array $opts, $dry_run) {
    $method = strtolower($files_cfg['method'] ?? 'skip');
    if ($method === 'skip') {
        deploy_log('Images production : ignorées (method=skip). Utilisation upload/ WAMP existant.');
        return;
    }
    if ($method === 'ssh') {
        deploy_log('SSH production indisponible pour ce compte — utilisez FTP ou method=skip.');
        deploy_fail('Configurez production_files.method=ftp dans config/deploy_wamp.php');
    }
    if ($method !== 'ftp' && $method !== 'ftps') {
        deploy_fail('Méthode production_files inconnue : ' . $method);
    }
    if ($dry_run) {
        deploy_log('(dry-run) Téléchargement FTP ' . ($files_cfg['remote_path'] ?? '') . ' → ' . $local_upload);
        return;
    }

    deploy_log('Téléchargement FTP production → WAMP (' . ($files_cfg['remote_path'] ?? '') . ')...');
    $conn = deploy_ftp_connect($files_cfg);
    if (!$conn) {
        deploy_fail('Connexion FTP production impossible. Vérifiez production_files dans config/deploy_wamp.php');
    }
    $count = 0;
    deploy_ftp_download_recursive($conn, $files_cfg['remote_path'] ?? '/', $local_upload, $count);
    ftp_close($conn);
    deploy_log('FTP terminé : ' . $count . ' fichiers.');
}

function deploy_push_upload_zip_to_server(array $server, $upload_dir, $dry_run) {
    $web_root = rtrim($server['web_root'] ?? '/var/www/fouta', '/');
    $zip_local = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fouta_upload_' . date('Ymd_His') . '.zip';
    $zip_remote = '/tmp/fouta_upload_deploy.zip';

    deploy_log('Compression upload/ pour envoi serveur...');
    if (!$dry_run && !deploy_zip_directory($upload_dir, $zip_local)) {
        deploy_fail('Impossible de créer le zip upload/. Activez l’extension PHP zip.');
    }
    if (!$dry_run) {
        deploy_log('Zip : ' . round(filesize($zip_local) / 1024 / 1024, 1) . ' Mo');
    }

    deploy_scp_to_server($server, $zip_local, $zip_remote, $dry_run);

    $remote_cmd = 'set -e; '
        . 'mkdir -p ' . escapeshellarg($web_root . '/upload') . '; '
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

    $remote_url = $sync_cfg['remote_url'] ?? 'https://infra.goo-bridge.com';
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

        deploy_log('Test connexion sync VPS depuis le serveur local...');
        deploy_ssh_run($server, 'cd ' . escapeshellarg($web_root) . ' && sudo -u www-data php scripts/sync_test_ping.php', $dry_run);

        if (!$dry_run && is_file($tmp_sync)) {
            @unlink($tmp_sync);
        }
    }

    deploy_log('Sync configurée. WAMP : php scripts/sync_run.php | Serveur : cron auto push_only → VPS');
}
