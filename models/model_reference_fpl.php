<?php
/**
 * LA RÉFÉRENCE FPL D'UNE PIÈCE — règle de la direction (07/09/2026).
 *
 *   FPL + code de la CATÉGORIE (3 chiffres) + abréviation de la MARQUE
 *       + les 6 derniers chiffres de la référence OEM
 *   exemple : FPL150MER105116, affichée « FPL150MER 105116 » (fpl_code_afficher).
 *
 *  - Les catégories portent un code rond qui ouvre un bloc : Accessoires 100,
 *    Carrosserie 150, Électricité 200, Moteur 250 (bloc large, 250 à 449),
 *    Suspension 450, Direction 500, Échappement 550, Roues 600, Tableau de
 *    bord 650, Freinage 700, Transmission 750, Cabine 800, Carburant 850,
 *    Châssis 900 ; une famille nouvelle prend le bloc suivant (950…), jamais
 *    au-delà de 999.
 *  - Les sous-catégories sont numérotées À LA SUITE dans le bloc de leur
 *    catégorie (151, 152… pour Carrosserie), dans l'ordre alphabétique de leur
 *    nom au moment du semis ; une nouvelle prend le premier numéro libre. Le
 *    code de la sous-catégorie ne va PAS dans la référence : il sert à voir
 *    d'un coup d'œil qu'un 15x appartient à Carrosserie.
 *  - Les abréviations de marques viennent de la liste de la direction
 *    (marques.pdf : MER, RVI, VOL…) ; une marque hors liste reçoit ses trois
 *    premières lettres, modifiable ensuite.
 *  - Replis quand la donnée manque (mesuré le 07/09 : 18 pièces sur 3 327 ont
 *    leur OEM en colonne, 1 103 n'ont pas de marque) : sans OEM → les 6
 *    derniers chiffres de l'identifiant interne (unique) ; sans marque → XXX.
 *  - LE CODE-BARRES ET LE QR NE CHANGENT PAS : ils restent tirés de
 *    l'identifiant interne numérique (etiquette70_ean12_pour_identifiant).
 *    La référence FPL est une donnée à part (colonne produits.reference_fpl),
 *    recalculée à chaque création / modification de pièce.
 */

require_once __DIR__ . '/../conn/conn.php';

/* --------------------------------------------------------------------- */
/*  SCHÉMA                                                                 */
/* --------------------------------------------------------------------- */

/** Une colonne existe-t-elle ? (petit cache par requête) */
function rf_colonne_ok($table, $colonne)
{
    global $db;
    static $cache = [];
    $k = $table . '.' . $colonne;
    if (!isset($cache[$k])) {
        try {
            $st = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS
                                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c");
            $st->execute([':t' => $table, ':c' => $colonne]);
            $cache[$k] = (int) $st->fetchColumn() > 0;
        } catch (PDOException $e) {
            $cache[$k] = false;
        }
    }
    return $cache[$k];
}

/** Tout le schéma de la règle est-il en place ? */
function reference_fpl_schema_ok()
{
    return rf_colonne_ok('categories', 'code') && rf_colonne_ok('sous_categories', 'code')
        && rf_colonne_ok('marques', 'abreviation') && rf_colonne_ok('produits', 'reference_fpl');
}

/** Pose les colonnes manquantes (idempotent). Renvoie la liste de ce qui a été ajouté. */
function reference_fpl_schema_poser()
{
    global $db;
    $faits = [];
    $plan = [
        ['categories', 'code', "ALTER TABLE categories ADD COLUMN code SMALLINT UNSIGNED NULL DEFAULT NULL COMMENT 'code FPL de la famille (100, 150…)' AFTER nom"],
        ['sous_categories', 'code', "ALTER TABLE sous_categories ADD COLUMN code SMALLINT UNSIGNED NULL DEFAULT NULL COMMENT 'numéro dans le bloc de la famille (151, 152…)' AFTER nom"],
        ['marques', 'abreviation', "ALTER TABLE marques ADD COLUMN abreviation VARCHAR(4) NULL DEFAULT NULL COMMENT 'abréviation dans la référence FPL (MER, RVI…)' AFTER nom"],
        ['produits', 'reference_fpl', "ALTER TABLE produits ADD COLUMN reference_fpl VARCHAR(24) NULL DEFAULT NULL COMMENT 'FPL + code famille + marque + 6 chiffres OEM' AFTER identifiant_interne, ADD INDEX idx_reference_fpl (reference_fpl)"],
    ];
    foreach ($plan as [$t, $c, $sql]) {
        if (!rf_colonne_ok($t, $c)) {
            $db->exec($sql);
            $faits[] = "$t.$c";
        }
    }
    return $faits;
}

