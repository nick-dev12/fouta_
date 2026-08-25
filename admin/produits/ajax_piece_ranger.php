<?php
/**
 * « OÙ RANGER CETTE PIÈCE ? » (JSON) — les rayons qui correspondent au nom
 * courant tapé, et les pièces déjà enregistrées portant ce mot, pour éviter
 * les doublons.
 * Programmation procédurale uniquement
 *
 * Traduction de fpl_natif/admin/ajax_piece_ranger.php.
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    http_response_code(403);
    echo json_encode(['categories' => [], 'products' => []]);
    exit;
}

require_once __DIR__ . '/../../includes/admin_route_access.php';
admin_route_enforce_json_empty();

require_once __DIR__ . '/../../includes/admin_permissions.php';
require_once __DIR__ . '/../../models/model_produits.php';
require_once __DIR__ . '/../../includes/fpl_texte.php';
require_once __DIR__ . '/../../includes/fpl_ui.php';
require_once __DIR__ . '/../../includes/site_url.php';

// Ranger une pièce, c'est en créer une : le compte restreint n'y a pas accès.
if (admin_is_restricted_admin_account()) {
    http_response_code(403);
    echo json_encode(['categories' => [], 'products' => []]);
    exit;
}

$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
if (mb_strlen($q) < 2) {
    echo json_encode(['categories' => [], 'products' => []]);
    exit;
}

echo json_encode(placement_recherche($q), JSON_UNESCAPED_UNICODE);
