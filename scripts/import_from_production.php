<?php
/**
 * Import complet production → serveur local (BDD + upload/).
 *
 * Prérequis :
 *   - config/import_production.php (copie de import_production.example.php)
 *   - mysqldump, mysql, rsync, ssh
 *   - Accès MySQL distant (port 3306) OU SSH vers le VPS
 *   - Clé SSH ou mot de passe pour rsync upload/
 *
 * CLI :
 *   php scripts/import_from_production.php
 *   php scripts/import_from_production.php --db-only
 *   php scripts/import_from_production.php --files-only
 *   php scripts/import_from_production.php --find-upload-path
 *   php scripts/import_from_production.php --dry-run
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI uniquement.\n");
    exit(1);
}

$root = dirname(__DIR__);
$config_path = $root . '/config/import_production.php';
if (!is_file($config_path)) {
    fwrite(STDERR, "Créez config/import_production.php depuis config/import_production.example.php\n");
    exit(1);
}

$cfg = require $config_path;
$argv = $argv ?? [];
$dry_run = in_array('--dry-run', $argv, true);
$db_only = in_array('--db-only', $argv, true);
$files_only = in_array('--files-only', $argv, true);
$find_path = in_array('--find-upload-path', $argv, true);

$prod_db = $cfg['production_db'] ?? [];
$ssh = $cfg['production_ssh'] ?? [];
$local = $cfg['local'] ?? [];
$import_opts = $cfg['import'] ?? [];

$local_root = rtrim((string) ($local['web_root'] ?? $root), '/');
$local_upload = $local_root . '/upload';

require_once $local_root . '/conn/conn.php';
if (!isset($db) || !$db instanceof PDO) {
    require_once $root . '/conn/conn.php';
}

function import_log($msg) {
    echo '[import] ' . $msg . "\n";
}

function import_fail($msg, $code = 1) {
    fwrite(STDERR, '[import] ERREUR : ' . $msg . "\n");
    exit($code);
}

function import_run($cmd, $dry_run = false) {
    import_log('$ ' . preg_replace('/(-p)[^\s]*/', '$1***', $cmd));
    if ($dry_run) {
        return ['code' => 0, 'output' => ['(dry-run)']];
    }
    $output = [];
    $code = 0;
    exec($cmd . ' 2>&1', $output, $code);
    return ['code' => $code, 'output' => $output];
}

function import_write_mysql_cnf(array $db_cfg, $prefix = 'client') {
    $file = sys_get_temp_dir() . '/fouta_mysql_' . $prefix . '_' . getmypid() . '.cnf';
    $content = "[client]\n"
        . 'host=' . ($db_cfg['host'] ?? 'localhost') . "\n"
        . 'port=' . (int) ($db_cfg['port'] ?? 3306) . "\n"
        . 'user=' . ($db_cfg['user'] ?? '') . "\n"
        . 'password=' . ($db_cfg['pass'] ?? '') . "\n";
    file_put_contents($file, $content);
    chmod($file, 0600);
    return $file;
}

function import_local_db_cfg(array $local, PDO $db = null) {
    global $root;
    $name = $local['db_name'] ?? '';
    $user = $local['db_user'] ?? '';
    $pass = $local['db_pass'] ?? '';
    if ($name === '' || $user === '' || $pass === '') {
        $conn_file = $root . '/conn/conn.php';
        if (is_file($conn_file)) {
            $db_host = $db_name = $db_user = $db_pass = null;
            include $conn_file;
            return [
                'host' => $local['db_host'] ?? ($db_host ?? 'localhost'),
                'port' => 3306,
                'name' => $name ?: ($db_name ?? 'fouta_local'),
                'user' => $user ?: ($db_user ?? 'root'),
                'pass' => $pass !== '' ? $pass : ($db_pass ?? ''),
            ];
        }
    }
    return [
        'host' => $local['db_host'] ?? 'localhost',
        'port' => 3306,
        'name' => $name,
        'user' => $user,
        'pass' => $pass,
    ];
}

