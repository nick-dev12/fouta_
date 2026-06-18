<?php
/**
 * Téléchargement PDF export catalogue — petits volumes (synchrone).
 * Au-delà de EXPORT_CATALOGUE_ASYNC_MIN produits, utiliser l’export arrière-plan.
 */

require_once __DIR__ . '/../../includes/admin_pdf_response.php';
admin_pdf_request_begin();

session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../includes/export_catalogue_job.php';

$filters = [
    'date_debut' => trim($_GET['date_debut'] ?? date('Y-m-d')),
    'date_fin' => trim($_GET['date_fin'] ?? date('Y-m-d')),
    'mode' => isset($_GET['mode']) ? strtolower(trim((string) $_GET['mode'])) : 'tous',
    'recherche' => trim($_GET['recherche'] ?? ''),
    'categorie_id' => isset($_GET['categorie_id']) ? (int) $_GET['categorie_id'] : 0,
    'marque_id' => isset($_GET['marque_id']) ? (int) $_GET['marque_id'] : 0,
    'fournisseur_id' => isset($_GET['fournisseur_id']) ? (int) $_GET['fournisseur_id'] : 0,
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

if ($total >= EXPORT_CATALOGUE_ASYNC_MIN) {
    header('Location: export-catalogue.php?' . http_build_query(array_merge($filters, ['async_pdf' => '1'])));
    exit;
}

require_once __DIR__ . '/../../models/model_produits.php';
require_once __DIR__ . '/../../includes/export_produits_catalogue_pdf.php';

$produits = get_admin_produits_export_catalogue(
    $filters['date_debut'],
    $filters['date_fin'],
    $filters['mode'],
    $filters['recherche'],
    $filters['categorie_id'],
    $filters['marque_id'],
    $filters['fournisseur_id'],
    EXPORT_CATALOGUE_PDF_MAX
);

if (!export_catalogue_send_pdf($produits, $meta)) {
    admin_pdf_send_error_html(
        'Export PDF impossible',
        export_catalogue_pdf_get_last_error() ?: 'Impossible de générer le PDF.',
        'export-catalogue.php?' . http_build_query($filters),
        'Retour à l’aperçu'
    );
}
