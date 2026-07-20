<?php
/**
 * Étiquettes FPL (aperçu + impression stock) — couleurs catégorie, pied société par défaut.
 */

require_once __DIR__ . '/site_url.php';
require_once __DIR__ . '/../models/model_categories.php';

/**
 * Valeur enregistrée par défaut en BDD (`categories.couleur_etiquette`) : traitée comme « auto »,
 * pas comme un choix explicite (couleur réelle via synonymes + palette stable).
 *
 * Doit rester alignée avec `categories_normaliser_couleur_etiquette(null)` et la migration SQL.
 */
function fpl_etiquette_couleur_bdd_defaut()
{
    return '#1E3A5F';
}

/**
 * Liste courte de secours sans catégorie / ancien comportement modulo ID.
 *
 * @return string[]
 */
function fpl_etiquette_palette_fallback()
{
    return ['#1e3a5f', '#3564a6', '#918a44', '#6b2f20', '#00695c', '#4527a0', '#c2410c', '#264653'];
}

/**
 * Couleurs assez sombres pour texte blanc sur bandeaux / pied (#fff sur fond accent).
 *
 * @return string[]
 */
function fpl_etiquette_palette_rotation_auto()
{
    return [
        '#0D47A1', '#B71C1C', '#1B5E20', '#4A148C', '#E65100', '#006064',
        '#880E4F', '#33691E', '#BF360C', '#1A237E', '#004D40', '#6A1B9A',
        '#C62828', '#1565C0', '#2E7D32', '#4527A0', '#D84315', '#00838F',
        '#AD1457', '#558B2F', '#4E342E', '#283593', '#00695C', '#5E35B1',
        '#C2185B', '#0277BD', '#388E3C', '#E64A19', '#00897B',
        '#8E24AA', '#37474F', '#D32F2F', '#1976D2',
    ];
}

/**
 * Synonymes : clé = sous-chaîne à chercher dans le nom de catégorie normalisé (minuscules, espaces uniques).
 * Ordre important : mettre les libellés les plus longs / spécifiques en premier.
 *
 * @return array<int, array{0:string,1:string}> [fragment, #RRGGBB]
 */
function fpl_etiquette_synonymes_couleur_liste()
{
    return [
        ['air compresseur', '#C62828'],
        ['compresseur d\'air', '#C62828'],
        ['compresseur air', '#C62828'],
        ['amortisseur', '#1565C0'],
    ];
}

/**
 * Normalise le nom de catégorie pour comparaison simple (synonymes).
 */
function fpl_etiquette_nom_normalise_synonyme(?string $nom)
{
    $s = trim((string) $nom);
    if ($s === '') {
        return '';
    }
    if (function_exists('mb_strtolower')) {
        $s = mb_strtolower($s, 'UTF-8');
    } else {
        $s = strtolower($s);
    }
    $s = preg_replace('/\s+/u', ' ', $s);
    if (function_exists('iconv')) {
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        if ($t !== false && $t !== '') {
            $s = strtolower($t);
        }
    }
    return $s;
}

/**
 * @return string '' si aucun synonyme
 */
function fpl_etiquette_couleur_par_synonyme(string $nom_normalise)
{
    if ($nom_normalise === '') {
        return '';
    }
    foreach (fpl_etiquette_synonymes_couleur_liste() as $row) {
        $needle = $row[0];
        $hex = fpl_etiquette_sanitize_hex_couleur($row[1]);
        if ($needle !== '' && $hex !== '' && strpos($nom_normalise, $needle) !== false) {
            return $hex;
        }
    }
    return '';
}

/**
 * CRC32 non signé (stable sur toutes les plateformes PHP).
 */
function fpl_etiquette_crc32_unsigned(string $data)
{
    if (function_exists('hash')) {
        return hexdec(hash('crc32b', $data));
    }
    $c = crc32($data);
    if ($c < 0) {
        $c += 4294967296;
    }
    return $c;
}

/**
 * Couleur automatique distincte par catégorie (stable si l’ID ou le nom ne change pas).
 */
function fpl_etiquette_couleur_stable_auto(int $categorie_id, string $nom_normalise)
{
    $palette = fpl_etiquette_palette_rotation_auto();
    $n = count($palette);
    if ($n < 1) {
        return fpl_etiquette_couleur_bdd_defaut();
    }
    $key = 'fpletiq|' . $categorie_id . '|' . $nom_normalise;
    $idx = (int) (fpl_etiquette_crc32_unsigned($key) % $n);
    return strtoupper($palette[$idx]);
}

/**
 * @return string Toujours une couleur hex #RRGGBB
 */
function fpl_etiquette_sanitize_hex_couleur(?string $value)
{
    if ($value === null || trim($value) === '') {
        return '';
    }
    $v = strtoupper(trim($value));
    if (preg_match('/^#([0-9A-F]{6})$/', $v)) {
        return '#' . substr($v, -6);
    }
    if (preg_match('/^([0-9A-F]{6})$/', $v)) {
        return '#' . $v;
    }
    return '';
}

function fpl_etiquette_hex_components(string $hex6)
{
    $hex6 = ltrim($hex6, '#');
    if (strlen($hex6) !== 6 || !preg_match('/^[0-9A-Fa-f]{6}$/', $hex6)) {
        return ['r' => 30, 'g' => 58, 'b' => 95];
    }
    return [
        'r' => hexdec(substr($hex6, 0, 2)),
        'g' => hexdec(substr($hex6, 2, 2)),
        'b' => hexdec(substr($hex6, 4, 2)),
    ];
}

