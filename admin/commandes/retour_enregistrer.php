<?php
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

/* LE GARDE-BARRIÈRE (31/08/2026) : cette page ne demandait jamais si le
 * compte connecté avait le droit d'être là. La règle existe depuis
 * toujours dans includes/admin_route_access.php ; il manquait l'appel. */
require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../models/model_commandes_retours.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$token = $_POST['csrf_token'] ?? '';
if (!hash_equals($_SESSION['admin_csrf'] ?? '', $token)) {
    $_SESSION['error_message'] = 'Session expirée (CSRF). Réessayez.';
    header('Location: index.php');
    exit;
}

$commande_id = isset($_POST['commande_id']) ? (int) $_POST['commande_id'] : 0;
$notes = trim($_POST['notes'] ?? '');
$qty_post = $_POST['qty'] ?? [];

if (!is_array($qty_post)) {
    $qty_post = [];
}

$quantites = [];
foreach ($qty_post as $ligne_id => $val) {
    $quantites[(int) $ligne_id] = (float) str_replace(',', '.', (string) $val);
}

$admin_id = (int) ($_SESSION['admin_id'] ?? 0);

$result = crc_create_retour_commande($commande_id, $admin_id, $notes, $quantites);

if (!empty($result['success'])) {
    $_SESSION['success_message'] = 'Retour « ' . ($result['numero_retour'] ?? '') . ' » enregistré.';
    header('Location: retour_voir.php?id=' . (int) ($result['retour_id'] ?? 0));
    exit;
}

$_SESSION['error_message'] = $result['message'] ?? 'Enregistrement impossible.';
header('Location: retour_creation.php?id=' . $commande_id);
exit;
