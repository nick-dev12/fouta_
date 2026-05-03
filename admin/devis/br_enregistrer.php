<?php
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../../includes/admin_permissions.php';
if (!admin_can_devis_bl()) {
    header('Location: ../dashboard.php');
    exit;
}

require_once __DIR__ . '/../../models/model_bons_retour.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?tab=br');
    exit;
}

$token = $_POST['csrf_token'] ?? '';
if (!hash_equals($_SESSION['admin_csrf'] ?? '', $token)) {
    $_SESSION['bl_erreur'] = 'Session expirée (CSRF). Réessayez.';
    header('Location: index.php?tab=br');
    exit;
}

$bl_id = isset($_POST['bl_id']) ? (int) $_POST['bl_id'] : 0;
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

$result = br_create_bon_retour($bl_id, $admin_id, $notes, $quantites);

if (!empty($result['success'])) {
    $_SESSION['success_message'] = 'Bon de retour ' . ($result['numero_br'] ?? '') . ' enregistré.';
    header('Location: br_voir.php?id=' . (int) ($result['br_id'] ?? 0));
    exit;
}

$_SESSION['bl_erreur'] = $result['message'] ?? 'Enregistrement impossible.';
header('Location: br_creation.php?bl_id=' . $bl_id);
exit;
