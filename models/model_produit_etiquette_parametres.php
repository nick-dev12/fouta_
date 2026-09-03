<?php
/**
 * Paramètres d’impression des étiquettes produit FPL (dimensions mm).
 * Maquette de référence : 70 × 70 mm (Zebra ZD420).
 */
require_once __DIR__ . '/../conn/conn.php';

/**
 * @return array{largeur_mm: float, hauteur_mm: float}
 */
function fpl_etiquette_dims_defaut() {
    return [
        'largeur_mm' => 70.0,
        'hauteur_mm' => 70.0,
    ];
}

/**
 * @return bool
 */
function fpl_etiquette_parametres_schema_ok($force_refresh = false) {
    global $db;
    if (!$db) {
        return false;
    }
    static $ok = null;
    if ($force_refresh) {
        $ok = null;
    }
    if ($ok !== null) {
        return $ok;
    }
    try {
        $db->query('SELECT largeur_mm, hauteur_mm FROM produit_etiquette_parametres LIMIT 1');
        $ok = true;
    } catch (PDOException $e) {
        $ok = false;
    }

    return $ok;
}

/**
 * @return bool
 */
function fpl_etiquette_parametres_ensure_schema() {
    if (fpl_etiquette_parametres_schema_ok()) {
        return true;
    }
    $runner = __DIR__ . '/../migrations/run_create_produit_etiquette_parametres.php';
    if (!is_file($runner)) {
        return false;
    }
    ob_start();
    include $runner;
    ob_end_clean();

    return fpl_etiquette_parametres_schema_ok(true);
}

/**
 * @param mixed $v
 * @param float $min
 * @param float $max
 * @param float $fallback
 * @return float
 */
function fpl_etiquette_mm_clamp($v, $min, $max, $fallback) {
    if (!is_numeric($v)) {
        return $fallback;
    }
    $n = round((float) $v, 1);
    if ($n < $min) {
        return $min;
    }
    if ($n > $max) {
        return $max;
    }

    return $n;
}

/**
 * Dimensions effectives + facteurs d’échelle (réf. 70×70).
 *
 * @return array{
 *   largeur_mm: float,
 *   hauteur_mm: float,
 *   sx: float,
 *   sy: float,
 *   s: float,
 *   label: string,
 *   meta: string,
 *   dots_w: int,
 *   dots_h: int
 * }
 */
function fpl_etiquette_dims() {
    $d = fpl_etiquette_dims_defaut();
    if (fpl_etiquette_parametres_ensure_schema()) {
        global $db;
        try {
            $row = $db->query('SELECT largeur_mm, hauteur_mm FROM produit_etiquette_parametres WHERE id = 1 LIMIT 1')
                ->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $d['largeur_mm'] = fpl_etiquette_mm_clamp($row['largeur_mm'] ?? null, 30, 200, $d['largeur_mm']);
                $d['hauteur_mm'] = fpl_etiquette_mm_clamp($row['hauteur_mm'] ?? null, 30, 200, $d['hauteur_mm']);
            }
        } catch (PDOException $e) {
            // defaults
        }
    }

    return fpl_etiquette_dims_finalize($d);
}

/**
 * @param array<string, mixed> $d
 * @return string
 */
function fpl_etiquette_dims_fmt_mm($n) {
    $n = (float) $n;
    if (abs($n - round($n)) < 0.05) {
        return (string) (int) round($n);
    }

    return rtrim(rtrim(number_format($n, 1, '.', ''), '0'), '.');
}

/**
 * @param array<string, mixed> $d
 * @return string
 */
function fpl_etiquette_dims_label_short(array $d) {
    return fpl_etiquette_dims_fmt_mm($d['largeur_mm'] ?? 70) . ' × ' . fpl_etiquette_dims_fmt_mm($d['hauteur_mm'] ?? 70) . ' mm';
}

/**
 * @param array<string, mixed> $d
 * @return string
 */
function fpl_etiquette_dims_label(array $d) {
    return 'Étiquette FPL ' . fpl_etiquette_dims_fmt_mm($d['largeur_mm'] ?? 70)
        . '×' . fpl_etiquette_dims_fmt_mm($d['hauteur_mm'] ?? 70) . ' mm';
}

