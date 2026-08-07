<?php
/**
 * Commande unique : données BDD + fichiers (images) local → VPS.
 * CLI :
 *   php scripts/sync_local_to_vps.php
 *   php scripts/sync_local_to_vps.php --files-only
 *   php scripts/sync_local_to_vps.php --files-priority   (images liées aux produits modifiés)
 *   php scripts/sync_local_to_vps.php --dry-run
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

$argv = $argv ?? [];
$dry_run = in_array('--dry-run', $argv, true);
$files_only = in_array('--files-only', $argv, true);
$files_priority = in_array('--files-priority', $argv, true);

try {
    ini_set('max_execution_time', '0');
    $config = sync_load_config();
    echo "=== Sync local → VPS (node: {$config['node_id']}) ===\n";
    if ($files_only) {
        echo "Mode fichiers uniquement\n";
    } elseif ($files_priority) {
        echo "Priorité : images référencées en BDD (produits modifiés)\n";
    } else {
        echo "Données BDD + fichiers upload/ (produits en priorité)\n";
    }
    if ($dry_run) {
        echo "Mode dry-run activé\n";
    }

    $options = [
        'files_only' => $files_only,
        'files_priority_db' => $files_priority,
    ];
    $result = sync_local_to_vps($db, $config, $dry_run, $options);

    if (isset($result['push'])) {
        echo "\n--- Base de données ---\n";
        echo json_encode($result['push'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }
    if (isset($result['files'])) {
        echo "\n--- Fichiers ---\n";
        echo json_encode($result['files'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }

    echo "\nTerminé.\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'ERREUR : ' . $e->getMessage() . "\n");
    exit(1);
}
