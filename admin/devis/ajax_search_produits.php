<?php
/**
 * Recherche produits pour devis (réutilise la logique commande manuelle)
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    echo json_encode(['error' => 'Non autorisé', 'items' => []]);
    exit;
}

require_once __DIR__ . '/../../includes/admin_route_access.php';
admin_route_enforce_json_empty();

$recherche = isset($_GET['q']) ? trim($_GET['q']) : '';
$limit = min(50, max(5, (int) ($_GET['limit'] ?? 30)));

require_once __DIR__ . '/../../models/model_produits.php';
$items = search_produits_en_stock_commande_manuelle($recherche, $limit);

echo json_encode(['items' => $items]);
