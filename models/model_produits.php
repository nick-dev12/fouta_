<?php
/**
 * Modèle pour la gestion des produits
 * Programmation procédurale uniquement
 */

// Inclusion du fichier de connexion à la BDD
require_once __DIR__ . '/../conn/conn.php';

/**
 * Indique si une colonne existe sur la table produits (cache SHOW COLUMNS)
 */
function produits_has_column($name) {
    static $cols = null;
    global $db;
    if ($cols === null) {
        $cols = [];
        if (!$db) {
            return false;
        }
        try {
            $stmt = $db->query('SHOW COLUMNS FROM produits');
            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $cols[$r['Field']] = true;
            }
        } catch (PDOException $e) {
            $cols = [];
        }
    }
    return isset($cols[$name]);
}

/**
 * Génère le prochain identifiant interne FPLXXXXXX (6 chiffres)
 */
function generate_next_identifiant_interne_produit() {
    global $db;
    if (!$db || !produits_has_column('identifiant_interne')) {
        return null;
    }
    try {
        $stmt = $db->query("
            SELECT identifiant_interne FROM produits
            WHERE identifiant_interne REGEXP '^FPL[0-9]{6}$'
            ORDER BY identifiant_interne DESC LIMIT 1
        ");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $next = 1;
        if ($row && !empty($row['identifiant_interne']) && preg_match('/^FPL(\d{6})$/', $row['identifiant_interne'], $m)) {
            $next = (int) $m[1] + 1;
        }
        if ($next > 999999) {
            $next = 1;
        }
        return 'FPL' . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    } catch (PDOException $e) {
        return 'FPL' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
    }
}

/**
 * Récupère tous les produits
 * @param string $statut Filtrer par statut (optionnel)
 * @return array|false Tableau des produits ou False en cas d'erreur
 */
function get_all_produits($statut = null)
{
    global $db;

    try {
        if ($statut) {
            $stmt = $db->prepare("
                SELECT p.*, c.nom as categorie_nom
                FROM produits p 
                LEFT JOIN categories c ON p.categorie_id = c.id 
                WHERE p.statut = :statut 
                ORDER BY p.date_creation DESC
            ");
            $stmt->execute(['statut' => $statut]);
        } else {
            $stmt = $db->prepare("
                SELECT p.*, c.nom as categorie_nom
                FROM produits p 
                LEFT JOIN categories c ON p.categorie_id = c.id 
                ORDER BY p.date_creation DESC
            ");
            $stmt->execute();
        }

        $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $produits ? $produits : [];
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Récupère les produits d'une catégorie spécifique
 * @param int $categorie_id L'ID de la catégorie
 * @return array|false Tableau des produits ou False en cas d'erreur
 */
function get_produits_by_categorie($categorie_id)
{
    global $db;

    try {
        $stmt = $db->prepare("
            SELECT p.*, c.nom as categorie_nom 
            FROM produits p 
            LEFT JOIN categories c ON p.categorie_id = c.id 
            WHERE p.categorie_id = :categorie_id AND p.statut = 'actif'
            ORDER BY p.date_creation DESC
        ");
        $stmt->execute(['categorie_id' => $categorie_id]);
        $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $produits ? $produits : [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Récupère un produit par son ID
 * @param int $id L'ID du produit
 * @return array|false Les données du produit ou False si non trouvé
 */
function get_produit_by_id($id)
{
    global $db;

    try {
        $stmt = $db->prepare("
            SELECT p.*, c.nom as categorie_nom
            FROM produits p 
            LEFT JOIN categories c ON p.categorie_id = c.id 
            WHERE p.id = :id
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Récupère un produit par son identifiant interne FPLxxxxxx (insensible à la casse)
 * @param string $code Ex. FPL000042
 * @param bool $only_actif Si true, uniquement les produits actifs ou en promo (exclut inactif)
 * @return array|false
 */
function get_produit_by_identifiant_interne($code, $only_actif = false)
{
    global $db;

    if (!produits_has_column('identifiant_interne')) {
        return false;
    }
    $code = strtoupper(trim((string) $code));
    if (!preg_match('/^FPL\d{6}$/', $code)) {
        return false;
    }

    try {
        $sql = "
            SELECT p.*, c.nom as categorie_nom
            FROM produits p
            LEFT JOIN categories c ON p.categorie_id = c.id
            WHERE UPPER(TRIM(p.identifiant_interne)) = :code
        ";
        if ($only_actif) {
            $sql .= " AND p.statut IN ('actif', 'rupture_stock')";
        }
        $stmt = $db->prepare($sql);
        $stmt->execute(['code' => $code]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Extrait les 5 derniers chiffres de la partie numérique du code (style caisse : saisie rapide)
 * Ex. FPL000151 → "00151", FPL100001 → "00001"
 */
function produit_identifiant_derniers_5_chiffres($identifiant_interne)
{
    $d = preg_replace('/\D/', '', (string) $identifiant_interne);
    if (strlen($d) < 5) {
        return '';
    }

    return substr($d, -5);
}

/**
 * Expression SQL MySQL : les 5 derniers chiffres du numéro (après retrait du préfixe FPL)
 * @param string $table_prefix Préfixe de table/colonne, ex. 'p' → p.identifiant_interne ; '' → identifiant_interne
 */
function produits_sql_identifiant_suffix_5_expr($table_prefix = 'p')
{
    $col = $table_prefix === '' ? 'identifiant_interne' : $table_prefix . '.identifiant_interne';

    return "RIGHT(REPLACE(REPLACE(REPLACE(UPPER(TRIM($col)), 'F', ''), 'P', ''), 'L', ''), 5)";
}

/**
 * Liste des produits dont le code se termine par ces 5 chiffres (recherche rapide)
 */
function get_produits_by_identifiant_suffix_5_chiffres($suffix5, $offset = 0, $limit = 20, $only_actif = true)
{
    global $db;

    if (!produits_has_column('identifiant_interne')) {
        return [];
    }
    $suffix5 = preg_replace('/\D/', '', (string) $suffix5);
    if (strlen($suffix5) !== 5) {
        return [];
    }

    $statut_sql = $only_actif ? "p.statut IN ('actif', 'rupture_stock')" : '1=1';
    $sql = '
        SELECT p.*, c.nom as categorie_nom
        FROM produits p
        LEFT JOIN categories c ON p.categorie_id = c.id
        WHERE ' . $statut_sql . '
        AND p.identifiant_interne IS NOT NULL AND TRIM(p.identifiant_interne) != \'\'
        AND ' . produits_sql_identifiant_suffix_5_expr('p') . ' = :suf
        ORDER BY p.date_creation DESC
        LIMIT :limit OFFSET :offset
    ';

    try {
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':suf', $suffix5, PDO::PARAM_STR);
        $stmt->bindValue(':limit', (int) $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int) $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $rows ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Compte les produits correspondant aux 5 derniers chiffres
 */
function count_produits_by_identifiant_suffix_5_chiffres($suffix5, $only_actif = true)
{
    global $db;

    if (!produits_has_column('identifiant_interne')) {
        return 0;
    }
    $suffix5 = preg_replace('/\D/', '', (string) $suffix5);
    if (strlen($suffix5) !== 5) {
        return 0;
    }

    $statut_sql = $only_actif ? "statut IN ('actif', 'rupture_stock')" : '1=1';
    $sql = '
        SELECT COUNT(*) FROM produits
        WHERE ' . $statut_sql . '
        AND identifiant_interne IS NOT NULL AND TRIM(identifiant_interne) != \'\'
        AND ' . produits_sql_identifiant_suffix_5_expr('') . ' = :suf
    ';

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute(['suf' => $suffix5]);

        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Attribue un identifiant FPLxxxxxx si absent (produits anciens)
 * @return string|null Le code attribué ou existant
 */
function ensure_produit_identifiant_interne($produit_id)
{
    global $db;

    $produit_id = (int) $produit_id;
    if ($produit_id <= 0 || !produits_has_column('identifiant_interne')) {
        return null;
    }

    $p = get_produit_by_id($produit_id);
    if (!$p) {
        return null;
    }
    if (!empty($p['identifiant_interne'])) {
        return trim($p['identifiant_interne']);
    }

    for ($attempt = 0; $attempt < 8; $attempt++) {
        $ident = generate_next_identifiant_interne_produit();
        if (!$ident) {
            return null;
        }
        try {
            $stmt = $db->prepare('
                UPDATE produits
                SET identifiant_interne = :i
                WHERE id = :id AND (identifiant_interne IS NULL OR identifiant_interne = \'\')
            ');
            $stmt->execute(['i' => $ident, 'id' => $produit_id]);
            if ($stmt->rowCount() > 0) {
                return $ident;
            }
            $p2 = get_produit_by_id($produit_id);
            if ($p2 && !empty($p2['identifiant_interne'])) {
                return trim($p2['identifiant_interne']);
            }
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate') !== false || strpos($e->getMessage(), '1062') !== false) {
                continue;
            }
            return null;
        }
    }

    return null;
}

/**
 * Récupère tous les produits actifs avec pagination
 * @param int $offset Nombre de produits à ignorer (pour pagination)
 * @param int $limit Nombre maximum de produits à retourner
 * @return array Tableau des produits
 */
function get_all_produits_paginated($offset = 0, $limit = 20)
{
    global $db;

    try {
        $stmt = $db->prepare("
            SELECT p.*, c.nom as categorie_nom 
            FROM produits p 
            LEFT JOIN categories c ON p.categorie_id = c.id 
            WHERE p.statut = 'actif'
            ORDER BY p.date_creation DESC
            LIMIT :limit OFFSET :offset
        ");

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $produits ? $produits : [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Recherche des produits par nom ou description
 * @param string $recherche Terme de recherche
 * @param int $offset Décalage pour pagination
 * @param int $limit Nombre max de résultats
 * @return array Tableau des produits trouvés
 */
function search_produits($recherche, $offset = 0, $limit = 20)
{
    global $db;

    if (empty(trim($recherche))) {
        return get_all_produits_paginated($offset, $limit);
    }

    $t = trim($recherche);
    if (produits_has_column('identifiant_interne') && preg_match('/^\d{5}$/', $t)) {
        return get_produits_by_identifiant_suffix_5_chiffres($t, $offset, $limit, true);
    }
    if (produits_has_column('identifiant_interne') && preg_match('/^FPL\d{6}$/i', $t)) {
        $p = get_produit_by_identifiant_interne(strtoupper($t), true);
        return $p ? [$p] : [];
    }

    try {
        $term = '%' . trim($recherche) . '%';
        $stmt = $db->prepare("
            SELECT p.*, c.nom as categorie_nom 
            FROM produits p 
            LEFT JOIN categories c ON p.categorie_id = c.id 
            WHERE p.statut = 'actif' 
            AND (p.nom LIKE :term OR p.description LIKE :term)
            ORDER BY p.date_creation DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':term', $term, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $produits ? $produits : [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Compte les produits correspondant à une recherche
 * @param string $recherche Terme de recherche
 * @return int Nombre de produits
 */
function count_search_produits($recherche)
{
    global $db;

    if (empty(trim($recherche))) {
        return count_all_produits_actifs();
    }

    $t = trim($recherche);
    if (produits_has_column('identifiant_interne') && preg_match('/^\d{5}$/', $t)) {
        return count_produits_by_identifiant_suffix_5_chiffres($t, true);
    }
    if (produits_has_column('identifiant_interne') && preg_match('/^FPL\d{6}$/i', $t)) {
        $p = get_produit_by_identifiant_interne(strtoupper($t), true);
        return $p ? 1 : 0;
    }

    try {
        $term = '%' . trim($recherche) . '%';
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM produits 
            WHERE statut = 'actif' 
            AND (nom LIKE :term OR description LIKE :term)
        ");
        $stmt->execute(['term' => $term]);
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Recherche des produits avec filtres (recherche texte + prix min/max + catégorie)
 * @param string $recherche Terme de recherche (optionnel)
 * @param float|null $prix_min Prix minimum en FCFA (optionnel)
 * @param float|null $prix_max Prix maximum en FCFA (optionnel)
 * @param int|null $categorie_id ID catégorie (optionnel)
 * @param string $tri Tri: 'date', 'prix_asc', 'prix_desc', 'nom' (défaut: date)
 * @param int $offset Décalage pour pagination
 * @param int $limit Nombre max de résultats
 * @return array Tableau des produits trouvés
 */
function search_produits_with_filters($recherche = '', $prix_min = null, $prix_max = null, $categorie_id = null, $tri = 'date', $offset = 0, $limit = 50)
{
    global $db;

    try {
        $conditions = ["p.statut = 'actif'"];
        $params = [];

        if (!empty(trim($recherche))) {
            $tr = trim($recherche);
            if (produits_has_column('identifiant_interne') && preg_match('/^\d{5}$/', $tr)) {
                $conditions[] = 'p.identifiant_interne IS NOT NULL AND TRIM(p.identifiant_interne) != \'\' AND ' . produits_sql_identifiant_suffix_5_expr('p') . ' = :suffix5';
                $params['suffix5'] = $tr;
            } elseif (produits_has_column('identifiant_interne') && preg_match('/^FPL\d{6}$/i', $tr)) {
                $conditions[] = 'UPPER(TRIM(p.identifiant_interne)) = :ident_exact';
                $params['ident_exact'] = strtoupper($tr);
            } else {
                $conditions[] = '(p.nom LIKE :term OR p.description LIKE :term)';
                $params['term'] = '%' . $tr . '%';
            }
        }

        if ($prix_min !== null && $prix_min !== '') {
            $prix_min = (float) $prix_min;
            $conditions[] = "(CASE WHEN p.prix_promotion IS NOT NULL AND p.prix_promotion > 0 AND p.prix_promotion < p.prix THEN p.prix_promotion ELSE p.prix END) >= :prix_min";
            $params['prix_min'] = $prix_min;
        }

        if ($prix_max !== null && $prix_max !== '') {
            $prix_max = (float) $prix_max;
            $conditions[] = "(CASE WHEN p.prix_promotion IS NOT NULL AND p.prix_promotion > 0 AND p.prix_promotion < p.prix THEN p.prix_promotion ELSE p.prix END) <= :prix_max";
            $params['prix_max'] = $prix_max;
        }

        if ($categorie_id !== null && $categorie_id !== '') {
            $categorie_id = (int) $categorie_id;
            $conditions[] = "p.categorie_id = :categorie_id";
            $params['categorie_id'] = $categorie_id;
        }

        $order = "p.date_creation DESC";
        if ($tri === 'prix_asc') {
            $order = "(CASE WHEN p.prix_promotion IS NOT NULL AND p.prix_promotion > 0 AND p.prix_promotion < p.prix THEN p.prix_promotion ELSE p.prix END) ASC";
        } elseif ($tri === 'prix_desc') {
            $order = "(CASE WHEN p.prix_promotion IS NOT NULL AND p.prix_promotion > 0 AND p.prix_promotion < p.prix THEN p.prix_promotion ELSE p.prix END) DESC";
        } elseif ($tri === 'nom') {
            $order = "p.nom ASC";
        }

        $where = implode(' AND ', $conditions);
        $params['limit'] = $limit;
        $params['offset'] = $offset;

        $stmt = $db->prepare("
            SELECT p.*, c.nom as categorie_nom 
            FROM produits p 
            LEFT JOIN categories c ON p.categorie_id = c.id 
            WHERE $where
            ORDER BY $order
            LIMIT :limit OFFSET :offset
        ");
        foreach ($params as $k => $v) {
            if ($k === 'limit' || $k === 'offset') {
                $stmt->bindValue(':' . $k, $v, PDO::PARAM_INT);
            } else {
                $stmt->bindValue(':' . $k, $v);
            }
        }
        $stmt->execute();
        $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $produits ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Compte les produits avec les mêmes filtres que search_produits_with_filters
 */
function count_search_produits_with_filters($recherche = '', $prix_min = null, $prix_max = null, $categorie_id = null)
{
    global $db;

    try {
        $conditions = ["statut = 'actif'"];
        $params = [];

        if (!empty(trim($recherche))) {
            $tr = trim($recherche);
            if (produits_has_column('identifiant_interne') && preg_match('/^\d{5}$/', $tr)) {
                $conditions[] = 'identifiant_interne IS NOT NULL AND TRIM(identifiant_interne) != \'\' AND ' . produits_sql_identifiant_suffix_5_expr('') . ' = :suffix5';
                $params['suffix5'] = $tr;
            } elseif (produits_has_column('identifiant_interne') && preg_match('/^FPL\d{6}$/i', $tr)) {
                $conditions[] = 'UPPER(TRIM(identifiant_interne)) = :ident_exact';
                $params['ident_exact'] = strtoupper($tr);
            } else {
                $conditions[] = '(nom LIKE :term OR description LIKE :term)';
                $params['term'] = '%' . $tr . '%';
            }
        }

        if ($prix_min !== null && $prix_min !== '') {
            $prix_min = (float) $prix_min;
            $conditions[] = "(CASE WHEN prix_promotion IS NOT NULL AND prix_promotion > 0 AND prix_promotion < prix THEN prix_promotion ELSE prix END) >= :prix_min";
            $params['prix_min'] = $prix_min;
        }

        if ($prix_max !== null && $prix_max !== '') {
            $prix_max = (float) $prix_max;
            $conditions[] = "(CASE WHEN prix_promotion IS NOT NULL AND prix_promotion > 0 AND prix_promotion < prix THEN prix_promotion ELSE prix END) <= :prix_max";
            $params['prix_max'] = $prix_max;
        }

        if ($categorie_id !== null && $categorie_id !== '') {
            $categorie_id = (int) $categorie_id;
            $conditions[] = "categorie_id = :categorie_id";
            $params['categorie_id'] = $categorie_id;
        }

        $where = implode(' AND ', $conditions);
        $stmt = $db->prepare("SELECT COUNT(*) FROM produits WHERE $where");
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Compte le nombre total de produits actifs
 * @return int Nombre total de produits actifs
 */
function count_all_produits_actifs()
{
    global $db;

    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM produits WHERE statut = 'actif'");
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Récupère les produits en promotion (prix_promotion défini et inférieur au prix)
 * @param int $offset Décalage pour pagination
 * @param int $limit Nombre maximum de produits à retourner
 * @return array Tableau des produits en promo
 */
function get_produits_en_promo($offset = 0, $limit = 50)
{
    global $db;

    try {
        $stmt = $db->prepare("
            SELECT p.*, c.nom as categorie_nom 
            FROM produits p 
            LEFT JOIN categories c ON p.categorie_id = c.id 
            WHERE p.statut = 'actif' 
            AND p.prix_promotion IS NOT NULL 
            AND p.prix_promotion > 0 
            AND p.prix_promotion < p.prix
            ORDER BY (p.prix - p.prix_promotion) DESC, p.date_creation DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $produits ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Compte les produits en promotion
 * @return int Nombre de produits en promo
 */
function count_produits_en_promo()
{
    global $db;

    try {
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM produits 
            WHERE statut = 'actif' 
            AND prix_promotion IS NOT NULL 
            AND prix_promotion > 0 
            AND prix_promotion < prix
        ");
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Récupère les produits les plus récents (nouveautés)
 * @param int $limit Nombre maximum de produits à retourner (par défaut 4)
 * @return array Tableau des produits les plus récents
 */
function get_produits_nouveautes($limit = 4)
{
    global $db;

    try {
        $stmt = $db->prepare("
            SELECT p.*, c.nom as categorie_nom 
            FROM produits p 
            LEFT JOIN categories c ON p.categorie_id = c.id 
            WHERE p.statut = 'actif'
            ORDER BY p.date_creation DESC, p.date_modification DESC
            LIMIT :limit
        ");

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $produits ? $produits : [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Récupère tous les produits nouveautés avec pagination
 * @param int $offset Décalage pour pagination
 * @param int $limit Nombre maximum de produits à retourner
 * @return array Tableau des produits les plus récents
 */
function get_produits_nouveautes_paginated($offset = 0, $limit = 20)
{
    global $db;

    try {
        $stmt = $db->prepare("
            SELECT p.*, c.nom as categorie_nom 
            FROM produits p 
            LEFT JOIN categories c ON p.categorie_id = c.id 
            WHERE p.statut = 'actif'
            ORDER BY p.date_creation DESC, p.date_modification DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $produits ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Récupère les produits vedettes (les plus ajoutés au panier et les plus commandés)
 * @param int $limit Nombre maximum de produits à retourner
 * @return array Tableau des produits vedettes mélangés aléatoirement
 */
function get_produits_vedettes($limit = 20)
{
    global $db;

    try {
        // Récupérer les produits les plus ajoutés au panier et les plus commandés
        $stmt = $db->prepare("
            SELECT DISTINCT
                p.*,
                c.nom as categorie_nom,
                COALESCE(panier_stats.nb_ajouts_panier, 0) as nb_ajouts_panier,
                COALESCE(commande_stats.nb_commandes, 0) as nb_commandes,
                (COALESCE(panier_stats.nb_ajouts_panier, 0) + COALESCE(commande_stats.nb_commandes, 0)) as score_popularite
            FROM produits p
            LEFT JOIN categories c ON p.categorie_id = c.id
            LEFT JOIN (
                SELECT produit_id, COUNT(*) as nb_ajouts_panier
                FROM panier
                GROUP BY produit_id
            ) panier_stats ON p.id = panier_stats.produit_id
            LEFT JOIN (
                SELECT produit_id, COUNT(*) as nb_commandes
                FROM commande_produits
                GROUP BY produit_id
            ) commande_stats ON p.id = commande_stats.produit_id
            WHERE p.statut = 'actif'
            HAVING score_popularite > 0
            ORDER BY score_popularite DESC, p.date_creation DESC
            LIMIT :limit
        ");

        $stmt->bindValue(':limit', $limit * 2, PDO::PARAM_INT); // Récupérer plus pour avoir de la variété
        $stmt->execute();
        $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Si aucun produit vedette (pas encore de statistiques), récupérer tous les produits actifs
        if (empty($produits)) {
            $produits = get_all_produits('actif');
        }

        // Mélanger aléatoirement les produits à chaque appel
        if (!empty($produits)) {
            // Utiliser une graine basée sur le temps pour varier l'ordre
            mt_srand(time() + (int) (microtime(true) * 1000000));
            shuffle($produits);
            // Limiter au nombre demandé après le mélange
            $produits = array_slice($produits, 0, $limit);
        }

        return $produits ? $produits : [];
    } catch (PDOException $e) {
        // En cas d'erreur, retourner tous les produits actifs mélangés
        $produits = get_all_produits('actif');
        if (!empty($produits)) {
            mt_srand(time() + (int) (microtime(true) * 1000000));
            shuffle($produits);
            $produits = array_slice($produits, 0, $limit);
        }
        return $produits ? $produits : [];
    }
}

/**
 * Crée un nouveau produit
 * @param array $data Les données du produit
 * @return int|false L'ID du produit créé ou False en cas d'erreur
 */
function create_produit($data)
{
    global $db;

    try {
        $cols = "nom, description, prix, prix_promotion, stock, categorie_id, image_principale, images, poids, unite, date_creation, statut";
        $vals = ":nom, :description, :prix, :prix_promotion, :stock, :categorie_id, :image_principale, :images, :poids, :unite, NOW(), :statut";
        $params = [
            'nom' => $data['nom'],
            'description' => $data['description'],
            'prix' => $data['prix'],
            'prix_promotion' => $data['prix_promotion'] ?? null,
            'stock' => $data['stock'],
            'categorie_id' => $data['categorie_id'],
            'image_principale' => $data['image_principale'] ?? null,
            'images' => $data['images'] ?? null,
            'poids' => $data['poids'] ?? null,
            'unite' => $data['unite'] ?? 'unité',
            'statut' => $data['statut'] ?? 'actif'
        ];
        if (produits_has_column('identifiant_interne')) {
            $ident = isset($data['identifiant_interne']) && $data['identifiant_interne'] !== ''
                ? $data['identifiant_interne']
                : generate_next_identifiant_interne_produit();
            if ($ident) {
                $cols = "identifiant_interne, " . $cols;
                $vals = ":identifiant_interne, " . $vals;
                $params['identifiant_interne'] = $ident;
            }
        }
        if (produits_has_column('etage')) {
            $cols .= ", etage";
            $vals .= ", :etage";
            $params['etage'] = isset($data['etage']) && $data['etage'] !== '' ? trim($data['etage']) : null;
        }
        if (produits_has_column('numero_rayon')) {
            $cols .= ", numero_rayon";
            $vals .= ", :numero_rayon";
            $params['numero_rayon'] = isset($data['numero_rayon']) && $data['numero_rayon'] !== '' ? trim($data['numero_rayon']) : null;
        }
        $with_extras = isset($data['couleurs']) || isset($data['taille']);
        if ($with_extras) {
            $cols .= ", couleurs, taille";
            $vals .= ", :couleurs, :taille";
            $params['couleurs'] = $data['couleurs'] ?? null;
            $params['taille'] = $data['taille'] ?? null;
        }
        try {
            $stmt = $db->prepare("INSERT INTO produits ($cols) VALUES ($vals)");
            $result = $stmt->execute($params);
        } catch (PDOException $e) {
            if ($with_extras && (strpos($e->getMessage(), 'couleurs') !== false || strpos($e->getMessage(), 'taille') !== false)) {
                $cols = "nom, description, prix, prix_promotion, stock, categorie_id, image_principale, images, poids, unite, date_creation, statut";
                $vals = ":nom, :description, :prix, :prix_promotion, :stock, :categorie_id, :image_principale, :images, :poids, :unite, NOW(), :statut";
                unset($params['couleurs'], $params['taille']);
                $stmt = $db->prepare("INSERT INTO produits ($cols) VALUES ($vals)");
                $result = $stmt->execute($params);
            } else {
                throw $e;
            }
        }

        if ($result) {
            return $db->lastInsertId();
        }

        return false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Met à jour un produit
 * @param int $id L'ID du produit
 * @param array $data Les nouvelles données du produit
 * @return bool True en cas de succès, False sinon
 */
function update_produit($id, $data)
{
    global $db;

    try {
        $sets = "nom = :nom, description = :description, prix = :prix, prix_promotion = :prix_promotion, stock = :stock, categorie_id = :categorie_id, image_principale = :image_principale, images = :images, poids = :poids, unite = :unite, statut = :statut, date_modification = NOW()";
        $params = [
            'id' => $id,
            'nom' => $data['nom'],
            'description' => $data['description'],
            'prix' => $data['prix'],
            'prix_promotion' => $data['prix_promotion'] ?? null,
            'stock' => $data['stock'],
            'categorie_id' => $data['categorie_id'],
            'image_principale' => $data['image_principale'] ?? null,
            'images' => $data['images'] ?? null,
            'poids' => $data['poids'] ?? null,
            'unite' => $data['unite'] ?? 'unité',
            'statut' => $data['statut'] ?? 'actif'
        ];
        if (produits_has_column('etage')) {
            $sets .= ", etage = :etage";
            $params['etage'] = isset($data['etage']) && $data['etage'] !== '' ? trim($data['etage']) : null;
        }
        if (produits_has_column('numero_rayon')) {
            $sets .= ", numero_rayon = :numero_rayon";
            $params['numero_rayon'] = isset($data['numero_rayon']) && $data['numero_rayon'] !== '' ? trim($data['numero_rayon']) : null;
        }
        $with_extras = isset($data['couleurs']) || isset($data['taille']);
        if ($with_extras) {
            $sets .= ", couleurs = :couleurs, taille = :taille";
            $params['couleurs'] = $data['couleurs'] ?? null;
            $params['taille'] = $data['taille'] ?? null;
        }
        try {
            $stmt = $db->prepare("UPDATE produits SET $sets WHERE id = :id");
            return $stmt->execute($params);
        } catch (PDOException $e) {
            if ($with_extras && (strpos($e->getMessage(), 'couleurs') !== false || strpos($e->getMessage(), 'taille') !== false)) {
                $sets = "nom = :nom, description = :description, prix = :prix, prix_promotion = :prix_promotion, stock = :stock, categorie_id = :categorie_id, image_principale = :image_principale, images = :images, poids = :poids, unite = :unite, statut = :statut, date_modification = NOW()";
                unset($params['couleurs'], $params['taille']);
                $stmt = $db->prepare("UPDATE produits SET $sets WHERE id = :id");
                return $stmt->execute($params);
            }
            throw $e;
        }
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Supprime un produit
 * @param int $id L'ID du produit
 * @return bool True en cas de succès, False sinon
 */
function delete_produit($id)
{
    global $db;

    try {
        $stmt = $db->prepare("DELETE FROM produits WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Met à jour le statut d'un produit
 * @param int $id L'ID du produit
 * @param string $statut Le nouveau statut
 * @return bool True en cas de succès, False sinon
 */
function update_produit_statut($id, $statut)
{
    global $db;

    try {
        $stmt = $db->prepare("UPDATE produits SET statut = :statut, date_modification = NOW() WHERE id = :id");
        return $stmt->execute(['id' => $id, 'statut' => $statut]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Parse poids ou taille avec surcoûts (JSON ou comma-separated)
 * @param string|null $raw Valeur brute (JSON [{"v":"500g","s":300}] ou "500g, 1kg")
 * @return array [["v"=>"500g","s"=>0], ["v"=>"1kg","s"=>300]]
 */
function parse_options_with_surcharge($raw)
{
    if (empty(trim($raw ?? '')))
        return [];
    $raw = trim($raw);
    if ($raw === '[]' || $raw === '[ ]' || strtolower($raw) === 'null') {
        return [];
    }
    $dec = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        if (!is_array($dec) || empty($dec)) {
            return [];
        }
        $out = [];
        foreach ($dec as $item) {
            if (is_array($item) && isset($item['v']) && trim((string) $item['v']) !== '') {
                $out[] = ['v' => trim($item['v']), 's' => isset($item['s']) ? (float) $item['s'] : 0];
            } elseif (is_string($item) && trim($item) !== '') {
                $out[] = ['v' => trim($item), 's' => 0];
            }
        }
        return $out;
    }
    $arr = array_map('trim', array_filter(explode(',', $raw)));
    $arr = array_values(array_filter($arr, function ($x) {
        $v = trim((string) $x);
        return $v !== '' && $v !== '[]' && $v !== '[ ]' && strtolower($v) !== 'null';
    }));
    return array_map(function ($x) {
        return ['v' => $x, 's' => 0]; }, $arr);
}

/**
 * Récupère le surcoût pour une option (poids ou taille)
 * @param array $options Résultat de parse_options_with_surcharge
 * @param string $value Valeur sélectionnée (ex: "1kg")
 * @return float Surcoût en FCFA
 */
function get_surcharge_for_option($options, $value)
{
    if (empty($value))
        return 0;
    foreach ($options as $opt) {
        if (trim($opt['v']) === trim($value)) {
            return (float) ($opt['s'] ?? 0);
        }
    }
    return 0;
}

/**
 * Décrémente le stock d'un produit (produits.stock)
 * @param int $produit_id ID du produit
 * @param int $quantite Quantité à soustraire
 * @return int|false Nouvelle quantité ou False en cas d'erreur
 */
function decrement_produit_stock($produit_id, $quantite)
{
    global $db;

    try {
        $stmt = $db->prepare("UPDATE produits SET stock = GREATEST(0, stock - :qty), date_modification = NOW() WHERE id = :id");
        $stmt->execute(['id' => (int) $produit_id, 'qty' => (int) $quantite]);
        $stmt = $db->prepare("SELECT stock FROM produits WHERE id = :id");
        $stmt->execute(['id' => (int) $produit_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int) $row['stock'] : false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Recherche des produits en stock pour commande manuelle
 * @param string $recherche Terme de recherche (nom produit ou catégorie)
 * @param int $limit Nombre max de résultats
 * @return array Produits avec stock > 0
 */
function search_produits_en_stock_commande_manuelle($recherche = '', $limit = 30)
{
    global $db;

    try {
        $sql = "
            SELECT p.id, p.nom, p.prix, p.prix_promotion, p.stock, p.image_principale,
                   c.nom as categorie_nom,
                   p.stock as stock_dispo
            FROM produits p
            LEFT JOIN categories c ON p.categorie_id = c.id
            WHERE p.statut = 'actif' AND p.stock > 0
        ";
        $params = ['limit' => (int) $limit];

        if (!empty(trim($recherche))) {
            $sql .= " AND (p.nom LIKE :term OR c.nom LIKE :term2)";
            $params['term'] = '%' . trim($recherche) . '%';
            $params['term2'] = '%' . trim($recherche) . '%';
        }

        $sql .= " ORDER BY p.nom ASC LIMIT :limit";
        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * @deprecated Table stock_articles supprimée. Retourne toujours [].
 * Le stock est géré uniquement par produits.stock.
 */
function get_produits_by_stock_article($stock_article_id)
{
    return [];
}

?>