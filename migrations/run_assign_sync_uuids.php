<?php
/**
 * Migration : assigner sync_uuid aux enregistrements existants.
 * CLI : php migrations/run_assign_sync_uuids.php
 */

require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/../includes/sync_functions.php';

global $db;

$is_cli = (PHP_SAPI === 'cli');
if (!$is_cli) {
    require_once __DIR__ . '/../includes/migration_web_auth.php';
    migration_web_require_auth();
}

if (!$db instanceof PDO) {
    $msg = 'Connexion à la base indisponible.';
    if ($is_cli) {
        echo $msg . "\n";
        exit(1);
    }
    migration_web_render_page('Sync UUID', '<p>' . htmlspecialchars($msg) . '</p>');
    exit;
}

$config = null;
try {
    $config = sync_load_config();
} catch (Throwable $e) {
    $config = require dirname(__DIR__) . '/config/sync.example.php';
}

$lines = [];
$lines[] = '=== Assignation sync_uuid ===';

try {
    sync_ensure_infrastructure($db);
    $updated = sync_assign_missing_uuids($db, $config);
    $lines[] = 'Enregistrements mis à jour : ' . $updated;
} catch (Throwable $e) {
    $lines[] = 'ERREUR : ' . $e->getMessage();
}

$output = implode("\n", $lines) . "\n";

if ($is_cli) {
    echo $output;
    exit(strpos($output, 'ERREUR') !== false ? 1 : 0);
}

$body = '<h1>Assignation sync_uuid</h1><pre>' . htmlspecialchars($output) . '</pre>';
migration_web_render_page('Assignation sync_uuid', $body);
