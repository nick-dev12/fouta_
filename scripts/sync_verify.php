<?php
/**
 * Compare le nombre d'enregistrements par table (local vs remote_db_verify).
 * CLI : php scripts/sync_verify.php
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

try {
    $config = sync_load_config();
    echo "=== Vérification sync (node: {$config['node_id']}) ===\n";
    $report = sync_verify_remote_db($db, $config);

    printf("%-40s %10s %10s %10s\n", 'Table', 'Local', 'Remote', 'Diff');
    echo str_repeat('-', 74) . "\n";
    foreach ($report as $row) {
        printf(
            "%-40s %10d %10s %10s\n",
            $row['table'],
            $row['local'],
            $row['remote'] >= 0 ? (string) $row['remote'] : 'N/A',
            $row['diff'] !== null ? (string) $row['diff'] : 'N/A'
        );
    }
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'ERREUR : ' . $e->getMessage() . "\n");
    exit(1);
}