/* --------------------------------------------------------------------- */
/*  LE BARÈME DES CATÉGORIES                                               */
/* --------------------------------------------------------------------- */

/** Le barème validé par la direction : nom normalisé → code. */
function reference_fpl_bareme_categories()
{
    return [
        'accessoires et equipements' => 100,
        'carrosserie' => 150,
        'electricite et l eclairage' => 200,
        'electricite et eclairage' => 200,
        'moteur' => 250,
        'suspension' => 450,
        'direction' => 500,
        'echappement' => 550,
        'roues et pneus' => 600,
        'tableau de bord' => 650,
        'systeme de freinage' => 700,
        'transmissions' => 750,
        'transmission' => 750,
        'cabine' => 800,
        'carburant' => 850,
        'chassis' => 900,
    ];
}

/** Un nom sans accents, sans ponctuation, en minuscules, espaces simples. */
function rf_normaliser_nom($nom)
{
    $s = (string) $nom;
    /* Une base importée avec le mauvais jeu de caractères peut porter des noms
     * abîmés (« Ã‰chappement ») : on les répare avant de comparer. */
    if (preg_match('/Ã.|Â./u', $s) && function_exists('mb_convert_encoding')) {
        /* le texte a été lu en Windows-1252 puis ré-encodé : on refait le chemin inverse */
        foreach (['Windows-1252', 'ISO-8859-1'] as $cp) {
            $r = @mb_convert_encoding($s, $cp, 'UTF-8');
            if ($r !== false && $r !== '' && preg_match('//u', $r) && !preg_match('/Ã.|Â./u', $r)) {
                $s = $r;
                break;
            }
        }
    }
    /* les accents, sans dépendre d'iconv (qui rend « ? » sous Windows) */
    $s = strtr($s, [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a', 'À' => 'a', 'Á' => 'a', 'Â' => 'a', 'Ä' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'É' => 'e', 'È' => 'e', 'Ê' => 'e', 'Ë' => 'e',
        'î' => 'i', 'ï' => 'i', 'í' => 'i', 'Î' => 'i', 'Ï' => 'i',
        'ô' => 'o', 'ö' => 'o', 'ó' => 'o', 'õ' => 'o', 'Ô' => 'o', 'Ö' => 'o',
        'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ú' => 'u', 'Ù' => 'u', 'Û' => 'u', 'Ü' => 'u',
        'ç' => 'c', 'Ç' => 'c', 'ñ' => 'n', 'Ñ' => 'n', 'œ' => 'oe', 'Œ' => 'oe', 'æ' => 'ae', 'Æ' => 'ae',
    ]);
    $s = strtolower($s);
    $s = preg_replace('/[^a-z0-9]+/', ' ', $s);
    return trim(preg_replace('/\s+/', ' ', $s));
}

/** Le prochain bloc libre pour une nouvelle famille (multiple de 50, ≥ 100, < 1000). */
function categorie_code_prochain()
{
    global $db;
    $max = (int) $db->query("SELECT COALESCE(MAX(code), 50) FROM categories WHERE code IS NOT NULL")->fetchColumn();
    $suivant = (int) (floor($max / 50) * 50) + 50;
    if ($suivant < 100) {
        $suivant = 100;
    }
    return $suivant <= 999 ? $suivant : null;
}

/** La fin du bloc d'une catégorie : le code de la catégorie suivante moins 1 (borné à 999). */
function categorie_bloc_fin($code)
{
    global $db;
    $st = $db->prepare("SELECT MIN(code) FROM categories WHERE code > :c AND code IS NOT NULL");
    $st->execute([':c' => (int) $code]);
    $suivant = $st->fetchColumn();
    return $suivant ? ((int) $suivant - 1) : 999;
}

