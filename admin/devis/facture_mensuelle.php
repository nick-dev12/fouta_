<?php
/**
 * Affichage facture mensuelle HT (B2B) — design identique facture commande
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}
require_once __DIR__ . '/../includes/require_access.php';


require_once __DIR__ . '/../../includes/admin_permissions.php';
if (!admin_can_devis_bl() && !admin_can_comptabilite()) {
    header('Location: ../dashboard.php');
    exit;
}

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

$facture_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($facture_id <= 0) {
    header('Location: index.php?tab=bl');
    exit;
}

require_once __DIR__ . '/../../models/model_factures_mensuelles.php';
require_once __DIR__ . '/../../models/model_clients_b2b.php';
require_once __DIR__ . '/../../includes/site_url.php';

if (!factures_mensuelles_table_ok()) {
    header('Location: index.php?tab=bl');
    exit;
}

$facture_fm = get_facture_mensuelle_by_id($facture_id);
if (!$facture_fm) {
    header('Location: index.php?tab=bl');
    exit;
}

$client = get_client_b2b_by_id((int) $facture_fm['client_b2b_id']);
if (!$client) {
    header('Location: index.php?tab=bl');
    exit;
}

$detail_bls = get_bls_et_lignes_facture_mensuelle($facture_id);

$facture = [
    'numero_facture' => $facture_fm['numero_facture'],
    'montant_total' => (float) $facture_fm['total_ht'],
    'client_b2b_id' => (int) $facture_fm['client_b2b_id'],
];

$client_nom = $client['raison_sociale'] ?? '';
$client_telephone = $client['telephone'] ?? '';
$adresse_livraison = $client['adresse'] ?? '';

$mois_fr = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
$periode_label = $mois_fr[max(1, min(12, (int) $facture_fm['mois'])) - 1] . ' ' . (int) $facture_fm['annee'];

$st = $facture_fm['statut'] ?? 'brouillon';
$fm_statut = $st;
if ($st === 'brouillon') {
    $statut_fm_label = 'Brouillon';
} elseif ($st === 'validee' || $st === 'payee') {
    $statut_fm_label = 'Payée';
} else {
    $statut_fm_label = $st;
}

$d_src = $facture_fm['date_emission'] ?? $facture_fm['date_creation'];
$d_facture = strtotime($d_src ?: 'now');
$mois_noms_long = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
$date_facture_aff = date('j', $d_facture) . ' ' . $mois_noms_long[(int) date('n', $d_facture) - 1] . ' ' . date('Y', $d_facture);

$entreprise_nom = 'FOUTA POIDS LOURDS';
$entreprise_rc = 'SN.DKR.2022.A.702';
$entreprise_ninea = '009116684';
$entreprise_adresse = 'Rond point ZAC MBAO, Dakar';
$entreprise_tel1 = '338700070';
$entreprise_tel2 = '';
$entreprise_site = 'https://www.foutapoidslourds.com';
$entreprise_email = 'info@foutapoidslourds.com';

$is_public = false;

$__role_fm = admin_normalize_role_for_route($_SESSION['admin_role'] ?? 'admin');
if ($__role_fm === 'comptabilite') {
    $facture_back_url = '../comptabilite/bl-fiche-client.php?id=' . (int) $facture_fm['client_b2b_id'];
    $facture_back_label = 'Retour fiche client (comptabilité)';
} else {
    $facture_back_url = 'bl_par_client.php?id=' . (int) $facture_fm['client_b2b_id'];
    $facture_back_label = 'Retour aux BL du client';
}

$fm_show_validate = ($st === 'brouillon') && admin_can_comptabilite();
$facture_mensuelle_id = $facture_id;
$admin_csrf_token = $_SESSION['admin_csrf'];

$tel_whatsapp = preg_replace('/\D/', '', $client_telephone ?? '');
if (strlen($tel_whatsapp) === 9 && in_array(substr($tel_whatsapp, 0, 2), ['70', '76', '77', '78'], true)) {
    $tel_whatsapp = '221' . $tel_whatsapp;
} elseif (strlen($tel_whatsapp) === 10 && substr($tel_whatsapp, 0, 1) === '0') {
    $tel_whatsapp = '221' . substr($tel_whatsapp, 1);
}

$base_url = get_site_base_url();
$lignes_txt = [];
foreach ($detail_bls as $block) {
    foreach ($block['lignes'] ?? [] as $ln) {
        $lignes_txt[] = '- ' . ($ln['designation'] ?? '') . ' · ' . ($ln['quantite'] ?? '') . ' × ' . number_format((float) ($ln['prix_unitaire_ht'] ?? 0), 0, ',', ' ');
    }
}
$msg_whatsapp = "Bonjour,\n\n"
    . "Votre facture HT n°" . ($facture_fm['numero_facture'] ?? '') . " (" . $periode_label . ") d’un montant de "
    . number_format((float) $facture_fm['total_ht'], 0, ',', ' ') . " CFA est disponible.\n\n"
    . "Détail :\n" . implode("\n", $lignes_txt) . "\n\nCordialement,\n" . $entreprise_nom;
$whatsapp_url = !empty($tel_whatsapp) ? 'https://wa.me/' . $tel_whatsapp . '?text=' . rawurlencode($msg_whatsapp) : '';

$fm_flash_success = $_SESSION['success_message'] ?? null;
if (isset($_SESSION['success_message'])) {
    unset($_SESSION['success_message']);
}

$facture_show_client_zone = true;

require __DIR__ . '/../../includes/facture_mensuelle_content.php';
