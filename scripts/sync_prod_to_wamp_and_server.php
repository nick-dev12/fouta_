<?php
/**
 * Déploiement complet depuis WAMP :
 *   1. Production (e.foutapoidslourds.com) → WAMP (BDD + images FTP)
 *   2. WAMP → serveur local fplserver (BDD + upload/)
 *
 * Prérequis WAMP :
 *   - config/deploy_wamp.php (copie de deploy_wamp.example.php)
 *   - PHP CLI, extension zip, ftp
 *   - mysqldump / mysql (WAMP)
 *   - scp + ssh (OpenSSH Windows)
 *
 * Usage :
 *   php scripts/sync_prod_to_wamp_and_server.php
 *   php scripts/sync_prod_to_wamp_and_server.php --prod-only
 *   php scripts/sync_prod_to_wamp_and_server.php --server-only
 *   php scripts/sync_prod_to_wamp_and_server.php --db-only
 *   php scripts/sync_prod_to_wamp_and_server.php --files-only
 *   php scripts/sync_prod_to_wamp_and_server.php --dry-run
 *
 * Ou double-clic : scripts/sync_prod_to_wamp_and_server.bat
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI uniquement.\n");
    exit(1);
}

$root = dirname(__DIR__);
$config_path = $root . '/config/deploy_wamp.php';
if (!is_file($config_path)) {
    fwrite(STDERR, "Créez config/deploy_wamp.php depuis config/deploy_wamp.example.php\n");
    exit(1);
}

require_once $root . '/includes/deploy_helpers.php';

$cfg = require $config_path;
$argv = $argv ?? [];

$dry_run = in_array('--dry-run', $argv, true);
$prod_only = in_array('--prod-only', $argv, true);
$server_only = in_array('--server-only', $argv, true);
$db_only = in_array('--db-only', $argv, true);
$files_only = in_array('--files-only', $argv, true);

$prod_db = $cfg['production_db'] ?? [];
$prod_files = $cfg['production_files'] ?? [];
$wamp = $cfg['wamp'] ?? [];
$server = $cfg['local_server'] ?? [];
$opts = $cfg['options'] ?? [];

$wamp_root = rtrim(str_replace('\\', '/', $wamp['web_root'] ?? $root), '/');
$wamp_root_os = str_replace('/', DIRECTORY_SEPARATOR, $wamp_root);
$wamp_upload = $wamp_root_os . DIRECTORY_SEPARATOR . 'upload';

$tools = deploy_mysql_tools($wamp);
$wamp_db = deploy_wamp_db_cfg($wamp, $root);

deploy_log('=== Déploiement WAMP → serveur local ===');
deploy_log('Production : ' . ($cfg['production_site_url'] ?? ''));
deploy_log('WAMP       : ' . $wamp_root_os . ' (' . $wamp_db['name'] . ')');
deploy_log('Serveur    : ' . ($server['site_url'] ?? '') . ' (' . ($server['web_root'] ?? '') . ')');

$dump_file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fouta_deploy_' . date('Ymd_His') . '.sql';

// -------------------------------------------------------------------------
// PHASE 1 — Production → WAMP
// -------------------------------------------------------------------------
if (!$server_only && !empty($opts['pull_from_production'])) {

    deploy_log('');
    deploy_log('--- Phase 1 : Production → WAMP ---');

    if (!$files_only) {
        deploy_log('Export BDD production (' . ($prod_db['name'] ?? '') . '@' . ($prod_db['host'] ?? '') . ')...');
        if (!$dry_run) {
            if (!deploy_dump_database($prod_db, $tools['mysqldump'], $dump_file, false)) {
                deploy_fail('Export BDD production impossible. Vérifiez production_db et l’accès MySQL distant.');
            }
            deploy_log('Dump production : ' . round(filesize($dump_file) / 1024 / 1024, 1) . ' Mo');
        }

        deploy_log('Import dans WAMP (' . $wamp_db['name'] . ')...');
        $res = deploy_import_database(
            $wamp_db,
            $tools['mysql'],
            $dump_file,
            !empty($opts['recreate_wamp_database']),
            $dry_run
        );
        if (!$dry_run && $res['code'] !== 0) {
            deploy_fail('Import WAMP : ' . implode("\n", $res['output']));
        }
        deploy_log('Base WAMP mise à jour.');
    }

    if (!$db_only && !empty($opts['sync_upload_to_wamp'])) {
        deploy_sync_upload_from_production($prod_files, $wamp_upload, $opts, $dry_run);
    }
}

// -------------------------------------------------------------------------
// PHASE 2 — WAMP → Serveur local
// -------------------------------------------------------------------------
if (!$prod_only && !empty($opts['push_to_local_server'])) {

    deploy_log('');
    deploy_log('--- Phase 2 : WAMP → serveur local ---');

    if (!$files_only) {
        $wamp_dump = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fouta_wamp_push_' . date('Ymd_His') . '.sql';

        deploy_log('Export BDD WAMP (' . $wamp_db['name'] . ')...');
        if (!$dry_run) {
            if (!deploy_dump_database($wamp_db, $tools['mysqldump'], $wamp_dump, false)) {
                deploy_fail('Export BDD WAMP impossible.');
            }
            deploy_log('Dump WAMP : ' . round(filesize($wamp_dump) / 1024 / 1024, 1) . ' Mo');
        }

        deploy_log('Envoi + import BDD sur serveur local...');
        deploy_push_database_to_server(
            $server,
            $dry_run ? $dump_file : $wamp_dump,
            $dry_run
        );
        deploy_log('Base serveur local mise à jour.');

        if (!$dry_run && is_file($wamp_dump)) {
            @unlink($wamp_dump);
        }
    }

    if (!$db_only && !empty($opts['sync_upload_to_server'])) {
        if (!is_dir($wamp_upload) && !$dry_run) {
            deploy_fail('Dossier upload/ WAMP introuvable : ' . $wamp_upload);
        }
        deploy_push_upload_zip_to_server($server, $wamp_upload, $dry_run);
        deploy_log('Images envoyées sur le serveur local.');
    }

    if (!empty($opts['run_sync_migrations_on_server'])) {
        deploy_log('Migrations sync sur serveur local...');
        deploy_finalize_server($server, $dry_run);
    }
}

if (!$dry_run && is_file($dump_file) && filesize($dump_file) > 0) {
    @unlink($dump_file);
}

// -------------------------------------------------------------------------
// PHASE 3 — Configuration sync incrémentale (WAMP + serveur → VPS)
// -------------------------------------------------------------------------
if (!empty($opts['configure_sync']) && !empty($cfg['sync'])) {
    $sync_scope = [];
    if ($prod_only) {
        $sync_scope = ['wamp_only' => true];
    } elseif ($server_only) {
        $sync_scope = ['server_only' => true];
    }
    deploy_setup_sync_nodes($root, $cfg['sync'], $server, $dry_run, $sync_scope);
}

deploy_log('');
deploy_log('=== Déploiement terminé ===');
deploy_log('WAMP    : http://localhost/Fouta/');
deploy_log('Serveur : ' . ($server['site_url'] ?? 'http://192.168.1.217') . '/');
