<?php
/**
 * Affichage public d'une facture (accès par token, sans authentification)
 * URL: /facture.php?token=xxx
 */
$token = isset($_GET['token']) ? trim($_GET['token']) : '';
if (empty($token)) {
    header('HTTP/1.0 404 Not Found');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Facture introuvable</title></head><body><h1>Facture introuvable</h1><p>Le lien est invalide ou a expiré.</p></body></html>';
    exit;
}

require_once __DIR__ . '/models/model_factures.php';
require_once __DIR__ . '/models/model_commandes_admin.php';

$facture = get_facture_by_token($token);
if (!$facture) {
    header('HTTP/1.0 404 Not Found');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Facture introuvable</title></head><body><h1>Facture introuvable</h1><p>Le lien est invalide ou a expiré.</p></body></html>';
    exit;
}

$commande = get_commande_by_id($facture['commande_id']);
$produits = get_produits_by_commande($facture['commande_id']);
$produits = is_array($produits) ? $produits : [];

$client_nom = trim(($commande['user_prenom'] ?? '') . ' ' . ($commande['user_nom'] ?? ''));
$client_telephone = $commande['user_telephone'] ?? $commande['telephone_livraison'] ?? '';
$adresse_livraison = $commande['adresse_livraison'] ?? '';

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

$is_public = true;
require __DIR__ . '/includes/facture_content.php';
