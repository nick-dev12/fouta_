<?php
/**
 * Téléchargement PDF export catalogue produits.
 */

require_once __DIR__ . '/../../includes/admin_pdf_response.php';
admin_pdf_request_begin();

session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../models/model_produits.php';
require_once __DIR__ . '/../../models/model_categories.php';
require_once __DIR__ . '/../../includes/export_produits_catalogue_pdf.php';

$today = date('Y-m-d');
$date_debut = trim($_GET['date_debut'] ?? $today);
$date_fin = trim($_GET['date_fin'] ?? $today);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_debut)) {
    $date_debut = $today;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_fin)) {
    $date_fin = $today;
}

$mode = isset($_GET['mode']) ? strtolower(trim((string) $_GET['mode'])) : 'tous';
if (!in_array($mode, ['complet', 'ajout', 'modification', 'tous'], true)) {
    $mode = 'tous';
}

$recherche = trim($_GET['recherche'] ?? '');
$categorie_id = isset($_GET['categorie_id']) ? (int) $_GET['categorie_id'] : 0;
$marque_id = isset($_GET['marque_id']) ? (int) $_GET['marque_id'] : 0;
$fournisseur_id = isset($_GET['fournisseur_id']) ? (int) $_GET['fournisseur_id'] : 0;

$categorie_nom = 'Toutes les catégories';
if ($categorie_id > 0) {
    $cat = get_categorie_by_id($categorie_id);
    if ($cat && !empty($cat['nom'])) {
        $categorie_nom = (string) $cat['nom'];
    }
}

$has_marque_filtre = produits_has_column('marque_id');
$marque_nom = 'Toutes les marques';
if ($has_marque_filtre && $marque_id > 0) {
    require_once __DIR__ . '/../../models/model_marques.php';
    if (marques_table_ok()) {
        $marque = get_marque_by_id($marque_id);
        if ($marque && !empty($marque['nom'])) {
            $marque_nom = (string) $marque['nom'];
        }
    }
}

$has_fournisseur_filtre = produits_has_column('fournisseur_id');
$fournisseur_nom = 'Tous les fournisseurs';
if ($has_fournisseur_filtre && $fournisseur_id > 0) {
    require_once __DIR__ . '/../../models/model_fournisseurs.php';
    $four = get_fournisseur_by_id($fournisseur_id);
    if ($four && !empty($four['nom'])) {
        $fournisseur_nom = (string) $four['nom'];
    }
}

$total = count_admin_produits_export_catalogue($date_debut, $date_fin, $mode, $recherche, $categorie_id, $marque_id, $fournisseur_id);
$produits = get_admin_produits_export_catalogue($date_debut, $date_fin, $mode, $recherche, $categorie_id, $marque_id, $fournisseur_id, 1000);

$meta = [
    'date_debut' => $date_debut,
    'date_fin' => $date_fin,
    'mode' => $mode,
    'mode_label' => export_catalogue_pdf_mode_label($mode),
    'recherche' => $recherche,
    'total' => $total,
    'categorie_nom' => $categorie_nom,
    'marque_nom' => $marque_nom,
    'fournisseur_nom' => $fournisseur_nom,
    'show_categorie_filtre' => true,
    'show_marque_filtre' => $has_marque_filtre,
    'show_fournisseur_filtre' => $has_fournisseur_filtre,
];

if (!export_catalogue_send_pdf($produits, $meta)) {
    admin_pdf_send_error_html(
        'Export PDF impossible',
        export_catalogue_pdf_get_last_error() ?: 'Impossible de générer le PDF.',
        'export-catalogue.php?' . http_build_query([
            'date_debut' => $date_debut,
            'date_fin' => $date_fin,
            'mode' => $mode,
            'recherche' => $recherche,
            'categorie_id' => $categorie_id,
            'marque_id' => $marque_id,
            'fournisseur_id' => $fournisseur_id,
        ]),
        'Retour à l’aperçu'
    );
}
