<?php
/**
 * Affichage d'une facture de devis (admin, design identique à facture commande)
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}
require_once __DIR__ . '/../includes/require_access.php';

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

$facture_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($facture_id <= 0) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../../models/model_factures_devis.php';
require_once __DIR__ . '/../../models/model_devis.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['marquer_facture_payee'])) {
    $tok = (string) ($_POST['csrf_token'] ?? '');
    if ($tok === '' || !hash_equals((string) ($_SESSION['admin_csrf'] ?? ''), $tok)) {
        $_SESSION['flash_facture_error'] = 'Session expirée. Réessayez.';
    } else {
        $r = marquer_facture_devis_payee($facture_id);
        if (!empty($r['ok'])) {
            $_SESSION['success_message'] = 'Facture marquée comme payée. Référence : ' . ($r['numero_reference_fpl'] ?? '');
        } else {
            $_SESSION['flash_facture_error'] = $r['error'] ?? 'Action impossible.';
        }
    }
    header('Location: facture.php?id=' . $facture_id);
    exit;
}

require_once __DIR__ . '/../../includes/site_url.php';

$facture = get_facture_devis_by_id($facture_id);
if (!$facture) {
    header('Location: index.php');
    exit;
}

$token = $facture['token'] ?? null;
if (empty($token)) {
    $token = ensure_facture_devis_token($facture_id);
    if ($token) {
        $facture = get_facture_devis_by_id($facture_id);
    }
}

$devis = get_devis_by_id($facture['devis_id']);
$produits = get_produits_by_devis($facture['devis_id']);
$produits = is_array($produits) ? $produits : [];

// Construire une structure compatible avec facture_content (commande-like)
$commande = [
    'user_prenom' => $devis['client_prenom'] ?? '',
    'user_nom' => $devis['client_nom'] ?? '',
    'user_telephone' => $devis['client_telephone'] ?? '',
    'telephone_livraison' => $devis['client_telephone'] ?? '',
    'adresse_livraison' => $devis['adresse_livraison'] ?? '',
    'notes' => $devis['notes'] ?? '—',
    'frais_livraison' => $devis['frais_livraison'] ?? 0,
    'numero_commande' => $devis['numero_devis'] ?? ''
];

$client_nom = trim(($devis['client_prenom'] ?? '') . ' ' . ($devis['client_nom'] ?? ''));
$client_telephone = $devis['client_telephone'] ?? '';
$adresse_livraison = $devis['adresse_livraison'] ?? '';

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

// Lien public de la facture (page facture devis publique)
$base_url = get_site_base_url();
$facture_url = $base_url . '/facture-devis.php?token=' . ($token ?? '');

// Message WhatsApp
$lignes_produits = [];
foreach ($produits as $p) {
    $nom = $p['produit_nom'] ?? $p['nom_produit'] ?? '';
    $qte = (int) ($p['quantite'] ?? 0);
    $lignes_produits[] = '- ' . $nom . ' x' . $qte;
}
$wa_numero_facture = (!empty($facture['payee']) && !empty($facture['numero_reference_fpl']))
    ? (string) $facture['numero_reference_fpl']
    : (string) ($facture['numero_facture'] ?? '');
$msg_whatsapp = "Bonjour " . $client_nom . ",\n\n"
    . "Votre facture n°" . $wa_numero_facture . " pour le devis #" . ($devis['numero_devis'] ?? '') . " est prête.\n\n"
    . "Produits :\n" . implode("\n", $lignes_produits) . "\n\n"
    . "Adresse de livraison : " . str_replace(["\r", "\n"], ' ', $adresse_livraison) . "\n\n"
    . "Montant total : " . number_format($facture['montant_total'], 0, ',', ' ') . " CFA\n"
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
$facture_back_url = 'details.php?id=' . $devis['id'];
$facture_back_label = 'Retour au devis';

$facture_page_flash_success = '';
if (!empty($_SESSION['success_message'])) {
    $facture_page_flash_success = (string) $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
$facture_page_flash_error = '';
if (!empty($_SESSION['flash_facture_error'])) {
    $facture_page_flash_error = (string) $_SESSION['flash_facture_error'];
    unset($_SESSION['flash_facture_error']);
}

$facture_est_payee = !empty($facture['payee']);
$facture_numero_affichage = ($facture_est_payee && !empty($facture['numero_reference_fpl']))
    ? (string) $facture['numero_reference_fpl']
    : (string) ($facture['numero_facture'] ?? '');
$facture_afficher_marquer_payee = function_exists('factures_devis_col_payee_ok') && factures_devis_col_payee_ok() && !$facture_est_payee;
$facture_csrf_token = (string) ($_SESSION['admin_csrf'] ?? '');

require_once __DIR__ . '/../../includes/fiscal_tva.php';

$facture_tva_incluse = array_key_exists('tva_incluse', $facture)
    ? !empty($facture['tva_incluse'])
    : (devis_tva_columns_ok() && !empty($devis['tva_incluse']));
$facture_fiscal_ht = null;
$facture_fiscal_tva = null;
$facture_fiscal_taux = null;
if ($facture_tva_incluse) {
    if (factures_devis_fiscal_columns_ok()
        && isset($facture['montant_ht'])
        && $facture['montant_ht'] !== null
        && $facture['montant_ht'] !== '') {
        $facture_fiscal_ht = (float) $facture['montant_ht'];
        $facture_fiscal_tva = isset($facture['montant_tva']) ? (float) $facture['montant_tva'] : 0.0;
        $facture_fiscal_taux = (isset($facture['taux_tva_pourcent']) && (float) $facture['taux_tva_pourcent'] > 0)
            ? (float) $facture['taux_tva_pourcent']
            : fiscal_taux_tva_pourcent();
    }
    if ($facture_fiscal_ht === null) {
        $net = devis_calcul_net_ht((int) $facture['devis_id']);
        $ttp = (isset($devis['taux_tva_pourcent']) && (float) $devis['taux_tva_pourcent'] > 0)
            ? (float) $devis['taux_tva_pourcent']
            : null;
        $f = fiscal_decomposer_net_ht($net, true, $ttp);
        $facture_fiscal_ht = $f['montant_ht'];
        $facture_fiscal_tva = $f['montant_tva'];
        $facture_fiscal_taux = $ttp ?? fiscal_taux_tva_pourcent();
    }
}

require __DIR__ . '/../../includes/facture_content.php';