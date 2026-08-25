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
require_once __DIR__ . '/../../includes/export_catalogue_suivi.php';
require_once __DIR__ . '/../../includes/export_produits_catalogue_pdf.php';

$filters = export_catalogue_filters_from_request($_GET);
$meta = export_catalogue_build_meta_from_filters($_GET);
$prix_draft = export_catalogue_prix_draft_from_request($_GET);
$total = (int) ($meta['total'] ?? 0);

$redirect_query = [
    'date_debut' => export_catalogue_format_date_input_fr($filters['date_debut']),
    'date_fin' => export_catalogue_format_date_input_fr($filters['date_fin']),
    'mode' => $filters['mode'],
    'recherche' => $filters['recherche'],
    'categorie_id' => $filters['categorie_id'],
    'marque_id' => $filters['marque_id'],
];
$pdf_cols_redirect = isset($meta['pdf_cols']) && is_array($meta['pdf_cols']) ? $meta['pdf_cols'] : [];

/* Les retours vont au SUIVI (export-catalogue-fouta-origine.php) : c'est lui
 * qui porte la barre de progression du PDF asynchrone. Depuis le 23/08,
 * export-catalogue.php est la page « Exporter les pièces ». */
if ($total >= EXPORT_CATALOGUE_ASYNC_MIN) {
    $redirect_query['async_pdf'] = '1';
    $redirect_url = 'export-catalogue-fouta-origine.php?' . http_build_query($redirect_query);
    foreach ($pdf_cols_redirect as $col) {
        $redirect_url .= '&pdf_cols[]=' . rawurlencode((string) $col);
    }
    header('Location: ' . $redirect_url);
    exit;
}

require_once __DIR__ . '/../../models/model_produits.php';

$produits = export_catalogue_load_produits_for_pdf(
    array_merge($filters, isset($meta['pdf_cols']) ? ['pdf_cols' => $meta['pdf_cols']] : []),
    $prix_draft
);

$meta['total'] = count($produits);

$back_query = $redirect_query;
unset($back_query['async_pdf']);
$back_url = 'export-catalogue-fouta-origine.php?' . http_build_query($back_query);
foreach ($pdf_cols_redirect as $col) {
    $back_url .= '&pdf_cols[]=' . rawurlencode((string) $col);
}

if (!export_catalogue_send_pdf($produits, $meta)) {
    admin_pdf_send_error_html(
        'Export PDF impossible',
        export_catalogue_pdf_get_last_error() ?: 'Impossible de générer le PDF.',
        $back_url,
        'Retour au suivi'
    );
}
