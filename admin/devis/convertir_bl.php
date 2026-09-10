<?php
/**
 * Conversion devis → BL (POST avec jeton de sécurité, 10/09/2026)
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}
require_once __DIR__ . '/../includes/require_access.php';


require_once __DIR__ . '/../../includes/admin_permissions.php';
if (!admin_can_bl_retours_b2b()) {
    header('Location: ../dashboard.php');
    exit;
}

/* UNE ÉCRITURE NE PART PLUS D'UN SIMPLE LIEN (10/09/2026) : un lien piégé ouvert
   par un utilisateur connecté suffisait. Formulaire POST avec jeton de sécurité. */
$jeton_recu = (string) ($_POST['csrf_token'] ?? '');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $jeton_recu === ''
    || !hash_equals((string) ($_SESSION['admin_csrf'] ?? ''), $jeton_recu)) {
    $_SESSION['error_devis'] = 'Demande refusée : la conversion d’un devis en bon de livraison se fait par un formulaire.';
    header('Location: index.php');
    exit;
}
$devis_id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
if ($devis_id <= 0) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../../models/model_bl.php';

$res = create_bl_from_devis($devis_id, (int) $_SESSION['admin_id']);
if (!empty($res['success'])) {
    $_SESSION['success_message'] = 'Bon de livraison ' . ($res['numero_bl'] ?? '') . ' créé à partir du devis. Vous pouvez le compléter ou le valider.';
    header('Location: bl_voir.php?id=' . (int) $res['bl_id']);
    exit;
}

$_SESSION['error_devis'] = $res['message'] ?? 'Conversion impossible.';
header('Location: index.php');
exit;
