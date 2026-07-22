<?php
/**
 * Seuils d'alerte stock (paramètres + usage notifications)
 * Périmètre : catégories et sous-catégories (règles sans lien = globales).
 */
require_once __DIR__ . '/../conn/conn.php';

/**
 * @return bool
 */
function stock_alertes_tables_ok()
{
    global $db;
    if (!$db) {
        return false;
    }
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    try {
        $stmt = $db->query('SELECT 1 FROM stock_alertes_regles LIMIT 1');
        $stmt->fetchColumn();
        $ok = true;
    } catch (PDOException $e) {
        $ok = false;
    }
    return $ok;
}

/**
 * @return bool
 */
function stock_alertes_scope_tables_ok()
{
    global $db;
    if (!$db || !stock_alertes_tables_ok()) {
        return false;
    }
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    try {
        $db->query('SELECT 1 FROM stock_alertes_regles_categories LIMIT 1');
        $db->query('SELECT 1 FROM stock_alertes_regles_sous_categories LIMIT 1');
        $ok = true;
    } catch (PDOException $e) {
        $ok = false;
    }
    return $ok;
}

/**
 * @param list<int> $ids
 * @return list<int>
 */
function stock_alertes_normaliser_ids(array $ids)
{
    $out = [];
    foreach ($ids as $id) {
        $id = (int) $id;
        if ($id > 0) {
            $out[$id] = $id;
        }
    }
    return array_values($out);
}

/**
 * @param int $regle_id
 * @return array{categorie_ids: list<int>, sous_categorie_ids: list<int>, categories: list<string>, sous_categories: list<string>}
 */
