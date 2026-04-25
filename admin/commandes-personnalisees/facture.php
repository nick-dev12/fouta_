<?php
/**
 * Affichage d'une facture de commande personnalisée (Admin)
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}
require_once __DIR__ . '/../includes/require_access.php';


$facture_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($facture_id <= 0) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../../includes/asset_version.php';
require_once __DIR__ . '/../../models/model_factures_personnalisees.php';
require_once __DIR__ . '/../../models/model_commandes_personnalisees.php';
require_once __DIR__ . '/../../includes/site_url.php';

$facture = get_facture_personnalisee_by_id($facture_id);
if (!$facture) {
    header('Location: index.php');
    exit;
}

$token = $facture['token'] ?? null;
if (empty($token)) {
    $token = ensure_facture_personnalisee_token($facture_id);
    if ($token) {
        $facture = get_facture_personnalisee_by_id($facture_id);
    }
}

$cp = get_commande_personnalisee_by_id($facture['commande_personnalisee_id']);
if (!$cp) {
    header('Location: index.php');
    exit;
}

$client_nom = trim(($cp['prenom'] ?? '') . ' ' . ($cp['nom'] ?? ''));
$client_telephone = $cp['telephone'] ?? '';

// Normaliser le téléphone pour WhatsApp
$tel_whatsapp = preg_replace('/\D/', '', $client_telephone);
if (strlen($tel_whatsapp) === 9 && in_array(substr($tel_whatsapp, 0, 2), ['70', '76', '77', '78'])) {
    $tel_whatsapp = '221' . $tel_whatsapp;
} elseif (strlen($tel_whatsapp) === 10 && substr($tel_whatsapp, 0, 1) === '0') {
    $tel_whatsapp = '221' . substr($tel_whatsapp, 1);
}

$mois = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
$d_facture = strtotime($facture['date_facture']);
$date_facture_aff = date('j', $d_facture) . ' ' . $mois[(int) date('n', $d_facture) - 1] . ' ' . date('Y', $d_facture);

$base_url = get_site_base_url();
$facture_url = $base_url . '/facture-cp.php?token=' . ($token ?? '');

$montant_aff = ($facture['montant_total'] ?? 0) > 0
    ? number_format($facture['montant_total'], 0, ',', ' ') . ' CFA'
    : 'À définir';

$msg_whatsapp = "Bonjour " . $client_nom . ",\n\n"
    . "Votre devis/facture n°" . $facture['numero_facture'] . " pour la demande personnalisée #" . $cp['id'] . " est prête.\n\n"
    . "Description : " . substr(str_replace(["\r", "\n"], ' ', $cp['description'] ?? ''), 0, 200) . "...\n\n"
    . "Montant : " . $montant_aff . "\n"
    . "Date : " . $date_facture_aff . "\n\n"
    . "Consultez votre facture en ligne :\n" . $facture_url . "\n\n"
    . "Cordialement,\nFOUTA POIDS LOURDS";
$whatsapp_url = !empty($tel_whatsapp) ? 'https://wa.me/' . $tel_whatsapp . '?text=' . urlencode($msg_whatsapp) : '';

$entreprise_nom = 'FOUTA POIDS LOURDS';
$entreprise_rc = 'SN.DKR.2022.A.702';
$entreprise_ninea = '009116684';
$entreprise_adresse = 'Rond point ZAC MBAO, Dakar';
$entreprise_tel1 = '338700070';
$entreprise_tel2 = '';
$entreprise_site = 'https://www.foutapoidslourds.com';
$entreprise_email = 'info@foutapoidslourds.com';

$is_public = false;
require __DIR__ . '/../../includes/facture_cp_content.php';