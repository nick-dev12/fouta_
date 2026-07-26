<?php
/**
 * Paramètres d’impression des étiquettes barre / nœud entrepôt (dimensions mm).
 */
require_once __DIR__ . '/../conn/conn.php';

/**
 * Valeurs par défaut (90 × 40 mm).
 *
 * @return array{largeur_mm: float, hauteur_mm: float, qr_mm: float, texte_mm: float}
 */
function entrepot_etiquette_dims_defaut() {
    return [
        'largeur_mm' => 90.0,
        'hauteur_mm' => 40.0,
        'qr_mm' => 30.0,
        'texte_mm' => 11.0,
    ];
}

/**
 * @return bool
 */
function entrepot_etiquette_parametres_schema_ok($force_refresh = false) {
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
        $db->query('SELECT largeur_mm, hauteur_mm, qr_mm, texte_mm FROM entrepot_etiquette_parametres LIMIT 1');
        $ok = true;
    } catch (PDOException $e) {
        $ok = false;
    }

    return $ok;
}

/**
 * @return bool
 */
function entrepot_etiquette_parametres_ensure_schema() {
    if (entrepot_etiquette_parametres_schema_ok()) {
        return true;
    }
    $runner = __DIR__ . '/../migrations/run_create_entrepot_etiquette_parametres.php';
    if (!is_file($runner)) {
        return false;
    }
    ob_start();
    include $runner;
    ob_end_clean();

    return entrepot_etiquette_parametres_schema_ok(true);
}

/**
 * @param mixed $v
 * @param float $min
 * @param float $max
 * @param float $fallback
 * @return float
 */
function entrepot_etiquette_mm_clamp($v, $min, $max, $fallback) {
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
 * Dimensions effectives pour aperçu / impression / PDF.
 *
 * @return array{largeur_mm: float, hauteur_mm: float, qr_mm: float, texte_mm: float, label: string}
 */
function entrepot_etiquette_dims() {
    $d = entrepot_etiquette_dims_defaut();
    if (!entrepot_etiquette_parametres_ensure_schema()) {
        $d['label'] = entrepot_etiquette_dims_label($d);

        return $d;
    }
    global $db;
    try {
        $row = $db->query('SELECT largeur_mm, hauteur_mm, qr_mm, texte_mm FROM entrepot_etiquette_parametres WHERE id = 1 LIMIT 1')
            ->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $d['largeur_mm'] = entrepot_etiquette_mm_clamp($row['largeur_mm'] ?? null, 20, 200, $d['largeur_mm']);
            $d['hauteur_mm'] = entrepot_etiquette_mm_clamp($row['hauteur_mm'] ?? null, 15, 150, $d['hauteur_mm']);
            $d['qr_mm'] = entrepot_etiquette_mm_clamp($row['qr_mm'] ?? null, 8, 80, $d['qr_mm']);
            $d['texte_mm'] = entrepot_etiquette_mm_clamp($row['texte_mm'] ?? null, 4, 24, $d['texte_mm']);
        }
    } catch (PDOException $e) {
        // defaults
    }

    $max_qr = max(8.0, min($d['hauteur_mm'] - 4.0, $d['largeur_mm'] * 0.45));
    if ($d['qr_mm'] > $max_qr) {
        $d['qr_mm'] = round($max_qr, 1);
    }
    $d['label'] = entrepot_etiquette_dims_label($d);

    return $d;
}

/**
 * @param array<string, mixed> $d
 * @return string
 */
function entrepot_etiquette_dims_label(array $d) {
    $w = (float) ($d['largeur_mm'] ?? 90);
    $h = (float) ($d['hauteur_mm'] ?? 40);
    $wf = (fmod($w, 1.0) < 0.05) ? (string) (int) round($w) : rtrim(rtrim(number_format($w, 1, '.', ''), '0'), '.');
    $hf = (fmod($h, 1.0) < 0.05) ? (string) (int) round($h) : rtrim(rtrim(number_format($h, 1, '.', ''), '0'), '.');

    return 'Étiquette ' . $wf . '×' . $hf . ' mm';
}

/**
 * Enregistre les dimensions (mm).
 *
 * @param array<string, mixed> $data
 * @return array{success: bool, message: string}
 */
