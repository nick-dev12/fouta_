<?php
/**
 * API pour récupérer les produits avec pagination et filtres
 * Utilisé pour le chargement progressif via JavaScript
 */

header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/../models/model_produits.php';

// Récupérer les paramètres
$offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 20;
$recherche = isset($_GET['recherche']) ? trim($_GET['recherche']) : '';
$prix_min = isset($_GET['prix_min']) && $_GET['prix_min'] !== '' ? (float) $_GET['prix_min'] : null;
$prix_max = isset($_GET['prix_max']) && $_GET['prix_max'] !== '' ? (float) $_GET['prix_max'] : null;
$categorie_id = isset($_GET['categorie']) && $_GET['categorie'] !== '' ? (int) $_GET['categorie'] : null;
$tri = isset($_GET['tri']) && in_array($_GET['tri'], ['date', 'prix_asc', 'prix_desc', 'nom']) ? $_GET['tri'] : 'date';

// Valider les paramètres
if ($offset < 0) $offset = 0;
if ($limit < 1 || $limit > 50) $limit = 20;

$has_filters = !empty($recherche) || $prix_min !== null || $prix_max !== null || $categorie_id !== null || $tri !== 'date';

// Récupérer les produits (avec ou sans filtres)
if ($has_filters) {
    $produits = search_produits_with_filters($recherche, $prix_min, $prix_max, $categorie_id, $tri, $offset, $limit);
} else {
    $produits = get_all_produits_paginated($offset, $limit);
}

// Formater les produits pour le JSON
$produits_formatted = [];
foreach ($produits as $produit) {
    $prix_affichage = !empty($produit['prix_promotion']) && $produit['prix_promotion'] < $produit['prix'] 
        ? $produit['prix_promotion'] 
        : $produit['prix'];
    $has_promotion = !empty($produit['prix_promotion']) && $produit['prix_promotion'] < $produit['prix'];
    $pourcentage_promo = $has_promotion ? round((($produit['prix'] - $produit['prix_promotion']) / $produit['prix']) * 100) : 0;
    
    $produits_formatted[] = [
        'id' => $produit['id'],
        'nom' => $produit['nom'],
        'prix' => $produit['prix'],
        'prix_promotion' => $produit['prix_promotion'],
        'prix_affichage' => $prix_affichage,
        'has_promotion' => $has_promotion,
        'pourcentage_promo' => $pourcentage_promo,
        'stock' => $produit['stock'],
        'poids' => $produit['poids'] ?? '',
        'categorie_nom' => $produit['categorie_nom'] ?? '',
        'image_principale' => $produit['image_principale'] ?? 'produit1.jpg'
    ];
}

// Retourner la réponse JSON
echo json_encode([
    'success' => true,
    'produits' => $produits_formatted,
    'count' => count($produits_formatted),
    'offset' => $offset,
    'limit' => $limit
]);

?>

