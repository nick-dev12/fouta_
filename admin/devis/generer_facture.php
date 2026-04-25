<?php
/**
 * Génère une facture pour un devis
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}
require_once __DIR__ . '/../includes/require_access.php';


$devis_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($devis_id <= 0) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../../models/model_devis.php';
require_once __DIR__ . '/../../models/model_factures_devis.php';

$devis = get_devis_by_id($devis_id);
if (!$devis) {
    header('Location: index.php');
    exit;
}

$existant = get_facture_devis_by_devis($devis_id);
if ($existant) {
    header('Location: facture.php?id=' . $existant['id']);
    exit;
}

$result = create_facture_devis(
    $devis_id,
    (int) ($_SESSION['admin_id'] ?? 0) > 0 ? (int) $_SESSION['admin_id'] : null
);
if ($result && $result['success']) {
    $_SESSION['success_message'] = 'Facture #' . $result['numero_facture'] . ' générée avec succès.';
    header('Location: facture.php?id=' . $result['facture_id']);
    exit;
}

$_SESSION['success_message'] = 'Erreur lors de la génération de la facture.';
header('Location: details.php?id=' . $devis_id);
