<?php
/**
 * Document facture / HT pour un bon de livraison — même présentation que admin/commandes/facture.php
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}
require_once __DIR__ . '/../includes/require_access.php';


require_once __DIR__ . '/../../includes/admin_permissions.php';
if (!admin_can_devis_bl()) {
    header('Location: ../dashboard.php');
    exit;
}

require_once __DIR__ . '/../../models/model_bl.php';

$bl_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($bl_id <= 0 || !bl_tables_available()) {
    header('Location: index.php?tab=bl');
    exit;
}

$bl = get_bl_by_id($bl_id);
if (!$bl) {
    header('Location: index.php?tab=bl');
    exit;
}

$lignes = get_lignes_bl($bl_id);
$total_ht = (float) ($bl['total_ht'] ?? 0);

$d_bl = strtotime($bl['date_bl'] ?? 'now');
$mois = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
$date_facture_aff = date('j', $d_bl) . ' ' . $mois[(int) date('n', $d_bl) - 1] . ' ' . date('Y', $d_bl);

$produits = [];
foreach ($lignes as $l) {
    $produits[] = [
        'produit_nom' => $l['designation'] ?? '',
        'nom' => $l['designation'] ?? '',
        'prix_unitaire' => (float) ($l['prix_unitaire_ht'] ?? 0),
        'quantite' => $l['quantite'] ?? 0,
        'prix_total' => (float) ($l['total_ligne_ht'] ?? 0),
    ];
}

$facture = [
    'numero_facture' => $bl['numero_bl'] ?? '',
    'montant_total' => $total_ht,
    'commande_id' => 0,
];

$commande = [
    'notes' => $bl['notes'] ?? '',
    'frais_livraison' => 0,
];

$client_nom = trim($bl['raison_sociale'] ?? '');
$client_telephone = $bl['client_telephone'] ?? '';
$adresse_livraison = $bl['client_adresse'] ?? '';

$entreprise_nom = 'FOUTA POIDS LOURDS';
$entreprise_rc = 'SN.DKR.2022.A.702';
$entreprise_ninea = '009116684';
$entreprise_adresse = 'Rond point ZAC MBAO, Dakar';
$entreprise_tel1 = '338700070';
$entreprise_tel2 = '';
$entreprise_site = 'https://www.foutapoidslourds.com';
$entreprise_email = 'info@foutapoidslourds.com';

$is_public = false;
$whatsapp_url = '';
$facture_back_url = 'bl_voir.php?id=' . $bl_id;
$facture_back_label = 'Retour au bon de livraison';

$facture_bl_statut_libelle = bl_libelle_statut_facture($bl['statut'] ?? 'brouillon');
$facture_bl_statut_code = (string) ($bl['statut'] ?? 'brouillon');
$facture_show_client_zone = true;

require __DIR__ . '/../../includes/facture_content.php';