function import_ssh_rsync(array $ssh, $remote_upload, $local_upload, array $opts, $dry_run) {
    $host = $ssh['host'] ?? '';
    $user = $ssh['user'] ?? '';
    $port = (int) ($ssh['port'] ?? 22);
    if ($host === '' || $user === '' || $remote_upload === '') {
        import_fail('production_ssh incomplet (host, user, upload_path).');
    }

    if (!is_dir($local_upload)) {
        mkdir($local_upload, 0775, true);
    }

    $remote = $user . '@' . $host . ':' . rtrim($remote_upload, '/') . '/';
    $exclude = '';
    if (!empty($opts['exclude_barcodes'])) {
        $exclude = "--exclude 'barcodes/'";
    }

    $cmd = "rsync -avz --progress -e \"ssh -p {$port} -o StrictHostKeyChecking=accept-new\" {$exclude} "
        . escapeshellarg($remote) . ' ' . escapeshellarg($local_upload . '/');

    $res = import_run($cmd, $dry_run);
    if ($res['code'] !== 0) {
        import_fail("rsync échoué :\n" . implode("\n", $res['output']));
    }
    import_log('Upload copié vers ' . $local_upload);
}

function import_find_upload_paths(array $ssh) {
    $host = $ssh['host'] ?? '';
    $user = $ssh['user'] ?? '';
    $port = (int) ($ssh['port'] ?? 22);
    $cmd = 'ssh -p ' . $port . ' ' . escapeshellarg($user . '@' . $host)
        . ' ' . escapeshellarg("find /home/$user -type d -name upload 2>/dev/null | head -20");
    $res = import_run($cmd, false);
    import_log("Chemins upload possibles sur le serveur production :");
    foreach ($res['output'] as $line) {
        echo '  ' . trim($line) . "\n";
    }
    exit($res['code'] === 0 ? 0 : 1);
}

if ($find_path) {
    import_find_upload_paths($ssh);
}

import_log('=== Import production → local ===');
import_log('Production : ' . ($cfg['production_site_url'] ?? ''));
import_log('Cible : ' . ($local['site_url'] ?? '') . ' (' . $local_root . ')');

$local_db = import_local_db_cfg($local, $db ?? null);

// --- MySQL triggers fix ---
if (!empty($import_opts['fix_mysql_triggers']) && !$dry_run) {
    import_log('MySQL log_bin_trust_function_creators...');
    import_run('mysql -u root -e "SET GLOBAL log_bin_trust_function_creators = 1;"', false);
}

// --- BASE DE DONNÉES ---
if (!$files_only) {
    $dump_file = sys_get_temp_dir() . '/fouta_prod_import_' . date('Ymd_His') . '.sql';
    $prod_cnf = import_write_mysql_cnf($prod_db, 'prod');
    $local_cnf = import_write_mysql_cnf($local_db, 'local');

    import_log('Export BDD production (' . $prod_db['name'] . '@' . $prod_db['host'] . ')...');
    $dump_cmd = 'mysqldump --defaults-extra-file=' . escapeshellarg($prod_cnf)
        . ' --single-transaction --quick --routines --triggers --set-gtid-purged=OFF '
        . escapeshellarg($prod_db['name'])
        . ' > ' . escapeshellarg($dump_file);

    $res = import_run($dump_cmd, $dry_run);
    @unlink($prod_cnf);

    if (!$dry_run) {
        if ($res['code'] !== 0 || !is_file($dump_file) || filesize($dump_file) < 1000) {
            import_log('Dump direct impossible — tentative via SSH...');
            $ssh_host = $ssh['host'] ?? $prod_db['host'];
            $ssh_user = $ssh['user'] ?? 'jomas';
            $ssh_port = (int) ($ssh['port'] ?? 22);
            $remote_dump = "mysqldump -h localhost -u " . escapeshellarg($prod_db['user'])
                . " -p" . escapeshellarg($prod_db['pass'])
                . " --single-transaction --quick --routines --triggers "
                . escapeshellarg($prod_db['name']);
            $ssh_cmd = 'ssh -p ' . $ssh_port . ' ' . escapeshellarg($ssh_user . '@' . $ssh_host)
                . ' ' . escapeshellarg($remote_dump) . ' > ' . escapeshellarg($dump_file);
            $res = import_run($ssh_cmd, false);
        }

        if ($res['code'] !== 0 || !is_file($dump_file)) {
            import_fail("Impossible d'exporter la BDD production.\n" . implode("\n", $res['output']));
        }
        import_log('Dump : ' . $dump_file . ' (' . round(filesize($dump_file) / 1024 / 1024, 1) . ' Mo)');
    }

    if (!empty($import_opts['recreate_database']) && !$dry_run) {
        import_log('Recréation base locale ' . $local_db['name'] . '...');
        $sql = 'DROP DATABASE IF EXISTS `' . str_replace('`', '', $local_db['name']) . '`;'
            . ' CREATE DATABASE `' . str_replace('`', '', $local_db['name']) . '`'
            . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;';
        $res = import_run('mysql --defaults-extra-file=' . escapeshellarg($local_cnf) . ' -e ' . escapeshellarg($sql), false);
        if ($res['code'] !== 0) {
            import_fail('Recréation BDD : ' . implode("\n", $res['output']));
        }
    }

    if (!$dry_run) {
        import_log('Import dans ' . $local_db['name'] . '...');
        $import_cmd = 'mysql --defaults-extra-file=' . escapeshellarg($local_cnf)
            . ' ' . escapeshellarg($local_db['name'])
            . ' < ' . escapeshellarg($dump_file);
        $res = import_run($import_cmd, false);
        @unlink($local_cnf);
        if ($res['code'] !== 0) {
            import_fail('Import SQL : ' . implode("\n", $res['output']));
        }
        import_log('Base de données importée.');
    }
}

