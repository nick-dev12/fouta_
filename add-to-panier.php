<?php
/**
 * Traitement de l'ajout direct au panier depuis les cartes produits
 * Redirige vers la page d'origine ou le panier avec un message
 */
session_start();

require_once __DIR__ . '/controllers/controller_panier.php';

// Utilisateur non connecté : redirection vers la connexion
if (!isset($_SESSION['user_id'])) {
    $return = isset($_POST['return_url']) ? '&return=' . urlencode($_POST['return_url']) : '';
    header('Location: /user/connexion.php?redirect=panier' . $return);
    exit;
}

// Méthode POST uniquement
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['produit_id'])) {
    header('Location: /index.php');
    exit;
}

$result = process_add_to_panier();

if ($result['success']) {
    header('Location: /panier.php?added=1');
} else {
    $return_url = isset($_POST['return_url']) && $_POST['return_url'] !== '' ? $_POST['return_url'] : '/panier.php';
    $separator = (strpos($return_url, '?') !== false) ? '&' : '?';
    header('Location: ' . $return_url . $separator . 'error=' . urlencode($result['message']));
}
exit;
