<?php
/**
 * Annule un export catalogue PDF en cours (JSON).
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

$source = array_merge($_GET, $_POST);
$job_id = trim($source['job'] ?? '');
$token = trim($source['token'] ?? '');

if ($job_id === '' || $token === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Paramètres manquants.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$ok = export_catalogue_job_cancel($job_id, $token, $admin_id);

echo json_encode([
    'ok' => $ok,
    'error' => $ok ? '' : 'Impossible d’annuler cette tâche.',
], JSON_UNESCAPED_UNICODE);
