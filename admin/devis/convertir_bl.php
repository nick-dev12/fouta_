<?php
/**
 * Conversion devis → BL (GET avec confirmation)
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

$devis_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
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