/**
 * Le prochain numéro libre pour une sous-catégorie de cette famille
 * (code de la famille + 1 … fin du bloc). null si la famille n'a pas de code
 * ou si son bloc est plein.
 */
function sous_categorie_code_prochain($categorie_id)
{
    global $db;
    $st = $db->prepare("SELECT code FROM categories WHERE id = :id");
    $st->execute([':id' => (int) $categorie_id]);
    $code = $st->fetchColumn();
    if (!$code) {
        return null;
    }
    $code = (int) $code;
    $fin = categorie_bloc_fin($code);
    $pris = $db->prepare("SELECT code FROM sous_categories WHERE categorie_id = :c AND code IS NOT NULL AND sync_deleted_at IS NULL");
    $pris->execute([':c' => (int) $categorie_id]);
    $occupes = array_map('intval', $pris->fetchAll(PDO::FETCH_COLUMN));
    for ($n = $code + 1; $n <= $fin; $n++) {
        if (!in_array($n, $occupes, true)) {
            return $n;
        }
    }
    return null;
}

/**
 * Sème les codes des sous-catégories SANS code d'une famille, dans l'ordre
 * alphabétique de leur nom, à la suite de ce qui est déjà pris. Renvoie le
 * nombre posé ; -1 si le bloc est plein avant la fin.
 */
function sous_categories_semer_codes($categorie_id)
{
    global $db;
    $st = $db->prepare("SELECT id, nom FROM sous_categories WHERE categorie_id = :c AND (code IS NULL OR code = 0) AND sync_deleted_at IS NULL ORDER BY nom COLLATE utf8mb4_unicode_ci, id");
    $st->execute([':c' => (int) $categorie_id]);
    $maj = $db->prepare("UPDATE sous_categories SET code = :code WHERE id = :id");
    $n = 0;
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $sc) {
        $code = sous_categorie_code_prochain($categorie_id);
        if ($code === null) {
            return -1;
        }
        $maj->execute([':code' => $code, ':id' => (int) $sc['id']]);
        $n++;
    }
    return $n;
}

/* --------------------------------------------------------------------- */
/*  LES ABRÉVIATIONS DE MARQUES                                            */
/* --------------------------------------------------------------------- */

/** La liste de la direction (marques.pdf, 07/09) : nom normalisé → abréviation. */
function reference_fpl_abreviations_direction()
{
    return [
        'beiben' => 'BEI', 'camc' => 'CAM', 'caterpillar' => 'CAT', 'cummins' => 'CUM', 'daf' => 'DAF',
        'deutz' => 'DEU', 'dongfeng' => 'DON', 'faw' => 'FAW', 'ford' => 'FRD', 'foton' => 'FTN',
        'fruehauf' => 'FRU', 'hino' => 'HIN', 'howo' => 'HW', 'isuzu' => 'ISU', 'iveco' => 'IVC',
        'john deere' => 'JD', 'kamaz' => 'KMZ', 'kassbohrer' => 'KAS', 'mack' => 'MAC', 'man' => 'MN',
        'mercedes' => 'ME', 'mercedes benz' => 'ME',   /* ME, pas MER (direction, 08/09) */ 'perkins' => 'PER', 'renault rvi' => 'RVI',
        'rvi renault' => 'RVI', 'rvi' => 'RVI', 'renault' => 'RVI', 'saf' => 'SAF', 'scania' => 'SCA',
        'shacman' => 'SHA', 'smb' => 'SMB', 'tata' => 'TAT', 'trailor' => 'TRA', 'volvo' => 'VOL',
        'weichai' => 'WEI', 'yuchai' => 'YUC', 'yutong' => 'YUT',
        /* hors liste, proposées le 07/09 (3 lettres, sans collision) */
        'baldwin' => 'BAL', 'berliet' => 'BER', 'bmc pro' => 'BMC', 'bpw' => 'BPW', 'dana' => 'DAN',
        'donaldson' => 'DNL', 'fleetguard' => 'FLE', 'fuwa' => 'FUW', 'genlyon hongyan' => 'GEN',
        'hengst' => 'HEN', 'libebherr' => 'LIE', 'liebherr' => 'LIE', 'meritor' => 'MRT',
        'mitsubishi' => 'MIT', 'sany' => 'SAN', 'toyota' => 'TOY', 'volkswagen' => 'VWG',
        'ror' => 'ROR', 'york' => 'YOR',
    ];
}

