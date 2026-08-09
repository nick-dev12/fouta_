<?php
/**
 * Déploiement complet depuis WAMP :
 *   1. Production (e.foutapoidslourds.com) → WAMP (BDD + images)
 *   2. WAMP → serveur local fplserver (BDD + upload/)
 *
 * Refresh complet quotidien (recommandé) :
 *   php scripts/sync_prod_to_wamp_and_server.php --full
 *   scripts/sync_full_refresh.bat
 *
 * Autres options :
 *   --prod-only | --server-only | --db-only | --files-only | --dry-run
 *   --with-sync-config  (reconfigurer sync incrémentale en plus)
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
$full = in_array('--full', $argv, true);
$prod_only = in_array('--prod-only', $argv, true);
$server_only = in_array('--server-only', $argv, true);
$db_only = in_array('--db-only', $argv, true);
$files_only = in_array('--files-only', $argv, true);
$with_sync_config = in_array('--with-sync-config', $argv, true);

$prod_db = $cfg['production_db'] ?? [];
$prod_files = $cfg['production_files'] ?? [];
$wamp = $cfg['wamp'] ?? [];
$server = $cfg['local_server'] ?? [];
$opts = $cfg['options'] ?? [];

if ($full) {
    $opts['full_refresh'] = true;
}
$opts = deploy_apply_full_refresh_options($opts);
if ($with_sync_config) {
    $opts['configure_sync'] = true;
}

$wamp_root = rtrim(str_replace('\\', '/', $wamp['web_root'] ?? $root), '/');
$wamp_root_os = str_replace('/', DIRECTORY_SEPARATOR, $wamp_root);
$wamp_upload = $wamp_root_os . DIRECTORY_SEPARATOR . 'upload';

$tools = deploy_mysql_tools($wamp);
$wamp_db = deploy_wamp_db_cfg($wamp, $root);
$site_url = $cfg['production_site_url'] ?? 'https://e.foutapoidslourds.com';

deploy_log('=== Déploiement Fouta ===');
if (!empty($opts['full_refresh'])) {
    deploy_log('Mode : REFRESH COMPLET (production → WAMP → serveur local)');
}
deploy_log('Production : ' . $site_url);
deploy_log('WAMP       : ' . $wamp_root_os . ' (' . $wamp_db['name'] . ')');
deploy_log('Serveur    : ' . ($server['site_url'] ?? '') . ' (' . ($server['web_root'] ?? '') . ')');

$dump_file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fouta_deploy_' . date('Ymd_His') . '.sql';
$wamp_pdo = null;

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

        deploy_log('Import dans WAMP (' . $wamp_db['name'] . ') — recréation complète...');
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
        if (!$dry_run) {
            try {
                $wamp_pdo = deploy_connect_wamp_db($wamp_db);
                $count = (int) $wamp_pdo->query('SELECT COUNT(*) FROM produits')->fetchColumn();
                deploy_log('Produits en BDD WAMP : ' . $count);
            } catch (Throwable $e) {
                deploy_log('Note : impossible de compter les produits WAMP.');
            }
        }
    }

    if (!$db_only && !empty($opts['sync_upload_to_wamp'])) {
        deploy_sync_upload_from_production(
            $prod_files,
            $wamp_upload,
            $opts,
            $dry_run,
            $wamp_pdo,
            $site_url
        );
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

        deploy_log('Envoi + import BDD sur serveur local — recréation complète...');
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
        $file_count = $dry_run ? 0 : deploy_count_files($wamp_upload);
        deploy_log('Fichiers upload/ WAMP à envoyer : ' . $file_count);
        deploy_push_upload_zip_to_server(
            $server,
            $wamp_upload,
            $dry_run,
            !empty($opts['wipe_upload_on_server'])
        );
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
// PHASE 3 — Configuration sync incrémentale (optionnel)
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
deploy_log('Serveur : http://100.120.171.2/ (Tailscale) ou http://192.168.1.217/ (LAN)');
