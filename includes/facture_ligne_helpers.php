<?php
/**
 * Affichage image produit sur les lignes de facture / BL / devis.
 */

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
    $url = facture_ligne_image_web_path($row);
    if ($url === '') {
        return '<span class="facture-ligne-img facture-ligne-img--empty" aria-hidden="true">—</span>';
    }

    return '<img class="facture-ligne-img" src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" alt="" width="44" height="44" loading="lazy">';
}
