<?php
/**
 * Panneau colonnes de prix (cases à cocher + aperçu) — devis / BL.
 */
require_once __DIR__ . '/produit_formulaire_champs.php';

/**
 * Miniature produit pour une ligne devis / BL.
 *
 * @param array<string, mixed>|null $produit
 * @return string
 */
function devis_ligne_thumb_html($produit = null) {
    if (!function_exists('pf_champ_visible') || !pf_champ_visible('images_produit')) {
        return '';
    }
    $img = '';
    if (is_array($produit)) {
        $img = trim((string) ($produit['image_principale'] ?? ''));
    }
    if ($img === '') {
        return '<span class="ligne-bl-thumb ligne-bl-thumb--ph" aria-hidden="true"><i class="fas fa-box"></i></span>';
    }
    $src = ($img !== '' && $img[0] === '/') ? $img : '/upload/' . ltrim($img, '/');

    return '<img src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" alt="" class="ligne-bl-thumb" loading="lazy" decoding="async">';
}

/**
 * Cellule désignation (image + nom) pour ligne devis / BL côté serveur.
 *
 * @param int $idx
 * @param array<string, mixed> $ligne
 * @param array<string, mixed>|null $produit
 * @return void
 */
function devis_ligne_designation_cell_render($idx, array $ligne, $produit = null) {
    $nom = htmlspecialchars((string) ($ligne['nom_produit'] ?? ''), ENT_QUOTES, 'UTF-8');
    $pid = (int) ($ligne['produit_id'] ?? 0);
    echo '<div class="ligne-bl-cell ligne-bl-cell--designation">';
    echo '<input type="hidden" name="lignes[' . (int) $idx . '][produit_id]" value="' . $pid . '">';
    echo '<span class="ligne-bl-label">Désignation</span>';
    echo '<div class="ligne-bl-designation-inner">';
    echo devis_ligne_thumb_html($produit);
    echo '<input type="text" name="lignes[' . (int) $idx . '][nom_produit]" value="' . $nom . '" class="ligne-nom-input" aria-label="Désignation du produit">';
    echo '</div></div>';
}

/**
 * @param array<int, string>|null $colonnes_cochees
 * @param string|null $champ_calcul
 * @return array{0: array<int, string>, 1: string}
 */
function devis_prix_colonnes_etat_defaut($colonnes_cochees = null, $champ_calcul = null) {
    require_once __DIR__ . '/../models/model_produit_formulaire_champs.php';
    $manifest = produit_formulaire_devis_prix_manifest();
    $disponibles = array_map(function ($ch) {
        return (string) ($ch['slug'] ?? '');
    }, produit_formulaire_champs_prix_devis());
    $def_col = $manifest['colonnes_defaut'] ?? ['prix', 'prix_promotion'];
    $colonnes = [];
    if (is_array($colonnes_cochees) && $colonnes_cochees !== []) {
        foreach ($colonnes_cochees as $slug) {
            $slug = trim((string) $slug);
            if ($slug !== '' && in_array($slug, $disponibles, true)) {
                $colonnes[] = $slug;
            }
        }
    }
    if ($colonnes === []) {
        foreach ($def_col as $slug) {
            if (in_array($slug, $disponibles, true)) {
                $colonnes[] = $slug;
            }
        }
    }
    if ($colonnes === [] && $disponibles !== []) {
        $colonnes = array_slice($disponibles, 0, min(2, count($disponibles)));
    }
    $calc = trim((string) ($champ_calcul ?? ''));
    if ($calc === '' || !in_array($calc, $colonnes, true)) {
        $calc = in_array('prix', $colonnes, true) ? 'prix' : (string) ($colonnes[0] ?? 'prix');
    }

    return [$colonnes, $calc];
}

/**
 * @param string $panel_id
 * @param array<int, string>|null $colonnes_cochees
 * @param string|null $champ_calcul
 * @return void
 */
function devis_prix_colonnes_panel_render($panel_id, $colonnes_cochees = null, $champ_calcul = null) {
    require_once __DIR__ . '/../models/model_produit_formulaire_champs.php';
    $champs = produit_formulaire_champs_prix_devis();
    if ($champs === []) {
        return;
    }
    list($colonnes_actives, $calc_actif) = devis_prix_colonnes_etat_defaut($colonnes_cochees, $champ_calcul);

    echo '<div class="form-group devis-champ-prix-calcul-wrap" id="' . htmlspecialchars($panel_id, ENT_QUOTES, 'UTF-8') . '" data-devis-prix-panel="1">';
    echo '<fieldset class="devis-prix-colonnes-fieldset">';
    echo '<legend><i class="fas fa-columns" aria-hidden="true"></i> Colonnes de prix à afficher</legend>';
    echo '<p class="form-hint devis-prix-colonnes-hint">Cochez les colonnes visibles dans le tableau. Le bouton radio indique le prix utilisé pour le total.</p>';
    echo '<div class="devis-prix-colonnes-grid" role="group" aria-label="Colonnes de prix">';

    foreach ($champs as $ch) {
        $slug = (string) ($ch['slug'] ?? '');
        $label = (string) ($ch['label'] ?? $slug);
        if ($slug === '') {
            continue;
        }
        $checked = in_array($slug, $colonnes_actives, true);
        $is_calc = ($slug === $calc_actif);
        echo '<label class="devis-prix-colonne-chip' . ($checked ? ' is-checked' : '') . ($is_calc ? ' is-calc' : '') . '">';
        echo '<span class="devis-prix-colonne-chip__row">';
        echo '<input type="checkbox" name="prix_colonnes[]" value="' . htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') . '"';
        echo $checked ? ' checked' : '';
        echo ' data-prix-colonne="1" aria-label="Afficher ' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '">';
        echo '<span class="devis-prix-colonne-chip__label">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
        echo '</span>';
        echo '<span class="devis-prix-colonne-chip__calc">';
        echo '<input type="radio" name="champ_prix_calcul" value="' . htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') . '"';
        echo $is_calc ? ' checked' : '';
        echo ' data-prix-calcul-radio="1" aria-label="Utiliser ' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . ' pour le total">';
        echo '<span>Total</span>';
        echo '</span>';
        echo '</label>';
    }

    echo '</div></fieldset>';
    echo '<div class="devis-prix-apercu" aria-live="polite">';
    echo '<span class="devis-prix-apercu__label">Aperçu colonnes :</span> ';
    echo '<span class="devis-prix-apercu__cols" data-devis-prix-apercu-cols></span>';
    echo '</div>';
    echo '</div>';
}

