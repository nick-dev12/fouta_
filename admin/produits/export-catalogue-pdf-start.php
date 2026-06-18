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

require_once __DIR__ . '/../includes/require_access_json.php';
require_once __DIR__ . '/../../includes/export_catalogue_job.php';

$source = array_merge($_GET, $_POST);

$filters = [
    'date_debut' => trim($source['date_debut'] ?? date('Y-m-d')),
    'date_fin' => trim($source['date_fin'] ?? date('Y-m-d')),
    'mode' => isset($source['mode']) ? strtolower(trim((string) $source['mode'])) : 'tous',
    'recherche' => trim($source['recherche'] ?? ''),
    'categorie_id' => (int) ($source['categorie_id'] ?? 0),
    'marque_id' => (int) ($source['marque_id'] ?? 0),
    'fournisseur_id' => (int) ($source['fournisseur_id'] ?? 0),
];

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['date_debut'])) {
    $filters['date_debut'] = date('Y-m-d');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['date_fin'])) {
    $filters['date_fin'] = date('Y-m-d');
}
if (!in_array($filters['mode'], ['complet', 'ajout', 'modification', 'tous'], true)) {
    $filters['mode'] = 'tous';
}

try {
    $meta = export_catalogue_build_meta_from_filters($filters);
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

$job = export_catalogue_job_create((int) $_SESSION['admin_id'], $filters, $meta);
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
