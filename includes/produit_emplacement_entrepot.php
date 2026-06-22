<?php
/**
 * Emplacement entrepôt produit — champs, validation et rendu formulaire.
 */

/**
 * @return array<string, array{label: string, min: int, max: int, icon: string, hint: string}>
 */
function produit_emplacement_champs_config() {
    return [
        'etage' => [
            'label' => 'Étage (entrepôt)',
            'min' => 1,
            'max' => 3,
            'icon' => 'fa-warehouse',
            'hint' => 'Niveau de l’entrepôt (1 à 3).',
        ],
        'numero_rayon' => [
            'label' => 'N° de rayon',
            'min' => 1,
            'max' => 100,
            'icon' => 'fa-th-large',
            'hint' => 'Numéro du rayon (1 à 100).',
        ],
        'allee' => [
            'label' => 'Allée',
            'min' => 1,
            'max' => 10,
            'icon' => 'fa-road',
            'hint' => 'Allée empruntée pour rejoindre le rayon (1 à 10).',
        ],
        'zone_emplacement' => [
            'label' => 'Zone',
            'min' => 1,
            'max' => 10,
            'icon' => 'fa-map-marker-alt',
            'hint' => 'Zone dans le rayon (1 à 10).',
        ],
        'position_emplacement' => [
            'label' => 'Position',
            'min' => 1,
            'max' => 10,
            'icon' => 'fa-crosshairs',
            'hint' => 'Position précise dans la zone (1 à 10).',
        ],
        'barre_rayon' => [
            'label' => 'Barre',
            'min' => 1,
            'max' => 10,
            'icon' => 'fa-grip-lines',
            'hint' => 'Barre / étagère du rayon (1 à 10).',
        ],
    ];
}

/**
 * @return bool
 */
function produit_emplacement_colonne_active($col) {
    if (!function_exists('produits_has_column')) {
        return false;
    }
    if (in_array($col, ['etage', 'numero_rayon'], true)) {
        return produits_has_column($col);
    }

    return produits_has_column($col);
}

/**
 * @param array<string, mixed> $source
 * @return array<string, string|null>
 */
function produit_emplacement_from_source(array $source) {
    $out = [];
    foreach (produit_emplacement_champs_config() as $col => $cfg) {
        if (!produit_emplacement_colonne_active($col)) {
            continue;
        }
        $raw = isset($source[$col]) ? trim((string) $source[$col]) : '';
        if ($raw === '') {
            $out[$col] = null;
            continue;
        }
        if (!ctype_digit($raw)) {
            $out[$col] = null;
            continue;
        }
        $n = (int) $raw;
        if ($n < $cfg['min'] || $n > $cfg['max']) {
            $out[$col] = null;
            continue;
        }
        $out[$col] = (string) $n;
    }

    return $out;
}

/**
 * @param array<string, mixed> $produit
 * @return array<string, string|null>
 */
function produit_emplacement_from_produit(array $produit) {
    $vals = [];
    foreach (produit_emplacement_champs_config() as $col => $cfg) {
        if (!produit_emplacement_colonne_active($col)) {
            continue;
        }
        $v = isset($produit[$col]) ? trim((string) $produit[$col]) : '';
        $vals[$col] = $v !== '' ? $v : null;
    }

    return $vals;
}

/**
 * @param array<string, string|null> $vals
 * @return bool
 */
function produit_emplacement_a_des_donnees(array $vals) {
    foreach ($vals as $v) {
        if ($v !== null && $v !== '') {
            return true;
        }
    }

    return false;
}

/**
 * Préfixe affiché dans les listes déroulantes (ex. « Zone 1 »).
 *
 * @return string
 */
function produit_emplacement_option_prefix($col) {
    $map = [
        'etage' => 'Étage',
        'numero_rayon' => 'Rayon',
        'allee' => 'Allée',
        'zone_emplacement' => 'Zone',
        'position_emplacement' => 'Position',
        'barre_rayon' => 'Barre',
    ];

    return $map[$col] ?? 'Valeur';
}

