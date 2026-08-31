<?php
/**
 * Statut d’un export catalogue PDF (JSON).
 */

ob_start();

session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Non authentifié'], JSON_UNESCAPED_UNICODE);
    exit;
}

/* LE GARDE-BARRIÈRE (31/08/2026) : cette page ne demandait jamais si le
 * compte connecté avait le droit d'être là. La règle existe depuis
 * toujours dans includes/admin_route_access.php ; il manquait l'appel. */
require_once __DIR__ . '/../../includes/admin_route_access.php';
admin_route_enforce_json_empty();
require_once __DIR__ . '/../includes/require_access_json.php';
require_once __DIR__ . '/../../includes/export_catalogue_job.php';

$admin_id = (int) $_SESSION['admin_id'];

if (function_exists('session_write_close')) {
    session_write_close();
}

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$job_id = trim($_GET['job'] ?? '');
$token = trim($_GET['token'] ?? '');

if ($job_id === '' || $token === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Paramètres manquants.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$job = export_catalogue_job_load($job_id);
if ($job === null) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Tâche introuvable.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!export_catalogue_job_belongs_to_admin($job, $admin_id)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Accès refusé.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!export_catalogue_job_token_valid($job, $token)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Jeton invalide.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$status = (string) ($job['status'] ?? 'queued');
$download_url = '';
if ($status === 'done') {
    $download_url = 'export-catalogue-pdf-download.php?job=' . rawurlencode($job_id)
        . '&token=' . rawurlencode($token);
}

echo json_encode([
    'ok' => true,
    'status' => $status,
    'progress' => (int) ($job['progress'] ?? 0),
    'message' => (string) ($job['message'] ?? ''),
    'error' => (string) ($job['error'] ?? ''),
    'total' => (int) ($job['total'] ?? 0),
    'download_url' => $download_url,
    'filename' => (string) ($job['pdf_filename'] ?? 'catalogue.pdf'),
], JSON_UNESCAPED_UNICODE);
