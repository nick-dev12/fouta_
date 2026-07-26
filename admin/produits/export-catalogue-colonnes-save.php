<?php
/**
 * Enregistrement des colonnes visibles du tableau suivi catalogue (par administrateur).
 */
session_start();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Non authentifié'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../includes/require_access_json.php';
require_once __DIR__ . '/../../conn/conn.php';
require_once __DIR__ . '/../../includes/export_catalogue_suivi.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Méthode non autorisée.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$csrf = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
$session_csrf = isset($_SESSION['admin_csrf']) ? (string) $_SESSION['admin_csrf'] : '';
if ($csrf === '' || $session_csrf === '' || !hash_equals($session_csrf, $csrf)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Session expirée. Rechargez la page.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$cols_in = [];
if (isset($_POST['cols']) && is_array($_POST['cols'])) {
    $cols_in = $_POST['cols'];
} elseif (isset($_POST['cols']) && is_string($_POST['cols'])) {
    $decoded = json_decode($_POST['cols'], true);
    if (is_array($decoded)) {
        $cols_in = $decoded;
    }
}

$res = export_catalogue_suivi_colonnes_save((int) $_SESSION['admin_id'], $cols_in);
if (!$res['success']) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $res['message']], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'ok' => true,
    'message' => $res['message'],
    'colonnes' => $res['colonnes'] ?? [],
], JSON_UNESCAPED_UNICODE);
exit;
