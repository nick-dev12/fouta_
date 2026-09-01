<?php
/**
 * Helpers affichage — champs dynamiques formulaire produit.
 */
require_once __DIR__ . '/../models/model_produit_formulaire_champs.php';

/**
 * @param string $slug
 * @return bool
 */
function pf_champ_visible($slug) {
    return produit_formulaire_champ_visible($slug);
}

/**
 * @param string $section
 * @return bool
 */
function pf_section_visible($section) {
    return produit_formulaire_section_visible($section);
}

/**
 * @param string $slug
 * @return bool
 */
function pf_champ_obligatoire($slug) {
    $ch = produit_formulaire_champ_get_by_slug($slug);
    if ($ch === null || !pf_champ_visible($slug)) {
        return false;
    }

    return (int) ($ch['obligatoire'] ?? 0) === 1;
}

/** Colonne prix visible en listes admin (prix vente ou promo). */
function pf_liste_col_prix_visible() {
    return pf_champ_visible('prix') || pf_champ_visible('prix_promotion');
}

/** Colonne stock visible en listes admin. */
function pf_liste_col_stock_visible() {
    return pf_champ_visible('stock');
}

/** Colonne catégorie visible en listes admin. */
function pf_liste_col_categorie_visible() {
    return pf_champ_visible('categorie_id');
}

/** Colonne image visible en listes admin. */
function pf_liste_col_image_visible() {
    return pf_champ_visible('images_produit');
}

/** Colonne statut visible en listes admin. */
function pf_liste_col_statut_visible() {
    return pf_champ_visible('statut');
}

/** Colonne fournisseur visible en listes admin. */
function pf_liste_col_fournisseur_visible() {
    return pf_champ_visible('fournisseur_id');
}

/** Colonne référence FPL visible en listes admin. */
function pf_liste_col_ident_visible() {
    return pf_champ_visible('identifiant_interne')
        && function_exists('produits_has_column')
        && produits_has_column('identifiant_interne');
}

/**
 * @param array<string, mixed> $post
 * @param array<int, string> $errors
 * @return void
 */
function produit_formulaire_valider_custom(array $post, array &$errors) {
    foreach (produit_formulaire_champs_custom_actifs() as $ch) {
        $slug = (string) ($ch['slug'] ?? '');
        if ($slug === '') {
            continue;
        }
        $label = (string) ($ch['label'] ?? $slug);
        $key = 'pf_custom_' . $slug;
        $val = isset($post[$key]) ? trim((string) $post[$key]) : '';
        $type = (string) ($ch['type_champ'] ?? 'texte');

        if ((int) ($ch['obligatoire'] ?? 0) === 1 && $val === '') {
            $errors[] = 'Le champ « ' . $label . ' » est obligatoire.';
            continue;
        }
        if ($val === '') {
            continue;
        }
        if ($type === 'nombre' && !is_numeric($val)) {
            $errors[] = 'Le champ « ' . $label . ' » doit être un nombre valide.';
        }
        if ($type === 'select') {
            $opts = json_decode((string) ($ch['options_json'] ?? '[]'), true);
            if (is_array($opts) && !in_array($val, $opts, true)) {
                $errors[] = 'Valeur invalide pour « ' . $label . ' ».';
            }
        }
    }
}

/**
 * @param string $section
 * @param array<string, string> $values slug => valeur
 * @param array<string, mixed>|null $post
 * @return void
 */
function produit_formulaire_render_champs_custom($section, array $values = [], $post = null) {
    $custom = [];
    foreach (produit_formulaire_champs_custom_actifs() as $ch) {
        if (($ch['section'] ?? '') === $section) {
            $custom[] = $ch;
        }
    }
    if ($custom === []) {
        return;
    }
    echo '<div class="pf-custom-fields" data-section="' . htmlspecialchars($section, ENT_QUOTES, 'UTF-8') . '">';
    foreach ($custom as $ch) {
        $slug = (string) ($ch['slug'] ?? '');
        $label = (string) ($ch['label'] ?? $slug);
        $type = (string) ($ch['type_champ'] ?? 'texte');
        $key = 'pf_custom_' . $slug;
        $req = (int) ($ch['obligatoire'] ?? 0) === 1;
        $val = '';
        if ($post !== null && array_key_exists($key, $post)) {
            $val = trim((string) $post[$key]);
        } elseif (isset($values[$slug])) {
            $val = (string) $values[$slug];
        }
        $id = 'pf_custom_' . preg_replace('/[^a-z0-9_]/', '_', $slug);
        echo '<div class="form-group pf-custom-field">';
        echo '<label for="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '">';
        echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
        if ($req) {
            echo ' *';
        }
        echo '</label>';
        if ($type === 'textarea') {
            echo '<textarea id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '" name="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '"';
            if ($req) {
                echo ' required';
            }
            echo ' placeholder="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '">';
            echo htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
            echo '</textarea>';
        } elseif ($type === 'select') {
            $opts = json_decode((string) ($ch['options_json'] ?? '[]'), true);
            if (!is_array($opts)) {
                $opts = [];
            }
            echo '<select id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '" name="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '"';
            if ($req) {
                echo ' required';
            }
            echo '><option value="">— Choisir —</option>';
            foreach ($opts as $opt) {
                $opt = (string) $opt;
                $sel = ($val === $opt) ? ' selected' : '';
                echo '<option value="' . htmlspecialchars($opt, ENT_QUOTES, 'UTF-8') . '"' . $sel . '>';
                echo htmlspecialchars($opt, ENT_QUOTES, 'UTF-8') . '</option>';
            }
            echo '</select>';
        } else {
            $input_type = ($type === 'nombre') ? 'number' : 'text';
            echo '<input type="' . $input_type . '" id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '" name="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '"';
            if ($input_type === 'number') {
                echo ' step="any"';
            }
            if ($req) {
                echo ' required';
            }
            echo ' value="' . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '" placeholder="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '">';
        }
        echo '</div>';
    }
    echo '</div>';
}
