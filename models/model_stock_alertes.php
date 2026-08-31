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

/* =====================================================================
   LE SEUIL D'UNE PIÈCE ET SES SUITES (31/08/2026)
   ---------------------------------------------------------------------
   Trois manques comblés d'un coup, mesurés contre FPL natif :
     1. l'EXCEPTION par pièce — un seul nombre ne peut pas gouverner un
        rayon entier : le boulon et la boîte de vitesses n'ont pas le même
        point de rupture ;
     2. la SUGGESTION par les ventes — un seuil réglé au doigt mouillé ne
        veut rien dire ; celui qui sort de « ce qu'on vend par jour × le
        nombre de jours qu'on veut tenir » se défend ;
     3. l'ÉTAT PERMANENT — le bandeau de 30 secondes ne parle qu'à celui
        qui était devant l'écran au moment du franchissement. Le compte des
        pièces sous leur seuil, lui, attend qu'on vienne le lire.

   Ordre de résolution : la pièce, puis la sous-catégorie, puis la
   catégorie, puis la règle générale.
   ===================================================================== */

/**
 * @return bool
 */
function stock_alertes_seuil_piece_colonne_ok()
{
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    global $db;
    $ok = false;
    if ($db) {
        try {
            $db->query('SELECT seuil_alerte FROM produits LIMIT 1');
            $ok = true;
        } catch (PDOException $e) {
            $ok = false;
        }
    }

    return $ok;
}

/**
 * Les règles, chargées une seule fois par requête.
 *
 * @return array<int, array<string, mixed>>
 */
function stock_alertes_regles_en_cache()
{
    static $regles = null;
    if ($regles === null) {
        $regles = stock_alertes_get_all_regles();
    }

    return $regles;
}

/**
 * Le seuil qui s'applique VRAIMENT à cette pièce, et d'où il vient.
 *
 * @param array<string, mixed> $produit id, stock, seuil_alerte, categorie_id, sous_categorie_id
 * @return array{seuil: int|null, origine: string, niveau: string|null, libelle: string}
 */
function stock_alerte_seuil_effectif(array $produit)
{
    if (stock_alertes_seuil_piece_colonne_ok()
        && isset($produit['seuil_alerte']) && $produit['seuil_alerte'] !== null && $produit['seuil_alerte'] !== '') {
        return [
            'seuil' => (int) $produit['seuil_alerte'],
            'origine' => 'piece',
            'niveau' => null,
            'libelle' => 'Seuil propre à la pièce',
        ];
    }

    $cat = (int) ($produit['categorie_id'] ?? 0);
    $sous = (int) ($produit['sous_categorie_id'] ?? 0);
    $meilleur = null;
    $meilleur_rang = -1;
    foreach (stock_alertes_regles_en_cache() as $regle) {
        if (!stock_alertes_regle_applique_produit($regle, $cat, $sous)) {
            continue;
        }
        /* La plus précise gagne : sous-catégorie (3), catégorie (2), générale (1). */
        $rang = !empty($regle['sous_categorie_ids']) ? 3 : (!empty($regle['categorie_ids']) ? 2 : 1);
        if ($rang > $meilleur_rang) {
            $meilleur = $regle;
            $meilleur_rang = $rang;
            continue;
        }
        if ($rang === $meilleur_rang && $meilleur !== null) {
            /* À précision égale, la plus protectrice : le seuil le plus haut,
               puis la gravité la plus forte. */
            $mieux = (int) $regle['seuil'] > (int) $meilleur['seuil']
                || ((int) $regle['seuil'] === (int) $meilleur['seuil']
                    && stock_alertes_gravite_niveau($regle['niveau']) > stock_alertes_gravite_niveau($meilleur['niveau']));
            if ($mieux) {
                $meilleur = $regle;
            }
        }
    }
    if ($meilleur === null) {
        return ['seuil' => null, 'origine' => 'aucun', 'niveau' => null, 'libelle' => 'Aucun seuil'];
    }
    $origine = $meilleur_rang === 3 ? 'sous_categorie' : ($meilleur_rang === 2 ? 'categorie' : 'generale');
    $libelle = $origine === 'sous_categorie' ? 'Règle de sous-catégorie'
        : ($origine === 'categorie' ? 'Règle de catégorie' : 'Règle générale');

    return [
        'seuil' => (int) $meilleur['seuil'],
        'origine' => $origine,
        'niveau' => (string) $meilleur['niveau'],
        'libelle' => $libelle,
    ];
}

/**
 * Pose ou retire le seuil propre d'une pièce.
 *
 * @param int $produit_id
 * @param int|null $seuil null retire l'exception
 * @return array{success: bool, message: string}
 */
