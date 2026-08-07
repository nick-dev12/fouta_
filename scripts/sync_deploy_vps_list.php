<?php
/**
 * Liste les fichiers à uploader sur le VPS pour activer la synchronisation.
 * CLI : php scripts/sync_deploy_vps_list.php
 */

$root = dirname(__DIR__);
$paths = [
    'sync/api.php',
    'sync/.htaccess',
    'includes/sync_registry.php',
    'includes/sync_functions.php',
    'includes/sync_hooks.php',
    'config/sync.example.php',
    'migrations/create_sync_infrastructure.sql',
    'migrations/run_add_sync_columns.php',
    'migrations/run_assign_sync_uuids.php',
    'scripts/sync_run.php',
    'scripts/sync_push.php',
    'scripts/sync_pull.php',
    'scripts/sync_files.php',
    'scripts/sync_verify.php',
    'scripts/sync_test_ping.php',
    'admin/sync/index.php',
    'docs/DEPLOIEMENT_LOCAL_ET_SYNC.md',
];

echo "=== Fichiers sync à déployer sur le VPS ===\n\n";
foreach ($paths as $rel) {
    $full = $root . '/' . $rel;
    $status = is_file($full) ? 'OK' : 'MANQUANT';
    echo "[$status] $rel\n";
}

echo "\n=== Étapes post-upload VPS ===\n";
echo "1. cp config/sync.example.php config/sync.php\n";
echo "2. Configurer node_id=vps_prod et remote_api_token (identique au local)\n";
echo "3. php migrations/run_add_sync_columns.php\n";
echo "4. php migrations/run_assign_sync_uuids.php\n";
echo "5. php scripts/sync_test_ping.php (depuis WAMP)\n";
echo "6. php scripts/sync_pull.php (pull initial WAMP)\n";
