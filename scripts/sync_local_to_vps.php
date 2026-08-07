<?php
/**
 * Commande unique : données BDD + fichiers (images) local → VPS.
 * CLI : php scripts/sync_local_to_vps.php [--dry-run]
 *
 * Équivalent à : sync_push + sync_files (incrémental uniquement).
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
    ini_set('max_execution_time', '900');
    $config = sync_load_config();
    echo "=== Sync local → VPS (node: {$config['node_id']}) ===\n";
    echo "Données BDD + fichiers upload/ (incrémental)\n";
    if ($dry_run) {
        echo "Mode dry-run activé\n";
    }

    $result = sync_local_to_vps($db, $config, $dry_run);

    if (isset($result['push'])) {
        echo "\n--- Base de données ---\n";
        echo json_encode($result['push'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }
    if (isset($result['files'])) {
        echo "\n--- Fichiers (upload/produits, slider, employes, etc.) ---\n";
        echo json_encode($result['files'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }

    echo "\nTerminé.\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'ERREUR : ' . $e->getMessage() . "\n");
    exit(1);
}