/** Assombrit ou éclaircit (amt négatif) pour barres fines */
function fpl_etiquette_hex_adjust_rgb(string $hex6, float $amt)
{
    $c = fpl_etiquette_hex_components($hex6);
    $r = max(0, min(255, (int) round($c['r'] + $amt)));
    $g = max(0, min(255, (int) round($c['g'] + $amt)));
    $b = max(0, min(255, (int) round($c['b'] + $amt)));
    return sprintf('#%02X%02X%02X', $r, $g, $b);
}

/**
 * Couleur d'accent pour l'étiquette FPL (bandeau, écusson, séparateur vertical, pied de page, pictogrammes).
 * Valeur unique pour tous les produits et toutes les catégories.
 *
 * @param array|null $categorie Paramètres ignorés (compatibilité des appels existants)
 * @return string #RRGGBB
 */
function fpl_etiquette_couleur_pour_categorie($categorie, $categorie_id = null)
{
    return '#19377d';
}

/** Petite ligne numérique au-dessus du QR à partir du code FPL */
function fpl_etiquette_mini_ref_qr($identifiant)
{
    $d = preg_replace('/\D+/', '', (string) $identifiant);
    if ($d === '') {
        return substr(preg_replace('/[^A-Za-z0-9]/', '', (string) $identifiant), 0, 12);
    }
    return substr(str_pad($d, 9, '0', STR_PAD_LEFT), -9);
}

/**
 * Affichage référence type maquette : « FPL01 807570 ».
 */
function fpl_etiquette_format_ref_affichage($identifiant)
{
    $id = strtoupper(trim((string) $identifiant));
    if (preg_match('/^(FPL)(\d{2})(\d{4,})$/', $id, $m)) {
        return $m[1] . $m[2] . ' ' . $m[3];
    }
    return $id;
}

/**
 * Lignes compatibilité colonne droite (véhicules + réf. constructeur / fournisseur).
 *
 * @param array<string,mixed> $produit
 * @return array{0:string,1:string}
 */
function fpl_etiquette_lignes_compatibilite(array $produit)
{
    $l1 = '';
    $l2 = '';

    $desc = trim((string) ($produit['description'] ?? ''));
    if ($desc !== '') {
        $lines = preg_split('/\R+/u', $desc) ?: [];
        $clean = [];
        foreach ($lines as $ln) {
            $ln = trim(preg_replace('/\s+/u', ' ', (string) $ln));
            if ($ln !== '') {
                $clean[] = $ln;
            }
        }
        if (isset($clean[0])) {
            $l1 = $clean[0];
        }
        if (isset($clean[1]) && $l2 === '') {
            $l2 = $clean[1];
        }
    }

    $marque = '';
    if (function_exists('produits_marque_libelle_from_row')) {
        $marque = trim((string) produits_marque_libelle_from_row($produit));
    }
    if ($marque === '') {
        $marque = trim((string) ($produit['marque_nom'] ?? $produit['nom_fournisseur'] ?? ''));
    }
    $ref_f = trim((string) ($produit['reference_fournisseur'] ?? ''));

    if ($l2 === '' && ($marque !== '' || $ref_f !== '')) {
        $l2 = trim($marque . ($marque !== '' && $ref_f !== '' ? ' ' : '') . $ref_f);
    } elseif ($l1 === '' && $marque !== '') {
        $l1 = $marque;
        if ($l2 === '' && $ref_f !== '') {
            $l2 = $ref_f;
        }
    }

    if (function_exists('mb_substr')) {
        if ($l1 !== '') {
            $l1 = mb_substr($l1, 0, 48, 'UTF-8');
        }
        if ($l2 !== '') {
            $l2 = mb_substr($l2, 0, 48, 'UTF-8');
        }
    } else {
        $l1 = substr($l1, 0, 48);
        $l2 = substr($l2, 0, 48);
    }

    return [$l1, $l2];
}

/**
 * Pied de page défaut « FOUTA POIDS LOURDS » — maquette étiquette 70×70.
 *
 * @return array{entreprise:string,sous_nom:string,adr:string,adr_rue:string,adr_bp:string,tels:string,web:string,mail:string}
 */
function fpl_etiquette_footer_textes_par_defaut()
{
    return [
        'entreprise' => 'FPL — FOUTA POIDS LOURDS',
        'sous_nom' => 'The Solution Suarl',
        'adr' => 'SG/ROND-POINT ZAC MBAO 106 RUE MARSAT X BLAISE DIAGNE BP - 7661 DAKAR (SENEGAL)',
        'adr_rue' => 'SG/ROND-POINT ZAC MBAO 106 RUE MARSAT X BLAISE DIAGNE',
        'adr_bp' => 'BP - 7661 DAKAR (SENEGAL)',
        'tels' => '+221 33 870 00 70 / +221 33 842 78 77',
        'web' => 'www.foutapoidslourds.com',
        'mail' => 'info@foutapoidslourds.com',
    ];
}

/**
 * Badge véhicule PL pour vignette étiquette : remorque, camion, tracteur, bus (Font Awesome solid, FA >= 6).
 *
 * @param int $svg_px Taille du pictogramme en px (SVG ou équivalent Font Awesome).
 * @return array{title:string,svg:string} svg : fragment HTML (SVG ou balise &lt;i&gt; FA)
 */
function fpl_etiquette_thumb_vehicle_badge(int $thumb_index, int $svg_px = 12)
{
    $i = $thumb_index % 4;
    $svg_px = max(8, min(64, $svg_px));
    $titles = ['Bus', 'Camion', 'Tracteur', 'Bus'];
    $icons = ['fa-trailer', 'fa-truck', 'fa-tractor', 'fa-bus'];

    return [
        'title' => $titles[$i],
        'svg' => '<i class="fa-solid ' . $icons[$i] . ' fpl-etiq__thumb-fa" style="font-size:' . $svg_px . 'px;" aria-hidden="true"></i>',
    ];
}
