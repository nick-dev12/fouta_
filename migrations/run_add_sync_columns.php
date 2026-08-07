<?php
/**
 * Migration : infrastructure sync + colonnes sync sur toutes les tables.
 * CLI : php migrations/run_add_sync_columns.php
 * Web : /migrations/run_add_sync_columns.php (admin ou token)
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
    migration_web_render_page('Sync migration', '<p>' . htmlspecialchars($msg) . '</p>');
    exit;
}

$config = null;
try {
    $config = sync_load_config();
} catch (Throwable $e) {
    $config = require dirname(__DIR__) . '/config/sync.example.php';
}

$lines = [];
$lines[] = '=== Migration colonnes sync ===';

try {
    sync_ensure_infrastructure($db);
    $lines[] = 'OK: tables sync_log, sync_state, sync_id_map, sync_file_queue';

    $result = sync_add_columns_to_tables($db, $config);
    $lines[] = 'Tables analysées : ' . $result['tables'];
    $lines[] = 'Colonnes ajoutées : ' . $result['added'];
    $lines[] = 'Tables déjà prêtes : ' . $result['skipped'];

    $triggers = sync_create_all_triggers($db, $config);
    $lines[] = 'Triggers créés/mis à jour : ' . $triggers;
} catch (Throwable $e) {
    $lines[] = 'ERREUR : ' . $e->getMessage();
}

$output = implode("\n", $lines) . "\n";

if ($is_cli) {
    echo $output;
    exit(strpos($output, 'ERREUR') !== false ? 1 : 0);
}

$body = '<h1>Migration colonnes sync</h1><pre>' . htmlspecialchars($output) . '</pre>';
migration_web_render_page('Migration sync colonnes', $body);
