<?php
/**
 * Suppression d'une ligne de BL (POST, CSRF)
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
    header('Location: index.php?tab=bl');
    exit;
}

$token = $_POST['csrf_token'] ?? '';
$expected = $_SESSION['admin_csrf'] ?? '';
if ($token === '' || !hash_equals((string) $expected, (string) $token)) {
    $_SESSION['bl_erreur'] = 'Session expirée.';
    header('Location: index.php?tab=bl');
    exit;
}

$bl_id = (int) ($_POST['bl_id'] ?? 0);
$ligne_id = (int) ($_POST['ligne_id'] ?? 0);

require_once __DIR__ . '/../../models/model_bl.php';

if ($bl_id <= 0 || $ligne_id <= 0) {
    header('Location: index.php?tab=bl');
    exit;
}

$res = delete_bl_ligne($ligne_id, $bl_id);
if (!empty($res['success'])) {
    $_SESSION['success_message'] = 'Ligne supprimée.';
} else {
    $_SESSION['bl_erreur'] = $res['message'] ?? 'Suppression impossible.';
}

header('Location: bl_modifier.php?id=' . $bl_id);
exit;
