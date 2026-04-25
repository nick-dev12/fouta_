<?php
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
require_once __DIR__ . '/../../models/model_bl.php';

if ($bl_id > 0 && delete_bl($bl_id)) {
    $_SESSION['success_message'] = 'Bon de livraison supprimé.';
} else {
    $_SESSION['bl_erreur'] = 'Suppression impossible (BL introuvable, lié à une facture, ou déjà validé).';
}

header('Location: index.php');
exit;