/**
 * @param array<string, mixed> $data
 * @return array{success: bool, message: string}
 */
function fpl_etiquette_parametres_save(array $data) {
    global $db;
    if (!$db || !fpl_etiquette_parametres_ensure_schema()) {
        return ['success' => false, 'message' => 'Table des paramètres étiquettes produit indisponible.'];
    }
    $def = fpl_etiquette_dims_defaut();
    $w = fpl_etiquette_mm_clamp($data['largeur_mm'] ?? null, 30, 200, $def['largeur_mm']);
    $h = fpl_etiquette_mm_clamp($data['hauteur_mm'] ?? null, 30, 200, $def['hauteur_mm']);

    try {
        $st = $db->prepare(
            'INSERT INTO produit_etiquette_parametres (id, largeur_mm, hauteur_mm, date_modification)
             VALUES (1, :w, :h, NOW())
             ON DUPLICATE KEY UPDATE
               largeur_mm = VALUES(largeur_mm),
               hauteur_mm = VALUES(hauteur_mm),
               date_modification = NOW()'
        );
        $st->execute([':w' => $w, ':h' => $h]);

        return [
            'success' => true,
            'message' => 'Dimensions d’étiquette produit enregistrées ('
                . fpl_etiquette_dims_label(['largeur_mm' => $w, 'hauteur_mm' => $h]) . ').',
        ];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Erreur : ' . $e->getMessage()];
    }
}

/**
 * Bloc &lt;style&gt; CSS variables à injecter.
 *
 * @param array<string, mixed>|null $dims
 * @return string
 */
function fpl_etiquette_dims_style_block($dims = null) {
    if (!is_array($dims)) {
        $dims = fpl_etiquette_dims();
    }
    $w = (float) ($dims['largeur_mm'] ?? 70);
    $h = (float) ($dims['hauteur_mm'] ?? 70);
    $sx = (float) ($dims['sx'] ?? ($w / 70));
    $sy = (float) ($dims['sy'] ?? ($h / 70));
    $s = (float) ($dims['s'] ?? min($sx, $sy));

    return '<style id="fpl-etiq-dims-vars">'
        . ':root{'
        . '--fpl-w:' . $w . 'mm;'
        . '--fpl-h:' . $h . 'mm;'
        . '--fpl-sx:' . $sx . ';'
        . '--fpl-sy:' . $sy . ';'
        . '--fpl-s:' . $s . ';'
        . '}'
        . '</style>';
}

/**
 * Attributs data-* pour l’impression JS.
 *
 * @param array<string, mixed>|null $dims
 * @return string
 */
function fpl_etiquette_dims_data_attrs($dims = null) {
    if (!is_array($dims)) {
        $dims = fpl_etiquette_dims();
    }
    $w = htmlspecialchars((string) (float) ($dims['largeur_mm'] ?? 70), ENT_QUOTES, 'UTF-8');
    $h = htmlspecialchars((string) (float) ($dims['hauteur_mm'] ?? 70), ENT_QUOTES, 'UTF-8');
    $sx = htmlspecialchars((string) (float) ($dims['sx'] ?? 1), ENT_QUOTES, 'UTF-8');
    $sy = htmlspecialchars((string) (float) ($dims['sy'] ?? 1), ENT_QUOTES, 'UTF-8');

    return 'data-fpl-w="' . $w . '" data-fpl-h="' . $h . '" data-fpl-sx="' . $sx . '" data-fpl-sy="' . $sy . '"';
}

/* =====================================================================
 *  LES FORMATS D'ÉTIQUETTE (24/08) — les tailles proposées à l'impression,
 *  comme chez FPL natif. La table `etiquette_formats` vient de
 *  migrations/run_etiquette_formats.php ; sans elle, tout continue de
 *  marcher à la taille unique des réglages.
 * ===================================================================== */