function entrepot_etiquette_parametres_save(array $data) {
    global $db;
    if (!$db || !entrepot_etiquette_parametres_ensure_schema()) {
        return ['success' => false, 'message' => 'Table des paramètres étiquettes indisponible.'];
    }
    $def = entrepot_etiquette_dims_defaut();
    $w = entrepot_etiquette_mm_clamp($data['largeur_mm'] ?? null, 20, 200, $def['largeur_mm']);
    $h = entrepot_etiquette_mm_clamp($data['hauteur_mm'] ?? null, 15, 150, $def['hauteur_mm']);
    $qr = entrepot_etiquette_mm_clamp($data['qr_mm'] ?? null, 8, 80, $def['qr_mm']);
    $tx = entrepot_etiquette_mm_clamp($data['texte_mm'] ?? null, 4, 24, $def['texte_mm']);
    $max_qr = max(8.0, min($h - 4.0, $w * 0.45));
    if ($qr > $max_qr) {
        $qr = round($max_qr, 1);
    }

    try {
        $st = $db->prepare(
            'INSERT INTO entrepot_etiquette_parametres (id, largeur_mm, hauteur_mm, qr_mm, texte_mm, date_modification)
             VALUES (1, :w, :h, :qr, :tx, NOW())
             ON DUPLICATE KEY UPDATE
               largeur_mm = VALUES(largeur_mm),
               hauteur_mm = VALUES(hauteur_mm),
               qr_mm = VALUES(qr_mm),
               texte_mm = VALUES(texte_mm),
               date_modification = NOW()'
        );
        $st->execute([
            ':w' => $w,
            ':h' => $h,
            ':qr' => $qr,
            ':tx' => $tx,
        ]);

        return [
            'success' => true,
            'message' => 'Dimensions d’étiquette enregistrées (' . entrepot_etiquette_dims_label([
                'largeur_mm' => $w,
                'hauteur_mm' => $h,
            ]) . ').',
        ];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Erreur : ' . $e->getMessage()];
    }
}

/**
 * CSS variables + bloc style à injecter dans &lt;head&gt;.
 *
 * @param array<string, mixed>|null $dims
 * @return string
 */
function entrepot_etiquette_dims_style_block($dims = null) {
    if (!is_array($dims)) {
        $dims = entrepot_etiquette_dims();
    }
    $w = (float) ($dims['largeur_mm'] ?? 90);
    $h = (float) ($dims['hauteur_mm'] ?? 40);
    $qr = (float) ($dims['qr_mm'] ?? 30);
    $tx = (float) ($dims['texte_mm'] ?? 11);

    return '<style id="ee-etiq-dims-vars">'
        . ':root{'
        . '--ee-etiq-w:' . $w . 'mm;'
        . '--ee-etiq-h:' . $h . 'mm;'
        . '--ee-etiq-qr:' . $qr . 'mm;'
        . '--ee-etiq-texte:' . $tx . 'mm;'
        . '}'
        . '</style>';
}

/**
 * Attributs data-* pour le bloc racine d’étiquette (impression JS).
 *
 * @param array<string, mixed>|null $dims
 * @return string
 */
function entrepot_etiquette_dims_data_attrs($dims = null) {
    if (!is_array($dims)) {
        $dims = entrepot_etiquette_dims();
    }
    $w = htmlspecialchars((string) (float) ($dims['largeur_mm'] ?? 90), ENT_QUOTES, 'UTF-8');
    $h = htmlspecialchars((string) (float) ($dims['hauteur_mm'] ?? 40), ENT_QUOTES, 'UTF-8');
    $qr = htmlspecialchars((string) (float) ($dims['qr_mm'] ?? 30), ENT_QUOTES, 'UTF-8');
    $tx = htmlspecialchars((string) (float) ($dims['texte_mm'] ?? 11), ENT_QUOTES, 'UTF-8');

    return 'data-etiq-w="' . $w . '" data-etiq-h="' . $h . '" data-etiq-qr="' . $qr . '" data-etiq-texte="' . $tx . '"';
}

/**
 * mm → points Dompdf (72 dpi).
 *
 * @param float $mm
 * @return float
 */
function entrepot_etiquette_mm_to_pt($mm) {
    return ((float) $mm) * 72.0 / 25.4;
}
