<?php
/**
 * Génère une facture pour une commande personnalisée
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}
require_once __DIR__ . '/../includes/require_access.php';


$cp_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($cp_id <= 0) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../../models/model_commandes_personnalisees.php';
require_once __DIR__ . '/../../models/model_factures_personnalisees.php';

$cp = get_commande_personnalisee_by_id($cp_id);
if (!$cp) {
    header('Location: index.php');
    exit;
}

$montant = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['montant'])) {
    $montant = (float) str_replace([' ', ','], ['', '.'], $_POST['montant']);
} elseif (isset($_GET['montant'])) {
    $montant = (float) str_replace([' ', ','], ['', '.'], $_GET['montant']);
} else {
    $prix_cp = isset($cp['prix']) && $cp['prix'] !== null && (float) $cp['prix'] > 0 ? (float) $cp['prix'] : 0;
    $frais_liv = isset($cp['zone_prix_livraison']) && (float) $cp['zone_prix_livraison'] > 0 ? (float) $cp['zone_prix_livraison'] : 0;
    $montant = $prix_cp + $frais_liv;
}

$existant = get_facture_personnalisee_by_cp($cp_id);
if ($existant) {
    header('Location: facture.php?id=' . $existant['id']);
    exit;
}

$result = create_facture_personnalisee($cp_id, $montant);
if ($result && $result['success']) {
    $_SESSION['success_message'] = 'Facture #' . $result['numero_facture'] . ' générée avec succès.';
    header('Location: facture.php?id=' . $result['facture_id']);
    exit;
}

$_SESSION['success_message'] = 'Erreur lors de la génération de la facture.';
header('Location: details.php?id=' . $cp_id);
