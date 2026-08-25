<?php
/**
 * Recherche live liste admin produits (AJAX) — résultats HTML séparés de la grille paginée.
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    echo json_encode(['error' => 'Non autorisé', 'html' => '', 'total' => 0, 'truncated' => false]);
    exit;
}

require_once __DIR__ . '/../../includes/admin_route_access.php';
admin_route_enforce_json_empty();

require_once __DIR__ . '/../../models/model_produits.php';

require_once __DIR__ . '/../../includes/site_url.php';

$recherche = trim($_GET['q'] ?? $_GET['recherche'] ?? '');
$categorie_id = isset($_GET['categorie_id']) ? (int) $_GET['categorie_id'] : 0;
$marque_id = isset($_GET['marque_id']) ? (int) $_GET['marque_id'] : 0;
$fournisseur_id = isset($_GET['fournisseur_id']) ? (int) $_GET['fournisseur_id'] : 0;
$context = trim((string) ($_GET['context'] ?? 'index'));
$offset = isset($_GET['offset']) ? max(0, (int) $_GET['offset']) : 0;

$result = search_admin_produits_liste_live(
    $recherche,
    $categorie_id,
    $marque_id,
    $fournisseur_id,
    ADMIN_PRODUITS_LIVE_SEARCH_LIMIT,
    $offset
);

$html = '';
$items = $result['items'] ?? [];
if (!empty($items)) {
    /* Les noms de modèles de véhicule, résolus une fois pour toutes les lignes :
     * la ligne au balisage FPL en a besoin pour sa colonne « Modèle ». */
    $fpl_modeles_noms = [];
    if ($context === 'fpl') {
        try {
            foreach ($db->query('SELECT id, nom FROM vehicule_modeles') as $vm) {
                $fpl_modeles_noms[(int) $vm['id']] = (string) $vm['nom'];
            }
        } catch (PDOException $e) {
            $fpl_modeles_noms = [];
        }
    }
    ob_start();
    foreach ($items as $produit) {
        if ($context === 'dashboard') {
            $pcm_paths = ['base' => 'produits/', 'upload' => '/upload/'];
            include __DIR__ . '/../includes/carte_produit_dashboard.php';
        } elseif ($context === 'fpl') {
            /* LE CATALOGUE AU BALISAGE DE FPL NATIF. La recherche en direct doit
             * rendre EXACTEMENT les mêmes lignes que le tableau qu'elle remplace,
             * sinon les résultats changent de forme en cours de frappe. */
            $upload_base = rtrim(get_public_root_uri_path(), '/') . '/upload/';
            include __DIR__ . '/includes/ligne_piece_fpl.php';
        } elseif ($context === 'categorie') {
            $upload_base = rtrim(get_public_root_uri_path(), '/') . '/upload/';
            $produits_path_prefix = '../produits/';
            $hide_categorie_col = true;
            include __DIR__ . '/includes/ligne_produit_table.php';
        } else {
            $upload_base = rtrim(get_public_root_uri_path(), '/') . '/upload/';
            include __DIR__ . '/includes/ligne_produit_table.php';
        }
    }
    $html = ob_get_clean();
}

$shown = count($items);
$total = (int) ($result['total'] ?? 0);

echo json_encode([
    'html' => $html,
    'total' => $total,
    'truncated' => !empty($result['truncated']),
    'has_more' => !empty($result['truncated']),
    'shown' => $shown,
    'offset' => $offset,
    'next_offset' => $offset + $shown,
    'limit' => (int) ($result['limit'] ?? ADMIN_PRODUITS_LIVE_SEARCH_LIMIT),
    'displayed' => $offset + $shown,
]);
