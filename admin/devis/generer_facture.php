<?php
/**
 * Génère une facture pour un devis
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}
require_once __DIR__ . '/../includes/require_access.php';

require_once __DIR__ . '/../../includes/admin_permissions.php';
if (!admin_can_devis()) {
    header('Location: ../dashboard.php');
    exit;
}

/* UNE ÉCRITURE NE PART PLUS D'UN SIMPLE LIEN (10/09/2026) : un lien piégé ouvert
   par un utilisateur connecté suffisait. Formulaire POST avec jeton de sécurité. */
$jeton_recu = (string) ($_POST['csrf_token'] ?? '');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $jeton_recu === ''
    || !hash_equals((string) ($_SESSION['admin_csrf'] ?? ''), $jeton_recu)) {
    $_SESSION['error_devis'] = 'Demande refusée : générez la facture depuis la fiche du devis.';
    header('Location: devis.php');
    exit;
}
$devis_id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
if ($devis_id <= 0) {
    header('Location: devis.php');
    exit;
}

require_once __DIR__ . '/../../models/model_devis.php';
require_once __DIR__ . '/../../models/model_factures_devis.php';

$devis = get_devis_by_id($devis_id);
if (!$devis) {
    header('Location: devis.php');
    exit;
}

$existant = get_facture_devis_by_devis($devis_id);
if ($existant) {
    header('Location: facture.php?id=' . $existant['id']);
    exit;
}

require_once __DIR__ . '/../../models/model_bl.php';
if (bl_exists_for_devis($devis_id)) {
    $_SESSION['error_devis'] = 'Ce devis est parti en bon de livraison : il se facture avec la facture du mois du client, pas seul.';
    header('Location: devis.php');
    exit;
}

$result = create_facture_devis(
    $devis_id,
    (int) ($_SESSION['admin_id'] ?? 0) > 0 ? (int) $_SESSION['admin_id'] : null
);
if ($result && $result['success']) {
    $_SESSION['success_message'] = 'Facture #' . $result['numero_facture'] . ' générée avec succès.';
    header('Location: facture.php?id=' . $result['facture_id']);
    exit;
}

$_SESSION['error_devis'] = 'La facture n’a pas pu être générée.';
header('Location: devis.php');
