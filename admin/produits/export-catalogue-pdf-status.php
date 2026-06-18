<?php
/**
 * Statut d’un export catalogue PDF (JSON).
 */

session_start();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Non authentifié']);
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../includes/export_catalogue_job.php';

$job_id = trim($_GET['job'] ?? '');
$token = trim($_GET['token'] ?? '');

if ($job_id === '' || $token === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Paramètres manquants.']);
    exit;
}

$job = export_catalogue_job_load($job_id);
if ($job === null) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Tâche introuvable.']);
    exit;
}

if (!export_catalogue_job_belongs_to_admin($job, (int) $_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Accès refusé.']);
    exit;
}

if (!export_catalogue_job_token_valid($job, $token)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Jeton invalide.']);
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
]);