/**
 * La colonne qui dit d'où vient le seuil d'une pièce : 'manuel' ou
 * 'suggestion'. Une base qui ne l'a pas encore traite tout comme manuel —
 * dans le doute, on protège le travail de la personne.
 *
 * @return bool
 */
function stock_alertes_seuil_source_colonne_ok()
{
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    global $db;
    $ok = false;
    if ($db) {
        try {
            $db->query('SELECT seuil_alerte_source FROM produits LIMIT 1');
            $ok = true;
        } catch (PDOException $e) {
            $ok = false;
        }
    }

    return $ok;
}

/**
 * @param array<string, mixed> $produit
 * @return bool  le seuil de cette pièce a-t-il été posé par une personne ?
 */
function stock_alertes_seuil_pose_a_la_main(array $produit)
{
    if (!isset($produit['seuil_alerte']) || $produit['seuil_alerte'] === null || $produit['seuil_alerte'] === '') {
        return false;
    }
    if (!stock_alertes_seuil_source_colonne_ok()) {
        return true;
    }

    return ((string) ($produit['seuil_alerte_source'] ?? 'manuel')) !== 'suggestion';
}

function stock_alertes_seuil_piece_enregistrer($produit_id, $seuil, $source = 'manuel')
{
    global $db;
    $produit_id = (int) $produit_id;
    if ($produit_id <= 0 || !$db || !stock_alertes_seuil_piece_colonne_ok()) {
        return ['success' => false, 'message' => 'Seuil par pièce indisponible.'];
    }
    if ($seuil !== null && (!is_numeric($seuil) || (int) $seuil < 0)) {
        return ['success' => false, 'message' => 'Le seuil doit être un nombre positif.'];
    }
    $source = $source === 'suggestion' ? 'suggestion' : 'manuel';
    try {
        $avec_source = stock_alertes_seuil_source_colonne_ok();
        $sql = $avec_source
            ? 'UPDATE produits SET seuil_alerte = :s, seuil_alerte_source = :src, date_modification = NOW() WHERE id = :id'
            : 'UPDATE produits SET seuil_alerte = :s, date_modification = NOW() WHERE id = :id';
        $st = $db->prepare($sql);
        $params = [':s' => $seuil === null ? null : (int) $seuil, ':id' => $produit_id];
        if ($avec_source) {
            $params[':src'] = $seuil === null ? null : $source;
        }
        $st->execute($params);

        return [
            'success' => true,
            'message' => $seuil === null
                ? 'Exception retirée : la pièce suit de nouveau la règle de sa catégorie.'
                : 'Seuil de la pièce fixé à ' . (int) $seuil . '.',
        ];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Enregistrement impossible.'];
    }
}

/**
 * Les pièces qui sont SOUS leur seuil effectif, maintenant.
 *
 * @param int $limite
 * @return array<int, array<string, mixed>>
 */
