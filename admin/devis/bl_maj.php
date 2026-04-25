<?php
/**
 * Mise à jour lignes + en-tête BL (POST)
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}
require_once __DIR__ . '/../includes/require_access.php';


require_once __DIR__ . '/../../includes/admin_permissions.php';
if (!admin_can_devis_bl()) {
    header('Location: ../dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$token = $_POST['csrf_token'] ?? '';
$expected = $_SESSION['admin_csrf'] ?? '';
if ($token === '' || !hash_equals((string) $expected, (string) $token)) {
    $_SESSION['bl_erreur'] = 'Session expirée.';
    header('Location: index.php');
    exit;
}

$bl_id = (int) ($_POST['bl_id'] ?? 0);
$date_bl = trim($_POST['date_bl'] ?? '');
$notes = trim($_POST['notes'] ?? '');

$lignes = [];
if (!empty($_POST['lignes']) && is_array($_POST['lignes'])) {
    foreach ($_POST['lignes'] as $l) {
        if (!is_array($l)) {
            continue;
        }
        $lignes[] = [
            'produit_id' => !empty($l['produit_id']) ? (int) $l['produit_id'] : null,
            'designation' => trim($l['designation'] ?? ''),
            'quantite' => $l['quantite'] ?? 0,
            'prix_unitaire_ht' => $l['prix_unitaire_ht'] ?? 0,
        ];
    }
}

require_once __DIR__ . '/../../models/model_bl.php';

if ($bl_id <= 0) {
    header('Location: index.php');
    exit;
}

$bl = get_bl_by_id($bl_id);
if (!$bl) {
    $_SESSION['bl_erreur'] = 'BL introuvable.';
    header('Location: index.php');
    exit;
}

if (bl_est_statut_verrouille($bl['statut'] ?? '')) {
    $_SESSION['bl_erreur'] = 'Ce bon est validé pour la comptabilité : modification des lignes et de l’en-tête impossible.';
    header('Location: bl_voir.php?id=' . $bl_id);
    exit;
}

update_bl_entete($bl_id, $date_bl, $notes);
$res = replace_bl_lignes($bl_id, $lignes);

if (!empty($res['success'])) {
    $_SESSION['success_message'] = 'Bon de livraison mis à jour.';
    header('Location: bl_voir.php?id=' . $bl_id);
    exit;
}

$_SESSION['bl_erreur'] = $res['message'] ?? 'Erreur de mise à jour.';
header('Location: bl_modifier.php?id=' . $bl_id);
exit;
