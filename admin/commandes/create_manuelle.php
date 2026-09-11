<?php
/**
 * Traitement du formulaire de commande manuelle
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
$jeton_recu = (string) ($_POST['csrf_token'] ?? '');
if ($jeton_recu === '' || !hash_equals((string) ($_SESSION['admin_csrf'] ?? ''), $jeton_recu)) {
    $_SESSION['commande_manuelle_erreur'] = 'Session expirée : rechargez la page puis recommencez.';
    header('Location: index.php?modal=commande_manuelle');
    exit;
}

require_once __DIR__ . '/../../models/model_commandes.php';

$client_nom = trim($_POST['client_nom'] ?? '');
$client_prenom = trim($_POST['client_prenom'] ?? '');
$client_telephone = trim($_POST['client_telephone'] ?? '');
$client_email = trim($_POST['client_email'] ?? '');
$adresse_livraison = trim($_POST['adresse_livraison'] ?? '');
$notes = trim($_POST['notes'] ?? '');
$zone_livraison_id = isset($_POST['zone_livraison_id']) && $_POST['zone_livraison_id'] !== '' && $_POST['zone_livraison_id'] !== 'custom' ? (int) $_POST['zone_livraison_id'] : null;
$frais_livraison = (float) ($_POST['frais_livraison'] ?? 0);

/* LE PRIX VIENT DU CATALOGUE (11/09/2026), décision de la direction : le vendeur
 * ne tape plus aucun prix. Prix et promotion de chaque ligne sont repris au
 * catalogue ; le prix envoyé par l'écran est ignoré. Règles : models/model_demandes_prix.php. */
require_once __DIR__ . '/../../models/model_demandes_prix.php';
$lignes_catalogue = demandes_prix_lignes_commande(!empty($_POST['lignes']) && is_array($_POST['lignes']) ? $_POST['lignes'] : []);
$items = $lignes_catalogue['items'];

$erreur = null;
if (empty($client_nom)) $erreur = 'Le nom du client est requis.';
elseif (empty($client_prenom)) $erreur = 'Le prénom du client est requis.';
elseif (empty($client_telephone)) $erreur = 'Le téléphone du client est requis.';
elseif (empty($adresse_livraison)) $erreur = "L'adresse de livraison est requise.";
elseif ($lignes_catalogue['sans_prix'] !== []) $erreur = demandes_prix_refus_document($lignes_catalogue['sans_prix'], (int) $_SESSION['admin_id']);
elseif (empty($items)) $erreur = 'Ajoutez au moins un produit à la commande.';

if ($erreur) {
    $_SESSION['commande_manuelle_erreur'] = $erreur;
    $_SESSION['commande_manuelle_post'] = $_POST;
    header('Location: index.php?modal=commande_manuelle');
    exit;
}

require_once __DIR__ . '/../../models/model_contacts.php';

$result = create_commande_manuelle(
    $items,
    $client_nom,
    $client_prenom,
    $client_telephone,
    $adresse_livraison,
    $client_email ?: null,
    $notes ?: null,
    $zone_livraison_id,
    $frais_livraison,
    (int) ($_SESSION['admin_id'] ?? 0) > 0 ? (int) $_SESSION['admin_id'] : null
);

if ($result && isset($result['success']) && $result['success']) {
    // Si le téléphone n'existe pas en base (users ou contacts), enregistrer le contact
    if (!telephone_exists_in_users_or_contacts($client_telephone)) {
        create_contact($client_nom, $client_prenom, $client_telephone, $client_email ?: null);
    }
    $_SESSION['success_message'] = 'Commande manuelle #' . $result['numero_commande'] . ' enregistrée avec succès. Elle apparaît dans les commandes à traiter.';
    header('Location: index.php');
    exit;
}

$msg_erreur = (is_array($result) && !empty($result['error'])) ? $result['error'] : 'Erreur lors de l\'enregistrement. Vérifiez les quantités et le stock disponible.';
$_SESSION['commande_manuelle_erreur'] = $msg_erreur;
$_SESSION['commande_manuelle_post'] = $_POST;
header('Location: index.php?modal=commande_manuelle');
