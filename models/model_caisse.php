<?php
/**
 * Caisse magasin (panier en session, enregistrement vente + stock)
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/model_produits.php';
require_once __DIR__ . '/model_mouvements_stock.php';

/** @var string Clé session panier caisse */
define('CAISSE_SESSION_KEY', 'caisse_cart_v1');

/**
 * Taux TVA caisse (constant utilisée pour la décomposition TTC et l’ajout au net HT).
 * Les prix catalogue restent saisis comme aujourd’hui ; suivant la case « Inclure la TVA »,
 * le net panier est interprété comme TTC (décomposition) ou comme HT (TVA en sus).
 */
if (!defined('CAISSE_TVA_TAUX_POURCENT')) {
    define('CAISSE_TVA_TAUX_POURCENT', 18.0);
}

/** Modes de paiement en caisse (ENUM après migration canaux). */
function caisse_modes_paiement_valides()
{
    return ['especes', 'carte', 'orange_money', 'wave', 'cheque', 'mixte', 'autre'];
}

/**
 * Modes pour lesquels le formulaire propose « montant reçu / versé » et « monnaie à rendre » (hors paiement mixte).
 */
function caisse_mode_avec_montant_recu_affiche($mode_paiement)
{
    return in_array((string) $mode_paiement, ['especes', 'orange_money', 'wave', 'carte', 'cheque', 'autre'], true);
}

/**
 * Somme saisie pour contrôle paiement mixte (champs texte formulaire).
 */
function caisse_mixte_somme_saisie(array $d)
{
    $sum = 0.0;
    foreach (['montant_especes', 'montant_carte', 'montant_orange_money', 'montant_wave', 'montant_mobile_money'] as $k) {
        if (!array_key_exists($k, $d) || $d[$k] === null || $d[$k] === '') {
            continue;
        }
        $sum += round(max(0, (float) str_replace(',', '.', (string) $d[$k])), 2);
    }

    return $sum;
}

/**
 * Montants enregistrés sur la vente : total alloué au canal pour un mode simple,
 * ou parts saisies pour le mixte (ancien champ montant_mobile_money fusionné dans Orange Money).
 *
 * @param array $d montant_especes, montant_carte, montant_orange_money, montant_wave, montant_mobile_money (float|string|null)
 * @return array{montant_especes:?float,montant_carte:?float,montant_orange_money:?float,montant_wave:?float,montant_mobile_money:?float}
 */
function caisse_normaliser_montants_paiement_pour_db($mode_paiement, $montant_total, array $d)
{
    $mt = round(max(0, (float) $montant_total), 2);
    $one = function ($key) use ($d) {
        if (!array_key_exists($key, $d) || $d[$key] === null || $d[$key] === '') {
            return null;
        }
        $v = round(max(0, (float) str_replace(',', '.', (string) $d[$key])), 2);

        return $v > 0 ? $v : null;
    };
    $me = $one('montant_especes');
    $mc = $one('montant_carte');
    $mo = $one('montant_orange_money');
    $mw = $one('montant_wave');
    $mm = $one('montant_mobile_money');

    $vide = ['montant_especes' => null, 'montant_carte' => null, 'montant_orange_money' => null, 'montant_wave' => null, 'montant_mobile_money' => null];

    if ($mode_paiement === 'mixte') {
        $orange = ($mo ?? 0) + ($mm ?? 0);

        return [
            'montant_especes' => $me,
            'montant_carte' => $mc,
            'montant_orange_money' => $orange > 0 ? round($orange, 2) : null,
            'montant_wave' => $mw,
            'montant_mobile_money' => null,
        ];
    }

    switch ($mode_paiement) {
        case 'especes':
            $vide['montant_especes'] = $mt;

            return $vide;
        case 'carte':
            $vide['montant_carte'] = $mt;

            return $vide;
        case 'orange_money':
            $vide['montant_orange_money'] = $mt;

            return $vide;
        case 'wave':
            $vide['montant_wave'] = $mt;

            return $vide;
        default:

            return $vide;
    }
}

/**
 * Décompose un montant TTC en HT + TVA (TVA incluse dans le montant)
 * — utilisé pour l’affichage d’anciens tickets sans colonnes fiscales.
 *
 * @return array{ht:float, tva:float, ttc:float}
 */
function caisse_decomposer_ttc($montant_ttc)
{
    $ttc = max(0, round((float) $montant_ttc, 2));
    $t = (float) CAISSE_TVA_TAUX_POURCENT / 100.0;
    if ($t <= 0) {
        return ['ht' => $ttc, 'tva' => 0.0, 'ttc' => $ttc];
    }
    $ht = round($ttc / (1 + $t), 2);
    $tva = round($ttc - $ht, 2);
    return ['ht' => $ht, 'tva' => $tva, 'ttc' => $ttc];
}

/**
 * Récap HT / TVA / net à payer pour l’impression ticket et les écrans.
 * Avec colonnes fiscales en base : montant_total = TTC à payer ;
 * tva_incluse = 1 → TVA ajoutée au net HT catalogue ; 0 → TVA comprise dans le total (montant HT et TVA = décomposition du TTC).
 * Sans colonnes (ticket ancien) : décomposition « TTC inclus » sur montant_total.
 *
 * @return array{ht:float, tva:float, ttc:float, tva_incluse:bool, legacy_decompose:bool}
 */
function caisse_vente_recap_fiscal_affichage(array $vente_row)
{
    $ttc = max(0, round((float) ($vente_row['montant_total'] ?? 0), 2));
    $mh = array_key_exists('montant_ht', $vente_row) ? $vente_row['montant_ht'] : null;
    $mv = array_key_exists('montant_tva', $vente_row) ? $vente_row['montant_tva'] : null;
    $has_stored = (array_key_exists('montant_ht', $vente_row) && $vente_row['montant_ht'] !== null)
        && (array_key_exists('montant_tva', $vente_row) && $vente_row['montant_tva'] !== null);

    if ($has_stored) {
        $ht = round((float) $mh, 2);
        $tva = round((float) $mv, 2);
        $incl = !empty($vente_row['tva_incluse']);
        return [
            'ht' => $ht,
            'tva' => $tva,
            'ttc' => $ttc,
            'tva_incluse' => $incl,
            'legacy_decompose' => false,
        ];
    }

    $dec = caisse_decomposer_ttc($ttc);
    return [
        'ht' => $dec['ht'],
        'tva' => $dec['tva'],
        'ttc' => $dec['ttc'],
        'tva_incluse' => true,
        'legacy_decompose' => true,
    ];
}

/**
 * Prix unitaire HT en caisse (promotion si applicable)
 */
function caisse_prix_unitaire_produit(array $p)
{
    $promo = isset($p['prix_promotion']) && $p['prix_promotion'] !== '' && (float) $p['prix_promotion'] > 0
        ? (float) $p['prix_promotion']
        : null;
    $base = (float) ($p['prix'] ?? 0);
    return $promo !== null ? $promo : $base;
}

/** Nombre max d’articles dans le catalogue live caisse.
 *  Relevé de 2500 à 10000 le 04/09 : à 2500 (ORDER BY nom ASC) les 741 pièces
 *  en stock au-delà du rang alphabétique 2500 (noms après ~R) étaient
 *  introuvables au clavier de la caisse. 10000 couvre tout le catalogue actif
 *  (3241 en stock) avec de la marge ; le blob reste mis en cache 90 s. */
if (!defined('CAISSE_CATALOG_LIVE_LIMIT')) {
    define('CAISSE_CATALOG_LIVE_LIMIT', 10000);
}

/**
 * Fichier cache du catalogue live (TTL court).
 */
function caisse_catalog_live_cache_role_key()
{
    require_once dirname(__DIR__) . '/includes/admin_permissions.php';
    $role = function_exists('admin_current_role')
        ? (string) admin_current_role()
        : (string) ($_SESSION['admin_role'] ?? 'anon');
    $role = preg_replace('/[^a-z0-9_]/', '', $role);
    if ($role === '') {
        $role = 'anon';
    }

    return $role;
}

/**
 * Fichier cache du catalogue live (TTL court), un fichier par rôle.
 */
function caisse_catalog_live_cache_file()
{
    return dirname(__DIR__) . '/cache/caisse_catalog_live_' . caisse_catalog_live_cache_role_key() . '.json';
}

function caisse_catalog_live_cache_invalidate()
{
    $dir = dirname(__DIR__) . '/cache';
    $legacy = $dir . '/caisse_catalog_live.json';
    if (is_file($legacy)) {
        @unlink($legacy);
    }
    if (!is_dir($dir)) {
        return;
    }
    foreach (glob($dir . '/caisse_catalog_live_*.json') ?: [] as $f) {
        @unlink($f);
    }
}