function stock_alertes_get_scope_regle($regle_id)
{
    global $db;
    $regle_id = (int) $regle_id;
    $empty = [
        'categorie_ids' => [],
        'sous_categorie_ids' => [],
        'categories' => [],
        'sous_categories' => [],
    ];
    if ($regle_id <= 0 || !stock_alertes_scope_tables_ok()) {
        return $empty;
    }
    try {
        $stmt = $db->prepare('
            SELECT rc.categorie_id, c.nom
            FROM stock_alertes_regles_categories rc
            INNER JOIN categories c ON c.id = rc.categorie_id
            WHERE rc.regle_id = :rid
            ORDER BY c.nom ASC
        ');
        $stmt->execute(['rid' => $regle_id]);
        $cat_rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $stmt = $db->prepare('
            SELECT rsc.sous_categorie_id, s.nom, s.categorie_id, c.nom AS categorie_nom
            FROM stock_alertes_regles_sous_categories rsc
            INNER JOIN sous_categories s ON s.id = rsc.sous_categorie_id
            INNER JOIN categories c ON c.id = s.categorie_id
            WHERE rsc.regle_id = :rid
            ORDER BY c.nom ASC, s.nom ASC
        ');
        $stmt->execute(['rid' => $regle_id]);
        $sc_rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return $empty;
    }
    $categorie_ids = [];
    $categories = [];
    foreach ($cat_rows as $row) {
        $cid = (int) $row['categorie_id'];
        $categorie_ids[] = $cid;
        $categories[] = (string) $row['nom'];
    }
    $sous_categorie_ids = [];
    $sous_categories = [];
    foreach ($sc_rows as $row) {
        $sid = (int) $row['sous_categorie_id'];
        $sous_categorie_ids[] = $sid;
        $sous_categories[] = (string) $row['nom'] . ' (' . (string) $row['categorie_nom'] . ')';
    }
    return [
        'categorie_ids' => $categorie_ids,
        'sous_categorie_ids' => $sous_categorie_ids,
        'categories' => $categories,
        'sous_categories' => $sous_categories,
    ];
}

/**
 * @return array<int, array<string, mixed>>
 */
function stock_alertes_get_all_regles()
{
    global $db;
    if (!stock_alertes_tables_ok()) {
        return [];
    }
    try {
        $stmt = $db->query(
            'SELECT id, niveau, seuil, date_creation FROM stock_alertes_regles
             ORDER BY FIELD(niveau, \'standard\',\'moyen\',\'haut\'), seuil ASC, id ASC'
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
    foreach ($rows as &$row) {
        $scope = stock_alertes_get_scope_regle((int) $row['id']);
        $row['categorie_ids'] = $scope['categorie_ids'];
        $row['sous_categorie_ids'] = $scope['sous_categorie_ids'];
        $row['categories'] = $scope['categories'];
        $row['sous_categories'] = $scope['sous_categories'];
        $row['est_globale'] = empty($scope['categorie_ids']) && empty($scope['sous_categorie_ids']);
        $row['scope_libelle'] = stock_alertes_libelle_scope($scope['categories'], $scope['sous_categories'], $row['est_globale']);
    }
    unset($row);
    return $rows;
}

/**
 * @param list<string> $categories
 * @param list<string> $sous_categories
 * @param bool $est_globale
 * @return string
 */
function stock_alertes_libelle_scope(array $categories, array $sous_categories, $est_globale = false)
{
    if ($est_globale) {
        return 'Toutes les catégories';
    }
    $parts = [];
    if (!empty($sous_categories)) {
        $parts[] = implode(', ', array_slice($sous_categories, 0, 4));
        if (count($sous_categories) > 4) {
            $parts[0] .= '… (+' . (count($sous_categories) - 4) . ')';
        }
    } elseif (!empty($categories)) {
        $parts[] = implode(', ', array_slice($categories, 0, 4));
        if (count($categories) > 4) {
            $parts[0] .= '… (+' . (count($categories) - 4) . ')';
        }
        $parts[0] .= ' (toutes sous-catégories)';
    }
    return $parts[0] ?? '—';
}

/**
 * @param string $niveau standard|moyen|haut
 * @return string
 */
function stock_alertes_libelle_niveau($niveau)
{
    $n = (string) $niveau;
    $map = [
        'standard' => 'Niveau standard',
        'moyen' => 'Niveau moyen',
        'haut' => 'Niveau haut',
    ];
    return $map[$n] ?? $n;
}

/**
 * Gravité pour comparer (plus grand = plus critique)
 */
function stock_alertes_gravite_niveau($niveau)
{
    $n = (string) $niveau;
    if ($n === 'haut') {
        return 3;
    }
    if ($n === 'moyen') {
        return 2;
    }
    return 1;
}

/**
 * @param int $regle_id
 * @param list<int> $categorie_ids
 * @param list<int> $sous_categorie_ids
 * @return bool
 */
function stock_alertes_enregistrer_scope_regle($regle_id, array $categorie_ids, array $sous_categorie_ids)
{
    global $db;
    $regle_id = (int) $regle_id;
    if ($regle_id <= 0 || !stock_alertes_scope_tables_ok()) {
        return false;
    }
    $categorie_ids = stock_alertes_normaliser_ids($categorie_ids);
    $sous_categorie_ids = stock_alertes_normaliser_ids($sous_categorie_ids);
    try {
        $db->prepare('DELETE FROM stock_alertes_regles_categories WHERE regle_id = ?')->execute([$regle_id]);
        $db->prepare('DELETE FROM stock_alertes_regles_sous_categories WHERE regle_id = ?')->execute([$regle_id]);
        if (!empty($categorie_ids)) {
            $ins = $db->prepare('INSERT INTO stock_alertes_regles_categories (regle_id, categorie_id) VALUES (?, ?)');
            foreach ($categorie_ids as $cid) {
                $ins->execute([$regle_id, $cid]);
            }
        }
        if (!empty($sous_categorie_ids)) {
            require_once __DIR__ . '/model_sous_categories.php';
            $ins = $db->prepare('INSERT INTO stock_alertes_regles_sous_categories (regle_id, sous_categorie_id) VALUES (?, ?)');
            foreach ($sous_categorie_ids as $sid) {
                $sc = get_sous_categorie_by_id($sid);
                if (!$sc) {
                    continue;
                }
                if (!empty($categorie_ids) && !in_array((int) $sc['categorie_id'], $categorie_ids, true)) {
                    continue;
                }
                $ins->execute([$regle_id, $sid]);
            }
        }
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * @param string $niveau
 * @param int $seuil
 * @param list<int> $categorie_ids
 * @param list<int> $sous_categorie_ids
 * @return array{success:bool, message:string}
 */
function stock_alertes_enregistrer_regle($niveau, $seuil, array $categorie_ids = [], array $sous_categorie_ids = [])
{
    global $db;
    if (!stock_alertes_tables_ok()) {
        return ['success' => false, 'message' => 'Table absente — exécutez migrations/run_create_stock_alertes.php'];
    }
    if (!stock_alertes_scope_tables_ok()) {
        return ['success' => false, 'message' => 'Migration catégories absente — exécutez migrations/run_migrate_stock_alertes_par_categorie.php'];
    }
    $niveau = (string) $niveau;
    if (!in_array($niveau, ['standard', 'moyen', 'haut'], true)) {
        return ['success' => false, 'message' => 'Niveau d’alerte invalide.'];
    }
    $seuil = (int) $seuil;
    if ($seuil < 0 || $seuil > 2147483646) {
        return ['success' => false, 'message' => 'Seuil de stock invalide.'];
    }
    $categorie_ids = stock_alertes_normaliser_ids($categorie_ids);
    $sous_categorie_ids = stock_alertes_normaliser_ids($sous_categorie_ids);
    if (empty($categorie_ids)) {
        return ['success' => false, 'message' => 'Sélectionnez au moins une catégorie.'];
    }
    require_once __DIR__ . '/model_categories.php';
    require_once __DIR__ . '/model_sous_categories.php';
    foreach ($categorie_ids as $cid) {
        if (!get_categorie_by_id($cid)) {
            return ['success' => false, 'message' => 'Catégorie invalide (id ' . $cid . ').'];
        }
    }
    foreach ($sous_categorie_ids as $sid) {
        $sc = get_sous_categorie_by_id($sid);
        if (!$sc) {
            return ['success' => false, 'message' => 'Sous-catégorie invalide (id ' . $sid . ').'];
        }
        if (!in_array((int) $sc['categorie_id'], $categorie_ids, true)) {
            return ['success' => false, 'message' => 'Une sous-catégorie ne correspond pas aux catégories sélectionnées.'];
        }
    }
    try {
        $db->beginTransaction();
        $stmt = $db->prepare(
            'INSERT INTO stock_alertes_regles (niveau, seuil, date_creation)
             VALUES (:niveau, :seuil, NOW())'
        );
        $stmt->execute(['niveau' => $niveau, 'seuil' => $seuil]);
        $regle_id = (int) $db->lastInsertId();
        if ($regle_id <= 0 || !stock_alertes_enregistrer_scope_regle($regle_id, $categorie_ids, $sous_categorie_ids)) {
            throw new PDOException('Scope non enregistré');
        }
        $db->commit();
        return ['success' => true, 'message' => 'Seuil enregistré pour le périmètre sélectionné.'];
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        return ['success' => false, 'message' => 'Erreur lors de l’enregistrement.'];
    }
}

/**
 * @param int $id
 * @return bool
 */
function stock_alertes_supprimer_regle($id)
{
    global $db;
    $id = (int) $id;
    if ($id <= 0 || !stock_alertes_tables_ok()) {
        return false;
    }
    try {
        $stmt = $db->prepare('DELETE FROM stock_alertes_regles WHERE id = ?');
        return $stmt->execute([$id]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * @param array<string, mixed> $regle
 * @param int $categorie_id
 * @param int $sous_categorie_id
 * @return bool
 */
function stock_alertes_regle_applique_produit(array $regle, $categorie_id, $sous_categorie_id)
{
    $categorie_id = (int) $categorie_id;
    $sous_categorie_id = (int) $sous_categorie_id;
    $cat_ids = isset($regle['categorie_ids']) ? (array) $regle['categorie_ids'] : [];
    $sc_ids = isset($regle['sous_categorie_ids']) ? (array) $regle['sous_categorie_ids'] : [];
    if (empty($cat_ids) && empty($sc_ids)) {
        return true;
    }
    if (!empty($sc_ids)) {
        return $sous_categorie_id > 0 && in_array($sous_categorie_id, $sc_ids, true);
    }
    return $categorie_id > 0 && in_array($categorie_id, $cat_ids, true);
}

/**
 * @param int $produit_id
 * @return list<array<string, mixed>>
 */
function stock_alertes_get_regles_pour_produit($produit_id)
{
    $produit_id = (int) $produit_id;
    if ($produit_id <= 0) {
        return [];
    }
    require_once __DIR__ . '/model_produits.php';
    $produit = get_produit_by_id($produit_id);
    if (!$produit) {
        return [];
    }
    $categorie_id = (int) ($produit['categorie_id'] ?? 0);
    $sous_categorie_id = 0;
    if (produits_has_column('sous_categorie_id')) {
        $sous_categorie_id = (int) ($produit['sous_categorie_id'] ?? 0);
    }
    $out = [];
    foreach (stock_alertes_get_all_regles() as $regle) {
        if (stock_alertes_regle_applique_produit($regle, $categorie_id, $sous_categorie_id)) {
            $out[] = $regle;
        }
    }
    return $out;
}

/**
 * Règles dont le seuil est franchi à la baisse (ancien > seuil et nouveau <= seuil)
 *
 * @param int $stock_avant
 * @param int $stock_apres
 * @param array $regles
 * @return list<array{id:int,niveau:string,seuil:int}>
 */
function stock_alertes_regles_franchies($stock_avant, $stock_apres, array $regles)
{
    $avant = (int) $stock_avant;
    $apres = (int) $stock_apres;
    if ($apres >= $avant) {
        return [];
    }
    $out = [];
    foreach ($regles as $r) {
        $s = (int) ($r['seuil'] ?? 0);
        if ($avant > $s && $apres <= $s) {
            $out[] = $r;
        }
    }
    return $out;
}

/**
 * Produits actuellement sous au moins un seuil (aperçu popup)
 *
 * @return array{items: list<array>, total: int}
 */
function stock_alertes_resume_pour_popup($limit = 30)
{
    $limit = max(1, min(100, (int) $limit));
    if (!stock_alertes_tables_ok()) {
        return ['items' => [], 'total' => 0];
    }
    $regles = stock_alertes_get_all_regles();
    if (empty($regles)) {
        return ['items' => [], 'total' => 0];
    }
    $max_seuil = 0;
    foreach ($regles as $r) {
        $max_seuil = max($max_seuil, (int) $r['seuil']);
    }
    global $db;
    $has_sc = false;
    require_once __DIR__ . '/model_produits.php';
    if (produits_has_column('sous_categorie_id')) {
        $has_sc = true;
    }
    try {
        $cols = 'id, nom, stock, categorie_id';
        if ($has_sc) {
            $cols .= ', sous_categorie_id';
        }
        $stmt = $db->prepare(
            "SELECT $cols FROM produits WHERE stock <= :mx ORDER BY stock ASC, nom ASC LIMIT 250"
        );
        $stmt->execute(['mx' => $max_seuil]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return ['items' => [], 'total' => 0];
    }

    $items_full = [];
    foreach ($rows as $row) {
        $sid = (int) $row['id'];
        $nom = (string) $row['nom'];
        $stock = (int) $row['stock'];
        $cid = (int) ($row['categorie_id'] ?? 0);
        $scid = $has_sc ? (int) ($row['sous_categorie_id'] ?? 0) : 0;
        $pire = null;
        foreach ($regles as $r) {
            if (!stock_alertes_regle_applique_produit($r, $cid, $scid)) {
                continue;
            }
            $seuil = (int) $r['seuil'];
            if ($stock > $seuil) {
                continue;
            }
            if ($pire === null || stock_alertes_gravite_niveau($r['niveau']) > stock_alertes_gravite_niveau($pire['niveau'])) {
                $pire = $r;
            }
        }
        if ($pire !== null) {
            $items_full[] = [
                'produit_id' => $sid,
                'nom' => $nom,
                'stock' => $stock,
                'seuil_ref' => (int) $pire['seuil'],
                'niveau' => (string) $pire['niveau'],
                'niveau_libelle' => stock_alertes_libelle_niveau($pire['niveau']),
                'scope_libelle' => (string) ($pire['scope_libelle'] ?? ''),
            ];
        }
    }
    $total = count($items_full);
    $items = array_slice($items_full, 0, $limit);
    return ['items' => $items, 'total' => $total];
}
