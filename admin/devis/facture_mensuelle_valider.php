<?php
/**
 * Valide une facture mensuelle (transmission comptabilité)
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}
require_once __DIR__ . '/../includes/require_access.php';


require_once __DIR__ . '/../../includes/admin_permissions.php';
if (!admin_can_comptabilite()) {
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
    $_SESSION['fm_erreur'] = 'Session expirée. Réessayez.';
    header('Location: index.php?tab=bl');
    exit;
}

$facture_mensuelle_id = (int) ($_POST['facture_mensuelle_id'] ?? 0);

require_once __DIR__ . '/../../models/model_factures_mensuelles.php';

if ($facture_mensuelle_id <= 0 || !valider_facture_mensuelle($facture_mensuelle_id)) {
    $_SESSION['fm_erreur'] = 'Validation impossible (facture introuvable ou déjà validée).';
    $redir_id = $facture_mensuelle_id > 0 ? $facture_mensuelle_id : 0;
    header('Location: ' . ($redir_id > 0 ? 'facture_mensuelle.php?id=' . $redir_id : 'index.php?tab=bl'));
    exit;
}

$_SESSION['success_message'] = 'Facture HT validée.';
header('Location: facture_mensuelle.php?id=' . $facture_mensuelle_id);
exit;
