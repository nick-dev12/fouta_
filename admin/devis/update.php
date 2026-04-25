<?php
/**
 * Mise à jour d'un devis (POST)
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
    $_SESSION['devis_erreur'] = 'Session expirée. Réessayez.';
    $rid = isset($_POST['devis_id']) ? (int) $_POST['devis_id'] : 0;
    header('Location: ' . ($rid > 0 ? 'modifier.php?id=' . $rid : 'index.php'));
    exit;
}

$devis_id = isset($_POST['devis_id']) ? (int) $_POST['devis_id'] : 0;
if ($devis_id <= 0) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../../models/model_devis.php';

$d = get_devis_by_id($devis_id);
if (!$d || ($d['statut'] ?? '') !== 'brouillon') {
    $_SESSION['devis_erreur'] = 'Ce devis ne peut pas être modifié.';
    header('Location: modifier.php?id=' . $devis_id);
    exit;
}

$client_nom = trim($_POST['client_nom'] ?? '');
$client_prenom = trim($_POST['client_prenom'] ?? '');
$client_telephone = trim($_POST['client_telephone'] ?? '');
$client_email = trim($_POST['client_email'] ?? '');
$adresse_livraison = trim($_POST['adresse_livraison'] ?? '');
$notes = trim($_POST['notes'] ?? '');
$zone_livraison_id = isset($_POST['zone_livraison_id']) && $_POST['zone_livraison_id'] !== '' && $_POST['zone_livraison_id'] !== 'custom'
    ? (int) $_POST['zone_livraison_id'] : null;
$frais_livraison = (float) ($_POST['frais_livraison'] ?? 0);

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
                'nom_produit' => isset($l['nom_produit']) ? trim($l['nom_produit']) : null,
            ];
        }
    }
}

$erreur = null;
if ($client_nom === '') {
    $erreur = 'Le nom du client est requis.';
} elseif ($client_prenom === '') {
    $erreur = 'Le prénom du client est requis.';
} elseif ($client_telephone === '') {
    $erreur = 'Le téléphone du client est requis.';
} elseif ($adresse_livraison === '') {
    $erreur = "L'adresse de livraison est requise.";
} elseif (empty($items)) {
    $erreur = 'Ajoutez au moins un produit au devis.';
}

if ($erreur) {
    $_SESSION['devis_erreur'] = $erreur;
    $_SESSION['devis_post'] = $_POST;
    header('Location: modifier.php?id=' . $devis_id);
    exit;
}

$infos = [
    'client_nom' => $client_nom,
    'client_prenom' => $client_prenom,
    'client_telephone' => $client_telephone,
    'client_email' => $client_email,
    'adresse_livraison' => $adresse_livraison,
    'zone_livraison_id' => $zone_livraison_id,
    'frais_livraison' => $frais_livraison,
    'notes' => $notes,
];

if (update_devis($devis_id, $items, $infos)) {
    $_SESSION['success_message'] = 'Devis mis à jour.';
    header('Location: details.php?id=' . $devis_id);
    exit;
}

$_SESSION['devis_erreur'] = 'Erreur lors de la mise à jour.';
$_SESSION['devis_post'] = $_POST;
header('Location: modifier.php?id=' . $devis_id);
exit;
