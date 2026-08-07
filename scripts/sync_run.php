<?php
/**
 * Synchronisation selon config/sync.php (par défaut : push local → VPS).
 * CLI : php scripts/sync_run.php [--dry-run]
 */

require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/../includes/sync_functions.php';

global $db;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Ce script doit être exécuté en CLI.\n");
    exit(1);
}

if (!$db instanceof PDO) {
    fwrite(STDERR, "Connexion base indisponible.\n");
    exit(1);
}

$dry_run = in_array('--dry-run', $argv ?? [], true);

try {
    ini_set('max_execution_time', '600');
    $config = sync_load_config();
    echo '=== Sync (' . sync_direction_label($config) . ") — node: {$config['node_id']} ===\n";
    if ($dry_run) {
        echo "Mode dry-run activé\n";
    }

    $result = sync_run($db, $config, $dry_run);
    if (isset($result['pull'])) {
        echo 'Pull : ' . json_encode($result['pull'], JSON_UNESCAPED_UNICODE) . "\n";
    }
    if (isset($result['push'])) {
        echo 'Push : ' . json_encode($result['push'], JSON_UNESCAPED_UNICODE) . "\n";
    }
    if (isset($result['message'])) {
        echo $result['message'] . "\n";
    }
    echo "Terminé.\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'ERREUR : ' . $e->getMessage() . "\n");
    exit(1);
}
