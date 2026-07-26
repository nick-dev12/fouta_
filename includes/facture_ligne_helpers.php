<?php
/**
 * Affichage image produit sur les lignes de facture / BL / devis.
 */

function facture_ligne_img_size_px()
{
    return 64;
}

function facture_ligne_image_from_produit_id($produit_id)
{
    static $cache = [];
    $produit_id = (int) $produit_id;
    if ($produit_id <= 0) {
        return '';
    }
    if (!array_key_exists($produit_id, $cache)) {
        $cache[$produit_id] = '';
        if (!function_exists('get_produit_by_id')) {
            require_once __DIR__ . '/../models/model_produits.php';
        }
        $pr = get_produit_by_id($produit_id);
        if ($pr) {
            $cache[$produit_id] = trim((string) ($pr['image_principale'] ?? ''));
        }
    }

    return $cache[$produit_id];
}

function facture_ligne_ref_fpl_from_produit_id($produit_id)
{
    static $cache = [];
    $produit_id = (int) $produit_id;
    if ($produit_id <= 0) {
        return '';
    }
    if (!array_key_exists($produit_id, $cache)) {
        $cache[$produit_id] = '';
        if (function_exists('produits_has_column') && produits_has_column('identifiant_interne')) {
            if (!function_exists('get_produit_by_id')) {
                require_once __DIR__ . '/../models/model_produits.php';
            }
            $pr = get_produit_by_id($produit_id);
            if ($pr) {
                $cache[$produit_id] = strtoupper(trim((string) ($pr['identifiant_interne'] ?? '')));
            }
        }
    }

    return $cache[$produit_id];
}

function facture_ligne_ref_fpl_from_row(array $row)
{
    foreach (['ref_fpl', 'identifiant_interne', 'ref_produit'] as $key) {
        $val = trim((string) ($row[$key] ?? ''));
        if ($val !== '') {
            return strtoupper($val);
        }
    }

    return facture_ligne_ref_fpl_from_produit_id((int) ($row['produit_id'] ?? 0));
}

function facture_ligne_nom_from_row(array $row)
{
    return trim((string) (
        $row['produit_nom']
        ?? $row['nom_produit']
        ?? $row['nom']
        ?? $row['designation']
        ?? ''
    ));
}

function facture_ligne_article_cell_html(array $row)
{
    $nom = facture_ligne_nom_from_row($row);
    $ref = facture_ligne_ref_fpl_from_row($row);
    $html = htmlspecialchars($nom, ENT_QUOTES, 'UTF-8');
    if ($ref !== '') {
        $html .= '<span class="facture-ligne-ref"><code>' . htmlspecialchars($ref, ENT_QUOTES, 'UTF-8') . '</code></span>';
    }

    return $html;
}

function facture_ligne_image_web_path(array $row)
{
    $img = trim((string) ($row['image_afficher'] ?? $row['image_principale'] ?? ''));
    if ($img === '') {
        $pid = (int) ($row['produit_id'] ?? 0);
        if ($pid > 0) {
            $img = facture_ligne_image_from_produit_id($pid);
        }
    }
    if ($img === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $img)) {
        return $img;
    }

    return '/upload/' . ltrim(str_replace('\\', '/', $img), '/');
}

function facture_ligne_image_cell_html(array $row)
{
    $size = facture_ligne_img_size_px();
    $url = facture_ligne_image_web_path($row);
    if ($url === '') {
        return '<span class="facture-ligne-img facture-ligne-img--empty" aria-hidden="true">—</span>';
    }

    return '<img class="facture-ligne-img" src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" alt="" width="' . $size . '" height="' . $size . '" loading="lazy">';
}
