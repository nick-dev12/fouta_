<?php
/**
 * Suppression de devis (POST, brouillon uniquement — logique dans delete_devis)
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
    $_SESSION['error_devis'] = 'Session expirée. Réessayez.';
    header('Location: index.php');
    exit;
}

$devis_id = isset($_POST['devis_id']) ? (int) $_POST['devis_id'] : 0;
require_once __DIR__ . '/../../models/model_devis.php';

if ($devis_id > 0 && delete_devis($devis_id)) {
    $_SESSION['success_message'] = 'Le devis a été supprimé.';
} else {
    $_SESSION['error_devis'] = 'Suppression impossible (devis introuvable, déjà facturé ou statut différent de brouillon).';
}

header('Location: index.php');
exit;
