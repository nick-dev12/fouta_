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
require_once __DIR__ . '/../../includes/admin_permissions.php';
require_once __DIR__ . '/../../includes/fpl_ui.php';
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
    /* On cherche le nom, le code FPL, la réf OEM ET la RÉFÉRENCE FOURNISSEUR
       (elle manquait — l'écran promettait pourtant « référence »). Les
       références se comparent en forme NORMALISÉE (majuscules, sans espaces
       ni tirets, O ramené à 0) pour retrouver la pièce quelle que soit la
       façon dont on tape ou dont la base a stocké la référence. Le résultat
       n'expose la référence fournisseur QU'À CEUX QUI ONT LE DROIT DE LA
       CORRIGER (12/09/2026 : l'infographiste, en plus des profils étendus) —
       la règle « le stock simple ne voit pas le fournisseur » reste tenue
       pour tous les autres. */
    $like = '%' . $q . '%';
    $norm = '%' . produits_ref_normalise($q) . '%';
    $or = ['p.nom LIKE :q_like'];
    $params = ['q_like' => $like, 'q_norm' => $norm];
    if (produits_has_column('identifiant_interne')) {
        $or[] = 'p.identifiant_interne LIKE :q_like';
        if (produits_has_column('reference_fpl')) {
            $or[] = 'p.reference_fpl LIKE :q_like';
        }
        $or[] = produits_ref_normalise_sql('p.identifiant_interne') . ' LIKE :q_norm';
    }
    if (produits_has_column('reference_oem')) {
        $or[] = 'p.reference_oem LIKE :q_like';
        $or[] = produits_ref_normalise_sql('p.reference_oem') . ' LIKE :q_norm';
    }
    if (produits_has_column('reference_fournisseur')) {
        $or[] = 'p.reference_fournisseur LIKE :q_like';
        $or[] = produits_ref_normalise_sql('p.reference_fournisseur') . ' LIKE :q_norm';
    }
    $col_ref_fpl = produits_has_column('reference_fpl') ? 'p.reference_fpl' : 'NULL AS reference_fpl';
    $col_ref_f = produits_has_column('reference_fournisseur') ? 'p.reference_fournisseur' : "'' AS reference_fournisseur";
    $stmt = $db->prepare("SELECT p.id, p.nom, p.identifiant_interne, $col_ref_fpl, p.reference_oem, $col_ref_f, p.stock,
                                 p.image_principale, p.entrepot_noeud_id,
                                 c.nom AS categorie_nom, sc.nom AS sous_categorie_nom
                          FROM produits p
                          LEFT JOIN categories c ON c.id = p.categorie_id
                          LEFT JOIN sous_categories sc ON sc.id = p.sous_categorie_id
                          WHERE p.sync_deleted_at IS NULL
                            AND (" . implode(' OR ', $or) . ")
                          ORDER BY p.nom
                          LIMIT 8");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $rows = [];
}

/* Qui lit la référence fournisseur ici : ceux qui ont le droit de la corriger
   (profils étendus et, depuis le 12/09, l'infographiste). */
$voit_fournisseur = function_exists('admin_can_modifier_references_piece') && admin_can_modifier_references_piece();

$products = [];
foreach ($rows as $r) {
    $chemin = '';
    if (!empty($r['entrepot_noeud_id']) && function_exists('entrepot_noeud_chemin_libelle')) {
        $chemin = (string) entrepot_noeud_chemin_libelle((int) $r['entrepot_noeud_id']);
    }
    $products[] = [
        'id' => (int) $r['id'],
        'name' => fpl_texte((string) $r['nom']),
        'code' => function_exists('fpl_reference_piece') ? fpl_reference_piece($r) : (string) $r['identifiant_interne'],
        'oem' => fpl_texte((string) ($r['reference_oem'] ?? '')),
        'ref_fournisseur' => $voit_fournisseur ? fpl_texte((string) ($r['reference_fournisseur'] ?? '')) : '',
        'categorie' => fpl_texte(trim((string) ($r['categorie_nom'] ?? '')
            . ((!empty($r['categorie_nom']) && !empty($r['sous_categorie_nom'])) ? ' › ' : '')
            . (string) ($r['sous_categorie_nom'] ?? ''))),
        'emplacement' => $chemin,
        'stock' => (int) $r['stock'],
        'image' => !empty($r['image_principale']) ? '../../upload/' . ltrim((string) $r['image_principale'], '/') : '',
    ];
}

/* L'infographiste (rôle photographe) ne voit ni stock ni emplacement : on les
 * retire de la réponse pour lui (07/09) — son écran n'en a pas l'usage. */
if (function_exists('admin_current_role') && admin_current_role() === 'photographe') {
    foreach ($products as &$pp) {
        unset($pp['stock'], $pp['emplacement']);
    }
    unset($pp);
}
echo json_encode(['products' => $products], JSON_UNESCAPED_UNICODE);
