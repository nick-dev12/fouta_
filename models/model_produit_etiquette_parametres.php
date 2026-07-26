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

    $w = (float) $d['largeur_mm'];
    $h = (float) $d['hauteur_mm'];
    $sx = round($w / 70.0, 5);
    $sy = round($h / 70.0, 5);
    $s = min($sx, $sy);
    $dots_w = (int) round($w * 8);
    $dots_h = (int) round($h * 8);

    $d['sx'] = $sx;
    $d['sy'] = $sy;
    $d['s'] = $s;
    $d['dots_w'] = $dots_w;
    $d['dots_h'] = $dots_h;
    $d['label'] = fpl_etiquette_dims_label($d);
    $d['meta'] = 'Format d’impression ' . fpl_etiquette_dims_label_short($d)
        . ' · Zebra ZD420 (203 dpi ≈ 8 dots/mm · ' . $dots_w . '×' . $dots_h . ' dots) · Aperçu agrandi à l’écran';

    return $d;
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
