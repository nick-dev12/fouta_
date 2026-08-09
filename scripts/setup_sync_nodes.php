<?php
/**
 * Configure la sync incrémentale sans refaire le deploy complet.
 *
 * Usage :
 *   php scripts/setup_sync_nodes.php
 *   php scripts/setup_sync_nodes.php --wamp-only
 *   php scripts/setup_sync_nodes.php --server-only
 *   php scripts/setup_sync_nodes.php --dry-run
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
$scope = [];
if (in_array('--wamp-only', $argv, true)) {
    $scope['wamp_only'] = true;
}
if (in_array('--server-only', $argv, true)) {
    $scope['server_only'] = true;
}

if (empty($cfg['sync'])) {
    deploy_fail('Section sync manquante dans config/deploy_wamp.php');
}

deploy_setup_sync_nodes($root, $cfg['sync'], $cfg['local_server'] ?? [], $dry_run, $scope);

deploy_log('=== Configuration sync terminée ===');
deploy_log('WAMP    : php scripts/sync_test_ping.php && php scripts/sync_run.php');
deploy_log('Serveur : ssh jomas@100.120.171.2 "sudo -u www-data php /var/www/fouta/scripts/sync_test_ping.php"');