// --- FICHIERS upload/ ---
if (!$db_only && !empty($import_opts['sync_upload'])) {
    $remote_upload = $ssh['upload_path'] ?? '';
    import_log('Copie upload/ depuis ' . $remote_upload . ' ...');
    import_ssh_rsync($ssh, $remote_upload, $local_upload, $import_opts, $dry_run);
}

if ($dry_run) {
    import_log('Dry-run terminé.');
    exit(0);
}

// --- Config locale site.php ---
$site_url = $local['site_url'] ?? 'http://192.168.1.217';
$site_php = $local_root . '/config/site.php';
file_put_contents($site_php, "<?php\nreturn [\n    'site_url' => " . var_export($site_url, true) . ",\n];\n");
import_log('config/site.php → ' . $site_url);

// --- conn.php local (si champs définis dans import config) ---
if (!empty($local['db_pass'])) {
    $conn_php = $local_root . '/conn/conn.php';
    $autoload = "<?php\n\$autoload = __DIR__ . '/../vendor/autoload.php';\nif (file_exists(\$autoload)) { require_once \$autoload; }\n";
    $body = "\$db_host = " . var_export($local_db['host'], true) . ";\n"
        . "\$db_name = " . var_export($local_db['name'], true) . ";\n"
        . "\$db_user = " . var_export($local_db['user'], true) . ";\n"
        . "\$db_pass = " . var_export($local_db['pass'], true) . ";\n"
        . "\$pdo_options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];\n"
        . "try {\n    \$db = new PDO(\"mysql:host=\$db_host;dbname=\$db_name;charset=utf8mb4\", \$db_user, \$db_pass, \$pdo_options);\n    \$db->exec(\"SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci\");\n} catch (PDOException \$e) { \$db = null; }\n";
    file_put_contents($conn_php, $autoload . $body);
}

// --- .htaccess local (pas de redirect HTTPS prod) ---
$htaccess = $local_root . '/.htaccess';
if (is_file($htaccess)) {
    $content = file_get_contents($htaccess);
    $content = preg_replace('/^(RewriteRule \^ https:\/\/e\.foutapoidslourds\.com)/m', '#$1', $content);
    $content = preg_replace('/^(RewriteCond %{HTTPS} off)/m', '#$1', $content);
    $content = preg_replace('/^(RewriteCond %{HTTP:X-Forwarded-Proto} !https)/m', '#$1', $content);
    $content = preg_replace('/^(RewriteCond %{REQUEST_URI} !\^\\\\\/\\\\\.well-known)/m', '#$1', $content);
    file_put_contents($htaccess, $content);
    import_log('.htaccess adapté pour HTTP local.');
}

// --- Permissions ---
import_run('chown -R www-data:www-data ' . escapeshellarg($local_root), false);
import_run('chmod -R 775 ' . escapeshellarg($local_upload), false);

// --- Migrations sync ---
if (!empty($import_opts['run_sync_migrations'])) {
    import_log('Migrations sync...');
    $php = PHP_BINARY ?: 'php';
    import_run($php . ' ' . escapeshellarg($local_root . '/migrations/run_add_sync_columns.php'), false);
    import_run($php . ' ' . escapeshellarg($local_root . '/migrations/run_assign_sync_uuids.php'), false);
    // Réinitialiser file queue pour re-sync propre vers VPS
    $local_cnf2 = import_write_mysql_cnf($local_db, 'local2');
    import_run('mysql --defaults-extra-file=' . escapeshellarg($local_cnf2) . ' '
        . escapeshellarg($local_db['name']) . ' -e "TRUNCATE TABLE sync_file_queue;"', false);
    @unlink($local_cnf2);
}

import_log('=== Import terminé ===');
import_log('Site : ' . $site_url . '/');
import_log('Admin : ' . $site_url . '/admin/');
import_log('Sync VPS : cd ' . $local_root . ' && sudo -u www-data php scripts/sync_test_ping.php');
