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
if (!admin_can_bl_retours_b2b()) {
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
$adresse_client = trim($_POST['adresse_client'] ?? '');

require_once __DIR__ . '/../../models/model_bl.php';
require_once __DIR__ . '/../../models/model_demandes_prix.php';

/* LE PRIX VIENT DU CATALOGUE (11/09/2026), décision de la direction : le vendeur
 * ne tape plus aucun prix. Une pièce déjà sur le bon garde son prix enregistré,
 * une pièce ajoutée prend son prix au catalogue, et le prix envoyé par l'écran
 * est ignoré. Règles : models/model_demandes_prix.php. */
$lignes_catalogue = demandes_prix_lignes_bl(
    !empty($_POST['lignes']) && is_array($_POST['lignes']) ? $_POST['lignes'] : [],
    $bl_id > 0 ? get_lignes_bl($bl_id) : []
);
$lignes = $lignes_catalogue['lignes'];

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

if ($lignes_catalogue['sans_prix'] !== [] || $lignes_catalogue['libres'] !== []) {
    $motifs = [];
    if ($lignes_catalogue['sans_prix'] !== []) {
        $motifs[] = demandes_prix_refus_document($lignes_catalogue['sans_prix'], (int) $_SESSION['admin_id']);
    }
    if ($lignes_catalogue['libres'] !== []) {
        $motifs[] = demandes_prix_message_lignes_libres($lignes_catalogue['libres']);
    }
    $_SESSION['bl_erreur'] = implode(' ', $motifs) . ' Le bon n’a pas été modifié.';
    header('Location: bl_modifier.php?id=' . $bl_id);
    exit;
}

update_bl_entete($bl_id, $date_bl, $notes, $adresse_client);
$res = replace_bl_lignes($bl_id, $lignes);

if (!empty($res['success'])) {
    $_SESSION['success_message'] = 'Bon de livraison mis à jour.';
    header('Location: bl_voir.php?id=' . $bl_id);
    exit;
}

$_SESSION['bl_erreur'] = $res['message'] ?? 'Erreur de mise à jour.';
header('Location: bl_modifier.php?id=' . $bl_id);
exit;
