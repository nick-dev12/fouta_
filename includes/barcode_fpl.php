<?php
/**
 * Génération du code-barres (Code 128) pour l'identifiant interne FPLxxxxxx
 */

require_once __DIR__ . '/../models/model_produits.php';

/**
 * Génère et enregistre le PNG sous upload/barcodes/produit_{id}.png
 * @return bool
 */
function generer_barcode_produit_fpl($produit_id)
{
    if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
        return false;
    }
    require_once __DIR__ . '/../vendor/autoload.php';

    $produit_id = (int) $produit_id;
    if ($produit_id <= 0) {
        return false;
    }

    $code = ensure_produit_identifiant_interne($produit_id);
    if ($code === null || $code === '') {
        return false;
    }
    $code = strtoupper(trim($code));
    if (!preg_match('/^FPL\d{6}$/', $code)) {
        return false;
    }

    $dir = __DIR__ . '/../upload/barcodes/';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $file = $dir . 'produit_' . $produit_id . '.png';

    try {
        $generator = new \Picqer\Barcode\BarcodeGeneratorPNG();
        if (function_exists('imagecreate')) {
            $generator->useGd();
        }
        $png = $generator->getBarcode($code, $generator::TYPE_CODE_128, 2, 56);
        if ($png === false || $png === '') {
            return false;
        }
        return file_put_contents($file, $png) !== false && is_file($file);
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * URL web du fichier code-barres si présent
 */
function get_barcode_produit_web_path($produit_id)
{
    $produit_id = (int) $produit_id;
    if ($produit_id <= 0) {
        return '';
    }
    $rel = 'barcodes/produit_' . $produit_id . '.png';
    $full = __DIR__ . '/../upload/' . $rel;
    if (is_file($full)) {
        return '/upload/' . $rel;
    }
    return '';
}
