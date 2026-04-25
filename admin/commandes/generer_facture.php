<?php
/**
 * Génère une facture pour une commande
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}
require_once __DIR__ . '/../includes/require_access.php';


$commande_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($commande_id <= 0) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../../models/model_factures.php';
require_once __DIR__ . '/../../models/model_commandes_admin.php';

$commande = get_commande_by_id($commande_id);
if (!$commande) {
    header('Location: index.php');
    exit;
}

$existant = get_facture_by_commande($commande_id);
if ($existant) {
    header('Location: facture.php?id=' . $existant['id']);
    exit;
}

$result = create_facture($commande_id);
if ($result && $result['success']) {
    $_SESSION['success_message'] = 'Facture #' . $result['numero_facture'] . ' générée avec succès.';
    header('Location: facture.php?id=' . $result['facture_id']);
    exit;
}

$_SESSION['success_message'] = 'Erreur lors de la génération de la facture.';
header('Location: details.php?id=' . $commande_id);
