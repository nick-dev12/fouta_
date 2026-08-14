<?php
/**
 * Modèle pour la gestion des produits
 * Programmation procédurale uniquement
 */

// Inclusion du fichier de connexion à la BDD
require_once __DIR__ . '/../conn/conn.php';

/**
 * Applique le masquage des champs produit selon le rôle admin (espace admin uniquement).
 *
 * @param array<string, mixed>|false $produit
 * @return array<string, mixed>|false
 */
function produits_appliquer_filtre_acces_champs($produit)
{
    if (!is_array($produit)) {
        return $produit;
    }
    require_once __DIR__ . '/model_produit_formulaire_champs.php';

    return produit_formulaire_filtrer_produit_acces($produit);
}

/**
 * @param array<int, array<string, mixed>>|false $produits
 * @return array<int, array<string, mixed>>|false
 */
function produits_appliquer_filtre_acces_liste($produits)
{
    if (!is_array($produits)) {
        return $produits;
    }
    require_once __DIR__ . '/model_produit_formulaire_champs.php';

    return produit_formulaire_filtrer_produits_liste_acces($produits);
}

/**
 * Indique si une colonne existe sur la table produits (cache SHOW COLUMNS)
 */
function produits_has_column($name)
{
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
 * Valeur d’une colonne résultat SQL insensible à la casse du nom (PDO / MySQL).
 *
 * @param array<string, mixed> $row
 * @return mixed|null
 */
function produits_assoc_ci(array $row, $key)
{
    $key = (string) $key;
    if (array_key_exists($key, $row)) {
        return $row[$key];
    }
    $lk = strtolower($key);
    foreach ($row as $k => $v) {
        if (strtolower((string) $k) === $lk) {
            return $v;
        }
    }

    return null;
}

/**
 * URLs publiques /upload/... pour les images produit (JSON images ou image principale)
 *
 * @param array $p Ligne produit (champs image_principale, images)
 * @return array<int, string>
 */
function produits_galerie_web_urls($p)
{
    $galerie = [];
    if (!empty($p['images'])) {
        $dec = json_decode((string) $p['images'], true);
        if (is_array($dec)) {
            foreach ($dec as $one) {
                $one = trim((string) $one);
                if ($one !== '') {
                    $galerie[] = $one;
                }
            }
        }
    }
    if (empty($galerie) && !empty($p['image_principale'])) {
        $galerie = [trim((string) $p['image_principale'])];
    }
    $out = [];
    foreach ($galerie as $rel) {
        $rel = str_replace('\\', '/', $rel);
        if ($rel === '') {
            continue;
        }
        $out[] = '/upload/' . ltrim($rel, '/');
    }
    return $out;
}

/**
 * Extrait un extrait lisible de la description (HTML retiré).
 *
 * @param string|null $description
 * @param int $max_len
 * @return string
 */
function produits_description_excerpt($description, $max_len = 30)
{
    $raw = (string) $description;
    if ($raw === '') {
        return '';
    }
    $t = trim(strip_tags(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    if ($t === '') {
        return '';
    }
    $max_len = max(1, (int) $max_len);
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($t, 'UTF-8') <= $max_len) {
            return $t;
        }

        return rtrim(mb_substr($t, 0, $max_len, 'UTF-8')) . '…';
    }
    if (strlen($t) <= $max_len) {
        return $t;
    }

    return substr($t, 0, $max_len) . '…';
}

/**
 * Description produit en texte brut (HTML / entités retirés) pour la recherche admin.
 *
 * @param string|null $description
 * @return string
 */
function produits_description_plain_text($description)
{
    $raw = (string) $description;
    if ($raw === '') {
        return '';
    }
    $t = trim(strip_tags(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    if ($t === '') {
        return '';
    }

    return preg_replace('/\s+/u', ' ', $t);
}

/**
 * Normalise une chaîne pour comparaison insensible à la casse (recherche admin).
 *
 * @param string $text
 * @return string
 */
function produits_recherche_normalize($text)
{
    $t = function_exists('mb_strtolower') ? mb_strtolower((string) $text) : strtolower((string) $text);

    return trim(preg_replace('/\s+/u', ' ', $t));
}

/**
 * Indique si un produit correspond à la recherche de la liste admin (nom, description, codes…).
 *
 * @param array<string, mixed> $produit
 * @param string $recherche
 * @return bool
 */
function produit_admin_liste_match_recherche(array $produit, $recherche)
{
    $recherche = trim((string) $recherche);
    if ($recherche === '') {
        return true;
    }

    if (preg_match('/^FPL(\d{6}|\d{9})$/i', $recherche)) {
        $code = strtoupper($recherche);
        $ident = strtoupper(trim((string) ($produit['identifiant_interne'] ?? '')));

        return $ident !== '' && $ident === $code;
    }

    if (preg_match('/^\d{5}$/', $recherche)) {
        $ident = $produit['identifiant_interne'] ?? '';

        return produit_identifiant_derniers_5_chiffres($ident) === $recherche;
    }

    $haystacks = produit_admin_liste_search_champs($produit);

    $needle = produits_recherche_normalize($recherche);

    foreach ($haystacks as $value) {
        $value = produits_recherche_normalize((string) $value);
        if ($value !== '' && strpos($value, $needle) !== false) {
            return true;
        }
    }

    $combined = produits_recherche_normalize(implode(' ', $haystacks));
    if ($combined !== '' && strpos($combined, $needle) !== false) {
        return true;
    }

    $tokens = preg_split('/\s+/u', $needle, -1, PREG_SPLIT_NO_EMPTY);
    if (count($tokens) > 1 && $combined !== '') {
        foreach ($tokens as $token) {
            if ($token === '') {
                continue;
            }
            $token_len = function_exists('mb_strlen') ? mb_strlen($token, 'UTF-8') : strlen($token);
            if ($token_len < 2 && !preg_match('/^\d+$/', $token)) {
                continue;
            }
            if (strpos($combined, $token) === false) {
                return false;
            }
        }

        return true;
    }

    return false;
}

/**
 * Champs textuels indexables pour la recherche admin (liste produits).
 *
 * @param array<string, mixed> $produit
 * @return array<int, string>
 */
function produit_admin_liste_search_champs(array $produit)
{
    $champs = [
        (string) ($produit['nom'] ?? ''),
        produits_description_plain_text($produit['description'] ?? ''),
        (string) ($produit['categorie_nom'] ?? ''),
        produits_marque_libelle_from_row($produit),
        (string) ($produit['statut'] ?? ''),
        (string) ($produit['identifiant_interne'] ?? ''),
        (string) ($produit['poids'] ?? ''),
        (string) ($produit['unite'] ?? ''),
    ];

    if (function_exists('produits_fournisseur_nom_affichage')) {
        $four = trim(produits_fournisseur_nom_affichage($produit));
        if ($four !== '') {
            $champs[] = $four;
        }
    }

    if (function_exists('produits_has_column')) {
        if (produits_has_column('reference_fournisseur')) {
            $champs[] = (string) ($produit['reference_fournisseur'] ?? '');
        }
        if (produits_has_column('nom_fournisseur')) {
            $champs[] = (string) ($produit['nom_fournisseur'] ?? '');
        }
        if (produits_has_column('etage')) {
            $champs[] = (string) ($produit['etage'] ?? '');
        }
        if (produits_has_column('numero_rayon')) {
            $champs[] = (string) ($produit['numero_rayon'] ?? '');
        }
        if (produits_has_column('allee')) {
            $champs[] = (string) ($produit['allee'] ?? '');
        }
        if (produits_has_column('zone_emplacement')) {
            $champs[] = (string) ($produit['zone_emplacement'] ?? '');
        }
        if (produits_has_column('position_emplacement')) {
            $champs[] = (string) ($produit['position_emplacement'] ?? '');
        }
        if (produits_has_column('barre_rayon')) {
            $champs[] = (string) ($produit['barre_rayon'] ?? '');
        }
        if (produits_has_column('couleurs')) {
            $champs[] = (string) ($produit['couleurs'] ?? '');
        }
        if (produits_has_column('taille')) {
            $champs[] = (string) ($produit['taille'] ?? '');
        }
    }

    return array_values(array_filter($champs, function ($value) {
        return trim((string) $value) !== '';
    }));
}

/**
 * Texte normalisé pour data-search / filtrage live (liste admin produits).
 *
 * @param array<string, mixed> $produit
 * @return string
 */
function produit_admin_liste_search_blob(array $produit)
{
    return produits_recherche_normalize(implode(' ', produit_admin_liste_search_champs($produit)));
}

/**
 * Applique tous les filtres de la liste admin produits (catégorie, marque, fournisseur, recherche).
 *
 * @param array<string, mixed> $produit
 * @param string $recherche
 * @param int $categorie_id
 * @param int $marque_id
 * @param int $fournisseur_id
 * @return bool
 */
function produit_admin_liste_pass_filtres(array $produit, $recherche, $categorie_id = 0, $marque_id = 0, $fournisseur_id = 0)
{
    if ($categorie_id > 0 && (int) ($produit['categorie_id'] ?? 0) !== $categorie_id) {
        return false;
    }

    if ($marque_id > 0) {
        if (!produits_has_column('marque_id')) {
            return false;
        }
        if ((int) ($produit['marque_id'] ?? 0) !== $marque_id) {
            return false;
        }
    }

    if ($fournisseur_id > 0) {
        if (!produits_has_column('fournisseur_id')) {
            return false;
        }
        if ((int) ($produit['fournisseur_id'] ?? 0) !== $fournisseur_id) {
            return false;
        }
    }

    return produit_admin_liste_match_recherche($produit, $recherche);
}

/** Nombre de produits par page — liste admin catalogue */
if (!defined('ADMIN_PRODUITS_LISTE_PER_PAGE')) {
    define('ADMIN_PRODUITS_LISTE_PER_PAGE', 30);
}

/** Limite résultats recherche live admin (AJAX) */
if (!defined('ADMIN_PRODUITS_LIVE_SEARCH_LIMIT')) {
    define('ADMIN_PRODUITS_LIVE_SEARCH_LIMIT', 60);
}

/**
 * Clause SQL AND pour filtres liste admin (catégorie, marque, fournisseur).
 *
 * @param int $categorie_id
 * @param int $marque_id
 * @param int $fournisseur_id
 * @param array<string, int> $params
 * @return string
 */
function admin_produits_liste_filtres_sql($categorie_id = 0, $marque_id = 0, $fournisseur_id = 0, array &$params = [])
{
    $parts = [];

    if ($categorie_id > 0) {
        $parts[] = 'p.categorie_id = :adm_cat_id';
        $params['adm_cat_id'] = $categorie_id;
    }

    if ($marque_id > 0 && produits_has_column('marque_id')) {
        $parts[] = 'p.marque_id = :adm_marque_id';
        $params['adm_marque_id'] = $marque_id;
    }

    if ($fournisseur_id > 0 && produits_has_column('fournisseur_id')) {
        $parts[] = 'p.fournisseur_id = :adm_fournisseur_id';
        $params['adm_fournisseur_id'] = $fournisseur_id;
    }

    if (empty($parts)) {
        return '';
    }

    return ' AND ' . implode(' AND ', $parts);
}

/**
 * Conditions SQL OR pour recherche texte (liste admin).
 *
 * @param string $recherche
 * @param array<string, string> $params
 * @return string Chaîne « AND ( … ) » ou vide
 */
function admin_produits_liste_recherche_sql($recherche, array &$params = [])
{
    $tr = trim((string) $recherche);
    if ($tr === '') {
        return '';
    }

    $or = [];
    $or[] = 'p.nom LIKE :adm_st_nom';
    $params['adm_st_nom'] = '%' . $tr . '%';
    $or[] = 'c.nom LIKE :adm_st_cat';
    $params['adm_st_cat'] = '%' . $tr . '%';

    if (produits_has_column('description')) {
        $or[] = 'p.description LIKE :adm_st_desc';
        $params['adm_st_desc'] = '%' . $tr . '%';
    }
    if (produits_has_column('reference_fournisseur')) {
        $or[] = 'p.reference_fournisseur LIKE :adm_st_rf';
        $params['adm_st_rf'] = '%' . $tr . '%';
    }
    if (produits_has_column('nom_fournisseur')) {
        $or[] = 'p.nom_fournisseur LIKE :adm_st_nf';
        $params['adm_st_nf'] = '%' . $tr . '%';
    }
    if (produits_has_column('fournisseur_id')) {
        $or[] = 'f.nom LIKE :adm_st_fourn';
        $params['adm_st_fourn'] = '%' . $tr . '%';
    }
    if (produits_has_column('marque_id') && function_exists('marques_table_ok') && marques_table_ok()) {
        $or[] = 'EXISTS (SELECT 1 FROM marques adm_mx WHERE adm_mx.id = p.marque_id AND adm_mx.nom LIKE :adm_st_marque)';
        $params['adm_st_marque'] = '%' . $tr . '%';
    }
    if (produits_has_column('identifiant_interne')) {
        if (preg_match('/^FPL(\d{6}|\d{9})$/i', $tr)) {
            $or[] = 'UPPER(TRIM(p.identifiant_interne)) = :adm_st_ident_ex';
            $params['adm_st_ident_ex'] = strtoupper($tr);
        } elseif (preg_match('/^\d{5}$/', $tr)) {
            $or[] = 'p.identifiant_interne IS NOT NULL AND TRIM(p.identifiant_interne) != \'\' AND ' . produits_sql_identifiant_suffix_5_expr('p') . ' = :adm_st_suf5';
            $params['adm_st_suf5'] = $tr;
        } else {
            $or[] = '(p.identifiant_interne IS NOT NULL AND TRIM(p.identifiant_interne) != \'\' AND p.identifiant_interne LIKE :adm_st_idlike)';
            $params['adm_st_idlike'] = '%' . $tr . '%';
        }
    }

    return ' AND (' . implode(' OR ', $or) . ')';
}

/**
 * Nombre total de produits (liste admin, filtres select uniquement).
 *
 * @param int $categorie_id
 * @param int $marque_id
 * @param int $fournisseur_id
 * @return int
 */
function count_admin_produits_liste($categorie_id = 0, $marque_id = 0, $fournisseur_id = 0)
{
    global $db;

    try {
        $jb = produits_catalog_join_bundle();
        $joinx = $jb['join'];
        $params = [];
        $sql = "
            SELECT COUNT(*) AS cnt
            FROM produits p
            LEFT JOIN categories c ON p.categorie_id = c.id
            $joinx
            WHERE 1=1
        ";
        $sql .= admin_produits_liste_filtres_sql($categorie_id, $marque_id, $fournisseur_id, $params);

        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int) ($row['cnt'] ?? 0);
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Produits paginés pour la liste admin (sans recherche texte — pagination serveur).
 *
 * @param int $categorie_id
 * @param int $marque_id
 * @param int $fournisseur_id
 * @param int $offset
 * @param int $limit
 * @return array<int, array<string, mixed>>
 */
function get_admin_produits_liste_paginated($categorie_id = 0, $marque_id = 0, $fournisseur_id = 0, $offset = 0, $limit = 30)
{
    global $db;

    try {
        $jb = produits_catalog_join_bundle();
        $selx = $jb['sel'];
        $joinx = $jb['join'];
        $offset = max(0, (int) $offset);
        $limit = max(1, min(100, (int) $limit));
        $params = [];

        $sql = "
            SELECT p.*, c.nom AS categorie_nom $selx
            FROM produits p
            LEFT JOIN categories c ON p.categorie_id = c.id
            $joinx
            WHERE 1=1
        ";
        $sql .= admin_produits_liste_filtres_sql($categorie_id, $marque_id, $fournisseur_id, $params);
        $sql .= ' ORDER BY p.date_creation DESC LIMIT :adm_limit OFFSET :adm_offset';

        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':adm_limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':adm_offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return produits_appliquer_filtre_acces_liste($rows ? $rows : []);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Recherche live admin (AJAX) — tous statuts, limite stricte.
 *
 * @param string $recherche
 * @param int $categorie_id
 * @param int $marque_id
 * @param int $fournisseur_id
 * @param int $limit
 * @param int $offset
 * @return array{items: array<int, array<string, mixed>>, total: int, truncated: bool, offset: int, limit: int}
 */
function search_admin_produits_liste_live($recherche = '', $categorie_id = 0, $marque_id = 0, $fournisseur_id = 0, $limit = 60, $offset = 0)
{
    global $db;

    $recherche = trim((string) $recherche);
    if ($recherche === '') {
        return ['items' => [], 'total' => 0, 'truncated' => false, 'offset' => 0, 'limit' => 0];
    }

    $limit = max(5, min(ADMIN_PRODUITS_LIVE_SEARCH_LIMIT, (int) $limit));
    $offset = max(0, (int) $offset);

    try {
        $jb = produits_catalog_join_bundle();
        $selx = $jb['sel'];
        $joinx = $jb['join'];
        $params = [];

        $baseFrom = "
            FROM produits p
            LEFT JOIN categories c ON p.categorie_id = c.id
            $joinx
            WHERE 1=1
        ";
        $baseFrom .= admin_produits_liste_filtres_sql($categorie_id, $marque_id, $fournisseur_id, $params);
        $baseFrom .= admin_produits_liste_recherche_sql($recherche, $params);

        $countSql = 'SELECT COUNT(*) AS cnt ' . $baseFrom;
        $stmtCount = $db->prepare($countSql);
        foreach ($params as $k => $v) {
            $stmtCount->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmtCount->execute();
        $total = (int) ($stmtCount->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);

        $sql = 'SELECT p.*, c.nom AS categorie_nom ' . $selx . $baseFrom . ' ORDER BY p.nom ASC LIMIT :adm_live_limit OFFSET :adm_live_offset';
        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':adm_live_limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':adm_live_offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $items = produits_appliquer_filtre_acces_liste($items);
        $shown = count($items);

        return [
            'items' => $items,
            'total' => $total,
            'truncated' => ($offset + $shown) < $total,
            'offset' => $offset,
            'limit' => $limit,
        ];
    } catch (PDOException $e) {
        return ['items' => [], 'total' => 0, 'truncated' => false, 'offset' => 0, 'limit' => 0];
    }
}

/**
 * Libellé marque pour une ligne produit (jointure marque_nom ou lookup marque_id).
 *
 * @param array<string, mixed> $row
 * @return string
 */
function produits_marque_libelle_from_row(array $row)
{
    $raw = produits_assoc_ci($row, 'marque_libelle_catalogue');
    if ($raw === null) {
        $raw = produits_assoc_ci($row, 'pcn_marque_join_nom');
    }
    if ($raw === null) {
        $raw = produits_assoc_ci($row, 'marque_nom');
    }
    $t = trim((string) ($raw ?? ''));
    if ($t !== '') {
        return $t;
    }
    $mid = (int) (produits_assoc_ci($row, 'marque_id') ?? 0);
    if ($mid <= 0) {
        return '';
    }
    if (!function_exists('produits_has_column') || !produits_has_column('marque_id')) {
        return '';
    }
    static $id_to_nom = false;
    if ($id_to_nom === false) {
        $id_to_nom = [];
        // Charger le modèle marques si disponible (nécessaire pour marques_table_ok())
        $marquesPhp = __DIR__ . '/model_marques.php';
        if (file_exists($marquesPhp)) {
            require_once $marquesPhp;
        }
        if (function_exists('marques_table_ok') && marques_table_ok()) {
            foreach (get_all_marques_ordered_by_nom() as $m) {
                $id_to_nom[(int) $m['id']] = trim((string) ($m['nom'] ?? ''));
            }
        }
    }

    return $id_to_nom[$mid] ?? '';
}

/**
 * Fragment HTML pour le titre de carte produit : ordre fixe 1) nom, 2) marque liée, 3) extrait de description.
 *
 * @param array<string, mixed> $produit nom, marque_nom, description (clés optionnelles)
 * @param int $desc_max_len Longueur max de l'extrait description
 * @param string|null $nom_override Remplace le nom principal (ex. nom + variante)
 * @return string HTML interne à placer dans &lt;h3 class="produit-card-nom"&gt;
 */
function produits_card_heading_inner_html(array $produit, $desc_max_len = 20, $nom_override = null)
{
    if ($nom_override !== null && trim((string) $nom_override) !== '') {
        $nom_raw = trim((string) $nom_override);
    } else {
        $nom_raw = trim((string) ($produit['nom'] ?? $produit['produit_nom'] ?? ''));
    }
    $nom_esc = htmlspecialchars($nom_raw, ENT_QUOTES, 'UTF-8');
    $marque = produits_marque_libelle_from_row($produit);
    $desc_ex = produits_description_excerpt($produit['description'] ?? '', (int) $desc_max_len);
    $parts = ['<span class="pcn-nom">' . $nom_esc . '</span>'];
    if ($marque !== '') {
        $parts[] = '<span class="pcn-marque">' . htmlspecialchars($marque, ENT_QUOTES, 'UTF-8') . '</span>';
    }
    if ($desc_ex !== '') {
        $parts[] = '<span class="pcn-desc">' . htmlspecialchars($desc_ex, ENT_QUOTES, 'UTF-8') . '</span>';
    }

    $sep = '<span class="pcn-sep" aria-hidden="true"> · </span>';

    return implode($sep, $parts);
}

/**
 * Nom fournisseur affiché : jointure table fournisseurs ou champ nom_fournisseur.
 *
 * @param array<string, mixed> $row
 * @return string
 */
function produits_fournisseur_nom_affichage($row)
{
    $t = trim((string) ($row['fournisseur_table_nom'] ?? ''));
    if ($t !== '') {
        return $t;
    }
    if (function_exists('produits_has_column') && produits_has_column('nom_fournisseur')) {
        $t = trim((string) ($row['nom_fournisseur'] ?? ''));
        if ($t !== '') {
            return $t;
        }
    }

    return '';
}

/**
 * Suffixe SELECT + JOIN pour marque / fournisseur (listes catalogue admin).
 *
 * @return array{sel: string, join: string}
 */
function produits_catalog_join_bundle()
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $sel = '';
    $join = '';
    // Charger le modèle marques si disponible (nécessaire pour marques_table_ok())
    $marquesPhp = __DIR__ . '/model_marques.php';
    if (file_exists($marquesPhp)) {
        require_once $marquesPhp;
    }
    if (produits_has_column('marque_id') && function_exists('marques_table_ok') && marques_table_ok()) {
        /* Sous-requête : évite tout conflit de nom avec p.* / PDO (alias unique). */
        $sel .= ', (SELECT mx.nom FROM marques mx WHERE mx.id = p.marque_id LIMIT 1) AS marque_libelle_catalogue';
    } else {
        $sel .= ', NULL AS marque_libelle_catalogue';
    }
    if (produits_has_column('fournisseur_id')) {
        $join .= ' LEFT JOIN fournisseurs f ON p.fournisseur_id = f.id ';
        $sel .= ', f.nom AS fournisseur_table_nom';
    } else {
        $sel .= ', NULL AS fournisseur_table_nom';
    }
    $cache = ['sel' => $sel, 'join' => $join];

    return $cache;
}

/**
 * Génère le prochain identifiant interne FPLXXXXXX (6 chiffres)
 */
function generate_next_identifiant_interne_produit()
{
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
 * Indique si un identifiant interne est déjà utilisé
 */
function produits_identifiant_interne_existe($code, $exclude_produit_id = 0)
{
    global $db;
    if (!produits_has_column('identifiant_interne') || !$db) {
        return false;
    }
    $exclude_produit_id = (int) $exclude_produit_id;
    $code = strtoupper(trim((string) $code));
    try {
        $stmt = $db->prepare('SELECT id FROM produits WHERE UPPER(TRIM(identifiant_interne)) = :c LIMIT 1');
        $stmt->execute(['c' => $code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return false;
        }
        if ($exclude_produit_id > 0 && (int) $row['id'] === $exclude_produit_id) {
            return false;
        }
        return true;
    } catch (PDOException $e) {
        return true;
    }
}

/**
 * Prochain préfixe à 3 chiffres pour les codes FPL + 9 chiffres (001–999)
 */
function produits_prochain_prefix_3_chiffres()
{
    global $db;
    if (!$db || !produits_has_column('identifiant_interne')) {
        return '001';
    }
    $maxPref = 0;
    try {
        $stmt = $db->query("
            SELECT identifiant_interne FROM produits
            WHERE identifiant_interne REGEXP '^FPL[0-9]{9}$'
        ");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $c = strtoupper(trim((string) ($row['identifiant_interne'] ?? '')));
            if (preg_match('/^FPL(\d{3})(\d{6})$/', $c, $m)) {
                $maxPref = max($maxPref, (int) $m[1]);
            }
        }
    } catch (PDOException $e) {
        return '001';
    }
    $next = $maxPref + 1;
    if ($next > 999) {
        $next = 1;
    }
    return str_pad((string) $next, 3, '0', STR_PAD_LEFT);
}

/**
 * Construit FPL + 3 chiffres auto + 6 chiffres saisis, en garantissant l'unicité
 * @return string|null ex. FPL001123456
 */
function produits_allouer_identifiant_fpl_9($suffix6, $exclude_produit_id = 0)
{
    $suffix6 = preg_replace('/\D/', '', (string) $suffix6);
    if (strlen($suffix6) > 6) {
        $suffix6 = substr($suffix6, -6);
    }
    $suffix6 = str_pad($suffix6, 6, '0', STR_PAD_LEFT);
    $exclude_produit_id = (int) $exclude_produit_id;

    $prefixBase = (int) produits_prochain_prefix_3_chiffres();
    if ($prefixBase <= 0) {
        $prefixBase = 1;
    }
    for ($delta = 0; $delta < 999; $delta++) {
        $p = ($prefixBase + $delta - 1) % 999 + 1;
        $pref = str_pad((string) $p, 3, '0', STR_PAD_LEFT);
        $full = 'FPL' . $pref . $suffix6;
        if (!produits_identifiant_interne_existe($full, $exclude_produit_id)) {
            return $full;
        }
    }
    return null;
}

/**
 * Alloue un identifiant FPL 9 chiffres avec suffixe automatique : « 00 » + 4 chiffres aléatoires,
 * et préfixe 3 chiffres comme {@see produits_allouer_identifiant_fpl_9}. Réessaie si collision.
 *
 * @return string|null ex. FPL001004523
 */
function produits_allouer_identifiant_fpl_9_auto($exclude_produit_id = 0)
{
    if (!produits_has_column('identifiant_interne')) {
        return null;
    }
    $exclude_produit_id = (int) $exclude_produit_id;
    $max_attempts = 100;
    for ($attempt = 0; $attempt < $max_attempts; $attempt++) {
        $suffix6 = '00' . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $code = produits_allouer_identifiant_fpl_9($suffix6, $exclude_produit_id);
        if ($code !== null && $code !== '') {
            return $code;
        }
    }
    return null;
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
        $jb = produits_catalog_join_bundle();
        $selx = $jb['sel'];
        $joinx = $jb['join'];
        if ($statut) {
            $stmt = $db->prepare("
                SELECT p.*, c.nom as categorie_nom $selx
                FROM produits p 
                LEFT JOIN categories c ON p.categorie_id = c.id 
                $joinx
                WHERE p.statut = :statut 
                ORDER BY p.date_creation DESC
            ");
            $stmt->execute(['statut' => $statut]);
        } else {
            $stmt = $db->prepare("
                SELECT p.*, c.nom as categorie_nom $selx
                FROM produits p 
                LEFT JOIN categories c ON p.categorie_id = c.id 
                $joinx
                ORDER BY p.date_creation DESC
            ");
            $stmt->execute();
        }

        $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return produits_appliquer_filtre_acces_liste($produits ? $produits : []);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Derniers produits ajoutés au catalogue (tableau de bord, suivi des ajouts)
 *
 * @param int $limit Nombre max (1–50)
 * @return array<int, array<string, mixed>>
 */
function get_derniers_produits_ajoutes($limit = 8)
{
    global $db;
    $limit = max(1, min(50, (int) $limit));
    try {
        $has_admin = produits_has_column('admin_createur_id');
        if ($has_admin) {
            $sql = "
                SELECT p.id, p.nom, p.image_principale, p.prix, p.prix_promotion, p.stock, p.statut, p.date_creation,
                       c.nom AS categorie_nom,
                       a.prenom AS createur_prenom, a.nom AS createur_nom
                FROM produits p
                LEFT JOIN categories c ON p.categorie_id = c.id
                LEFT JOIN admin a ON p.admin_createur_id = a.id
                ORDER BY p.date_creation DESC, p.id DESC
                LIMIT " . (int) $limit;
        } else {
            $sql = "
                SELECT p.id, p.nom, p.image_principale, p.prix, p.prix_promotion, p.stock, p.statut, p.date_creation,
                       c.nom AS categorie_nom
                FROM produits p
                LEFT JOIN categories c ON p.categorie_id = c.id
                ORDER BY p.date_creation DESC, p.id DESC
                LIMIT " . (int) $limit;
        }
        $stmt = $db->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return produits_appliquer_filtre_acces_liste($rows);
    } catch (PDOException $e) {
        return [];
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
        $jb = produits_catalog_join_bundle();
        $selx = $jb['sel'];
        $joinx = $jb['join'];
        $stmt = $db->prepare("
            SELECT p.*, c.nom as categorie_nom $selx
            FROM produits p 
            LEFT JOIN categories c ON p.categorie_id = c.id 
            $joinx
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
 * Produits rattachés à une sous-catégorie (admin : tous statuts).
 * @param int $sous_categorie_id
 * @return array<int, array<string, mixed>>
 */
function get_produits_by_sous_categorie_id($sous_categorie_id)
{
    global $db;

    if (!produits_has_column('sous_categorie_id')) {
        return [];
    }
    $sous_categorie_id = (int) $sous_categorie_id;
    if ($sous_categorie_id <= 0) {
        return [];
    }

    try {
        $jb = produits_catalog_join_bundle();
        $selx = $jb['sel'];
        $joinx = $jb['join'];
        $stmt = $db->prepare("
            SELECT p.*, c.nom as categorie_nom $selx
            FROM produits p
            LEFT JOIN categories c ON p.categorie_id = c.id
            $joinx
            WHERE p.sous_categorie_id = :sid
            ORDER BY p.date_creation DESC, p.id DESC
        ");
        $stmt->execute(['sid' => $sous_categorie_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * @param int $sous_categorie_id
 * @return int
 */
function count_produits_by_sous_categorie_id($sous_categorie_id)
{
    global $db;
    if (!produits_has_column('sous_categorie_id')) {
        return 0;
    }
    $sous_categorie_id = (int) $sous_categorie_id;
    if ($sous_categorie_id <= 0) {
        return 0;
    }
    try {
        $stmt = $db->prepare('SELECT COUNT(*) FROM produits WHERE sous_categorie_id = :sid');
        $stmt->execute(['sid' => $sous_categorie_id]);
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
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
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: false;

        return produits_appliquer_filtre_acces_champs($row);
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
    if (!preg_match('/^FPL(\d{6}|\d{9})$/', $code)) {
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
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: false;

        return produits_appliquer_filtre_acces_champs($row);
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
    if (produits_has_column('identifiant_interne') && preg_match('/^FPL(\d{6}|\d{9})$/i', $t)) {
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
    if (produits_has_column('identifiant_interne') && preg_match('/^FPL(\d{6}|\d{9})$/i', $t)) {
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
 * @param int|null $marque_id Filtre marque (si colonne marque_id)
 * @return array Tableau des produits trouvés
 */
function search_produits_with_filters($recherche = '', $prix_min = null, $prix_max = null, $categorie_id = null, $tri = 'date', $offset = 0, $limit = 50, $marque_id = null)
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
            } elseif (produits_has_column('identifiant_interne') && preg_match('/^FPL(\d{6}|\d{9})$/i', $tr)) {
                $conditions[] = 'UPPER(TRIM(p.identifiant_interne)) = :ident_exact';
                $params['ident_exact'] = strtoupper($tr);
            } else {
                $or = ['p.nom LIKE :term', 'p.description LIKE :term'];
                if (produits_has_column('identifiant_interne')) {
                    $or[] = '(p.identifiant_interne IS NOT NULL AND TRIM(p.identifiant_interne) != \'\' AND UPPER(TRIM(p.identifiant_interne)) LIKE UPPER(:term))';
                }
                if (produits_has_column('reference_fournisseur')) {
                    $or[] = '(p.reference_fournisseur IS NOT NULL AND p.reference_fournisseur LIKE :term)';
                }
                $conditions[] = '(' . implode(' OR ', $or) . ')';
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

        if ($marque_id !== null && $marque_id !== '' && function_exists('produits_has_column') && produits_has_column('marque_id')) {
            $mid = (int) $marque_id;
            if ($mid > 0) {
                $conditions[] = 'p.marque_id = :marque_id';
                $params['marque_id'] = $mid;
            }
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

        $jb = produits_catalog_join_bundle();
        $selx = $jb['sel'];
        $joinx = $jb['join'];
        $stmt = $db->prepare("
            SELECT p.*, c.nom as categorie_nom $selx
            FROM produits p 
            LEFT JOIN categories c ON p.categorie_id = c.id 
            $joinx
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

        $rows = produits_appliquer_filtre_acces_liste($produits ? $produits : []);
        require_once __DIR__ . '/model_produit_formulaire_champs.php';

        return produit_formulaire_attacher_valeurs_custom_liste($rows);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Compte les produits avec les mêmes filtres que search_produits_with_filters
 */
function count_search_produits_with_filters($recherche = '', $prix_min = null, $prix_max = null, $categorie_id = null, $marque_id = null)
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
            } elseif (produits_has_column('identifiant_interne') && preg_match('/^FPL(\d{6}|\d{9})$/i', $tr)) {
                $conditions[] = 'UPPER(TRIM(identifiant_interne)) = :ident_exact';
                $params['ident_exact'] = strtoupper($tr);
            } else {
                $or = ['nom LIKE :term', 'description LIKE :term'];
                if (produits_has_column('identifiant_interne')) {
                    $or[] = '(identifiant_interne IS NOT NULL AND TRIM(identifiant_interne) != \'\' AND UPPER(TRIM(identifiant_interne)) LIKE UPPER(:term))';
                }
                if (produits_has_column('reference_fournisseur')) {
                    $or[] = '(reference_fournisseur IS NOT NULL AND reference_fournisseur LIKE :term)';
                }
                $conditions[] = '(' . implode(' OR ', $or) . ')';
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

        if ($marque_id !== null && $marque_id !== '' && function_exists('produits_has_column') && produits_has_column('marque_id')) {
            $mid = (int) $marque_id;
            if ($mid > 0) {
                $conditions[] = 'marque_id = :marque_id';
                $params['marque_id'] = $mid;
            }
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
        if (produits_has_column('allee')) {
            $cols .= ", allee";
            $vals .= ", :allee";
            $params['allee'] = isset($data['allee']) && $data['allee'] !== '' && $data['allee'] !== null ? (int) $data['allee'] : null;
        }
        if (produits_has_column('zone_emplacement')) {
            $cols .= ", zone_emplacement";
            $vals .= ", :zone_emplacement";
            $params['zone_emplacement'] = isset($data['zone_emplacement']) && $data['zone_emplacement'] !== '' && $data['zone_emplacement'] !== null ? (int) $data['zone_emplacement'] : null;
        }
        if (produits_has_column('position_emplacement')) {
            $cols .= ", position_emplacement";
            $vals .= ", :position_emplacement";
            $params['position_emplacement'] = isset($data['position_emplacement']) && $data['position_emplacement'] !== '' && $data['position_emplacement'] !== null ? (int) $data['position_emplacement'] : null;
        }
        if (produits_has_column('barre_rayon')) {
            $cols .= ", barre_rayon";
            $vals .= ", :barre_rayon";
            $params['barre_rayon'] = isset($data['barre_rayon']) && $data['barre_rayon'] !== '' && $data['barre_rayon'] !== null ? (int) $data['barre_rayon'] : null;
        }
        if (produits_has_column('entrepot_position_id')) {
            $cols .= ", entrepot_position_id";
            $vals .= ", :entrepot_position_id";
            $epid = $data['entrepot_position_id'] ?? null;
            $params['entrepot_position_id'] = ($epid !== null && (int) $epid > 0) ? (int) $epid : null;
        }
        if (produits_has_column('entrepot_noeud_id')) {
            $cols .= ", entrepot_noeud_id";
            $vals .= ", :entrepot_noeud_id";
            $enid = $data['entrepot_noeud_id'] ?? null;
            $params['entrepot_noeud_id'] = ($enid !== null && (int) $enid > 0) ? (int) $enid : null;
        }
        if (produits_has_column('fournisseur_id')) {
            $cols .= ", fournisseur_id";
            $vals .= ", :fournisseur_id";
            $fid = $data['fournisseur_id'] ?? null;
            $params['fournisseur_id'] = ($fid !== null && (int) $fid > 0) ? (int) $fid : null;
        }
        if (produits_has_column('nom_fournisseur')) {
            $cols .= ", nom_fournisseur";
            $vals .= ", :nom_fournisseur";
            $nf = $data['nom_fournisseur'] ?? null;
            $params['nom_fournisseur'] = ($nf !== null && $nf !== '' && trim((string) $nf) !== '') ? trim((string) $nf) : null;
        }
        if (produits_has_column('admin_createur_id') && !empty($data['admin_createur_id'])) {
            $cols .= ", admin_createur_id";
            $vals .= ", :admin_createur_id";
            $params['admin_createur_id'] = (int) $data['admin_createur_id'];
        }
        if (produits_has_column('prix_achat')) {
            $cols .= ", prix_achat";
            $vals .= ", :prix_achat";
            $params['prix_achat'] = array_key_exists('prix_achat', $data) ? $data['prix_achat'] : null;
        }
        if (produits_has_column('sous_categorie_id')) {
            $cols .= ", sous_categorie_id";
            $vals .= ", :sous_categorie_id";
            $scid = $data['sous_categorie_id'] ?? null;
            $params['sous_categorie_id'] = ($scid !== null && (int) $scid > 0) ? (int) $scid : null;
        }
        if (produits_has_column('image_etiquette_fpl')) {
            $cols .= ", image_etiquette_fpl";
            $vals .= ", :image_etiquette_fpl";
            $ief = $data['image_etiquette_fpl'] ?? null;
            $params['image_etiquette_fpl'] = ($ief !== null && $ief !== '') ? trim((string) $ief) : null;
        }
        if (produits_has_column('marque_id')) {
            $cols .= ", marque_id";
            $vals .= ", :marque_id";
            $mid = $data['marque_id'] ?? null;
            $params['marque_id'] = ($mid !== null && (int) $mid > 0) ? (int) $mid : null;
        }
        if (produits_has_column('reference_fournisseur')) {
            $cols .= ", reference_fournisseur";
            $vals .= ", :reference_fournisseur";
            $rf = $data['reference_fournisseur'] ?? null;
            $params['reference_fournisseur'] = ($rf !== null && trim((string) $rf) !== '') ? trim((string) $rf) : null;
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
                if (produits_has_column('admin_createur_id') && !empty($params['admin_createur_id'])) {
                    $cols .= ", admin_createur_id";
                    $vals .= ", :admin_createur_id";
                }
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
        if (produits_has_column('allee')) {
            $sets .= ", allee = :allee";
            $params['allee'] = isset($data['allee']) && $data['allee'] !== '' && $data['allee'] !== null ? (int) $data['allee'] : null;
        }
        if (produits_has_column('zone_emplacement')) {
            $sets .= ", zone_emplacement = :zone_emplacement";
            $params['zone_emplacement'] = isset($data['zone_emplacement']) && $data['zone_emplacement'] !== '' && $data['zone_emplacement'] !== null ? (int) $data['zone_emplacement'] : null;
        }
        if (produits_has_column('position_emplacement')) {
            $sets .= ", position_emplacement = :position_emplacement";
            $params['position_emplacement'] = isset($data['position_emplacement']) && $data['position_emplacement'] !== '' && $data['position_emplacement'] !== null ? (int) $data['position_emplacement'] : null;
        }
        if (produits_has_column('barre_rayon')) {
            $sets .= ", barre_rayon = :barre_rayon";
            $params['barre_rayon'] = isset($data['barre_rayon']) && $data['barre_rayon'] !== '' && $data['barre_rayon'] !== null ? (int) $data['barre_rayon'] : null;
        }
        if (produits_has_column('entrepot_position_id')) {
            $sets .= ", entrepot_position_id = :entrepot_position_id";
            $epid = $data['entrepot_position_id'] ?? null;
            $params['entrepot_position_id'] = ($epid !== null && (int) $epid > 0) ? (int) $epid : null;
        }
        if (produits_has_column('entrepot_noeud_id')) {
            $sets .= ", entrepot_noeud_id = :entrepot_noeud_id";
            $enid = $data['entrepot_noeud_id'] ?? null;
            $params['entrepot_noeud_id'] = ($enid !== null && (int) $enid > 0) ? (int) $enid : null;
        }
        if (produits_has_column('fournisseur_id')) {
            $sets .= ", fournisseur_id = :fournisseur_id";
            $fid = $data['fournisseur_id'] ?? null;
            $params['fournisseur_id'] = ($fid !== null && (int) $fid > 0) ? (int) $fid : null;
        }
        if (produits_has_column('nom_fournisseur')) {
            $sets .= ", nom_fournisseur = :nom_fournisseur";
            $nf = $data['nom_fournisseur'] ?? null;
            $params['nom_fournisseur'] = ($nf !== null && $nf !== '' && trim((string) $nf) !== '') ? trim((string) $nf) : null;
        }
        if (produits_has_column('admin_dernier_modificateur_id') && !empty($data['admin_dernier_modificateur_id'])) {
            $sets .= ", admin_dernier_modificateur_id = :admin_dernier_modificateur_id";
            $params['admin_dernier_modificateur_id'] = (int) $data['admin_dernier_modificateur_id'];
        }
        if (produits_has_column('prix_achat')) {
            $sets .= ", prix_achat = :prix_achat";
            $params['prix_achat'] = array_key_exists('prix_achat', $data) ? $data['prix_achat'] : null;
        }
        if (produits_has_column('sous_categorie_id')) {
            $sets .= ", sous_categorie_id = :sous_categorie_id";
            $scid = $data['sous_categorie_id'] ?? null;
            $params['sous_categorie_id'] = ($scid !== null && (int) $scid > 0) ? (int) $scid : null;
        }
        if (produits_has_column('image_etiquette_fpl') && array_key_exists('image_etiquette_fpl', $data)) {
            $sets .= ", image_etiquette_fpl = :image_etiquette_fpl";
            $ief = $data['image_etiquette_fpl'];
            $params['image_etiquette_fpl'] = ($ief !== null && $ief !== '') ? trim((string) $ief) : null;
        }
        if (produits_has_column('marque_id')) {
            $sets .= ", marque_id = :marque_id";
            $mid = $data['marque_id'] ?? null;
            $params['marque_id'] = ($mid !== null && (int) $mid > 0) ? (int) $mid : null;
        }
        if (produits_has_column('reference_fournisseur')) {
            $sets .= ", reference_fournisseur = :reference_fournisseur";
            $rf = $data['reference_fournisseur'] ?? null;
            $params['reference_fournisseur'] = ($rf !== null && trim((string) $rf) !== '') ? trim((string) $rf) : null;
        }
        if (produits_has_column('identifiant_interne') && array_key_exists('identifiant_interne', $data) && $data['identifiant_interne'] !== null && $data['identifiant_interne'] !== '') {
            $sets .= ", identifiant_interne = :identifiant_interne";
            $params['identifiant_interne'] = trim((string) $data['identifiant_interne']);
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
                if (produits_has_column('admin_dernier_modificateur_id') && !empty($params['admin_dernier_modificateur_id'])) {
                    $sets .= ", admin_dernier_modificateur_id = :admin_dernier_modificateur_id";
                }
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
 * Indique si une table existe dans la base courante.
 */
function produits_db_table_exists($table)
{
    global $db;
    static $cache = [];
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $table);
    if ($table === '') {
        return false;
    }
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }
    try {
        $stmt = $db->query('SHOW TABLES LIKE ' . $db->quote($table));
        $cache[$table] = (bool) $stmt->fetchColumn();
    } catch (PDOException $e) {
        $cache[$table] = false;
    }
    return $cache[$table];
}

/**
 * Rend produit_id nullable et passe la FK en ON DELETE SET NULL
 * (conserve l’historique commandes / devis / caisse tout en autorisant la suppression du produit).
 */
function produits_ensure_fk_produit_on_delete_set_null($table)
{
    global $db;
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $table);
    if ($table === '' || !produits_db_table_exists($table)) {
        return;
    }
    try {
        $stmt = $db->query("SHOW COLUMNS FROM `$table` LIKE 'produit_id'");
        $col = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$col) {
            return;
        }
        if (strtoupper((string) ($col['Null'] ?? '')) === 'YES') {
            return;
        }

        $schema = $db->query('SELECT DATABASE()')->fetchColumn();
        $fkStmt = $db->prepare("
            SELECT DISTINCT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = :schema
              AND TABLE_NAME = :table
              AND COLUMN_NAME = 'produit_id'
              AND REFERENCED_TABLE_NAME = 'produits'
        ");
        $fkStmt->execute(['schema' => $schema, 'table' => $table]);
        $fks = $fkStmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($fks as $fkName) {
            $fkName = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $fkName);
            if ($fkName === '') {
                continue;
            }
            try {
                $db->exec("ALTER TABLE `$table` DROP FOREIGN KEY `$fkName`");
            } catch (PDOException $e) {
                // contrainte déjà absente
            }
        }

        $type = (string) ($col['Type'] ?? 'int(11)');
        if (!preg_match('/^[a-zA-Z0-9(),\s]+$/', $type)) {
            $type = 'int(11)';
        }
        $db->exec("ALTER TABLE `$table` MODIFY `produit_id` $type NULL");

        $newFk = 'fk_' . $table . '_produit_id';
        try {
            $db->exec("ALTER TABLE `$table` ADD CONSTRAINT `$newFk` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON DELETE SET NULL ON UPDATE CASCADE");
        } catch (PDOException $e) {
            // déjà en place
        }
    } catch (PDOException $e) {
        // ignore : la suppression tentera quand même de détacher les lignes
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

    $id = (int) $id;
    if ($id <= 0 || !$db) {
        return false;
    }

    $tables_historique = [
        'commande_produits',
        'devis_produits',
        'caisse_vente_lignes',
        'bl_lignes',
        'stock_mouvements',
        'commandes_retours_lignes',
    ];
    $tables_catalogue = [
        'produits_variantes',
        'panier',
        'favoris',
        'produits_visites',
        'produit_champ_valeur',
    ];

    foreach ($tables_historique as $table) {
        produits_ensure_fk_produit_on_delete_set_null($table);
    }

    try {
        $db->beginTransaction();

        foreach ($tables_historique as $table) {
            if (!produits_db_table_exists($table)) {
                continue;
            }
            try {
                $stmt = $db->prepare("UPDATE `$table` SET produit_id = NULL WHERE produit_id = :id");
                $stmt->execute(['id' => $id]);
            } catch (PDOException $e) {
                // colonne encore NOT NULL : la FK SET NULL prendra le relais au DELETE
            }
        }

        foreach ($tables_catalogue as $table) {
            if (!produits_db_table_exists($table)) {
                continue;
            }
            $stmt = $db->prepare("DELETE FROM `$table` WHERE produit_id = :id");
            $stmt->execute(['id' => $id]);
        }

        $stmt = $db->prepare("DELETE FROM produits WHERE id = :id");
        $ok = $stmt->execute(['id' => $id]);
        if (!$ok || $stmt->rowCount() < 1) {
            $db->rollBack();
            return false;
        }

        $db->commit();
        $caisse_cache = dirname(__DIR__) . '/cache/caisse_catalog_live.json';
        if (is_file($caisse_cache)) {
            @unlink($caisse_cache);
        }
        return true;
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        return false;
    }
}

/**
 * Mise à jour rapide des prix depuis le suivi catalogue (page courante).
 *
 * @param array<int, array<string, mixed>> $rows clé produit_id => ['prix' => ?, 'prix_achat' => ?]
 * @param int|null $admin_id
 * @return array{success: bool, message: string, updated: int}
 */
function export_catalogue_maj_prix_produits(array $rows, $admin_id = null)
{
    global $db;
    $updated = 0;
    if (!$db || $rows === []) {
        return ['success' => false, 'message' => 'Aucune modification à enregistrer.', 'updated' => 0];
    }
    $has_prix_achat = produits_has_column('prix_achat');
    $has_admin_mod = produits_has_column('admin_dernier_modificateur_id');
    try {
        foreach ($rows as $pid => $data) {
            $pid = (int) $pid;
            if ($pid <= 0 || !is_array($data)) {
                continue;
            }
            $sets = [];
            $params = [':id' => $pid];
            if (array_key_exists('prix', $data)) {
                $prix_raw = trim((string) $data['prix']);
                if ($prix_raw === '') {
                    continue;
                }
                if (!is_numeric($prix_raw) || (float) $prix_raw < 0) {
                    return ['success' => false, 'message' => 'Prix vente invalide pour le produit #' . $pid . '.', 'updated' => $updated];
                }
                $sets[] = 'prix = :prix';
                $params[':prix'] = round((float) $prix_raw, 2);
            }
            if ($has_prix_achat && array_key_exists('prix_achat', $data)) {
                $pa_raw = trim((string) $data['prix_achat']);
                if ($pa_raw === '') {
                    $sets[] = 'prix_achat = NULL';
                } elseif (!is_numeric($pa_raw) || (float) $pa_raw < 0) {
                    return ['success' => false, 'message' => 'Prix achat invalide pour le produit #' . $pid . '.', 'updated' => $updated];
                } else {
                    $sets[] = 'prix_achat = :prix_achat';
                    $params[':prix_achat'] = round((float) $pa_raw, 2);
                }
            }
            if ($sets === []) {
                continue;
            }
            $sets[] = 'date_modification = NOW()';
            if ($has_admin_mod && $admin_id !== null && (int) $admin_id > 0) {
                $sets[] = 'admin_dernier_modificateur_id = :admin_mod';
                $params[':admin_mod'] = (int) $admin_id;
            }
            $sql = 'UPDATE produits SET ' . implode(', ', $sets) . ' WHERE id = :id';
            $st = $db->prepare($sql);
            $st->execute($params);
            if ($st->rowCount() > 0) {
                $updated++;
            }
        }
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Erreur : ' . $e->getMessage(), 'updated' => $updated];
    }
    if ($updated === 0) {
        return ['success' => false, 'message' => 'Aucune ligne modifiée.', 'updated' => 0];
    }

    return ['success' => true, 'message' => $updated . ' produit(s) mis à jour.', 'updated' => $updated];
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
        return ['v' => $x, 's' => 0];
    }, $arr);
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
        $produit_id = (int) $produit_id;
        $qty = (int) $quantite;
        if ($qty <= 0) {
            return false;
        }
        $stmt = $db->prepare('SELECT stock FROM produits WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $produit_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return false;
        }
        $avant = (int) $row['stock'];

        $stmt = $db->prepare("UPDATE produits SET stock = GREATEST(0, stock - :qty), date_modification = NOW() WHERE id = :id");
        $stmt->execute(['id' => $produit_id, 'qty' => $qty]);
        $stmt = $db->prepare("SELECT stock FROM produits WHERE id = :id");
        $stmt->execute(['id' => $produit_id]);
        $row2 = $stmt->fetch(PDO::FETCH_ASSOC);
        $apres = $row2 ? (int) $row2['stock'] : false;
        if ($apres !== false) {
            require_once __DIR__ . '/../includes/stock_alertes_notifications.php';
            stock_alertes_notifier_baisse_stock($produit_id, $avant, $apres);
        }
        return $apres;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Incrémente le stock d'un produit (retour marchandise, annulation, etc.)
 *
 * @param int $produit_id ID du produit
 * @param int $quantite Quantité à ajouter
 * @return int|false Nouvelle quantité ou false en cas d'erreur
 */
function increment_produit_stock($produit_id, $quantite)
{
    global $db;

    try {
        $produit_id = (int) $produit_id;
        $qty = (int) $quantite;
        if ($qty <= 0) {
            return false;
        }
        $stmt = $db->prepare('SELECT stock FROM produits WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $produit_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return false;
        }
        $avant = (int) $row['stock'];

        $stmt = $db->prepare('UPDATE produits SET stock = stock + :qty, date_modification = NOW() WHERE id = :id');
        $stmt->execute(['id' => $produit_id, 'qty' => $qty]);
        $stmt = $db->prepare('SELECT stock FROM produits WHERE id = :id');
        $stmt->execute(['id' => $produit_id]);
        $row2 = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row2 ? (int) $row2['stock'] : false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Recherche des produits en stock pour commande manuelle
 * @param string $recherche Terme de recherche (nom produit ou catégorie)
 * @param int $limit Nombre max de résultats
 * @param int $offset Décalage pagination
 * @return array Produits avec stock > 0
 */
function search_produits_en_stock_commande_manuelle($recherche = '', $limit = 30, $offset = 0)
{
    global $db;

    try {
        $jb = produits_catalog_join_bundle();
        $selx = $jb['sel'];
        $joinx = $jb['join'];
        $limit = max(1, min(80, (int) $limit));
        $offset = max(0, (int) $offset);

        $sql = "
            SELECT p.id, p.nom, p.prix, p.prix_promotion, p.stock, p.image_principale, p.description,
                   c.nom as categorie_nom,
                   p.stock as stock_dispo
        ";
        if (produits_has_column('identifiant_interne')) {
            $sql .= ', p.identifiant_interne';
        }
        if (produits_has_column('reference_fournisseur')) {
            $sql .= ', p.reference_fournisseur';
        }
        if (produits_has_column('nom_fournisseur')) {
            $sql .= ', p.nom_fournisseur';
        }
        $sql .= $selx . "
            FROM produits p
            LEFT JOIN categories c ON p.categorie_id = c.id
            $joinx
            WHERE p.statut = 'actif' AND p.stock > 0
        ";

        $params = [];
        $tr = trim((string) $recherche);
        if ($tr !== '') {
            $or = [];
            $or[] = 'p.nom LIKE :st_nom';
            $or[] = 'c.nom LIKE :st_cat';
            $params['st_nom'] = '%' . $tr . '%';
            $params['st_cat'] = '%' . $tr . '%';
            if (produits_has_column('description')) {
                $or[] = 'p.description LIKE :st_desc';
                $params['st_desc'] = '%' . $tr . '%';
            }
            if (produits_has_column('reference_fournisseur')) {
                $or[] = 'p.reference_fournisseur LIKE :st_rf';
                $params['st_rf'] = '%' . $tr . '%';
            }
            if (produits_has_column('identifiant_interne')) {
                if (preg_match('/^FPL(\d{6}|\d{9})$/i', $tr)) {
                    $or[] = 'UPPER(TRIM(p.identifiant_interne)) = :st_ident_ex';
                    $params['st_ident_ex'] = strtoupper($tr);
                } elseif (preg_match('/^\d{5}$/', $tr)) {
                    $or[] = 'p.identifiant_interne IS NOT NULL AND TRIM(p.identifiant_interne) != \'\' AND ' . produits_sql_identifiant_suffix_5_expr('p') . ' = :st_suf5';
                    $params['st_suf5'] = $tr;
                } else {
                    $or[] = '(p.identifiant_interne IS NOT NULL AND TRIM(p.identifiant_interne) != \'\' AND p.identifiant_interne LIKE :st_idlike)';
                    $params['st_idlike'] = '%' . $tr . '%';
                }
            }
            $sql .= ' AND (' . implode(' OR ', $or) . ')';
        }

        $sql .= ' ORDER BY p.nom ASC LIMIT ' . $offset . ', ' . $limit;
        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v, PDO::PARAM_STR);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $out = [];
        foreach ($rows as $r) {
            $item = [
                'id' => (int) ($r['id'] ?? 0),
                'nom' => (string) ($r['nom'] ?? ''),
                'prix' => $r['prix'] ?? 0,
                'prix_promotion' => $r['prix_promotion'] ?? null,
                'stock' => (int) ($r['stock'] ?? 0),
                'stock_dispo' => (int) ($r['stock_dispo'] ?? $r['stock'] ?? 0),
                'image_principale' => $r['image_principale'] ?? '',
                'categorie_nom' => (string) ($r['categorie_nom'] ?? ''),
                'marque_nom' => trim((string) ($r['marque_libelle_catalogue'] ?? $r['pcn_marque_join_nom'] ?? $r['marque_nom'] ?? '')),
                'fournisseur_nom' => produits_fournisseur_nom_affichage($r),
                'ref_fournisseur' => (produits_has_column('reference_fournisseur') ? trim((string) ($r['reference_fournisseur'] ?? '')) : ''),
                'ref_produit' => (produits_has_column('identifiant_interne') ? strtoupper(trim((string) ($r['identifiant_interne'] ?? ''))) : ''),
                'desc_excerpt' => produits_description_excerpt($r['description'] ?? '', 20),
            ];
            $out[] = $item;
        }

        require_once __DIR__ . '/model_produit_formulaire_champs.php';

        return produit_formulaire_filtrer_produits_api_liste($out);
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

/**
 * Filtre date pour export catalogue admin.
 *
 * @param string $mode complet|ajout|modification|tous
 * @param string $date_debut Y-m-d
 * @param string $date_fin Y-m-d
 * @param array<string, mixed> $params
 * @return string SQL AND …
 */
function admin_produits_export_periode_sql($mode, $date_debut, $date_fin, array &$params)
{
    $allowed = ['complet', 'ajout', 'modification', 'tous'];
    $mode = in_array($mode, $allowed, true) ? $mode : 'tous';

    if ($mode === 'complet') {
        return '';
    }

    $debut = $date_debut . ' 00:00:00';
    $fin = $date_fin . ' 23:59:59';
    $params['exp_debut'] = $debut;
    $params['exp_fin'] = $fin;

    if ($mode === 'ajout') {
        return ' AND p.date_creation BETWEEN :exp_debut AND :exp_fin';
    }
    if ($mode === 'modification') {
        return ' AND p.date_modification IS NOT NULL AND p.date_modification BETWEEN :exp_debut AND :exp_fin';
    }

    return ' AND (
        p.date_creation BETWEEN :exp_debut AND :exp_fin
        OR (p.date_modification IS NOT NULL AND p.date_modification BETWEEN :exp_debut AND :exp_fin)
    )';
}

/**
 * Produits pour export catalogue (période, recherche, filtres).
 *
 * @param string $date_debut Y-m-d
 * @param string $date_fin Y-m-d
 * @param string $mode complet|ajout|modification|tous
 * @param string $recherche
 * @param int $categorie_id
 * @param int $marque_id
 * @param int $fournisseur_id
 * @param int $limit
 * @param int $offset
 * @return array<int, array<string, mixed>>
 */

function get_admin_produits_export_catalogue($date_debut, $date_fin, $mode = 'tous', $recherche = '', $categorie_id = 0, $marque_id = 0, $fournisseur_id = 0, $limit = 500, $offset = 0)
{
    global $db;

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_debut) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_fin)) {
        return [];
    }
    if ($date_debut > $date_fin) {
        $tmp = $date_debut;
        $date_debut = $date_fin;
        $date_fin = $tmp;
    }

    if (!$db) {
        return [];
    }

    $limit = max(1, min(5000, (int) $limit));
    $offset = max(0, (int) $offset);

    try {
        $jb = produits_catalog_join_bundle();
        $selx = $jb['sel'];
        $joinx = $jb['join'];
        $params = [];

        $sql = "
            SELECT p.*, c.nom AS categorie_nom $selx
            FROM produits p
            LEFT JOIN categories c ON p.categorie_id = c.id
            $joinx
            WHERE 1=1
        ";
        $sql .= admin_produits_liste_filtres_sql($categorie_id, $marque_id, $fournisseur_id, $params);
        $sql .= admin_produits_export_periode_sql($mode, $date_debut, $date_fin, $params);
        $sql .= admin_produits_liste_recherche_sql($recherche, $params);
        $sql .= ' ORDER BY COALESCE(p.date_modification, p.date_creation) DESC, p.id DESC LIMIT :exp_offset, :exp_limit';

        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':exp_offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':exp_limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $rows = produits_appliquer_filtre_acces_liste($rows ? $rows : []);
        require_once __DIR__ . '/model_produit_formulaire_champs.php';

        return produit_formulaire_attacher_valeurs_custom_liste($rows);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Charge tous les produits export (par lots) avec suivi de progression optionnel.
 *
 * @param callable|null $progress_callback function(int $loaded, int $total): void
 * @return array<int, array<string, mixed>>
 */
function get_admin_produits_export_catalogue_all($date_debut, $date_fin, $mode = 'tous', $recherche = '', $categorie_id = 0, $marque_id = 0, $fournisseur_id = 0, $batch_size = 200, $progress_callback = null)
{
    $total = count_admin_produits_export_catalogue($date_debut, $date_fin, $mode, $recherche, $categorie_id, $marque_id, $fournisseur_id);
    $batch_size = max(50, min(500, (int) $batch_size));
    $all = [];
    $offset = 0;

    while ($offset < $total) {
        $chunk = get_admin_produits_export_catalogue(
            $date_debut,
            $date_fin,
            $mode,
            $recherche,
            $categorie_id,
            $marque_id,
            $fournisseur_id,
            $batch_size,
            $offset
        );
        if ($chunk === []) {
            break;
        }
        foreach ($chunk as $row) {
            $all[] = $row;
        }
        $offset += count($chunk);
        if ($progress_callback !== null) {
            $progress_callback(count($all), $total);
        }
        if (count($chunk) < $batch_size) {
            break;
        }
    }

    return $all;
}

/**
 * Nombre de produits pour export catalogue (même filtres).
 */
function count_admin_produits_export_catalogue($date_debut, $date_fin, $mode = 'tous', $recherche = '', $categorie_id = 0, $marque_id = 0, $fournisseur_id = 0)
{
    global $db;

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_debut) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_fin)) {
        return 0;
    }
    if ($date_debut > $date_fin) {
        $tmp = $date_debut;
        $date_debut = $date_fin;
        $date_fin = $tmp;
    }

    if (!$db) {
        return 0;
    }

    try {
        $jb = produits_catalog_join_bundle();
        $joinx = $jb['join'];
        $params = [];

        $sql = "
            SELECT COUNT(*) AS cnt
            FROM produits p
            LEFT JOIN categories c ON p.categorie_id = c.id
            $joinx
            WHERE 1=1
        ";
        $sql .= admin_produits_liste_filtres_sql($categorie_id, $marque_id, $fournisseur_id, $params);
        $sql .= admin_produits_export_periode_sql($mode, $date_debut, $date_fin, $params);
        $sql .= admin_produits_liste_recherche_sql($recherche, $params);

        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int) ($row['cnt'] ?? 0);
    } catch (PDOException $e) {
        return 0;
    }
}

