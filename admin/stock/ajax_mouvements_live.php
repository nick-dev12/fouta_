<?php
/**
 * Recherche live + pagination — mouvements de stock (AJAX).
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../models/model_mouvements_stock.php';
require_once __DIR__ . '/../../includes/stock_mouvements_render.php';

$search = trim((string) ($_GET['q'] ?? $_GET['recherche'] ?? ''));
$categorie_id = isset($_GET['categorie_id']) ? (int) $_GET['categorie_id'] : 0;
$type_filter = isset($_GET['type']) && in_array($_GET['type'], ['entree', 'sortie', 'inventaire'], true)
    ? (string) $_GET['type']
    : null;
$per_page = max(10, min(100, (int) ($_GET['per_page'] ?? 25)));
$page = max(1, (int) ($_GET['page'] ?? 1));

$total = count_stock_mouvements(
    $categorie_id > 0 ? $categorie_id : null,
    $type_filter,
    $search !== '' ? $search : null
);
$total_pages = max(1, (int) ceil($total / $per_page));
if ($page > $total_pages) {
    $page = $total_pages;
}
$offset = ($page - 1) * $per_page;

$mouvements = get_stock_mouvements_paginated(
    $categorie_id > 0 ? $categorie_id : null,
    $type_filter,
    $search !== '' ? $search : null,
    $offset,
    $per_page
);

echo json_encode([
    'html_table' => stock_mouvements_render_table_rows($mouvements),
    'html_cards' => stock_mouvements_render_cards($mouvements),
    'html_pagination' => stock_mouvements_render_pagination($page, $total_pages, $per_page, $total),
    'total' => $total,
    'page' => $page,
    'total_pages' => $total_pages,
    'per_page' => $per_page,
    'empty' => empty($mouvements),
    'has_filters' => ($search !== '' || $categorie_id > 0 || $type_filter !== null),
]);
