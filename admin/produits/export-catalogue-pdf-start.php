<?php
/**
 * Démarre un export catalogue PDF en arrière-plan (JSON puis traitement).
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
require_once __DIR__ . '/../../includes/export_catalogue_suivi.php';
require_once __DIR__ . '/../../includes/export_produits_catalogue_pdf.php';

$source = array_merge($_GET, $_POST);

try {
    $filters = export_catalogue_filters_from_request($source);
    $meta = export_catalogue_build_meta_from_filters($source);
} catch (Throwable $e) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Erreur serveur : ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}

$total = (int) ($meta['total'] ?? 0);

if ($total <= 0) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Aucun produit à exporter.'], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($total > EXPORT_CATALOGUE_PDF_MAX) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Maximum ' . EXPORT_CATALOGUE_PDF_MAX . ' produits par export.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$job_filters = array_merge($filters, [
    'pdf_cols' => $meta['pdf_cols'] ?? [],
]);

$prix_draft = export_catalogue_prix_draft_from_request($source);
if ($prix_draft !== []) {
    $meta['prix_draft'] = $prix_draft;
}

$job = export_catalogue_job_create((int) $_SESSION['admin_id'], $job_filters, $meta);
if ($job === null) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => export_catalogue_job_last_setup_error()], JSON_UNESCAPED_UNICODE);
    exit;
}

export_catalogue_job_send_json_only($job);
exit;
