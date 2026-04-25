<?php
/**
 * Changement de statut BL (POST)
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
$nouveau = $_POST['statut'] ?? '';
if (!in_array($nouveau, ['brouillon', 'valide'], true)) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../../models/model_bl.php';

$bl_cur = $bl_id > 0 ? get_bl_by_id($bl_id) : false;
if ($bl_cur && bl_est_statut_verrouille($bl_cur['statut'] ?? '') && $nouveau === 'brouillon') {
    $_SESSION['bl_erreur'] = 'Un bon validé pour la comptabilité ne peut pas être repassé en brouillon.';
    header('Location: bl_voir.php?id=' . $bl_id);
    exit;
}

if ($bl_id > 0) {
    if (update_bl_statut($bl_id, $nouveau)) {
        if ($nouveau === 'valide') {
            $msg = 'BL validé pour la comptabilité.';
        } else {
            $msg = 'BL repassé en brouillon.';
        }
        $_SESSION['success_message'] = $msg;
    } else {
        $_SESSION['bl_erreur'] = 'Impossible d’enregistrer le statut. Vérifiez la base ou exécutez migrations/bl_statut_unify_valide.sql si nécessaire.';
    }
}

header('Location: bl_voir.php?id=' . $bl_id);
exit;
