<?php
/**
 * Test rapide connexion API sync distante.
 * CLI : php scripts/sync_test_ping.php
 */

require_once __DIR__ . '/../includes/sync_functions.php';

try {
    $config = sync_load_config();
    echo 'URL : ' . sync_build_api_url('ping', $config) . "\n";
    $result = sync_remote_request('ping', [], $config);
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'ERREUR : ' . $e->getMessage() . "\n");
    exit(1);
}
