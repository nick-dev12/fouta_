<?php
/**
 * Sauvegarde quotidienne : serveur local entreprise → VPS.
 *
 * Copie :
 *   - Dump MySQL fouta_local (gzip)
 *   - Dossier upload/ (rsync)
 *
 * Destination VPS : /home/jomas/backups/foutasvr/YYYYMMDD_HHMMSS/
 * (n'écrase PAS le site de production live)
 *
 * CLI :
 *   php scripts/backup_local_to_vps.php
 *   php scripts/backup_local_to_vps.php --dry-run
 *   php scripts/backup_local_to_vps.php --db-only
 *   php scripts/backup_local_to_vps.php --files-only
 *
 * Prérequis :
 *   config/backup_local_to_vps.php
 *   Clé SSH foutasvr → root@VPS
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI uniquement.\n");
    exit(1);
}

$root = dirname(__DIR__);
$config_path = $root . '/config/backup_local_to_vps.php';

require_once $root . '/includes/deploy_helpers.php';

deploy_init_realtime_output();

$argv = $argv ?? [];
$dry_run = in_array('--dry-run', $argv, true);
$db_only = in_array('--db-only', $argv, true);
$files_only = in_array('--files-only', $argv, true);

if (!is_file($config_path)) {
    deploy_fail('Créez config/backup_local_to_vps.php depuis config/backup_local_to_vps.example.php');
}

$cfg = require $config_path;
$web_root = rtrim((string) ($cfg['web_root'] ?? $root), '/');
$ssh = $cfg['vps_ssh'] ?? [];
$backup_opts = $cfg['backup'] ?? [];
$retention = max(1, (int) ($cfg['retention_days'] ?? 14));
$log_file = $cfg['log_file'] ?? '/var/log/fouta-backup-vps.log';

$stamp = date('Ymd_His');
$remote_root = rtrim((string) ($ssh['backup_root'] ?? '/home/jomas/backups/foutasvr'), '/');
$remote_dest = $remote_root . '/' . $stamp;

$host = trim((string) ($ssh['host'] ?? ''));
$user = trim((string) ($ssh['user'] ?? ''));
if ($host === '' || $user === '') {
    deploy_fail('vps_ssh.host et vps_ssh.user requis dans config/backup_local_to_vps.php');
}

// BDD locale
$db_cfg = $cfg['local_db'] ?? [];
if (empty($db_cfg['pass']) || preg_match('/CHANGEZ/i', (string) ($db_cfg['pass'] ?? ''))) {
    require_once $web_root . '/conn/conn.php';
    global $db_host, $db_name, $db_user, $db_pass;
    $db_cfg = [
        'host' => $db_cfg['host'] ?? ($db_host ?? 'localhost'),
        'name' => $db_cfg['name'] ?? ($db_name ?? 'fouta_local'),
        'user' => $db_cfg['user'] ?? ($db_user ?? 'fouta_user'),
        'pass' => $db_cfg['pass'] ?? ($db_pass ?? ''),
    ];
}

$upload_local = rtrim((string) ($backup_opts['upload_path'] ?? $web_root . '/upload'), '/');
$dump_local = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fouta_backup_' . $stamp . '.sql.gz';
$remote_dump = $remote_dest . '/fouta_local.sql.gz';
$remote_upload = $remote_dest . '/upload';

deploy_log('=== Sauvegarde local → VPS ===');
deploy_log('Source BDD  : ' . ($db_cfg['name'] ?? '') . '@' . ($db_cfg['host'] ?? 'localhost'));
deploy_log('Source files: ' . $upload_local);
deploy_log('Destination : ' . $user . '@' . $host . ':' . $remote_dest);
if ($dry_run) {
    deploy_log('Mode : DRY-RUN');
}

function backup_ssh_cmd(array $ssh, $remote_cmd) {
    $opts = deploy_ssh_port_opts($ssh);
    return 'ssh ' . $opts . ' ' . escapeshellarg($ssh['user'] . '@' . $ssh['host']) . ' ' . escapeshellarg($remote_cmd);
}

function backup_scp_to_vps(array $ssh, $local_file, $remote_path, $dry_run) {
    $opts = deploy_ssh_port_opts($ssh);
    $cmd = 'scp ' . str_replace('-T ', '', $opts) . ' '
        . escapeshellarg($local_file) . ' '
        . escapeshellarg($ssh['user'] . '@' . $ssh['host'] . ':' . $remote_path);
    deploy_log('Envoi : ' . basename($local_file) . ' → VPS');
    return deploy_run($cmd, $dry_run);
}

// Créer dossier distant
deploy_log('Création dossier backup sur VPS...');
$mkdir_cmd = backup_ssh_cmd($ssh, 'mkdir -p ' . escapeshellarg($remote_dest) . ' ' . escapeshellarg($remote_upload));
deploy_run($mkdir_cmd, $dry_run);

$errors = 0;

// --- Base de données ---
if (!$files_only && !empty($backup_opts['database'])) {
    deploy_log('');
    deploy_log('--- Dump MySQL local ---');
    $cnf = deploy_write_mysql_cnf([
        'host' => $db_cfg['host'] ?? 'localhost',
        'port' => 3306,
        'name' => $db_cfg['name'] ?? '',
        'user' => $db_cfg['user'] ?? '',
        'pass' => $db_cfg['pass'] ?? '',
    ], 'backup');

    $dump_sql = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fouta_backup_' . $stamp . '.sql';
    $tools = deploy_mysql_tools([]);
    $mysqldump = $tools['mysqldump'];
    $cmd = escapeshellarg($mysqldump)
        . ' --defaults-extra-file=' . escapeshellarg($cnf)
        . ' --single-transaction --quick --routines --triggers --set-gtid-purged=OFF '
        . escapeshellarg($db_cfg['name'] ?? '')
        . ' > ' . escapeshellarg($dump_sql);

    if ($dry_run) {
        deploy_log('(dry-run) mysqldump → ' . $dump_local);
    } else {
        $res = deploy_run($cmd, false);
        @unlink($cnf);
        if ($res['code'] !== 0 || !is_file($dump_sql)) {
            deploy_fail('mysqldump échoué : ' . implode("\n", $res['output']));
        }
        deploy_log('Compression gzip...');
        $res_gz = deploy_run('gzip -c ' . escapeshellarg($dump_sql) . ' > ' . escapeshellarg($dump_local), false);
        @unlink($dump_sql);
        if ($res_gz['code'] !== 0 || !is_file($dump_local)) {
            deploy_fail('Compression gzip échouée');
        }
        $size_mb = round(filesize($dump_local) / 1024 / 1024, 2);
        deploy_log('Dump local : ' . $size_mb . ' Mo');

        $res_scp = backup_scp_to_vps($ssh, $dump_local, $remote_dump, false);
        if ($res_scp['code'] !== 0) {
            deploy_log('ERREUR envoi dump : ' . implode("\n", $res_scp['output']));
            $errors++;
        } else {
            deploy_log('Dump envoyé : ' . $remote_dump);
        }
        @unlink($dump_local);
    }
}

// --- Fichiers upload ---
if (!$db_only && !empty($backup_opts['upload_dir'])) {
    deploy_log('');
    deploy_log('--- Fichiers upload/ ---');
    if (!is_dir($upload_local)) {
        deploy_log('WARN : dossier upload local absent — ' . $upload_local);
    } else {
        $count = deploy_count_files($upload_local);
        deploy_log('Fichiers locaux : ' . $count);

        $ssh_opts = '-T ' . deploy_ssh_port_opts($ssh);
        $remote = $user . '@' . $host . ':' . $remote_upload . '/';
        $rsync_cmd = 'rsync -avz --progress -e ' . escapeshellarg('ssh ' . $ssh_opts) . ' '
            . escapeshellarg(rtrim(str_replace('\\', '/', $upload_local), '/') . '/')
            . ' ' . escapeshellarg($remote);

        deploy_log('rsync upload/ → VPS...');
        $res = deploy_run($rsync_cmd, $dry_run);
        if ($res['code'] !== 0) {
            deploy_log('ERREUR rsync : ' . implode("\n", $res['output']));
            $errors++;
        } else {
            deploy_log('upload/ sauvegardé sur VPS');
        }
    }
}

// --- Métadonnées ---
if (!$dry_run) {
    $meta = [
        'timestamp' => date('c'),
        'hostname' => gethostname(),
        'db' => $db_cfg['name'] ?? '',
        'upload' => $upload_local,
        'remote_path' => $remote_dest,
    ];
    $meta_local = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fouta_backup_meta_' . $stamp . '.json';
    file_put_contents($meta_local, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    backup_scp_to_vps($ssh, $meta_local, $remote_dest . '/backup_info.json', false);
    @unlink($meta_local);
}

// --- Rétention ---
deploy_log('');
deploy_log('--- Rétention (' . $retention . ' jours) ---');
$clean_cmd = backup_ssh_cmd(
    $ssh,
    'find ' . escapeshellarg($remote_root) . ' -maxdepth 1 -type d -name "20*" -mtime +' . (int) $retention . ' -exec rm -rf {} + 2>/dev/null; echo RETENTION_OK'
);
$res_clean = deploy_run($clean_cmd, $dry_run);
if (!$dry_run && stripos(implode("\n", $res_clean['output']), 'RETENTION_OK') !== false) {
    deploy_log('Anciennes sauvegardes supprimées (> ' . $retention . ' jours)');
}

// --- Log fichier ---
$summary = date('Y-m-d H:i:s') . ' — backup ' . $remote_dest . ' — ' . ($errors ? 'ERREURS' : 'OK') . PHP_EOL;
if (!$dry_run && $log_file !== '') {
    @file_put_contents($log_file, $summary, FILE_APPEND | LOCK_EX);
}

deploy_log('');
if ($errors > 0) {
    deploy_fail('Sauvegarde terminée avec ' . $errors . ' erreur(s)');
}

deploy_log('=== Sauvegarde terminée ===');
deploy_log('VPS : ' . $remote_dest);
exit(0);
