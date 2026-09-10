<?php
/**
 * Ancienne porte « enregistrer le paiement » de la facture mensuelle (10/09/2026).
 * Elle passait la facture à « payée » d'un clic, sans montant, sans moyen ni
 * auteur. Le paiement s'enregistre désormais dans le bloc « Paiements » de la
 * facture (paiement_enregistrer.php) ; la porte reste pour renvoyer proprement.
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}
require_once __DIR__ . '/../includes/require_access.php';

$facture_mensuelle_id = (int) ($_POST['facture_mensuelle_id'] ?? 0);
$_SESSION['fm_erreur'] = 'Le paiement s’enregistre désormais avec son montant, son moyen et sa date, dans le bloc Paiements.';
header('Location: ' . ($facture_mensuelle_id > 0 ? 'facture_mensuelle.php?id=' . $facture_mensuelle_id : '../comptabilite/index.php?tab=bl'));
exit;
