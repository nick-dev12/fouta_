<?php
/**
 * Traitement du formulaire de création de devis
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}
require_once __DIR__ . '/../includes/require_access.php';


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../../includes/admin_permissions.php';
if (!admin_can_devis_bl()) {
    header('Location: ../dashboard.php');
    exit;
}

require_once __DIR__ . '/../../models/model_devis.php';

$client_nom = trim($_POST['client_nom'] ?? '');
$client_prenom = trim($_POST['client_prenom'] ?? '');
$client_telephone = trim($_POST['client_telephone'] ?? '');
$client_email = trim($_POST['client_email'] ?? '');
$adresse_livraison = trim($_POST['adresse_livraison'] ?? '');
$notes = trim($_POST['notes'] ?? '');
$zone_livraison_id = isset($_POST['zone_livraison_id']) && $_POST['zone_livraison_id'] !== '' && $_POST['zone_livraison_id'] !== 'custom' ? (int) $_POST['zone_livraison_id'] : null;
$frais_livraison = (float) ($_POST['frais_livraison'] ?? 0);
$user_id = isset($_POST['user_id']) && $_POST['user_id'] !== '' ? (int) $_POST['user_id'] : null;

$items = [];
if (!empty($_POST['lignes']) && is_array($_POST['lignes'])) {
    foreach (array_values($_POST['lignes']) as $l) {
        $produit_id = (int) ($l['produit_id'] ?? 0);
        $quantite = (int) ($l['quantite'] ?? 1);
        $prix_unitaire = (float) str_replace(',', '.', $l['prix_unitaire'] ?? '0');
        $prix_promotion = isset($l['prix_promotion']) && $l['prix_promotion'] !== '' ? (float) str_replace(',', '.', $l['prix_promotion']) : null;
        if ($produit_id > 0 && $quantite > 0 && $prix_unitaire > 0) {
            $items[] = [
                'produit_id' => $produit_id,
                'quantite' => $quantite,
                'prix_unitaire' => $prix_promotion ?? $prix_unitaire,
                'nom_produit' => isset($l['nom_produit']) ? trim($l['nom_produit']) : null
            ];
        }
    }
}

$erreur = null;
if (empty($client_nom)) $erreur = 'Le nom du client est requis.';
elseif (empty($client_prenom)) $erreur = 'Le prénom du client est requis.';
elseif (empty($client_telephone)) $erreur = 'Le téléphone du client est requis.';
elseif (empty($adresse_livraison)) $erreur = "L'adresse de livraison est requise.";
elseif (empty($items)) $erreur = 'Ajoutez au moins un produit au devis.';

if ($erreur) {
    $_SESSION['devis_erreur'] = $erreur;
    $_SESSION['devis_post'] = $_POST;
    header('Location: index.php?modal=devis');
    exit;
}

$result = create_devis(
    $items,
    $client_nom,
    $client_prenom,
    $client_telephone,
    $adresse_livraison,
    $client_email ?: null,
    $notes ?: null,
    $zone_livraison_id,
    $frais_livraison,
    $user_id,
    (int) ($_SESSION['admin_id'] ?? 0) > 0 ? (int) $_SESSION['admin_id'] : null
);

if ($result && $result['success']) {
    $_SESSION['success_message'] = 'Devis #' . $result['numero_devis'] . ' créé avec succès.';
    header('Location: details.php?id=' . $result['devis_id']);
    exit;
}

$_SESSION['devis_erreur'] = 'Erreur lors de l\'enregistrement du devis.';
$_SESSION['devis_post'] = $_POST;
header('Location: index.php?modal=devis');
