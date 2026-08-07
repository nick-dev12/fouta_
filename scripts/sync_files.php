<?php
/**
 * Sync fichiers upload/ vers le VPS.
 * CLI :
 *   php scripts/sync_files.php
 *   php scripts/sync_files.php --priority   (uniquement images référencées en BDD)
 *   php scripts/sync_files.php --dry-run
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
$priority = in_array('--priority', $argv, true);

try {
    ini_set('max_execution_time', '0');
    $config = sync_load_config();
    echo "=== Sync fichiers upload (node: {$config['node_id']}) ===\n";
    if ($priority) {
        echo "Mode priorité BDD (produits/catégories modifiés)\n";
    }
    $result = sync_files_push($db, $config, $dry_run, [
        'cli_progress' => true,
        'db_referenced_only' => $priority,
    ]);
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'ERREUR : ' . $e->getMessage() . "\n");
    exit(1);
}
