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

/* UNE ÉCRITURE NE PART PLUS D'UN SIMPLE LIEN (10/09/2026) : un lien piégé ouvert
   par un utilisateur connecté suffisait. Formulaire POST avec jeton de sécurité. */
$jeton_recu = (string) ($_POST['csrf_token'] ?? '');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $jeton_recu === ''
    || !hash_equals((string) ($_SESSION['admin_csrf'] ?? ''), $jeton_recu)) {
    $_SESSION['fm_erreur'] = 'Demande refusée : générez la facture mensuelle depuis la fiche du client.';
    header('Location: ../comptabilite/index.php?tab=bl');
    exit;
}
require_once __DIR__ . '/../../models/model_factures_mensuelles.php';

$client_b2b_id = isset($_POST['client_b2b_id']) ? (int) $_POST['client_b2b_id'] : 0;
if ($client_b2b_id <= 0) {
    header('Location: ../comptabilite/index.php?tab=bl');
    exit;
}

$annee_opt = isset($_POST['annee']) ? (int) $_POST['annee'] : null;
$mois_opt = isset($_POST['mois']) ? (int) $_POST['mois'] : null;
if ($annee_opt === null || $mois_opt === null || $annee_opt < 2000 || $annee_opt > 2100 || $mois_opt < 1 || $mois_opt > 12) {
    $annee_opt = null;
    $mois_opt = null;
}

$tva_incl = isset($_POST['inclure_tva']) && (string) $_POST['inclure_tva'] === '1';
$result = generer_ou_maj_facture_mensuelle($client_b2b_id, (int) ($_SESSION['admin_id'] ?? 0), $annee_opt, $mois_opt, $tva_incl);

if (!empty($result['success']) && !empty($result['facture_mensuelle_id'])) {
    header('Location: facture_mensuelle.php?id=' . (int) $result['facture_mensuelle_id']);
    exit;
}

$_SESSION['fm_erreur'] = $result['message'] ?? 'Génération impossible.';
header('Location: ../comptabilite/bl-fiche-client.php?id=' . $client_b2b_id);
exit;