/**
 * Libellé complet d’une option (ex. « Zone 3 », « Rayon 15 »).
 *
 * @param string $col
 * @param int|string $n
 * @return string
 */
function produit_emplacement_option_label($col, $n) {
    return produit_emplacement_option_prefix($col) . ' ' . (int) $n;
}

/**
 * @param array<string, string|null> $vals
 * @return string
 */
function produit_emplacement_resume_court(array $vals) {
    $parts = [];
    foreach (produit_emplacement_champs_config() as $col => $cfg) {
        if (!empty($vals[$col])) {
            $parts[] = produit_emplacement_option_label($col, $vals[$col]);
        }
    }

    return implode(' · ', $parts);
}

/**
 * Suffixe compact emplacement pour codes-barres (ex. E2|R15|A3|Z5|P2|B4).
 *
 * @param array<string, string|null> $vals
 * @return string
 */
function produit_emplacement_compact_suffix(array $vals) {
    $keys = [
        'etage' => 'E',
        'numero_rayon' => 'R',
        'allee' => 'A',
        'zone_emplacement' => 'Z',
        'position_emplacement' => 'P',
        'barre_rayon' => 'B',
    ];
    $parts = [];
    foreach ($keys as $col => $letter) {
        if (!empty($vals[$col])) {
            $parts[] = $letter . (int) $vals[$col];
        }
    }

    return implode('|', $parts);
}

/**
 * Charge utile Code 128 : FPL + emplacement si renseigné.
 *
 * @param string $fpl_code
 * @param array<string, string|null> $vals
 * @return string
 */
function produit_emplacement_barcode_payload($fpl_code, array $vals) {
    $fpl_code = strtoupper(trim((string) $fpl_code));
    $suffix = produit_emplacement_compact_suffix($vals);
    if ($suffix === '') {
        return $fpl_code;
    }

    return $fpl_code . ';' . $suffix;
}

/**
 * Extrait le code FPL d’une saisie scanner (avec ou sans suffixe emplacement).
 *
 * @param string $raw
 * @return string
 */
function produit_emplacement_extraire_fpl_du_scan($raw) {
    $raw = strtoupper(trim((string) $raw));
    if ($raw === '') {
        return '';
    }
    if (strpos($raw, ';') !== false) {
        $raw = trim((string) explode(';', $raw, 2)[0]);
    }

    return $raw;
}

/**
 * URL publique stock-info.php avec paramètres d’emplacement pour le QR code.
 *
 * @param int $produit_id
 * @param array<string, mixed> $produit
 * @return string
 */
function produit_emplacement_stock_info_url($produit_id, array $produit = []) {
    require_once __DIR__ . '/site_url.php';
    $produit_id = (int) $produit_id;
    $base = rtrim(get_site_base_url(), '/') . '/stock-info.php?id=' . $produit_id;
    if ($produit_id <= 0) {
        return $base;
    }

    $vals = produit_emplacement_from_produit($produit);
    $query_keys = [
        'etage' => 'e',
        'numero_rayon' => 'r',
        'allee' => 'a',
        'zone_emplacement' => 'z',
        'position_emplacement' => 'p',
        'barre_rayon' => 'b',
    ];
    $params = [];
    foreach ($query_keys as $col => $key) {
        if (!empty($vals[$col])) {
            $params[$key] = (int) $vals[$col];
        }
    }
    if ($params === []) {
        return $base;
    }

    return $base . '&' . http_build_query($params);
}

/**
 * @param string $col
 * @param array{label: string, min: int, max: int, icon: string, hint: string} $cfg
 * @param string|null $selected
 */