/** Les marques de la liste de la direction (marques.pdf), noms normalisés : jamais retirées. */
function reference_fpl_marques_direction()
{
    return ['beiben', 'camc', 'caterpillar', 'cummins', 'daf', 'deutz', 'dongfeng', 'faw', 'ford', 'foton',
        'fruehauf', 'hino', 'howo', 'isuzu', 'iveco', 'john deere', 'kamaz', 'kassbohrer', 'mack', 'man',
        'mercedes', 'mercedes benz', 'perkins', 'renault rvi', 'rvi renault', 'rvi', 'saf', 'scania',
        'shacman', 'smb', 'tata', 'trailor', 'volvo', 'weichai', 'yuchai', 'yutong'];
}

/** Une abréviation proposée pour un nom de marque (liste de la direction, sinon 3 lettres), unique. */
function marque_abreviation_proposer($nom, $sauf_id = 0)
{
    global $db;
    $n = rf_normaliser_nom($nom);
    $liste = reference_fpl_abreviations_direction();
    $base = isset($liste[$n]) ? $liste[$n] : strtoupper(substr(preg_replace('/[^a-z]/', '', $n), 0, 3));
    if ($base === '') {
        $base = 'XXX';
    }
    $st = $db->prepare("SELECT COUNT(*) FROM marques WHERE abreviation = :a AND id <> :id AND sync_deleted_at IS NULL");
    $cand = $base;
    $i = 2;
    while (true) {
        $st->execute([':a' => $cand, ':id' => (int) $sauf_id]);
        if ((int) $st->fetchColumn() === 0) {
            return $cand;
        }
        $cand = substr($base, 0, 3) . $i; // BAL2, BAL3…
        $i++;
        if ($i > 9) {
            return substr($base, 0, 2) . chr(64 + $i);
        }
    }
}

/* --------------------------------------------------------------------- */
/*  LA RÉFÉRENCE D'UNE PIÈCE                                               */
/* --------------------------------------------------------------------- */

/**
 * Calcule la référence FPL d'une pièce à partir de sa ligne (categorie_id,
 * marque_id, reference_oem, identifiant_interne). Renvoie
 * ['reference' => 'FPL150MER105116', 'oem_manquant' => bool, 'marque_manquante' => bool,
 *  'categorie_sans_code' => bool] ; 'reference' vaut '' si la catégorie n'a pas de code.
 */
/** À appeler après un changement de code de catégorie ou d'abréviation de marque. */
function produit_reference_fpl_cache_vider()
{
    $GLOBALS['rf_cache_version'] = (int) ($GLOBALS['rf_cache_version'] ?? 0) + 1;
}

function produit_reference_fpl_calculer(array $p)
{
    global $db;
    static $codes = null;
    static $abrs = null;
    static $version = -1;
    $version_courante = (int) ($GLOBALS['rf_cache_version'] ?? 0);
    if ($codes === null || $version !== $version_courante) {
        $version = $version_courante;
        $codes = [];
        foreach ($db->query("SELECT id, code FROM categories") as $r) {
            $codes[(int) $r['id']] = $r['code'] !== null ? (int) $r['code'] : null;
        }
        $abrs = [];
        foreach ($db->query("SELECT id, abreviation FROM marques") as $r) {
            $abrs[(int) $r['id']] = trim((string) $r['abreviation']);
        }
    }
    $out = ['reference' => '', 'oem_manquant' => false, 'marque_manquante' => false, 'categorie_sans_code' => false];
    $cid = (int) ($p['categorie_id'] ?? 0);
    $code = $codes[$cid] ?? null;
    if (!$code) {
        $out['categorie_sans_code'] = true;
        return $out;
    }
    $mid = (int) ($p['marque_id'] ?? 0);
    $abr = ($mid > 0 && !empty($abrs[$mid])) ? $abrs[$mid] : '';
    if ($abr === '') {
        $abr = 'XXX';
        $out['marque_manquante'] = true;
    }
    $oem = preg_replace('/\D+/', '', (string) ($p['reference_oem'] ?? ''));
    if ($oem === '') {
        $out['oem_manquant'] = true;
        $oem = preg_replace('/\D+/', '', (string) ($p['identifiant_interne'] ?? ''));
        if ($oem === '') {
            $oem = (string) (int) ($p['id'] ?? 0);
        }
    }
    $suffixe = str_pad(substr($oem, -6), 6, '0', STR_PAD_LEFT);
    $out['reference'] = 'FPL' . str_pad((string) $code, 3, '0', STR_PAD_LEFT) . $abr . $suffixe;
    return $out;
}

