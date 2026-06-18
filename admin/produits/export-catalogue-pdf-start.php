<?php
/**
 * Démarre un export catalogue PDF en arrière-plan (JSON).
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

$filters = [
    'date_debut' => trim($_GET['date_debut'] ?? $_POST['date_debut'] ?? date('Y-m-d')),
    'date_fin' => trim($_GET['date_fin'] ?? $_POST['date_fin'] ?? date('Y-m-d')),
    'mode' => isset($_GET['mode']) || isset($_POST['mode'])
        ? strtolower(trim((string) ($_GET['mode'] ?? $_POST['mode'] ?? 'tous'))) : 'tous',
    'recherche' => trim($_GET['recherche'] ?? $_POST['recherche'] ?? ''),
    'categorie_id' => (int) ($_GET['categorie_id'] ?? $_POST['categorie_id'] ?? 0),
    'marque_id' => (int) ($_GET['marque_id'] ?? $_POST['marque_id'] ?? 0),
    'fournisseur_id' => (int) ($_GET['fournisseur_id'] ?? $_POST['fournisseur_id'] ?? 0),
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

$meta = export_catalogue_build_meta_from_filters($filters);
$total = (int) ($meta['total'] ?? 0);

if ($total <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Aucun produit à exporter.']);
    exit;
}
if ($total > EXPORT_CATALOGUE_PDF_MAX) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Maximum ' . EXPORT_CATALOGUE_PDF_MAX . ' produits par export.']);
    exit;
}

$job = export_catalogue_job_create((int) $_SESSION['admin_id'], $filters, $meta);
if ($job === null) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Impossible de créer la tâche d’export.']);
    exit;
}

if (!export_catalogue_spawn_worker($job['id'], $job['token'])) {
    export_catalogue_job_fail($job, 'Impossible de lancer le worker en arrière-plan.');
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Impossible de démarrer l’export en arrière-plan.']);
    exit;
}

echo json_encode([
    'ok' => true,
    'job_id' => $job['id'],
    'token' => $job['token'],
    'total' => $total,
    'async' => true,
]);
