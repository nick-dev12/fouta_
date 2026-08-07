<?php
/**
 * API de synchronisation (HTTPS + token Bearer).
 * Actions : ping, pull, push, file_push
 */

declare(strict_types=1);

// Réponses JSON propres (pas de warnings PHP affichés)
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/conn/conn.php';
require_once dirname(__DIR__) . '/includes/sync_functions.php';

global $db;

function sync_api_json_error($message, $code = 400) {
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'error' => $message,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if (!$db instanceof PDO) {
        sync_api_json_error('Connexion base indisponible', 500);
    }

    $config = sync_load_config();
    sync_bootstrap_connection($db, $config);
    sync_ensure_infrastructure($db);

    if (!sync_api_verify_token($config)) {
        sync_api_json_error('Token non autorisé', 401);
    }

    $action = $_GET['action'] ?? 'ping';
    $raw = file_get_contents('php://input');
    $input = [];
    if ($raw !== false && trim($raw) !== '') {
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            sync_api_json_error('JSON invalide');
        }
        $input = $decoded;
    }

    switch ($action) {
        case 'ping':
            echo json_encode([
                'success' => true,
                'node_id' => $config['node_id'],
                'time' => date('c'),
                'tables' => count(sync_registry_sort_tables($db, $config)),
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'pull':
            $result = sync_api_handle_pull($db, $input, $config);
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            break;

        case 'push':
            $result = sync_api_handle_push($db, $input, $config);
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            break;

        case 'file_push':
            $result = sync_api_handle_file_push($input, $config);
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            break;

        case 'file_push_batch':
            $result = sync_api_handle_file_push_batch($input, $config);
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            break;

        default:
            sync_api_json_error('Action inconnue : ' . $action);
    }
} catch (Throwable $e) {
    sync_api_json_error($e->getMessage(), 500);
}