/**
 * Recalcule et enregistre la référence d'une pièce (si elle change).
 * Renvoie la référence, '' si non calculable, null si le schéma manque.
 */
function produit_reference_fpl_maj($produit_id)
{
    global $db;
    if (!reference_fpl_schema_ok()) {
        return null;
    }
    $st = $db->prepare("SELECT id, identifiant_interne, categorie_id, marque_id, reference_oem, reference_fpl FROM produits WHERE id = :id");
    $st->execute([':id' => (int) $produit_id]);
    $p = $st->fetch(PDO::FETCH_ASSOC);
    if (!$p) {
        return '';
    }
    $calc = produit_reference_fpl_calculer($p);
    $ref = $calc['reference'];
    if ($ref !== (string) ($p['reference_fpl'] ?? '')) {
        $db->prepare("UPDATE produits SET reference_fpl = :r WHERE id = :id")
           ->execute([':r' => $ref !== '' ? $ref : null, ':id' => (int) $produit_id]);
    }
    return $ref;
}

/**
 * Recalcule TOUTES les pièces (migration, ou après un changement de barème /
 * d'abréviation). Renvoie des compteurs.
 */
function produits_reference_fpl_recalculer_tout()
{
    global $db;
    $stats = ['total' => 0, 'posees' => 0, 'changees' => 0, 'sans_code' => 0, 'oem_manquant' => 0, 'marque_manquante' => 0];
    $maj = $db->prepare("UPDATE produits SET reference_fpl = :r WHERE id = :id");
    foreach ($db->query("SELECT id, identifiant_interne, categorie_id, marque_id, reference_oem, reference_fpl FROM produits WHERE sync_deleted_at IS NULL") as $p) {
        $stats['total']++;
        $c = produit_reference_fpl_calculer($p);
        if ($c['categorie_sans_code']) {
            $stats['sans_code']++;
            continue;
        }
        $stats['posees']++;
        if ($c['oem_manquant']) {
            $stats['oem_manquant']++;
        }
        if ($c['marque_manquante']) {
            $stats['marque_manquante']++;
        }
        if ($c['reference'] !== (string) ($p['reference_fpl'] ?? '')) {
            $maj->execute([':r' => $c['reference'], ':id' => (int) $p['id']]);
            $stats['changees']++;
        }
    }
    return $stats;
}

/**
 * Récupère la référence OEM écrite dans le texte de description
 * (« Référence OEM : C331015 », « Reference om: Nr.5001863728 »…) pour les
 * pièces dont la colonne reference_oem est vide. Renvoie le nombre rempli.
 */
function produits_oem_recuperer_des_descriptions()
{
    global $db;
    $n = 0;
    $maj = $db->prepare("UPDATE produits SET reference_oem = :o WHERE id = :id");
    $rows = $db->query("SELECT id, description FROM produits
                         WHERE (reference_oem IS NULL OR reference_oem = '')
                           AND description REGEXP 'R[ée]f[ée]rence ?(OM|OEM) ?:'")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        if (preg_match('/R[ée]f[ée]rence\s?(?:OM|OEM)\s?:\s*(?:Nr\.?\s*)?([A-Za-z0-9][A-Za-z0-9 .\/-]{2,40})/iu', (string) $r['description'], $m)) {
            $oem = trim($m[1]);
            $oem = preg_replace('/\s+(Longueur|Diam|Dimension|Largeur|Hauteur|Mod[eè]le|Marque).*$/iu', '', $oem);
            $oem = trim($oem, " .-/");
            if (preg_match('/\d{4,}/', $oem)) {
                $maj->execute([':o' => mb_substr($oem, 0, 60), ':id' => (int) $r['id']]);
                $n++;
            }
        }
    }
    return $n;
}
