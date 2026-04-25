<?php
/**
 * Code-barres Code 128 pour ticket caisse (même logique que produits FPL, Picqer)
 * Prérequis : vendor/autoload.php et caisse_ticket_valeur_code_barres() (model_caisse)
 */

/**
 * Génère / met à jour le PNG et retourne l’URL web (ex. /upload/barcodes/caisse_vente_6.png)
 */
function caisse_ticket_get_barcode_web_path(array $row)
{
    if (!function_exists('caisse_ticket_valeur_code_barres')) {
        return '';
    }
    $payload = caisse_ticket_valeur_code_barres($row);
    if ($payload === '') {
        return '';
    }
    $id = (int) ($row['id'] ?? 0);
    if ($id <= 0) {
        return '';
    }
    if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
        return '';
    }
    require_once __DIR__ . '/../vendor/autoload.php';

    $dir = __DIR__ . '/../upload/barcodes/';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $file = $dir . 'caisse_vente_' . $id . '.png';
    $rel = '/upload/barcodes/caisse_vente_' . $id . '.png';

    try {
        $generator = new \Picqer\Barcode\BarcodeGeneratorPNG();
        if (function_exists('imagecreate')) {
            $generator->useGd();
        }
        $png = $generator->getBarcode($payload, $generator::TYPE_CODE_128, 2, 56);
        if ($png === false || $png === '') {
            return '';
        }
        if (file_put_contents($file, $png) === false) {
            return '';
        }
        return $rel;
    } catch (Throwable $e) {
        return '';
    }
}
