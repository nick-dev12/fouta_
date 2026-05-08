<?php
/**
 * Génère ou met à jour la facture mensuelle (brouillon) avec les BL validés non facturés
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}
require_once __DIR__ . '/../includes/require_access.php';


require_once __DIR__ . '/../../includes/admin_permissions.php';
if (!admin_can_comptabilite()) {
    header('Location: ../dashboard.php');
    exit;
}

require_once __DIR__ . '/../../models/model_factures_mensuelles.php';

$client_b2b_id = isset($_GET['client_b2b_id']) ? (int) $_GET['client_b2b_id'] : 0;
if ($client_b2b_id <= 0) {
    header('Location: ../comptabilite/index.php?tab=bl');
    exit;
}

$annee_opt = isset($_GET['annee']) ? (int) $_GET['annee'] : null;
$mois_opt = isset($_GET['mois']) ? (int) $_GET['mois'] : null;
if ($annee_opt === null || $mois_opt === null || $annee_opt < 2000 || $annee_opt > 2100 || $mois_opt < 1 || $mois_opt > 12) {
    $annee_opt = null;
    $mois_opt = null;
}

$result = generer_ou_maj_facture_mensuelle($client_b2b_id, (int) ($_SESSION['admin_id'] ?? 0), $annee_opt, $mois_opt);

if (!empty($result['success']) && !empty($result['facture_mensuelle_id'])) {
    header('Location: facture_mensuelle.php?id=' . (int) $result['facture_mensuelle_id']);
    exit;
}

$_SESSION['fm_erreur'] = $result['message'] ?? 'Génération impossible.';
header('Location: ../comptabilite/bl-fiche-client.php?id=' . $client_b2b_id);
exit;
