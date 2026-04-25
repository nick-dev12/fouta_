<?php
/**
 * Affichage public d'une facture de commande personnalisée (accès par token)
 * URL: /facture-cp.php?token=xxx
 */
$token = isset($_GET['token']) ? trim($_GET['token']) : '';
if (empty($token)) {
    header('HTTP/1.0 404 Not Found');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Facture introuvable</title></head><body><h1>Facture introuvable</h1><p>Le lien est invalide ou a expiré.</p></body></html>';
    exit;
}

require_once __DIR__ . '/includes/asset_version.php';
require_once __DIR__ . '/models/model_factures_personnalisees.php';
require_once __DIR__ . '/models/model_commandes_personnalisees.php';

$facture = get_facture_personnalisee_by_token($token);
if (!$facture) {
    header('HTTP/1.0 404 Not Found');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Facture introuvable</title></head><body><h1>Facture introuvable</h1><p>Le lien est invalide ou a expiré.</p></body></html>';
    exit;
}

$cp = get_commande_personnalisee_by_id($facture['commande_personnalisee_id']);
if (!$cp) {
    header('HTTP/1.0 404 Not Found');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Facture introuvable</title></head><body><h1>Facture introuvable</h1></body></html>';
    exit;
}

$client_nom = trim(($cp['prenom'] ?? '') . ' ' . ($cp['nom'] ?? ''));
$client_telephone = $cp['telephone'] ?? '';

$mois = ['janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
$d_facture = strtotime($facture['date_facture']);
$date_facture_aff = date('j', $d_facture) . ' ' . $mois[(int)date('n', $d_facture) - 1] . ' ' . date('Y', $d_facture);

$entreprise_nom = 'FOUTA POIDS LOURDS';
$entreprise_rc = 'SN.DKR.2022.A.702';
$entreprise_ninea = '009116684';
$entreprise_adresse = 'Rond point ZAC MBAO, Dakar';
$entreprise_tel1 = '338700070';
$entreprise_tel2 = '';
$entreprise_site = 'https://www.foutapoidslourds.com';
$entreprise_email = 'info@foutapoidslourds.com';

$whatsapp_url = '';
$is_public = true;
require __DIR__ . '/includes/facture_cp_content.php';
