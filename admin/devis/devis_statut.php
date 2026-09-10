<?php
/**
 * CHANGER LE STATUT D'UN DEVIS (10/09/2026) : envoyé, accepté, refusé.
 *
 * Les statuts existaient en base mais aucun écran ne les posait : les 15 devis
 * sont restés « brouillon », impossible de savoir lequel relancer. Les règles
 * vivent dans devis_changer_statut() ; cette page ne fait que les appeler, par
 * un formulaire POST muni d'un jeton.
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}
require_once __DIR__ . '/../includes/require_access.php';

require_once __DIR__ . '/../../includes/admin_permissions.php';
if (!admin_can_devis()) {
    admin_redirect_role_home();
}

$jeton_recu = (string) ($_POST['csrf_token'] ?? '');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $jeton_recu === ''
    || !hash_equals((string) ($_SESSION['admin_csrf'] ?? ''), $jeton_recu)) {
    $_SESSION['error_devis'] = 'Demande refusée : changez le statut depuis la fiche du devis.';
    header('Location: devis.php');
    exit;
}

$devis_id = (int) ($_POST['id'] ?? 0);
$statut = (string) ($_POST['statut'] ?? '');

require_once __DIR__ . '/../../models/model_devis.php';
$resultat = devis_changer_statut($devis_id, $statut);

if (!empty($resultat['ok'])) {
    $_SESSION['success_message'] = $resultat['message'];
    header('Location: details.php?id=' . $devis_id);
    exit;
}

$_SESSION['error_devis'] = $resultat['message'];
header('Location: devis.php');
exit;
