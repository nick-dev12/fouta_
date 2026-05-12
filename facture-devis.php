<?php
/**
 * Affichage public d'une facture de devis (accès par token, sans authentification)
 * URL: /facture-devis.php?token=xxx
 */
$token = isset($_GET['token']) ? trim($_GET['token']) : '';
if (empty($token)) {
    header('HTTP/1.0 404 Not Found');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Facture introuvable</title></head><body><h1>Facture introuvable</h1><p>Le lien est invalide ou a expiré.</p></body></html>';
    exit;
}

require_once __DIR__ . '/models/model_factures_devis.php';
require_once __DIR__ . '/models/model_devis.php';

$facture = get_facture_devis_by_token($token);
if (!$facture) {
    header('HTTP/1.0 404 Not Found');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Facture introuvable</title></head><body><h1>Facture introuvable</h1><p>Le lien est invalide ou a expiré.</p></body></html>';
    exit;
}

$devis = get_devis_by_id($facture['devis_id']);
$produits = get_produits_by_devis($facture['devis_id']);
$produits = is_array($produits) ? $produits : [];

$commande = [
    'user_prenom' => $devis['client_prenom'] ?? '',
    'user_nom' => $devis['client_nom'] ?? '',
    'user_telephone' => $devis['client_telephone'] ?? '',
    'telephone_livraison' => $devis['client_telephone'] ?? '',
    'adresse_livraison' => $devis['adresse_livraison'] ?? '',
    'adresse_client' => trim((string) ($devis['adresse_client'] ?? '')),
    'notes' => $devis['notes'] ?? '—',
    'frais_livraison' => $devis['frais_livraison'] ?? 0,
    'numero_commande' => $devis['numero_devis'] ?? ''
];

$client_nom = trim(($devis['client_prenom'] ?? '') . ' ' . ($devis['client_nom'] ?? ''));
$client_telephone = $devis['client_telephone'] ?? '';
$adresse_livraison = $devis['adresse_livraison'] ?? '';

$mois = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
$d_facture = strtotime($facture['date_facture']);
$date_facture_aff = date('j', $d_facture) . ' ' . $mois[(int) date('n', $d_facture) - 1] . ' ' . date('Y', $d_facture);

$entreprise_nom = 'FOUTA POIDS LOURDS';
$entreprise_rc = 'SN.DKR.2022.A.702';
$entreprise_ninea = '009116684';
$entreprise_adresse = 'Rond point Zack Mbao, Dakar';
$entreprise_tel1 = '338700070';
$entreprise_tel2 = '';
$entreprise_site = 'https://www.foutapoidslourds.com';
$entreprise_email = 'info@foutapoidslourds.com';

$is_public = true;
$whatsapp_url = '';

$facture_recap_label_ht_decomp = 'TOTAL DEVIS';

require_once __DIR__ . '/includes/fiscal_tva.php';

$facture_tva_incluse = array_key_exists('tva_incluse', $facture)
    ? !empty($facture['tva_incluse'])
    : (devis_tva_columns_ok() && !empty($devis['tva_incluse']));
$facture_fiscal_ht = null;
$facture_fiscal_tva = null;
$facture_fiscal_taux = null;
if ($facture_tva_incluse) {
    if (function_exists('factures_devis_fiscal_columns_ok') && factures_devis_fiscal_columns_ok()
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

require __DIR__ . '/includes/facture_content.php';