/**
 * Première image catalogue (colonne principale uniquement — pas de JSON galerie).
 */
function caisse_catalog_first_image_url(array $p)
{
    $rel = trim(str_replace('\\', '/', (string) ($p['image_principale'] ?? '')));
    if ($rel === '') {
        return '';
    }
    return '/upload/' . ltrim($rel, '/');
}

/**
 * Catalogue caisse allégé : produits actifs en stock, colonnes utiles seulement.
 *
 * @return array<int, array<string, mixed>>
 */
function caisse_catalog_live_items($limit = null)
{
    global $db;
    $limit = $limit === null ? CAISSE_CATALOG_LIVE_LIMIT : max(1, min(5000, (int) $limit));
    if (!$db) {
        return [];
    }

    $has_ident = produits_has_column('identifiant_interne');
    $has_marque = produits_has_column('marque_id');
    $has_fourn = produits_has_column('fournisseur_id');
    $has_ref_f = produits_has_column('reference_fournisseur');
    $has_promo = produits_has_column('prix_promotion');

    $sel = 'p.id, p.nom, p.stock, p.prix, p.categorie_id, p.image_principale, LEFT(p.description, 280) AS description';
    if ($has_ident) {
        $sel .= ', p.identifiant_interne';
    }
    if ($has_marque) {
        $sel .= ', p.marque_id';
    }
    if ($has_fourn) {
        $sel .= ', p.fournisseur_id';
    }
    if ($has_ref_f) {
        $sel .= ', p.reference_fournisseur';
    }
    if ($has_promo) {
        $sel .= ', p.prix_promotion';
    }

    $jb = function_exists('produits_catalog_join_bundle') ? produits_catalog_join_bundle() : ['sel' => '', 'join' => ''];
    $sql = "SELECT $sel, c.nom AS categorie_nom" . ($jb['sel'] ?? '') . "
            FROM produits p
            LEFT JOIN categories c ON p.categorie_id = c.id
            " . ($jb['join'] ?? '') . "
            WHERE p.statut = 'actif' AND p.stock > 0
            ORDER BY p.nom ASC
            LIMIT " . (int) $limit;

    try {
        $stmt = $db->query($sql);
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (PDOException $e) {
        return [];
    }

    $out = [];
    foreach ($rows as $pr) {
        $nom = (string) ($pr['nom'] ?? '');
        $ref = $has_ident ? strtoupper(trim((string) ($pr['identifiant_interne'] ?? ''))) : '';
        $refF = $has_ref_f ? trim((string) ($pr['reference_fournisseur'] ?? '')) : '';
        $marqueNom = function_exists('produits_marque_libelle_from_row')
            ? produits_marque_libelle_from_row($pr)
            : trim((string) ($pr['marque_libelle_catalogue'] ?? ''));
        $fournisseurNom = function_exists('produits_fournisseur_nom_affichage')
            ? trim(produits_fournisseur_nom_affichage($pr))
            : '';
        $categorieNom = trim((string) ($pr['categorie_nom'] ?? ''));
        $desc = '';
        if (function_exists('produits_description_excerpt')) {
            $desc = produits_description_excerpt($pr['description'] ?? '', 80);
        }
        $search_parts = [$nom, $ref, $refF, $marqueNom, $fournisseurNom, $categorieNom, $desc];
        $img = caisse_catalog_first_image_url($pr);
        $out[] = [
            'id' => (int) ($pr['id'] ?? 0),
            'nom' => $nom,
            'nom_norm' => function_exists('produits_recherche_normalize') ? produits_recherche_normalize($nom) : mb_strtolower($nom),
            'search' => function_exists('produits_recherche_normalize')
                ? produits_recherche_normalize(implode(' ', $search_parts))
                : mb_strtolower(implode(' ', $search_parts)),
            'ref' => $ref,
            'ref_f' => $refF,
            'marque_nom' => $marqueNom,
            'desc' => $desc,
            'fournisseur_nom' => $fournisseurNom,
            'categorie_nom' => $categorieNom,
            'cat_id' => (int) ($pr['categorie_id'] ?? 0),
            'marque_id' => $has_marque ? (int) ($pr['marque_id'] ?? 0) : 0,
            'fournisseur_id' => $has_fourn ? (int) ($pr['fournisseur_id'] ?? 0) : 0,
            'prix' => round((float) caisse_prix_unitaire_produit($pr), 2),
            'stock' => (int) ($pr['stock'] ?? 0),
            'imgs' => $img !== '' ? [$img] : [],
        ];
    }

    require_once __DIR__ . '/model_produit_formulaire_champs.php';
    foreach ($out as $i => $item) {
        $item = produit_formulaire_filtrer_produit_api($item);
        $search_parts = [(string) ($item['nom'] ?? '')];
        foreach (['ref', 'ref_f', 'marque_nom', 'fournisseur_nom', 'categorie_nom', 'desc'] as $sk) {
            if (!empty($item[$sk])) {
                $search_parts[] = (string) $item[$sk];
            }
        }
        $item['search'] = function_exists('produits_recherche_normalize')
            ? produits_recherche_normalize(implode(' ', $search_parts))
            : mb_strtolower(implode(' ', $search_parts));
        $out[$i] = $item;
    }

    return $out;
}

/**
 * JSON du catalogue live (cache fichier 90 s).
 */
function caisse_catalog_live_json($limit = null)
{
    $limit = $limit === null ? CAISSE_CATALOG_LIVE_LIMIT : max(1, min(5000, (int) $limit));
    $cache_dir = dirname(__DIR__) . '/cache';
    $cache_file = caisse_catalog_live_cache_file();
    $ttl = 90;
    if (is_file($cache_file) && (time() - (int) filemtime($cache_file)) < $ttl) {
        $raw = @file_get_contents($cache_file);
        if (is_string($raw) && $raw !== '' && $raw[0] === '[') {
            return $raw;
        }
    }
    $json = json_encode(caisse_catalog_live_items($limit), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        $json = '[]';
    }
    if (!is_dir($cache_dir)) {
        @mkdir($cache_dir, 0755, true);
    }
    if (is_dir($cache_dir) && is_writable($cache_dir)) {
        @file_put_contents($cache_file, $json, LOCK_EX);
    }
    return $json;
}

/**
 * Parse un montant saisi en caisse (espaces, virgule décimale…)
 *
 * @return float|null null si vide ou invalide
 */
function caisse_parse_montant_saisi($raw)
{
    $t = trim((string) $raw);
    if ($t === '') {
        return null;
    }
    $t = preg_replace('/\s+/u', '', $t);
    $t = str_replace(',', '.', $t);
    if (!is_numeric($t)) {
        return null;
    }
    return (float) $t;
}

/**
 * Clé de ligne unique par produit (une ligne fusionnée par article)
 */
function caisse_line_key($produit_id)
{
    return 'p' . (int) $produit_id;
}

function caisse_cart_get()
{
    if (!isset($_SESSION[CAISSE_SESSION_KEY]) || !is_array($_SESSION[CAISSE_SESSION_KEY])) {
        $_SESSION[CAISSE_SESSION_KEY] = [
            'lines' => [],
            'remise_globale_pct' => 0.0,
            'inclure_tva' => 0,
        ];
    }
    if (!isset($_SESSION[CAISSE_SESSION_KEY]['lines']) || !is_array($_SESSION[CAISSE_SESSION_KEY]['lines'])) {
        $_SESSION[CAISSE_SESSION_KEY]['lines'] = [];
    }
    if (!isset($_SESSION[CAISSE_SESSION_KEY]['remise_globale_pct'])) {
        $_SESSION[CAISSE_SESSION_KEY]['remise_globale_pct'] = 0.0;
    }
    if (!array_key_exists('inclure_tva', $_SESSION[CAISSE_SESSION_KEY])) {
        $_SESSION[CAISSE_SESSION_KEY]['inclure_tva'] = 0;
    }
    return $_SESSION[CAISSE_SESSION_KEY];
}

function caisse_cart_save(array $cart)
{
    $_SESSION[CAISSE_SESSION_KEY] = $cart;
}

function caisse_cart_clear()
{
    unset($_SESSION[CAISSE_SESSION_KEY]);
}

/**
 * Sous-total catalogue (lignes × remises), net après remise globale, puis TVA.
 * - Case « Inclure la TVA » décochée : le net panier est considéré comme montant TTC à payer ;
 *   on en déduit le HT et la TVA pour l’affichage ticket / compta.
 * - Case cochée : le net panier est le HT ; la TVA s’ajoute (TTC plus élevé).
 */
function caisse_compute_totals(array $cart)
{
    $sous = 0.0;
    foreach ($cart['lines'] as $line) {
        $pu = (float) ($line['prix_unitaire'] ?? 0);
        $q = max(0, (int) ($line['quantite'] ?? 0));
        $rl = min(100, max(0, (float) ($line['remise_ligne_pct'] ?? 0)));
        $ligne_ht = $pu * $q * (1 - $rl / 100);
        $sous += $ligne_ht;
    }
    $rg = min(100, max(0, (float) ($cart['remise_globale_pct'] ?? 0)));
    $net_brut = round($sous * (1 - $rg / 100), 2);
    $inclure = !empty($cart['inclure_tva']);
    $taux = (float) CAISSE_TVA_TAUX_POURCENT / 100.0;

    if ($taux <= 0) {
        return [
            'sous_total' => round($sous, 2),
            'remise_globale_pct' => $rg,
            'total_ht' => $net_brut,
            'montant_tva' => 0.0,
            'total' => $net_brut,
            'total_ttc' => $net_brut,
            'taux_tva_pourcent' => (float) CAISSE_TVA_TAUX_POURCENT,
            'inclure_tva' => $inclure ? 1 : 0,
        ];
    }

    if ($inclure) {
        $montant_tva = round($net_brut * $taux, 2);
        $total_a_payer = round($net_brut + $montant_tva, 2);
        return [
            'sous_total' => round($sous, 2),
            'remise_globale_pct' => $rg,
            'total_ht' => $net_brut,
            'montant_tva' => $montant_tva,
            'total' => $total_a_payer,
            'total_ttc' => $total_a_payer,
            'taux_tva_pourcent' => (float) CAISSE_TVA_TAUX_POURCENT,
            'inclure_tva' => 1,
        ];
    }

    $dec = caisse_decomposer_ttc($net_brut);
    return [
        'sous_total' => round($sous, 2),
        'remise_globale_pct' => $rg,
        'total_ht' => $dec['ht'],
        'montant_tva' => $dec['tva'],
        'total' => $dec['ttc'],
        'total_ttc' => $dec['ttc'],
        'taux_tva_pourcent' => (float) CAISSE_TVA_TAUX_POURCENT,
        'inclure_tva' => 0,
    ];
}

/**
 * Tables caisse présentes en base
 */
function caisse_tables_exist()
{
    global $db;
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    try {
        $stmt = $db->query("SHOW TABLES LIKE 'caisse_ventes'");
        $cache = $stmt && $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        $cache = false;
    }
    return $cache;
}

/**
 * Résout un code saisi : ID numérique, FPLxxxxxx, 5 derniers chiffres, ou recherche nom (1 seul résultat requis pour auto-ajout)
 *
 * @return array{ok:bool, produit?:array, error?:string, ambigus?:array}
 */
function caisse_resoudre_produit_par_code($input)
{
    $t = trim((string) $input);
    if ($t === '') {
        return ['ok' => false, 'error' => 'Code ou recherche vide.'];
    }

    if (ctype_digit($t) && strlen($t) <= 9) {
        $id = (int) $t;
        if ($id > 0) {
            $p = get_produit_by_id($id);
            if ($p && ($p['statut'] ?? '') === 'actif') {
                return ['ok' => true, 'produit' => $p];
            }
        }
    }

    $found = search_produits($t, 0, 15);
    if (count($found) === 1) {
        return ['ok' => true, 'produit' => $found[0]];
    }
    if (count($found) === 0) {
        return ['ok' => false, 'error' => 'Aucun produit actif trouvé pour ce code ou cette recherche.'];
    }
    return ['ok' => false, 'error' => 'Plusieurs produits correspondent : affinez la recherche ou choisissez dans la liste.', 'ambigus' => $found];
}

/**
 * Ajoute ou incrémente une ligne produit
 *
 * @return array{ok:bool, error?:string}
 */
function caisse_cart_add_produit(array &$cart, array $produit, $quantite = 1)
{
    $quantite = max(1, (int) $quantite);
    if (($produit['statut'] ?? '') !== 'actif') {
        return ['ok' => false, 'error' => 'Ce produit n\'est pas disponible à la vente.'];
    }
    $stock = (int) ($produit['stock'] ?? 0);
    $key = caisse_line_key((int) $produit['id']);
    $ex = isset($cart['lines'][$key]) ? (int) $cart['lines'][$key]['quantite'] : 0;
    if ($stock < $ex + $quantite) {
        return ['ok' => false, 'error' => 'Stock insuffisant pour « ' . ($produit['nom'] ?? '') . ' » (disponible : ' . $stock . ').'];
    }

    $pu = caisse_prix_unitaire_produit($produit);
    $sans_prix_catalogue = $pu <= 0;

    if (isset($cart['lines'][$key])) {
        $cart['lines'][$key]['quantite'] = $ex + $quantite;
        if (empty($cart['lines'][$key]['prix_manuel'])) {
            $cart['lines'][$key]['prix_unitaire'] = max(0.0, $pu);
            if ($sans_prix_catalogue) {
                $cart['lines'][$key]['prix_manuel'] = 1;
            }
        }
        $cart['lines'][$key]['nom'] = $produit['nom'] ?? '';
    } else {
        $line = [
            'produit_id' => (int) $produit['id'],
            'nom' => $produit['nom'] ?? '',
            'prix_unitaire' => max(0.0, $pu),
            'quantite' => $quantite,
            'remise_ligne_pct' => 0.0,
        ];
        if ($sans_prix_catalogue) {
            $line['prix_manuel'] = 1;
        }
        $cart['lines'][$key] = $line;
    }
    return ['ok' => true];
}

/**
 * Met à jour le prix unitaire HT d'une ligne panier (saisie manuelle vendeur)
 *
 * @return array{ok:bool, error?:string}
 */
function caisse_cart_set_prix_ligne(array &$cart, $line_key, $prix_saisi)
{
    $key = trim((string) $line_key);
    if ($key === '' || !isset($cart['lines'][$key])) {
        return ['ok' => false, 'error' => 'Ligne panier introuvable.'];
    }
    $prix = caisse_parse_montant_saisi($prix_saisi);
    if ($prix === null || $prix <= 0) {
        $nom_ligne = trim((string) ($cart['lines'][$key]['nom'] ?? ''));
        $msg = 'Le prix unitaire doit être un montant supérieur à zéro.';
        if ($nom_ligne !== '') {
            $msg = 'Indiquez un prix unitaire pour « ' . $nom_ligne . ' ».';
        }
        return ['ok' => false, 'error' => $msg];
    }
    $prix = round($prix, 2);

    require_once __DIR__ . '/model_produits.php';
    $pid = (int) ($cart['lines'][$key]['produit_id'] ?? 0);
    $p = $pid > 0 ? get_produit_by_id($pid) : null;
    $prix_catalogue = $p ? round((float) caisse_prix_unitaire_produit($p), 2) : 0.0;

    $cart['lines'][$key]['prix_unitaire'] = $prix;
    if ($prix_catalogue > 0 && abs($prix - $prix_catalogue) < 0.005) {
        unset($cart['lines'][$key]['prix_manuel']);
    } else {
        $cart['lines'][$key]['prix_manuel'] = 1;
    }
    return ['ok' => true];
}

/**
 * Applique les prix saisis dans le POST (génération ticket / encaissement)
 *
 * @return array<int, string> Messages d'erreur éventuels
 */
function caisse_cart_apply_prix_posted(array &$cart, array $post)
{
    $prix_lignes = $post['prix_ligne'] ?? null;
    if (!is_array($prix_lignes) || empty($prix_lignes)) {
        return [];
    }
    $errs = [];
    foreach ($prix_lignes as $key => $raw) {
        $key = trim((string) $key);
        if ($key === '' || !isset($cart['lines'][$key])) {
            continue;
        }
        $res = caisse_cart_set_prix_ligne($cart, $key, $raw);
        if (!$res['ok']) {
            $errs[] = $res['error'] ?? 'Prix invalide.';
        }
    }
    return $errs;
}

/**
 * Met à jour la quantité d'une ligne panier (avec contrôle stock)
 *
 * @return array{ok:bool, error?:string}
 */
function caisse_cart_set_quantite_ligne(array &$cart, $line_key, $quantite)
{
    $key = trim((string) $line_key);
    if ($key === '' || !isset($cart['lines'][$key])) {
        return ['ok' => false, 'error' => 'Ligne panier introuvable.'];
    }
    $qty = (int) $quantite;
    if ($qty <= 0) {
        return ['ok' => false, 'error' => 'La quantité doit être supérieure à zéro.'];
    }

    require_once __DIR__ . '/model_produits.php';
    $pid = (int) ($cart['lines'][$key]['produit_id'] ?? 0);
    $p = $pid > 0 ? get_produit_by_id($pid) : null;
    if ($p) {
        $stock = (int) ($p['stock'] ?? 0);
        if ($qty > $stock) {
            return ['ok' => false, 'error' => 'Quantité supérieure au stock disponible (' . $stock . ').'];
        }
    }

    $cart['lines'][$key]['quantite'] = $qty;
    return ['ok' => true];
}

/**
 * Applique les quantités saisies dans le POST (génération ticket / encaissement)
 *
 * @return array<int, string>
 */
function caisse_cart_apply_quantites_posted(array &$cart, array $post)
{
    $qty_lignes = $post['quantite_ligne'] ?? null;
    if (!is_array($qty_lignes) || empty($qty_lignes)) {
        return [];
    }
    $errs = [];
    foreach ($qty_lignes as $key => $raw) {
        $key = trim((string) $key);
        if ($key === '' || !isset($cart['lines'][$key])) {
            continue;
        }
        $res = caisse_cart_set_quantite_ligne($cart, $key, $raw);
        if (!$res['ok']) {
            $errs[] = $res['error'] ?? 'Quantité invalide.';
        }
    }
    return $errs;
}

/**
 * Reconstruit le panier à partir des lignes réellement envoyées (prix + quantité).
 * Seules les clés présentes dans le POST sont conservées — ignore les lignes fantômes en session.
 *
 * @return array{ok:bool, error?:string}
 */
function caisse_cart_materialize_from_post(array &$cart, array $post, $require_snapshot = false)
{
    $qty_lignes = isset($post['quantite_ligne']) && is_array($post['quantite_ligne']) ? $post['quantite_ligne'] : [];
    $prix_lignes = isset($post['prix_ligne']) && is_array($post['prix_ligne']) ? $post['prix_ligne'] : [];
    $produit_lignes = isset($post['produit_ligne']) && is_array($post['produit_ligne']) ? $post['produit_ligne'] : [];
    $snapshot = isset($post['panier_ligne_cle']) && is_array($post['panier_ligne_cle']) ? $post['panier_ligne_cle'] : [];

    $keys_to_process = [];
    if (!empty($snapshot)) {
        foreach ($snapshot as $raw_key) {
            $key = trim((string) $raw_key);
            if ($key !== '') {
                $keys_to_process[] = $key;
            }
        }
        $keys_to_process = array_values(array_unique($keys_to_process));
    } else {
        foreach (array_keys($qty_lignes) as $raw_key) {
            $key = trim((string) $raw_key);
            if ($key !== '' && array_key_exists($key, $prix_lignes)) {
                $keys_to_process[] = $key;
            }
        }
    }

    if ($require_snapshot && empty($keys_to_process)) {
        return ['ok' => false, 'error' => 'Panier incomplet — rechargez la page puis réessayez.'];
    }
    if (empty($keys_to_process)) {
        return ['ok' => false, 'error' => 'Aucune ligne dans le panier — rechargez la page.'];
    }

    require_once __DIR__ . '/model_produits.php';

    $new_lines = [];
    $errs = [];

    foreach ($keys_to_process as $key) {
        if (!array_key_exists($key, $qty_lignes) || !array_key_exists($key, $prix_lignes)) {
            $errs[] = 'Données manquantes pour une ligne du panier.';
            continue;
        }

        $pid = (int) ($produit_lignes[$key] ?? ($cart['lines'][$key]['produit_id'] ?? 0));
        if ($pid <= 0 && preg_match('/^p(\d+)$/', $key, $m)) {
            $pid = (int) $m[1];
        }
        if ($pid <= 0) {
            $errs[] = 'Produit introuvable pour une ligne du panier.';
            continue;
        }

        $p = get_produit_by_id($pid);
        if (!$p || ($p['statut'] ?? '') !== 'actif') {
            $errs[] = 'Produit introuvable ou inactif.';
            continue;
        }

        $remise = isset($cart['lines'][$key]['remise_ligne_pct'])
            ? (float) $cart['lines'][$key]['remise_ligne_pct']
            : 0.0;

        $work = $cart;
        $work['lines'] = [
            $key => [
                'produit_id' => $pid,
                'nom' => (string) ($p['nom'] ?? ''),
                'prix_unitaire' => 0.0,
                'quantite' => 1,
                'remise_ligne_pct' => $remise,
            ],
        ];

        $res_qty = caisse_cart_set_quantite_ligne($work, $key, $qty_lignes[$key]);
        if (!$res_qty['ok']) {
            $errs[] = $res_qty['error'] ?? 'Quantité invalide.';
            continue;
        }
        $res_prix = caisse_cart_set_prix_ligne($work, $key, $prix_lignes[$key]);
        if (!$res_prix['ok']) {
            $errs[] = $res_prix['error'] ?? 'Prix invalide.';
            continue;
        }
        $new_lines[$key] = $work['lines'][$key];
    }

    if (!empty($errs)) {
        return ['ok' => false, 'error' => $errs[0]];
    }
    if (empty($new_lines)) {
        return ['ok' => false, 'error' => 'Aucune ligne valide dans le panier — rechargez la page.'];
    }

    $cart['lines'] = $new_lines;
    return ['ok' => true];
}

/**
 * Construit un panier caisse strictement depuis un payload JSON (sans session).
 * Chaque ligne est rechargée depuis la BDD via produit_id.
 *
 * @return array{ok:bool, cart?:array, error?:string}
 */
function caisse_build_cart_from_payload(array $payload)
{
    $lines_in = isset($payload['lines']) && is_array($payload['lines']) ? $payload['lines'] : [];
    if (empty($lines_in)) {
        return ['ok' => false, 'error' => 'Panier vide.'];
    }

    $cart = [
        'lines' => [],
        'remise_globale_pct' => min(100, max(0, (float) ($payload['remise_globale_pct'] ?? 0))),
        'inclure_tva' => !empty($payload['inclure_tva']) ? 1 : 0,
    ];

    $seen = [];
    foreach ($lines_in as $idx => $raw) {
        if (!is_array($raw)) {
            return ['ok' => false, 'error' => 'Ligne panier invalide.'];
        }
        $pid = (int) ($raw['produit_id'] ?? 0);
        if ($pid <= 0) {
            return ['ok' => false, 'error' => 'Identifiant produit manquant (ligne ' . ($idx + 1) . ').'];
        }
        if (isset($seen[$pid])) {
            return ['ok' => false, 'error' => 'Produit en double dans le panier.'];
        }
        $seen[$pid] = true;

        $p = get_produit_by_id($pid);
        if (!$p || ($p['statut'] ?? '') !== 'actif') {
            $nom_err = trim((string) ($raw['nom'] ?? ''));
            return ['ok' => false, 'error' => 'Produit introuvable ou inactif' . ($nom_err !== '' ? ' : « ' . $nom_err . ' »' : '') . '.'];
        }

        $qty = (int) ($raw['quantite'] ?? 0);
        if ($qty <= 0) {
            return ['ok' => false, 'error' => 'Quantité invalide pour « ' . ($p['nom'] ?? '') . ' ».'];
        }
        $stock = (int) ($p['stock'] ?? 0);
        if ($qty > $stock) {
            return ['ok' => false, 'error' => 'Stock insuffisant pour « ' . ($p['nom'] ?? '') . ' » (disponible : ' . $stock . ').'];
        }

        $prix = caisse_parse_montant_saisi($raw['prix_unitaire'] ?? null);
        if ($prix === null || $prix <= 0) {
            return ['ok' => false, 'error' => 'Prix invalide pour « ' . ($p['nom'] ?? '') . ' ».'];
        }
        $prix = round($prix, 2);

        $remise = min(100, max(0, (float) ($raw['remise_ligne_pct'] ?? 0)));
        $key = caisse_line_key($pid);
        $prix_catalogue = round((float) caisse_prix_unitaire_produit($p), 2);
        $line = [
            'produit_id' => $pid,
            'nom' => (string) ($p['nom'] ?? ''),
            'prix_unitaire' => $prix,
            'quantite' => $qty,
            'remise_ligne_pct' => $remise,
        ];
        if ($prix_catalogue <= 0 || abs($prix - $prix_catalogue) >= 0.005) {
            $line['prix_manuel'] = 1;
        }
        $cart['lines'][$key] = $line;
    }

    if (empty($cart['lines'])) {
        return ['ok' => false, 'error' => 'Aucune ligne valide dans le panier.'];
    }

    return ['ok' => true, 'cart' => $cart];
}

/**
 * Formate un produit pour l’API panier caisse (AJAX).
 */
function caisse_produit_api_format(array $p)
{
    $ref = '';
    if (function_exists('produits_has_column') && produits_has_column('identifiant_interne')) {
        $ref = strtoupper(trim((string) ($p['identifiant_interne'] ?? '')));
    }
    $prix = round((float) caisse_prix_unitaire_produit($p), 2);

    return [
        'id' => (int) ($p['id'] ?? 0),
        'nom' => (string) ($p['nom'] ?? ''),
        'ref' => $ref,
        'prix' => $prix,
        'stock' => (int) ($p['stock'] ?? 0),
        'sans_prix_catalogue' => $prix <= 0,
    ];
}

/**
 * Numéro provisoire (INSERT) — remplacé par caisse_ventes_appliquer_numero_officiel() juste après création
 */
function caisse_generer_numero_ticket_provisoire()
{
    return 'TMP-' . strtoupper(bin2hex(random_bytes(8)));
}

/**
 * Applique le numéro définitif : TKT + AAAAMMJJ + id (6 chiffres), ex. TKT20260331000006
 *
 * @return string Numéro final
 */
function caisse_ventes_appliquer_numero_officiel($vente_id)
{
    global $db;
    $vente_id = (int) $vente_id;
    if ($vente_id <= 0) {
        return '';
    }
    try {
        $stmt = $db->prepare("
            UPDATE caisse_ventes SET
                numero_ticket = CONCAT('TKT', DATE_FORMAT(date_vente, '%Y%m%d'), LPAD(id, 6, '0'))
            WHERE id = :id
        ");
        $stmt->execute(['id' => $vente_id]);
        $st = $db->prepare('SELECT numero_ticket FROM caisse_ventes WHERE id = :id LIMIT 1');
        $st->execute(['id' => $vente_id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ? (string) $r['numero_ticket'] : '';
    } catch (PDOException $e) {
        error_log('[caisse_ventes_appliquer_numero_officiel] ' . $e->getMessage());
        return '';
    }
}

/**
 * Colonne reference (recherche caisse 5 chiffres) présente
 */
function caisse_reference_caisse_column_exists()
{
    global $db;
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = false;
    try {
        if (!caisse_tables_exist()) {
            return $cache;
        }
        $st = $db->query("SHOW COLUMNS FROM caisse_ventes LIKE 'reference'");
        $cache = $st && $st->rowCount() > 0;
    } catch (PDOException $e) {
        $cache = false;
    }
    return $cache;
}

/**
 * Extrait une référence caisse 5 chiffres (complétée par zéros à gauche si 1–4 chiffres saisis)
 *
 * @return string|null null si la saisie ne correspond pas (ex. contient des lettres ou plus de 5 chiffres)
 */
function caisse_normaliser_saisie_reference_caisse($input)
{
    $d = preg_replace('/\D/u', '', (string) $input);
    if ($d === '' || strlen($d) > 5) {
        return null;
    }
    return str_pad($d, 5, '0', STR_PAD_LEFT);
}

/**
 * Ticket en attente par référence courte (encaissement)
 */
function caisse_get_vente_par_reference_caisse($ref_5)
{
    if (!preg_match('/^\d{5}$/', (string) $ref_5) || !caisse_tables_exist() || !caisse_reference_caisse_column_exists()) {
        return null;
    }
    global $db;
    try {
        $stmt = $db->prepare('SELECT id FROM caisse_ventes WHERE reference = :r AND statut = \'en_attente\' LIMIT 1');
        $stmt->execute(['r' => $ref_5]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        return caisse_get_vente_by_id((int) $row['id']);
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Attribue une référence aléatoire unique (00000–99999) à un ticket en attente
 *
 * @return string Référence ou chaîne vide si échec / colonne absente
 */
function caisse_ventes_assigner_reference_caisse($vente_id)
{
    global $db;
    $vente_id = (int) $vente_id;
    if ($vente_id <= 0 || !caisse_reference_caisse_column_exists()) {
        return '';
    }
    for ($try = 0; $try < 64; $try++) {
        $r = str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);
        try {
            $st = $db->prepare("
                UPDATE caisse_ventes SET reference = :r
                WHERE id = :id AND statut = 'en_attente' AND (reference IS NULL OR reference = '')
            ");
            $st->execute(['r' => $r, 'id' => $vente_id]);
            if ($st->rowCount() === 1) {
                return $r;
            }
            $chk = $db->prepare('SELECT reference FROM caisse_ventes WHERE id = :id LIMIT 1');
            $chk->execute(['id' => $vente_id]);
            $ex = $chk->fetch(PDO::FETCH_ASSOC);
            if ($ex && $ex['reference'] !== null && (string) $ex['reference'] !== '') {
                return (string) $ex['reference'];
            }
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            if (strpos($msg, '1062') !== false || stripos($msg, 'Duplicate') !== false) {
                continue;
            }
            error_log('[caisse_ventes_assigner_reference_caisse] ' . $msg);
            return '';
        }
    }
    return '';
}

/**
 * Affichage court demandé : TKT + date (AAAAMMJJ), ex. TKT20260331
 */
function caisse_ticket_numero_date_public(array $row)
{
    $d = $row['date_vente'] ?? '';
    if ($d !== '') {
        return 'TKT' . date('Ymd', strtotime($d));
    }
    return 'TKT';
}

/**
 * Valeur encodée dans le code-barres (scan à la caisse) — alignée sur numero_ticket officiel ou legacy
 */
function caisse_ticket_valeur_code_barres(array $row)
{
    $n = strtoupper(trim((string) ($row['numero_ticket'] ?? '')));
    if ($n !== '' && strpos($n, 'TMP-') !== 0) {
        return $n;
    }
    $id = (int) ($row['id'] ?? 0);
    $d = $row['date_vente'] ?? '';
    if ($id > 0 && $d !== '') {
        return 'TKT' . date('Ymd', strtotime($d)) . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }
    return $n;
}

/**
 * Scan zone A : si le code commence par TKT et correspond à un ticket, retourne l’id vente (sinon 0)
 */
function caisse_trouver_vente_id_par_code_scan($input)
{
    if (!caisse_tables_exist()) {
        return 0;
    }
    $raw = trim((string) $input);
    $compact = caisse_normaliser_saisie_numero_ticket($raw);
    if ($compact === '' || strncmp($compact, 'TKT', 3) !== 0) {
        return 0;
    }
    $v = caisse_get_vente_by_numero($raw);
    return $v ? (int) $v['id'] : 0;
}

/**
 * Statut ticket (ligne BDD) — rétrocompatible si colonne absente
 */
function caisse_vente_statut(array $row)
{
    $s = $row['statut'] ?? null;
    return ($s === 'en_attente') ? 'en_attente' : 'paye';
}

/**
 * Ticket en attente d’encaissement : lignes en BDD, pas de mouvement de stock
 *
 * @return array{ok:bool, vente_id?:int, numero_ticket?:string, error?:string}
 */
function caisse_creer_ticket_en_attente($admin_id, array $cart)
{
    global $db;

    if (!caisse_tables_exist()) {
        return ['ok' => false, 'error' => 'Tables caisse absentes : exécutez la migration create_caisse_tables.sql.'];
    }

    $totals = caisse_compute_totals($cart);
    if ($totals['total'] <= 0 || empty($cart['lines'])) {
        return ['ok' => false, 'error' => 'Panier vide ou total invalide.'];
    }

    $admin_id = (int) $admin_id;
    if ($admin_id <= 0) {
        return ['ok' => false, 'error' => 'Session administrateur invalide.'];
    }

    foreach ($cart['lines'] as $line) {
        $pid = (int) ($line['produit_id'] ?? 0);
        $q = (int) ($line['quantite'] ?? 0);
        if ($pid <= 0 || $q <= 0) {
            return ['ok' => false, 'error' => 'Ligne de panier invalide.'];
        }
        $p = get_produit_by_id($pid);
        if (!$p || ($p['statut'] ?? '') !== 'actif') {
            return ['ok' => false, 'error' => 'Produit introuvable ou inactif.'];
        }
        $stock = (int) ($p['stock'] ?? 0);
        if ($stock < $q) {
            return ['ok' => false, 'error' => 'Stock insuffisant pour « ' . ($p['nom'] ?? '#') . ' ».'];
        }
    }

    $numero_provisoire = caisse_generer_numero_ticket_provisoire();
    $montant_total = $totals['total'];
    $montant_ht = $totals['total_ht'];
    $montant_tva = $totals['montant_tva'];
    $tva_incluse = !empty($cart['inclure_tva']) ? 1 : 0;
    $rg = (float) ($cart['remise_globale_pct'] ?? 0);

    try {
        $db->beginTransaction();

        $stmt = $db->prepare("
            INSERT INTO caisse_ventes (
                admin_id, caissier_id, numero_ticket, montant_total, montant_ht, montant_tva, tva_incluse, remise_globale_pct, mode_paiement,
                montant_especes, montant_carte, montant_mobile_money, montant_recu, monnaie_rendue, notes,
                statut, date_vente, date_encaissement
            ) VALUES (
                :admin_id, NULL, :numero_ticket, :montant_total, :montant_ht, :montant_tva, :tva_incluse, :remise_globale_pct, 'especes',
                NULL, NULL, NULL, NULL, NULL, NULL,
                'en_attente', NOW(), NULL
            )
        ");
        $stmt->execute([
            'admin_id' => $admin_id,
            'numero_ticket' => $numero_provisoire,
            'montant_total' => $montant_total,
            'montant_ht' => $montant_ht,
            'montant_tva' => $montant_tva,
            'tva_incluse' => $tva_incluse,
            'remise_globale_pct' => $rg,
        ]);
        $vente_id = (int) $db->lastInsertId();

        $stmtL = $db->prepare("
            INSERT INTO caisse_vente_lignes (vente_id, produit_id, designation, quantite, prix_unitaire, remise_ligne_pct, total_ligne)
            VALUES (:vente_id, :produit_id, :designation, :quantite, :prix_unitaire, :remise_ligne_pct, :total_ligne)
        ");

        foreach ($cart['lines'] as $line) {
            $pid = (int) ($line['produit_id'] ?? 0);
            $q = (int) ($line['quantite'] ?? 0);
            $pu = (float) ($line['prix_unitaire'] ?? 0);
            $rl = min(100, max(0, (float) ($line['remise_ligne_pct'] ?? 0)));
            $designation = $line['nom'] ?? '';
            $total_ligne = round($pu * $q * (1 - $rl / 100), 2);

            $stmtL->execute([
                'vente_id' => $vente_id,
                'produit_id' => $pid,
                'designation' => $designation,
                'quantite' => $q,
                'prix_unitaire' => $pu,
                'remise_ligne_pct' => $rl,
                'total_ligne' => $total_ligne,
            ]);
        }

        $nb_attendu = count($cart['lines']);
        $stmtCnt = $db->prepare('SELECT COUNT(*) FROM caisse_vente_lignes WHERE vente_id = :id');
        $stmtCnt->execute(['id' => $vente_id]);
        $nb_insere = (int) $stmtCnt->fetchColumn();
        if ($nb_insere !== $nb_attendu) {
            throw new PDOException('Nombre de lignes ticket incohérent (attendu ' . $nb_attendu . ', obtenu ' . $nb_insere . ').');
        }

        $numero_final = caisse_ventes_appliquer_numero_officiel($vente_id);
        $ref_caisse = caisse_ventes_assigner_reference_caisse($vente_id);
        $db->commit();
        return [
            'ok' => true,
            'vente_id' => $vente_id,
            'numero_ticket' => ($numero_final !== '' ? $numero_final : $numero_provisoire),
            'reference_caisse' => $ref_caisse,
        ];
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('[caisse_creer_ticket_en_attente] ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Erreur lors de la création du ticket. Vérifiez la migration alter_caisse_ventes_statut_encaissement.sql.'];
    }
}

/**
 * Finalise un ticket en_attente : paiement, statut paye, stock, mouvements
 *
 * @return array{ok:bool, vente_id?:int, numero_ticket?:string, error?:string}
 */
function caisse_finaliser_vente_en_attente($vente_id, $caissier_admin_id, $mode_paiement, array $paiement_details)
{
    global $db;

    if (!caisse_tables_exist()) {
        return ['ok' => false, 'error' => 'Tables caisse absentes.'];
    }

    $vente_id = (int) $vente_id;
    $caissier_admin_id = (int) $caissier_admin_id;
    if ($vente_id <= 0 || $caissier_admin_id <= 0) {
        return ['ok' => false, 'error' => 'Références invalides.'];
    }

    $v = caisse_get_vente_by_id($vente_id);
    if (!$v) {
        return ['ok' => false, 'error' => 'Ticket introuvable.'];
    }
    if (caisse_vente_statut($v) !== 'en_attente') {
        return ['ok' => false, 'error' => 'Ce ticket est déjà encaissé ou n\'est pas en attente.'];
    }

    $cart = [
        'lines' => [],
        'remise_globale_pct' => (float) ($v['remise_globale_pct'] ?? 0),
        'inclure_tva' => (int) ($v['tva_incluse'] ?? 0),
    ];
    foreach ($v['lignes'] as $lg) {
        $key = caisse_line_key((int) $lg['produit_id']);
        $cart['lines'][$key] = [
            'produit_id' => (int) $lg['produit_id'],
            'nom' => $lg['designation'] ?? '',
            'prix_unitaire' => (float) ($lg['prix_unitaire'] ?? 0),
            'quantite' => (int) ($lg['quantite'] ?? 0),
            'remise_ligne_pct' => (float) ($lg['remise_ligne_pct'] ?? 0),
        ];
    }

    $totals = caisse_compute_totals($cart);
    $montant_total = $totals['total'];
    if ($montant_total <= 0 || empty($cart['lines'])) {
        return ['ok' => false, 'error' => 'Ticket invalide.'];
    }

    $modes_ok = caisse_modes_paiement_valides();
    if (!in_array($mode_paiement, $modes_ok, true)) {
        return ['ok' => false, 'error' => 'Mode de paiement invalide.'];
    }

    foreach ($cart['lines'] as $line) {
        $pid = (int) ($line['produit_id'] ?? 0);
        $q = (int) ($line['quantite'] ?? 0);
        $p = get_produit_by_id($pid);
        if (!$p || ($p['statut'] ?? '') !== 'actif') {
            return ['ok' => false, 'error' => 'Produit introuvable ou inactif.'];
        }
        $stock = (int) ($p['stock'] ?? 0);
        if ($stock < $q) {
            return ['ok' => false, 'error' => 'Stock insuffisant pour « ' . ($p['nom'] ?? '#') . ' ».'];
        }
    }

    $montant_recu = isset($paiement_details['montant_recu']) ? (float) $paiement_details['montant_recu'] : null;
    $monnaie_preset = isset($paiement_details['monnaie_rendue']) ? (float) $paiement_details['monnaie_rendue'] : null;
    $notes_in = isset($paiement_details['notes']) ? trim((string) $paiement_details['notes']) : '';
    $notes_val = $notes_in !== '' ? $notes_in : ($v['notes'] ?? null);

    if (caisse_mode_avec_montant_recu_affiche($mode_paiement) && $montant_recu !== null && $montant_recu + 0.001 < $montant_total) {
        return ['ok' => false, 'error' => 'Montant reçu inférieur au total à payer.'];
    }

    if ($mode_paiement === 'mixte') {
        $sum = caisse_mixte_somme_saisie($paiement_details);
        if ($sum + 0.01 < $montant_total) {
            return ['ok' => false, 'error' => 'La somme des règlements (espèces, carte, Orange Money, Wave) doit couvrir le total à payer.'];
        }
    }

    $montants_db = caisse_normaliser_montants_paiement_pour_db($mode_paiement, $montant_total, $paiement_details);
    $montant_especes = $montants_db['montant_especes'];
    $montant_carte = $montants_db['montant_carte'];
    $montant_orange = $montants_db['montant_orange_money'];
    $montant_wave = $montants_db['montant_wave'];
    $montant_mobile = $montants_db['montant_mobile_money'];
    $monnaie = $monnaie_preset;

    $numero = (string) ($v['numero_ticket'] ?? '');

    try {
        $db->beginTransaction();

        $sqlUp = "
            UPDATE caisse_ventes SET
                mode_paiement = :mode_paiement,
                montant_especes = :montant_especes,
                montant_carte = :montant_carte,
                montant_orange_money = :montant_orange_money,
                montant_wave = :montant_wave,
                montant_mobile_money = :montant_mobile_money,
                montant_recu = :montant_recu,
                monnaie_rendue = :monnaie_rendue,
                notes = :notes,
                statut = 'paye',
                caissier_id = :caissier_id,
                date_encaissement = NOW()";
        if (caisse_reference_caisse_column_exists()) {
            $sqlUp .= ",
                reference = NULL";
        }
        $sqlUp .= '
            WHERE id = :id AND statut = \'en_attente\'';
        $stmtU = $db->prepare($sqlUp);
        $stmtU->execute([
            'mode_paiement' => $mode_paiement,
            'montant_especes' => $montant_especes,
            'montant_carte' => $montant_carte,
            'montant_orange_money' => $montant_orange,
            'montant_wave' => $montant_wave,
            'montant_mobile_money' => $montant_mobile,
            'montant_recu' => $montant_recu,
            'monnaie_rendue' => $monnaie,
            'notes' => $notes_val !== null && $notes_val !== '' ? $notes_val : null,
            'caissier_id' => $caissier_admin_id,
            'id' => $vente_id,
        ]);
        if ($stmtU->rowCount() !== 1) {
            $db->rollBack();
            return ['ok' => false, 'error' => 'Impossible de finaliser le ticket (déjà traité ?).'];
        }

        foreach ($v['lignes'] as $line) {
            $pid = (int) $line['produit_id'];
            $q = (int) $line['quantite'];
            $produit = get_produit_by_id($pid);
            $quantite_avant = (int) ($produit['stock'] ?? 0);
            decrement_produit_stock($pid, $q);
            $quantite_apres = max(0, $quantite_avant - $q);

            $mv = [
                'type' => 'sortie',
                'produit_id' => $pid,
                'quantite' => $q,
                'quantite_avant' => $quantite_avant,
                'quantite_apres' => $quantite_apres,
                'reference_type' => 'caisse_vente',
                'reference_id' => $vente_id,
                'reference_numero' => $numero,
                'notes' => 'Vente caisse (encaissement)',
            ];
            if ($caissier_admin_id > 0) {
                $mv['admin_id'] = $caissier_admin_id;
            }
            create_stock_mouvement($mv);
        }

        $db->commit();
        return ['ok' => true, 'vente_id' => $vente_id, 'numero_ticket' => $numero];
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('[caisse_finaliser_vente_en_attente] ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Erreur lors de l\'encaissement.'];
    }
}

/**
 * Corrige le mode de paiement et les montants d’un ticket déjà payé (sans toucher au stock).
 *
 * @return array{ok:bool, vente_id?:int, numero_ticket?:string, error?:string}
 */
function caisse_corriger_paiement_vente_payee($vente_id, $mode_paiement, array $paiement_details)
{
    global $db;

    if (!caisse_tables_exist()) {
        return ['ok' => false, 'error' => 'Tables caisse absentes.'];
    }

    $vente_id = (int) $vente_id;
    if ($vente_id <= 0) {
        return ['ok' => false, 'error' => 'Ticket invalide.'];
    }

    $v = caisse_get_vente_by_id($vente_id);
    if (!$v) {
        return ['ok' => false, 'error' => 'Ticket introuvable.'];
    }
    if (caisse_vente_statut($v) !== 'paye') {
        return ['ok' => false, 'error' => 'Seuls les tickets déjà payés peuvent être corrigés ainsi.'];
    }

    $cart = [
        'lines' => [],
        'remise_globale_pct' => (float) ($v['remise_globale_pct'] ?? 0),
        'inclure_tva' => (int) ($v['tva_incluse'] ?? 0),
    ];
    foreach ($v['lignes'] as $lg) {
        $key = caisse_line_key((int) $lg['produit_id']);
        $cart['lines'][$key] = [
            'produit_id' => (int) $lg['produit_id'],
            'nom' => $lg['designation'] ?? '',
            'prix_unitaire' => (float) ($lg['prix_unitaire'] ?? 0),
            'quantite' => (int) ($lg['quantite'] ?? 0),
            'remise_ligne_pct' => (float) ($lg['remise_ligne_pct'] ?? 0),
        ];
    }

    $totals = caisse_compute_totals($cart);
    $montant_total = $totals['total'];
    if ($montant_total <= 0 || empty($cart['lines'])) {
        return ['ok' => false, 'error' => 'Ticket invalide.'];
    }

    $modes_ok = caisse_modes_paiement_valides();
    if (!in_array($mode_paiement, $modes_ok, true)) {
        return ['ok' => false, 'error' => 'Mode de paiement invalide.'];
    }

    $montant_recu = isset($paiement_details['montant_recu']) ? (float) $paiement_details['montant_recu'] : null;
    $monnaie_preset = isset($paiement_details['monnaie_rendue']) ? (float) $paiement_details['monnaie_rendue'] : null;
    $notes_in = isset($paiement_details['notes']) ? trim((string) $paiement_details['notes']) : '';
    $notes_val = $notes_in !== '' ? $notes_in : ($v['notes'] ?? null);

    if (caisse_mode_avec_montant_recu_affiche($mode_paiement) && $montant_recu !== null && $montant_recu + 0.001 < $montant_total) {
        return ['ok' => false, 'error' => 'Montant reçu inférieur au total à payer.'];
    }

    if ($mode_paiement === 'mixte') {
        $sum = caisse_mixte_somme_saisie($paiement_details);
        if ($sum + 0.01 < $montant_total) {
            return ['ok' => false, 'error' => 'La somme des règlements (espèces, carte, Orange Money, Wave) doit couvrir le total à payer.'];
        }
    }

    $montants_db = caisse_normaliser_montants_paiement_pour_db($mode_paiement, $montant_total, $paiement_details);
    $montant_especes = $montants_db['montant_especes'];
    $montant_carte = $montants_db['montant_carte'];
    $montant_orange = $montants_db['montant_orange_money'];
    $montant_wave = $montants_db['montant_wave'];
    $montant_mobile = $montants_db['montant_mobile_money'];
    $monnaie = $monnaie_preset;

    $numero = (string) ($v['numero_ticket'] ?? '');

    try {
        $sqlUp = '
            UPDATE caisse_ventes SET
                mode_paiement = :mode_paiement,
                montant_especes = :montant_especes,
                montant_carte = :montant_carte,
                montant_orange_money = :montant_orange_money,
                montant_wave = :montant_wave,
                montant_mobile_money = :montant_mobile_money,
                montant_recu = :montant_recu,
                monnaie_rendue = :monnaie_rendue,
                notes = :notes
            WHERE id = :id AND statut = \'paye\'';
        $stmtU = $db->prepare($sqlUp);
        $stmtU->execute([
            'mode_paiement' => $mode_paiement,
            'montant_especes' => $montant_especes,
            'montant_carte' => $montant_carte,
            'montant_orange_money' => $montant_orange,
            'montant_wave' => $montant_wave,
            'montant_mobile_money' => $montant_mobile,
            'montant_recu' => $montant_recu,
            'monnaie_rendue' => $monnaie,
            'notes' => $notes_val !== null && $notes_val !== '' ? $notes_val : null,
            'id' => $vente_id,
        ]);
        $chk = $db->prepare('SELECT COUNT(*) FROM caisse_ventes WHERE id = :id AND statut = \'paye\'');
        $chk->execute(['id' => $vente_id]);
        if ((int) $chk->fetchColumn() !== 1) {
            return ['ok' => false, 'error' => 'Impossible de mettre à jour le paiement (ticket introuvable ou non payé).'];
        }

        return ['ok' => true, 'vente_id' => $vente_id, 'numero_ticket' => $numero];
    } catch (PDOException $e) {
        error_log('[caisse_corriger_paiement_vente_payee] ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Erreur lors de la mise à jour du paiement.'];
    }
}

/**
 * Enregistre la vente, décrémente le stock, mouvements de stock
 *
 * @return array{ok:bool, vente_id?:int, numero_ticket?:string, error?:string}
 */
function caisse_enregistrer_vente($admin_id, array $cart, $mode_paiement, array $paiement_details)
{
    global $db;

    if (!caisse_tables_exist()) {
        return ['ok' => false, 'error' => 'Tables caisse absentes : exécutez la migration create_caisse_tables.sql.'];
    }

    $totals = caisse_compute_totals($cart);
    if ($totals['total'] <= 0 || empty($cart['lines'])) {
        return ['ok' => false, 'error' => 'Panier vide ou total invalide.'];
    }

    $modes_ok = caisse_modes_paiement_valides();
    if (!in_array($mode_paiement, $modes_ok, true)) {
        return ['ok' => false, 'error' => 'Mode de paiement invalide.'];
    }

    $admin_id = (int) $admin_id;
    if ($admin_id <= 0) {
        return ['ok' => false, 'error' => 'Session administrateur invalide.'];
    }

    foreach ($cart['lines'] as $key => $line) {
        $pid = (int) ($line['produit_id'] ?? 0);
        $q = (int) ($line['quantite'] ?? 0);
        if ($pid <= 0 || $q <= 0) {
            return ['ok' => false, 'error' => 'Ligne de panier invalide.'];
        }
        $p = get_produit_by_id($pid);
        if (!$p || ($p['statut'] ?? '') !== 'actif') {
            return ['ok' => false, 'error' => 'Produit introuvable ou inactif.'];
        }
        $stock = (int) ($p['stock'] ?? 0);
        if ($stock < $q) {
            return ['ok' => false, 'error' => 'Stock insuffisant pour « ' . ($p['nom'] ?? '#') . ' ».'];
        }
    }

    $numero_provisoire = caisse_generer_numero_ticket_provisoire();
    $montant_total = $totals['total'];
    $montant_ht = $totals['total_ht'];
    $montant_tva = $totals['montant_tva'];
    $tva_incluse = !empty($cart['inclure_tva']) ? 1 : 0;
    $rg = (float) ($cart['remise_globale_pct'] ?? 0);

    $montant_recu = isset($paiement_details['montant_recu']) ? (float) $paiement_details['montant_recu'] : null;
    $monnaie = isset($paiement_details['monnaie_rendue']) ? (float) $paiement_details['monnaie_rendue'] : null;
    $notes = isset($paiement_details['notes']) ? trim((string) $paiement_details['notes']) : null;

    if (caisse_mode_avec_montant_recu_affiche($mode_paiement) && $montant_recu !== null && $montant_recu + 0.001 < $montant_total) {
        return ['ok' => false, 'error' => 'Montant reçu inférieur au total à payer.'];
    }

    if ($mode_paiement === 'mixte') {
        $sum = caisse_mixte_somme_saisie($paiement_details);
        if ($sum + 0.01 < $montant_total) {
            return ['ok' => false, 'error' => 'La somme des règlements (espèces, carte, Orange Money, Wave) doit couvrir le total à payer.'];
        }
    }

    $montants_db = caisse_normaliser_montants_paiement_pour_db($mode_paiement, $montant_total, $paiement_details);
    $montant_especes = $montants_db['montant_especes'];
    $montant_carte = $montants_db['montant_carte'];
    $montant_orange = $montants_db['montant_orange_money'];
    $montant_wave = $montants_db['montant_wave'];
    $montant_mobile = $montants_db['montant_mobile_money'];

    try {
        $db->beginTransaction();

        $stmt = $db->prepare("
            INSERT INTO caisse_ventes (
                admin_id, caissier_id, numero_ticket, montant_total, montant_ht, montant_tva, tva_incluse, remise_globale_pct, mode_paiement,
                montant_especes, montant_carte, montant_orange_money, montant_wave, montant_mobile_money, montant_recu, monnaie_rendue, notes,
                statut, date_vente, date_encaissement
            ) VALUES (
                :admin_id, :caissier_id, :numero_ticket, :montant_total, :montant_ht, :montant_tva, :tva_incluse, :remise_globale_pct, :mode_paiement,
                :montant_especes, :montant_carte, :montant_orange_money, :montant_wave, :montant_mobile_money, :montant_recu, :monnaie_rendue, :notes,
                'paye', NOW(), NOW()
            )
        ");
        $stmt->execute([
            'admin_id' => $admin_id,
            'caissier_id' => $admin_id,
            'numero_ticket' => $numero_provisoire,
            'montant_total' => $montant_total,
            'montant_ht' => $montant_ht,
            'montant_tva' => $montant_tva,
            'tva_incluse' => $tva_incluse,
            'remise_globale_pct' => $rg,
            'mode_paiement' => $mode_paiement,
            'montant_especes' => $montant_especes,
            'montant_carte' => $montant_carte,
            'montant_orange_money' => $montant_orange,
            'montant_wave' => $montant_wave,
            'montant_mobile_money' => $montant_mobile,
            'montant_recu' => $montant_recu,
            'monnaie_rendue' => $monnaie,
            'notes' => $notes !== '' ? $notes : null,
        ]);
        $vente_id = (int) $db->lastInsertId();
        $numero_final = caisse_ventes_appliquer_numero_officiel($vente_id);
        if ($numero_final === '') {
            $numero_final = $numero_provisoire;
        }

        $stmtL = $db->prepare("
            INSERT INTO caisse_vente_lignes (vente_id, produit_id, designation, quantite, prix_unitaire, remise_ligne_pct, total_ligne)
            VALUES (:vente_id, :produit_id, :designation, :quantite, :prix_unitaire, :remise_ligne_pct, :total_ligne)
        ");

        foreach ($cart['lines'] as $line) {
            $pid = (int) ($line['produit_id'] ?? 0);
            $q = (int) ($line['quantite'] ?? 0);
            $pu = (float) ($line['prix_unitaire'] ?? 0);
            $rl = min(100, max(0, (float) ($line['remise_ligne_pct'] ?? 0)));
            $designation = $line['nom'] ?? '';
            $total_ligne = round($pu * $q * (1 - $rl / 100), 2);

            $stmtL->execute([
                'vente_id' => $vente_id,
                'produit_id' => $pid,
                'designation' => $designation,
                'quantite' => $q,
                'prix_unitaire' => $pu,
                'remise_ligne_pct' => $rl,
                'total_ligne' => $total_ligne,
            ]);

            $produit = get_produit_by_id($pid);
            $quantite_avant = (int) ($produit['stock'] ?? 0);
            decrement_produit_stock($pid, $q);
            $quantite_apres = max(0, $quantite_avant - $q);

            $mv = [
                'type' => 'sortie',
                'produit_id' => $pid,
                'quantite' => $q,
                'quantite_avant' => $quantite_avant,
                'quantite_apres' => $quantite_apres,
                'reference_type' => 'caisse_vente',
                'reference_id' => $vente_id,
                'reference_numero' => $numero_final,
                'notes' => 'Vente caisse',
            ];
            if ($admin_id > 0) {
                $mv['admin_id'] = $admin_id;
            }
            create_stock_mouvement($mv);
        }

        $db->commit();
        return ['ok' => true, 'vente_id' => $vente_id, 'numero_ticket' => $numero_final];
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('[caisse_enregistrer_vente] ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Erreur lors de l\'enregistrement de la vente.'];
    }
}

/**
 * Détail d'une vente pour réimpression ticket
 */
function caisse_get_vente_by_id($vente_id)
{
    global $db;
    if (!caisse_tables_exist() || (int) $vente_id <= 0) {
        return null;
    }
    try {
        $stmt = $db->prepare("
            SELECT v.*, a.nom AS admin_nom, a.prenom AS admin_prenom,
                   c.nom AS caissier_nom, c.prenom AS caissier_prenom
            FROM caisse_ventes v
            LEFT JOIN admin a ON a.id = v.admin_id
            LEFT JOIN admin c ON c.id = v.caissier_id
            WHERE v.id = :id
        ");
        $stmt->execute(['id' => (int) $vente_id]);
        $v = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$v) {
            return null;
        }
        $stmt2 = $db->prepare("SELECT * FROM caisse_vente_lignes WHERE vente_id = :id ORDER BY id ASC");
        $stmt2->execute(['id' => (int) $vente_id]);
        $v['lignes'] = $stmt2->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return $v;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Normalise une saisie « numéro ticket » : espaces supprimés, majuscules (scan / saisie manuelle)
 */
function caisse_normaliser_saisie_numero_ticket($s)
{
    $s = trim((string) $s);
    if ($s === '') {
        return '';
    }
    return strtoupper(preg_replace('/\s+/u', '', $s));
}

/**
 * Détail vente par numéro de ticket : correspondance exacte sur la valeur en base,
 * après normalisation (espaces, casse). Il faut le code complet (ex. TKT20260331000008),
 * comme sous le code-barres — pas seulement la date TKT20260331 ni les 6 derniers chiffres seuls.
 */
function caisse_get_vente_by_numero($numero_ticket)
{
    if (!caisse_tables_exist()) {
        return null;
    }
    $brut = trim((string) $numero_ticket);
    if ($brut === '') {
        return null;
    }
    $compact = caisse_normaliser_saisie_numero_ticket($brut);
    global $db;
    try {
        $stmt = $db->prepare('SELECT id FROM caisse_ventes WHERE numero_ticket = :n OR numero_ticket = :n2 LIMIT 1');
        $stmt->execute(['n' => $compact, 'n2' => $brut]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        return caisse_get_vente_by_id((int) $row['id']);
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Aperçu des tickets en attente d’encaissement (liste caisse)
 *
 * @param int $limit Max. lignes (plafonné)
 * @return list<array<string,mixed>>
 */
function caisse_list_ventes_en_attente_apercu($limit = 200)
{
    if (!caisse_tables_exist()) {
        return [];
    }
    $limit = max(1, min(500, (int) $limit));
    $refSql = caisse_reference_caisse_column_exists() ? ', v.reference' : '';
    global $db;
    try {
        $sql = "
            SELECT v.id, v.numero_ticket, v.montant_total, v.date_vente, v.statut{$refSql},
                   a.nom AS admin_nom, a.prenom AS admin_prenom
            FROM caisse_ventes v
            LEFT JOIN admin a ON a.id = v.admin_id
            WHERE v.statut = 'en_attente'
            ORDER BY v.date_vente ASC, v.id ASC
        ";
        $stmt = $db->prepare($sql . ' LIMIT ' . $limit);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows ?: [];
    } catch (PDOException $e) {
        error_log('[caisse_list_ventes_en_attente_apercu] ' . $e->getMessage());
        return [];
    }
}