function stock_alertes_produits_sous_seuil($limite = 200)
{
    global $db;
    if (!$db) {
        return [];
    }
    $plafond = 0;
    foreach (stock_alertes_regles_en_cache() as $r) {
        $plafond = max($plafond, (int) $r['seuil']);
    }
    $colonne_piece = stock_alertes_seuil_piece_colonne_ok();
    /* On ne remonte que ce qui PEUT être sous un seuil : un stock au-dessus du
       plus haut seuil connu n'alerte personne, sauf exception propre. */
    $sql = 'SELECT p.id, p.nom, p.identifiant_interne, p.stock, p.categorie_id, p.sous_categorie_id'
        . ($colonne_piece ? ', p.seuil_alerte' : '')
        . ' FROM produits p WHERE p.sync_deleted_at IS NULL AND (p.stock <= :plafond';
    if ($colonne_piece) {
        $sql .= ' OR (p.seuil_alerte IS NOT NULL AND p.stock <= p.seuil_alerte)';
    }
    $sql .= ') ORDER BY p.stock ASC, p.nom ASC';
    try {
        $st = $db->prepare($sql);
        $st->execute([':plafond' => $plafond]);
        $lignes = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
    $out = [];
    foreach ($lignes as $p) {
        $eff = stock_alerte_seuil_effectif($p);
        if ($eff['seuil'] === null || (int) $p['stock'] > $eff['seuil']) {
            continue;
        }
        $p['seuil_effectif'] = $eff['seuil'];
        $p['seuil_origine'] = $eff['origine'];
        $p['seuil_libelle'] = $eff['libelle'];
        $p['seuil_niveau'] = $eff['niveau'];
        $out[] = $p;
        if (count($out) >= $limite) {
            break;
        }
    }

    return $out;
}

/**
 * Ce que les VENTES conseillent : ce qui part par jour × le nombre de jours
 * qu'on veut tenir. La vente, ici, c'est la sortie de caisse.
 *
 * @param int $delai jours de couverture voulus (1 à 60)
 * @param int $fenetre_jours période observée
 * @return array<int, array<string, mixed>>
 */
function stock_alertes_suggestions($delai, $fenetre_jours = 30)
{
    global $db;
    $delai = max(1, min(60, (int) $delai));
    $fenetre_jours = max(1, (int) $fenetre_jours);
    if (!$db) {
        return [];
    }
    try {
        $st = $db->prepare("SELECT m.produit_id, SUM(m.quantite) AS total
                            FROM stock_mouvements m
                            WHERE m.type = 'sortie'
                              AND m.reference_type = 'caisse_vente'
                              AND m.sync_deleted_at IS NULL
                              AND m.date_mouvement >= :depuis
                            GROUP BY m.produit_id");
        $st->execute([':depuis' => date('Y-m-d H:i:s', strtotime('-' . $fenetre_jours . ' days'))]);
        $ventes = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $v) {
            $ventes[(int) $v['produit_id']] = (float) $v['total'];
        }
        if ($ventes === []) {
            return [];
        }
        $ids = implode(',', array_map('intval', array_keys($ventes)));
        $colonne_piece = stock_alertes_seuil_piece_colonne_ok();
        /* L'ORIGINE DU SEUIL VOYAGE AVEC LA PIÈCE (31/08) : sans elle, un
         * seuil déjà posé passait pour « posé à la main » et le calcul se
         * refusait à le mettre à jour — même le sien. */
        $rows = $db->query('SELECT p.id, p.nom, p.identifiant_interne, p.stock, p.categorie_id, p.sous_categorie_id'
            . ($colonne_piece ? ', p.seuil_alerte' : '')
            . (stock_alertes_seuil_source_colonne_ok() ? ', p.seuil_alerte_source' : '')
            . ' FROM produits p WHERE p.id IN (' . $ids . ') AND p.sync_deleted_at IS NULL')->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
    $out = [];
    foreach ($rows as $p) {
        $par_jour = $ventes[(int) $p['id']] / $fenetre_jours;
        $eff = stock_alerte_seuil_effectif($p);
        $out[] = [
            'produit' => $p,
            'vendus' => (int) $ventes[(int) $p['id']],
            'par_jour' => $par_jour,
            'seuil_actuel' => $eff['seuil'],
            'seuil_origine' => $eff['origine'],
            'suggere' => (int) max(1, ceil($par_jour * $delai)),
        ];
    }
    usort($out, function ($a, $b) {
        return $b['par_jour'] <=> $a['par_jour'];
    });

    return $out;
}

/**
 * Applique les suggestions : chaque pièce vendue reçoit SON seuil.
 *
 * @param int $delai
 * @param int $fenetre_jours
 * @return array{success: bool, message: string, appliques: int}
 */
function stock_alertes_appliquer_suggestions($delai, $fenetre_jours = 30)
{
    if (!stock_alertes_seuil_piece_colonne_ok()) {
        return ['success' => false, 'message' => 'Seuil par pièce indisponible.', 'appliques' => 0];
    }
    $n = 0;
    $gardes = 0;
    foreach (stock_alertes_suggestions($delai, $fenetre_jours) as $s) {
        /* LA MAIN L'EMPORTE (31/08) : un seuil décidé par une personne n'est
         * jamais remplacé par une moyenne. Le calcul ne repasse que sur ce
         * qu'il a lui-même posé, ou sur ce qui n'a pas de seuil. */
        if (stock_alertes_seuil_pose_a_la_main($s['produit'])) {
            $gardes++;
            continue;
        }
        $actuel = isset($s['produit']['seuil_alerte']) && $s['produit']['seuil_alerte'] !== null
            ? (int) $s['produit']['seuil_alerte'] : null;
        if ($actuel === (int) $s['suggere']) {
            continue;
        }
        $res = stock_alertes_seuil_piece_enregistrer((int) $s['produit']['id'], (int) $s['suggere'], 'suggestion');
        if ($res['success']) {
            $n++;
        }
    }

    $message = $n . ' seuil(s) appliqué(s) — ventes des ' . (int) $fenetre_jours
        . ' derniers jours ramenées à ' . max(1, min(60, (int) $delai)) . ' jour(s) de couverture.';
    if ($gardes > 0) {
        $message .= ' ' . $gardes . ' pièce(s) gardent le seuil posé à la main.';
    }

    return [
        'success' => true,
        'appliques' => $n,
        'gardes' => $gardes,
        'message' => $message,
    ];
}