function produit_emplacement_render_select($col, array $cfg, $selected) {
    $id = htmlspecialchars($col, ENT_QUOTES, 'UTF-8');
    $label = htmlspecialchars($cfg['label'], ENT_QUOTES, 'UTF-8');
    $icon = htmlspecialchars($cfg['icon'], ENT_QUOTES, 'UTF-8');
    $hint = htmlspecialchars($cfg['hint'], ENT_QUOTES, 'UTF-8');
    $sel = $selected !== null && $selected !== '' ? (string) $selected : '';

    echo '<div class="form-group">';
    echo '<label for="' . $id . '"><i class="fas ' . $icon . '" aria-hidden="true"></i> ' . $label . '</label>';
    echo '<select id="' . $id . '" name="' . $id . '">';
    echo '<option value="">— Non renseigné —</option>';
    for ($i = $cfg['min']; $i <= $cfg['max']; $i++) {
        $s = ((string) $i === $sel) ? ' selected' : '';
        $opt_label = htmlspecialchars(produit_emplacement_option_label($col, $i), ENT_QUOTES, 'UTF-8');
        echo '<option value="' . $i . '"' . $s . '>' . $opt_label . '</option>';
    }
    echo '</select>';
    echo '<small class="form-hint">' . $hint . '</small>';
    echo '</div>';
}

/**
 * @param array<string, string|null> $values
 */
function produit_emplacement_render_form_fields(array $values) {
    $champs = produit_emplacement_champs_config();
    $actifs = [];
    foreach ($champs as $col => $cfg) {
        if (produit_emplacement_colonne_active($col)) {
            $actifs[$col] = $cfg;
        }
    }
    if ($actifs === []) {
        echo '<p class="form-hint form-hint--warning"><i class="fas fa-info-circle" aria-hidden="true"></i> Emplacement entrepôt : exécutez les migrations <code>migration_admin_b2b_structure</code> et <code>run_add_produits_emplacement_entrepot.php</code>.</p>';
        return;
    }

    $pairs = array_chunk(array_keys($actifs), 2, true);
    foreach ($pairs as $pair) {
        echo '<div class="form-row pm-emplacement-row">';
        foreach ($pair as $col) {
            produit_emplacement_render_select($col, $actifs[$col], $values[$col] ?? null);
        }
        echo '</div>';
    }
}

/**
 * @param array<string, string|null> $vals
 */
function produit_emplacement_render_apercu(array $vals) {
    if (!produit_emplacement_a_des_donnees($vals)) {
        return;
    }

    $etapes = [
        ['col' => 'etage', 'label' => 'Étage', 'icon' => 'fa-building'],
        ['col' => 'numero_rayon', 'label' => 'Rayon', 'icon' => 'fa-th-large'],
        ['col' => 'allee', 'label' => 'Allée', 'icon' => 'fa-road'],
        ['col' => 'zone_emplacement', 'label' => 'Zone', 'icon' => 'fa-map-marker-alt'],
        ['col' => 'position_emplacement', 'label' => 'Position', 'icon' => 'fa-crosshairs'],
        ['col' => 'barre_rayon', 'label' => 'Barre', 'icon' => 'fa-grip-lines'],
    ];

    $resume = produit_emplacement_resume_court($vals);
    ?>
    <section class="pas-emplacement" aria-labelledby="pas-emplacement-title">
        <div class="pas-emplacement__head">
            <h4 id="pas-emplacement-title" class="pas-emplacement__title">
                <i class="fas fa-map-pin" aria-hidden="true"></i> Position exacte en entrepôt
            </h4>
            <?php if ($resume !== ''): ?>
            <p class="pas-emplacement__resume" title="Résumé du parcours"><?php echo htmlspecialchars($resume); ?></p>
            <?php endif; ?>
        </div>
        <div class="pas-emplacement__grille" role="list" aria-label="Détail des coordonnées">
            <?php foreach ($etapes as $etape):
                $col = $etape['col'];
                if (empty($vals[$col])) {
                    continue;
                }
            ?>
            <div class="pas-emplacement__cell" role="listitem">
                <span class="pas-emplacement__cell-ic" aria-hidden="true"><i class="fas <?php echo htmlspecialchars($etape['icon']); ?>"></i></span>
                <div class="pas-emplacement__cell-body">
                    <span class="pas-emplacement__cell-label"><?php echo htmlspecialchars($etape['label']); ?></span>
                    <span class="pas-emplacement__cell-value"><?php echo htmlspecialchars(produit_emplacement_option_label($col, $vals[$col])); ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php
}
