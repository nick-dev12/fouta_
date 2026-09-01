<?php
/**
 * Étiquette QR d’un nœud entrepôt — chargement à la demande (AJAX).
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../includes/admin_permissions.php';

if (!admin_can_gestion_stock_etendue()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès refusé']);
    exit;
}

$noeud_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($noeud_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Identifiant invalide']);
    exit;
}

require_once __DIR__ . '/../../models/model_entrepot_hierarchie_libre.php';

if (!entrepot_hierarchie_libre_schema_ok()) {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Schéma hiérarchie indisponible']);
    exit;
}

$payload = entrepot_noeud_etiquette_payload($noeud_id);
if ($payload === null) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Étiquette introuvable pour cet élément']);
    exit;
}

echo json_encode([
    'success' => true,
    'noeud_id' => $noeud_id,
    'etiquette' => $payload,
], JSON_UNESCAPED_UNICODE);
