<?php
/**
 * Suivi catalogue — colonnes tableau synchronisées avec champs formulaire produit.
 */

require_once __DIR__ . '/export_produits_catalogue_pdf.php';

/**
 * @return bool
 */
function export_catalogue_suivi_colonnes_ensure_schema()
{
    global $db;
    if (!$db) {
        return false;
    }
    static $ok = null;
    if ($ok === true) {
        return true;
    }
    try {
        $db->query('SELECT 1 FROM admin_export_catalogue_colonnes LIMIT 1');
        $ok = true;

        return true;
    } catch (PDOException $e) {
        /* création ci-dessous */
    }
    try {
        $db->exec(
            'CREATE TABLE IF NOT EXISTS admin_export_catalogue_colonnes (
                admin_id INT NOT NULL,
                colonnes_json TEXT NOT NULL,
                date_modification DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (admin_id),
                CONSTRAINT fk_aec_admin FOREIGN KEY (admin_id) REFERENCES admin (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        $db->query('SELECT 1 FROM admin_export_catalogue_colonnes LIMIT 1');
        $ok = true;

        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Définitions des colonnes du tableau suivi (clé => métadonnées).
 *
 * @return array<string, array<string, mixed>>
 */
function export_catalogue_suivi_columns_definitions()
{
    require_once __DIR__ . '/../models/model_produit_formulaire_champs.php';

    return produit_formulaire_export_colonnes_definitions('suivi');
}

/**
 * @param string $key
 * @return bool
 */
function export_catalogue_suivi_column_champ_visible($key)
{
    $defs = export_catalogue_suivi_columns_definitions();

    return isset($defs[$key]);
}

/**
 * Catalogue des colonnes disponibles (clé => libellé), filtré par champs formulaire produit.
 *
 * @return array<string, string>
 */
function export_catalogue_suivi_columns_catalog()
{
    require_once __DIR__ . '/../models/model_produit_formulaire_champs.php';

    return produit_formulaire_export_colonnes_catalog('suivi');
}

/**
 * @return array<int, string>
 */
function export_catalogue_suivi_colonnes_default()
{
    $defaults = ['img', 'nom', 'cat', 'prix_achat', 'prix', 'stock'];
    $catalog = export_catalogue_suivi_columns_catalog();
    $out = [];
    foreach ($defaults as $key) {
        if (isset($catalog[$key])) {
            $out[] = $key;
        }
    }
    if ($out === []) {
        return array_keys($catalog);
    }

    return $out;
}

/**
 * @param int $admin_id
 * @return array<int, string>|null
 */
function export_catalogue_suivi_colonnes_get_raw($admin_id)
{
    global $db;
    $admin_id = (int) $admin_id;
    if ($admin_id <= 0 || !$db || !export_catalogue_suivi_colonnes_ensure_schema()) {
        return null;
    }
    try {
        $st = $db->prepare('SELECT colonnes_json FROM admin_export_catalogue_colonnes WHERE admin_id = :aid LIMIT 1');
        $st->execute([':aid' => $admin_id]);
        $raw = $st->fetchColumn();
        if ($raw === false || trim((string) $raw) === '') {
            return null;
        }
        $data = json_decode((string) $raw, true);
        if (!is_array($data)) {
            return null;
        }
        $cols = [];
        foreach ($data as $item) {
            $item = trim((string) $item);
            if ($item !== '') {
                $cols[] = $item;
            }
        }

        return $cols !== [] ? $cols : null;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * @param int $admin_id
 * @return array<int, string>
 */
function export_catalogue_suivi_colonnes_resolved($admin_id)
{
    $catalog = export_catalogue_suivi_columns_catalog();
    $defs = export_catalogue_suivi_columns_definitions();
    $saved = export_catalogue_suivi_colonnes_get_raw($admin_id);
    if ($saved === null) {
        $saved = export_catalogue_suivi_colonnes_default();
    }
    $ordered = [];
    foreach (array_keys($defs) as $key) {
        if (!isset($catalog[$key])) {
            continue;
        }
        if (in_array($key, $saved, true)) {
            $ordered[] = $key;
        }
    }
    foreach ($defs as $key => $def) {
        if (!empty($def['locked']) && isset($catalog[$key]) && !in_array($key, $ordered, true)) {
            array_unshift($ordered, $key);
        }
    }
    if ($ordered === []) {
        return array_keys($catalog);
    }

    return array_values(array_unique($ordered));
}

/**
 * @param int $admin_id
 * @param array<int, string> $cols
 * @return array{success: bool, message: string, colonnes?: array<int, string>}
 */
function export_catalogue_suivi_colonnes_save($admin_id, array $cols)
{
    global $db;
    $admin_id = (int) $admin_id;
    if ($admin_id <= 0 || !$db || !export_catalogue_suivi_colonnes_ensure_schema()) {
        return ['success' => false, 'message' => 'Impossible d’enregistrer les colonnes.'];
    }
    $catalog = export_catalogue_suivi_columns_catalog();
    $defs = export_catalogue_suivi_columns_definitions();
    $selected = [];
    foreach ($cols as $col) {
        $col = trim((string) $col);
        if ($col !== '' && isset($catalog[$col])) {
            $selected[$col] = $col;
        }
    }
    foreach ($defs as $key => $def) {
        if (!empty($def['locked']) && isset($catalog[$key])) {
            $selected[$key] = $key;
        }
    }
    if ($selected === []) {
        return ['success' => false, 'message' => 'Sélectionnez au moins une colonne visible.'];
    }
    $ordered = [];
    foreach (array_keys($defs) as $key) {
        if (isset($selected[$key])) {
            $ordered[] = $key;
        }
    }
    $json = json_encode($ordered, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return ['success' => false, 'message' => 'Erreur lors de la préparation des données.'];
    }
    try {
        $st = $db->prepare(
            'INSERT INTO admin_export_catalogue_colonnes (admin_id, colonnes_json, date_modification)
             VALUES (:aid, :json, NOW())
             ON DUPLICATE KEY UPDATE colonnes_json = VALUES(colonnes_json), date_modification = NOW()'
        );
        $st->execute([':aid' => $admin_id, ':json' => $json]);

        return [
            'success' => true,
            'message' => 'Colonnes du tableau enregistrées.',
            'colonnes' => $ordered,
        ];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Erreur base de données lors de l’enregistrement.'];
    }
}

/**
 * @param string $statut
 * @return string
 */
function export_catalogue_suivi_statut_label($statut)
{
    $map = [
        'actif' => 'Actif',
        'inactif' => 'Inactif',
        'rupture_stock' => 'Rupture stock',
    ];
    $statut = trim((string) $statut);

    return $map[$statut] ?? ($statut !== '' ? $statut : '—');
}

/**
 * @param array<string, mixed> $produit
 * @return string
 */
function export_catalogue_suivi_sous_categorie_nom(array $produit)
{
    static $cache = [];
    $sid = isset($produit['sous_categorie_id']) ? (int) $produit['sous_categorie_id'] : 0;
    if ($sid <= 0) {
        return '—';
    }
    if (isset($cache[$sid])) {
        return $cache[$sid];
    }
    require_once __DIR__ . '/../models/model_sous_categories.php';
    if (!function_exists('get_sous_categorie_by_id') || !function_exists('sous_categories_table_ok') || !sous_categories_table_ok()) {
        $cache[$sid] = '—';

        return '—';
    }
    $sc = get_sous_categorie_by_id($sid);
    $cache[$sid] = ($sc && !empty($sc['nom'])) ? (string) $sc['nom'] : '—';

    return $cache[$sid];
}

/**
 * @param string $key
 * @param array<string, mixed> $produit
 * @param array<string, mixed> $ctx visible_cols, pid
 * @return string HTML cellule (non échappé pour inputs)
 */
function export_catalogue_suivi_render_cell_html($key, array $produit, array $ctx = [])
{
    $visible = isset($ctx['visible_cols']) && is_array($ctx['visible_cols']) ? $ctx['visible_cols'] : [];
    $pid = (int) ($ctx['pid'] ?? ($produit['id'] ?? 0));
    $show_ident_col = in_array('identifiant', $visible, true);
    $show_marque_col = in_array('marque', $visible, true);

    switch ($key) {
        case 'img':
            $img = trim((string) ($produit['image_principale'] ?? ''));
            $img_url = $img !== '' ? '/upload/' . ltrim(str_replace('\\', '/', $img), '/') : '';
            if ($img_url !== '') {
                return '<img src="' . htmlspecialchars($img_url, ENT_QUOTES, 'UTF-8') . '" alt="" width="44" height="44" loading="lazy">';
            }

            return '<span class="page-produits-export-table__no-img">—</span>';

        case 'nom':
            $ident = trim((string) ($produit['identifiant_interne'] ?? ''));
            $marque = function_exists('produits_marque_libelle_from_row') ? produits_marque_libelle_from_row($produit) : '';
            $html = '<strong>' . htmlspecialchars($produit['nom'] ?? '', ENT_QUOTES, 'UTF-8') . '</strong>';
            if ($ident !== '') {
                $meta_class = 'page-produits-export-table__meta is-suivi-nom-meta-ident';
                if ($show_ident_col) {
                    $meta_class .= ' is-suivi-nom-meta-hidden';
                }
                $html .= '<span class="' . $meta_class . '">Réf. ' . htmlspecialchars($ident, ENT_QUOTES, 'UTF-8') . '</span>';
            }
            if ($marque !== '') {
                $meta_class = 'page-produits-export-table__meta is-suivi-nom-meta-marque';
                if ($show_marque_col) {
                    $meta_class .= ' is-suivi-nom-meta-hidden';
                }
                $html .= '<span class="' . $meta_class . '">' . htmlspecialchars($marque, ENT_QUOTES, 'UTF-8') . '</span>';
            }

            return $html;

        case 'cat':
            return htmlspecialchars($produit['categorie_nom'] ?? '—', ENT_QUOTES, 'UTF-8');

        case 'marque':
            $marque = function_exists('produits_marque_libelle_from_row') ? produits_marque_libelle_from_row($produit) : '';

            return htmlspecialchars($marque !== '' ? $marque : '—', ENT_QUOTES, 'UTF-8');

        case 'identifiant':
            $ident = trim((string) ($produit['identifiant_interne'] ?? ''));

            return htmlspecialchars($ident !== '' ? $ident : '—', ENT_QUOTES, 'UTF-8');

        case 'fournisseur':
            $four = function_exists('produits_fournisseur_nom_affichage') ? produits_fournisseur_nom_affichage($produit) : '';

            return htmlspecialchars($four !== '' ? $four : '—', ENT_QUOTES, 'UTF-8');

        case 'prix_achat':
            $val = export_catalogue_prix_input_value($produit['prix_achat'] ?? null);

            return '<input type="number" class="page-produits-export-table__price-input"'
                . ' name="prix[' . $pid . '][prix_achat]"'
                . ' value="' . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '"'
                . ' min="0" step="1" inputmode="numeric" placeholder="—" aria-label="Prix achat">';

        case 'prix':
            $val = export_catalogue_prix_input_value($produit['prix'] ?? null);

            return '<input type="number" class="page-produits-export-table__price-input"'
                . ' name="prix[' . $pid . '][prix]"'
                . ' value="' . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '"'
                . ' min="0" step="1" inputmode="numeric" placeholder="—" aria-label="Prix vente">';

        case 'promo':
            return htmlspecialchars(
                export_catalogue_format_prix_fcfa_export($produit['prix_promotion'] ?? null, true),
                ENT_QUOTES,
                'UTF-8'
            );

        case 'stock':
            return htmlspecialchars(
                export_catalogue_format_stock_export($produit['stock'] ?? null),
                ENT_QUOTES,
                'UTF-8'
            );

        case 'statut':
            return htmlspecialchars(export_catalogue_suivi_statut_label($produit['statut'] ?? ''), ENT_QUOTES, 'UTF-8');

        case 'sous_cat':
            return htmlspecialchars(export_catalogue_suivi_sous_categorie_nom($produit), ENT_QUOTES, 'UTF-8');

        case 'poids':
            $poids = trim((string) ($produit['poids'] ?? ''));

            return htmlspecialchars($poids !== '' ? $poids : '—', ENT_QUOTES, 'UTF-8');

        case 'couleurs':
            $couleurs = trim((string) ($produit['couleurs'] ?? ''));

            return htmlspecialchars($couleurs !== '' ? $couleurs : '—', ENT_QUOTES, 'UTF-8');

        case 'taille':
            $taille = trim((string) ($produit['taille'] ?? ''));

            return htmlspecialchars($taille !== '' ? $taille : '—', ENT_QUOTES, 'UTF-8');

        default:
            if (strpos($key, 'custom_') === 0) {
                $slug = substr($key, 7);
                $custom = isset($produit['pf_custom']) && is_array($produit['pf_custom']) ? $produit['pf_custom'] : [];
                $val = trim((string) ($custom[$slug] ?? ''));

                return htmlspecialchars($val !== '' ? $val : '—', ENT_QUOTES, 'UTF-8');
            }

            return '—';
    }
}
