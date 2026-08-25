<?php
/**
 * RECHERCHE D'UNE PIÈCE (JSON) — le picker des écrans de mouvement
 * (Transfert d'emplacement, Pièces défectueuses, Entrée en stock) : tapez
 * un nom, une référence FPL ou OEM, la pièce se choisit d'un clic.
 *
 * Portage de l'idée de fpl_natif/admin/ajax_recherche_mouvements.php,
 * aux tables de ce dépôt. Aucune écriture.
 */

session_start();

if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['products' => []]);
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../includes/fpl_texte.php';
require_once __DIR__ . '/../../models/model_produits.php';
require_once __DIR__ . '/../../models/model_entrepot_hierarchie_libre.php';
require_once __DIR__ . '/../../includes/produit_emplacement_entrepot.php';

header('Content-Type: application/json; charset=utf-8');

$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
if (mb_strlen($q) < 2) {
    echo json_encode(['products' => []]);
    exit;
}

$rows = [];
try {
    $stmt = $db->prepare("SELECT p.id, p.nom, p.identifiant_interne, p.reference_oem, p.stock,
                                 p.image_principale, p.entrepot_noeud_id,
                                 c.nom AS categorie_nom, sc.nom AS sous_categorie_nom
                          FROM produits p
                          LEFT JOIN categories c ON c.id = p.categorie_id
                          LEFT JOIN sous_categories sc ON sc.id = p.sous_categorie_id
                          WHERE p.sync_deleted_at IS NULL
                            AND (p.nom LIKE :q1 OR p.identifiant_interne LIKE :q2 OR p.reference_oem LIKE :q3)
                          ORDER BY p.nom
                          LIMIT 8");
    $stmt->execute(['q1' => '%' . $q . '%', 'q2' => '%' . $q . '%', 'q3' => '%' . $q . '%']);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $rows = [];
}

$products = [];
foreach ($rows as $r) {
    $chemin = '';
    if (!empty($r['entrepot_noeud_id']) && function_exists('entrepot_noeud_chemin_libelle')) {
        $chemin = (string) entrepot_noeud_chemin_libelle((int) $r['entrepot_noeud_id']);
    }
    $products[] = [
        'id' => (int) $r['id'],
        'name' => fpl_texte((string) $r['nom']),
        'code' => (string) $r['identifiant_interne'],
        'oem' => fpl_texte((string) ($r['reference_oem'] ?? '')),
        'categorie' => fpl_texte(trim((string) ($r['categorie_nom'] ?? '')
            . ((!empty($r['categorie_nom']) && !empty($r['sous_categorie_nom'])) ? ' › ' : '')
            . (string) ($r['sous_categorie_nom'] ?? ''))),
        'emplacement' => $chemin,
        'stock' => (int) $r['stock'],
        'image' => !empty($r['image_principale']) ? '../../upload/' . ltrim((string) $r['image_principale'], '/') : '',
    ];
}

echo json_encode(['products' => $products], JSON_UNESCAPED_UNICODE);