/** @deprecated Utiliser devis_prix_colonnes_panel_render */
function devis_prix_champ_calcul_select_render($select_id, $name = 'champ_prix_calcul', $selected_slug = null) {
    devis_prix_colonnes_panel_render($select_id, null, $selected_slug);
}

/**
 * @param array<int, string> $slugs_visibles
 * @return void
 */
function devis_prix_ligne_head_cellules_render(array $slugs_visibles = []) {
    require_once __DIR__ . '/../models/model_produit_formulaire_champs.php';
    if ($slugs_visibles === []) {
        list($slugs_visibles,) = devis_prix_colonnes_etat_defaut();
    }
    $map = [];
    foreach (produit_formulaire_champs_prix_devis() as $ch) {
        $map[(string) ($ch['slug'] ?? '')] = (string) ($ch['label'] ?? '');
    }
    echo '<span class="lch-head-cell">Produit</span>';
    echo '<span class="lch-head-cell">Quantité</span>';
    foreach ($slugs_visibles as $slug) {
        if (!isset($map[$slug])) {
            continue;
        }
        echo '<span class="lch-head-cell" data-prix-head-slug="' . htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') . '">';
        echo htmlspecialchars($map[$slug], ENT_QUOTES, 'UTF-8') . ' FCFA';
        echo '</span>';
    }
    echo '<span class="lch-head-cell">Total</span>';
    echo '<span class="lch-head-cell lch-head-actions" aria-hidden="true"></span>';
}

/**
 * @param int $idx
 * @param array<string, mixed> $ligne
 * @param string $champ_calcul_slug
 * @param array<string, mixed>|null $produit
 * @param array<int, string> $slugs_visibles
 * @return void
 */
function devis_prix_ligne_cellules_render($idx, array $ligne, $champ_calcul_slug, $produit = null, array $slugs_visibles = []) {
    require_once __DIR__ . '/../models/model_produit_formulaire_champs.php';
    if ($slugs_visibles === []) {
        list($slugs_visibles, $champ_calcul_slug) = devis_prix_colonnes_etat_defaut(
            isset($ligne['prix_colonnes']) && is_array($ligne['prix_colonnes']) ? $ligne['prix_colonnes'] : null,
            $champ_calcul_slug
        );
    }
    $champs = produit_formulaire_champs_prix_devis();
    if ($champs === []) {
        return;
    }
    $prix_champs = isset($ligne['prix_champs']) && is_array($ligne['prix_champs']) ? $ligne['prix_champs'] : [];
    $pu_calc = produit_formulaire_devis_prix_unitaire_depuis_ligne($ligne, $champ_calcul_slug);
    foreach ($champs as $ch) {
        $slug = (string) ($ch['slug'] ?? '');
        if ($slug === '' || !in_array($slug, $slugs_visibles, true)) {
            continue;
        }
        $label = (string) ($ch['label'] ?? $slug);
        $val = '';
        if (isset($prix_champs[$slug]) && $prix_champs[$slug] !== '') {
            $val = (string) $prix_champs[$slug];
        } elseif ($produit !== null) {
            $num = produit_formulaire_devis_prix_valeur_produit($produit, $ch);
            $val = $num > 0 ? (string) $num : '';
        } elseif ($slug === $champ_calcul_slug && isset($ligne['prix_unitaire'])) {
            $val = (string) $ligne['prix_unitaire'];
        }
        $is_calc = ($slug === $champ_calcul_slug);
        echo '<div class="ligne-bl-cell ligne-bl-cell-prix' . ($is_calc ? ' ligne-bl-cell-prix--calc' : '') . '" data-prix-col-slug="' . htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') . '">';
        echo '<span class="ligne-bl-label">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
        echo '<div class="ligne-bl-prix-row">';
        echo '<input type="number" name="lignes[' . (int) $idx . '][prix_champs][' . htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') . ']"';
        echo ' value="' . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '" min="0" step="0.01"';
        echo ' class="ligne-prix-champ" data-slug="' . htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') . '"';
        echo ' aria-label="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . ' en FCFA" inputmode="decimal">';
        echo '<span class="ligne-unit-fcfa">FCFA</span>';
        echo '</div></div>';
    }
    echo '<input type="hidden" class="ligne-prix-unitaire-calc" name="lignes[' . (int) $idx . '][prix_unitaire]" value="' . htmlspecialchars((string) $pu_calc, ENT_QUOTES, 'UTF-8') . '">';
}