/** La table des formats est-elle là ? (une vérification par requête) */
function fpl_etiquette_formats_table_ok() {
    static $ok = null;
    global $db;
    if ($ok === null) {
        $ok = false;
        try {
            $s = $db->query("SELECT COUNT(*) FROM information_schema.TABLES
                             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'etiquette_formats'");
            $ok = (int) $s->fetchColumn() > 0;
        } catch (PDOException $e) {
            $ok = false;
        }
    }
    return $ok;
}

/** Les formats d'étiquette de PIÈCE, dans l'ordre. */
function fpl_etiquette_formats_pieces() {
    global $db;
    if (!fpl_etiquette_formats_table_ok()) {
        return [];
    }
    try {
        return $db->query("SELECT * FROM etiquette_formats
                           WHERE type = 'piece' AND sync_deleted_at IS NULL
                           ORDER BY ordre, id")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/** Un format par id, borné à un type. */
function fpl_etiquette_format_get($id, $type = 'piece') {
    global $db;
    if (!fpl_etiquette_formats_table_ok()) {
        return false;
    }
    try {
        $st = $db->prepare("SELECT * FROM etiquette_formats
                            WHERE id = :id AND type = :type AND sync_deleted_at IS NULL");
        $st->execute(['id' => (int) $id, 'type' => (string) $type]);
        $f = $st->fetch(PDO::FETCH_ASSOC);
        return $f ?: false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * LE FORMAT À SERVIR — résolution partagée par le PDF et l'image d'étiquette
 * (avant, chacun refaisait la sienne ; l'image ne la faisait pas du tout et
 * les pastilles de taille de la fiche ne changeaient rien à l'écran) :
 * le format demandé, sinon celui dont les mm sont ceux du réglage, sinon le
 * premier de la table, sinon le réglage lui-même en format de fortune.
 *
 * @param int $format_id id demandé (0 = aucun)
 * @return array{id:int, nom:string, largeur_mm:float, hauteur_mm:float}
 */
function fpl_etiquette_format_ou_reglage($format_id) {
    $format = $format_id > 0 ? fpl_etiquette_format_get((int) $format_id, 'piece') : false;
    if ($format === false) {
        $formats = fpl_etiquette_formats_pieces();
        $reglage = fpl_etiquette_dims();
        foreach ($formats as $fx) {
            if (abs((float) $fx['largeur_mm'] - (float) $reglage['largeur_mm']) < 0.01
                && abs((float) $fx['hauteur_mm'] - (float) $reglage['hauteur_mm']) < 0.01) {
                $format = $fx;
                break;
            }
        }
        if ($format === false) {
            $format = $formats[0] ?? false;
        }
        if ($format === false) {
            $format = ['id' => 0, 'nom' => fpl_etiquette_dims_label_short($reglage),
                'largeur_mm' => $reglage['largeur_mm'], 'hauteur_mm' => $reglage['hauteur_mm']];
        }
    }
    return ['id' => (int) ($format['id'] ?? 0), 'nom' => (string) $format['nom'],
        'largeur_mm' => (float) $format['largeur_mm'], 'hauteur_mm' => (float) $format['hauteur_mm']];
}

/**
 * Le calcul commun d'échelle + méta d'une étiquette, à partir des mm déjà
 * posés dans $d — partagé par fpl_etiquette_dims() (le réglage) et
 * fpl_etiquette_dims_pour_mm() (un format choisi). Référence : 70 mm à
 * l'échelle 1, 8 dots/mm (Zebra ZD420, 203 dpi).
 *
 * @param array<string, mixed> $d doit porter largeur_mm et hauteur_mm
 * @return array<string, mixed>
 */
function fpl_etiquette_dims_finalize(array $d) {
    $w = (float) $d['largeur_mm'];
    $h = (float) $d['hauteur_mm'];
    $sx = round($w / 70.0, 5);
    $sy = round($h / 70.0, 5);
    $dots_w = (int) round($w * 8);
    $dots_h = (int) round($h * 8);

    $d['sx'] = $sx;
    $d['sy'] = $sy;
    $d['s'] = min($sx, $sy);
    $d['dots_w'] = $dots_w;
    $d['dots_h'] = $dots_h;
    $d['label'] = fpl_etiquette_dims_label($d);
    $d['meta'] = 'Format d’impression ' . fpl_etiquette_dims_label_short($d)
        . ' · Zebra ZD420 (203 dpi ≈ 8 dots/mm · ' . $dots_w . '×' . $dots_h . ' dots) · Aperçu agrandi à l’écran';

    return $d;
}

/**
 * Les dimensions d'étiquette pour DES MM DONNÉS — le même calcul que
 * fpl_etiquette_dims(), mais depuis un format choisi plutôt que le réglage.
 */
function fpl_etiquette_dims_pour_mm($largeur_mm, $hauteur_mm) {
    $d = fpl_etiquette_dims_defaut();
    $d['largeur_mm'] = fpl_etiquette_mm_clamp($largeur_mm, 30, 200, $d['largeur_mm']);
    $d['hauteur_mm'] = fpl_etiquette_mm_clamp($hauteur_mm, 30, 200, $d['hauteur_mm']);

    return fpl_etiquette_dims_finalize($d);
}

/** Ajoute un format de pièce (nom auto « L × H mm »). */
function fpl_etiquette_format_ajouter($largeur_mm, $hauteur_mm) {
    global $db;
    if (!fpl_etiquette_formats_table_ok()) {
        return ['success' => false, 'message' => 'La table des formats n\'est pas installée.'];
    }
    $l = fpl_etiquette_mm_clamp($largeur_mm, 30, 200, 0);
    $h = fpl_etiquette_mm_clamp($hauteur_mm, 30, 200, 0);
    if ($l <= 0 || $h <= 0) {
        return ['success' => false, 'message' => 'Largeur et hauteur : entre 30 et 200 mm.'];
    }
    $nom = fpl_etiquette_dims_fmt_mm($l) . ' × ' . fpl_etiquette_dims_fmt_mm($h) . ' mm';
    try {
        $doublon = $db->prepare("SELECT COUNT(*) FROM etiquette_formats
                                 WHERE type = 'piece' AND sync_deleted_at IS NULL
                                   AND ABS(largeur_mm - :l) < 0.01 AND ABS(hauteur_mm - :h) < 0.01");
        $doublon->execute(['l' => $l, 'h' => $h]);
        if ((int) $doublon->fetchColumn() > 0) {
            return ['success' => false, 'message' => 'Cette taille existe déjà dans la liste.'];
        }
        $ordre = (int) $db->query("SELECT COALESCE(MAX(ordre), 0) + 1 FROM etiquette_formats WHERE type = 'piece'")->fetchColumn();
        $st = $db->prepare("INSERT INTO etiquette_formats
            (nom, type, largeur_mm, hauteur_mm, est_systeme, ordre, date_creation, date_modification, sync_uuid)
            VALUES (:nom, 'piece', :l, :h, 0, :o, NOW(), NOW(), UUID())");
        $st->execute(['nom' => $nom, 'l' => $l, 'h' => $h, 'o' => $ordre]);
        return ['success' => true, 'message' => 'Taille « ' . $nom . ' » ajoutée.'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'L\'ajout a échoué — réessayez.'];
    }
}

/** Retire un format (suppression DOUCE ; les tailles d'origine sont protégées). */
function fpl_etiquette_format_retirer($id) {
    global $db;
    if (!fpl_etiquette_formats_table_ok()) {
        return ['success' => false, 'message' => 'La table des formats n\'est pas installée.'];
    }
    $f = fpl_etiquette_format_get((int) $id, 'piece');
    if ($f === false) {
        return ['success' => false, 'message' => 'Ce format n\'existe pas.'];
    }
    if ((int) ($f['est_systeme'] ?? 0) === 1) {
        return ['success' => false, 'message' => 'Les tailles d\'origine ne se retirent pas.'];
    }
    try {
        $st = $db->prepare("UPDATE etiquette_formats SET sync_deleted_at = NOW(), date_modification = NOW() WHERE id = :id");
        $st->execute(['id' => (int) $id]);
        return ['success' => true, 'message' => 'Taille « ' . (string) $f['nom'] . ' » retirée.'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Le retrait a échoué — réessayez.'];
    }
}
