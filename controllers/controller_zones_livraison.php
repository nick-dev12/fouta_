<?php
/**
 * Contrôleur pour la gestion des zones de livraison
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../models/model_zones_livraison.php';

/**
 * Traite l'ajout d'une zone de livraison
 * @return array Tableau avec 'success' et 'message'
 */
function process_add_zone_livraison() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['success' => false, 'message' => ''];
    }
    $ville = isset($_POST['ville']) ? trim($_POST['ville']) : '';
    $quartier = isset($_POST['quartier']) ? trim($_POST['quartier']) : '';
    $prix_livraison = isset($_POST['prix_livraison']) ? (float) $_POST['prix_livraison'] : 0;
    $description = isset($_POST['description']) ? trim($_POST['description']) : null;
    return create_zone_livraison($ville, $quartier, $prix_livraison, $description);
}

/**
 * Traite la modification d'une zone de livraison
 * @param int $id L'ID de la zone
 * @return array Tableau avec 'success' et 'message'
 */
function process_update_zone_livraison($id) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['success' => false, 'message' => ''];
    }
    $ville = isset($_POST['ville']) ? trim($_POST['ville']) : '';
    $quartier = isset($_POST['quartier']) ? trim($_POST['quartier']) : '';
    $prix_livraison = isset($_POST['prix_livraison']) ? (float) $_POST['prix_livraison'] : 0;
    $description = isset($_POST['description']) ? trim($_POST['description']) : null;
    $statut = isset($_POST['statut']) ? $_POST['statut'] : 'actif';
    return update_zone_livraison($id, $ville, $quartier, $prix_livraison, $description, $statut);
}
