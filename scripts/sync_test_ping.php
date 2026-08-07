<?php
/**
 * Test rapide connexion API sync distante.
 * CLI : php scripts/sync_test_ping.php
 */

require_once __DIR__ . '/../includes/sync_functions.php';

try {
    $result = sync_remote_request('ping', []);
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'ERREUR : ' . $e->getMessage() . "\n");
    exit(1);
}
