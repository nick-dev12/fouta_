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
if (!admin_can_bl_retours_b2b()) {
    header('Location: ../dashboard.php');
    exit;
}

require_once __DIR__ . '/../../models/model_bl.php';
require_once __DIR__ . '/../../includes/fiscal_tva.php';

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

$tva_incl = bl_tva_columns_ok() && !empty($bl['tva_incluse']);
$taux_bl = (bl_tva_columns_ok() && isset($bl['taux_tva_pourcent']) && (float) $bl['taux_tva_pourcent'] > 0)
    ? (float) $bl['taux_tva_pourcent']
    : null;
$decomp = fiscal_decomposer_net_ht($total_ht, $tva_incl, $taux_bl);

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
    'montant_total' => $tva_incl ? $decomp['montant_ttc'] : $total_ht,
    'commande_id' => 0,
    'tva_incluse' => $tva_incl ? 1 : 0,
    'montant_ht' => $decomp['montant_ht'],
    'montant_tva' => $decomp['montant_tva'],
    'taux_tva_pourcent' => $taux_bl ?? fiscal_taux_tva_pourcent(),
];
$facture_tva_incluse = $tva_incl;
$facture_fiscal_ht = $decomp['montant_ht'];
$facture_fiscal_tva = $decomp['montant_tva'];
$facture_fiscal_taux = $taux_bl ?? fiscal_taux_tva_pourcent();

$commande = [
    'notes' => $bl['notes'] ?? '',
    'frais_livraison' => 0,
    'adresse_client' => trim((string) ($bl['adresse_client'] ?? '')),
];

$client_nom = trim($bl['raison_sociale'] ?? '');
$client_telephone = $bl['client_telephone'] ?? '';
$adresse_livraison = $bl['client_adresse'] ?? '';

$entreprise_nom = 'FOUTA POIDS LOURDS';
$entreprise_rc = 'SN.DKR.2022.A.702';
$entreprise_ninea = '009116684';
$entreprise_adresse = 'Rond point Zack Mbao, Dakar';
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
$facture_recap_label_ht_decomp = 'TOTAL BL';

require __DIR__ . '/../../includes/facture_content.php';
