<?php
/**
 * Génération QR code et code-barres pour les barres entrepôt.
 */
require_once __DIR__ . '/../models/model_entrepot_referentiel.php';
require_once __DIR__ . '/site_url.php';

/**
 * @param int $barre_id
 * @return string URL scan admin
 */
function entrepot_barre_scan_url($barre_id) {
    $barre = entrepot_get_barre_by_id($barre_id);
    if ($barre === null || empty($barre['code_scan'])) {
        return '';
    }

    return rtrim(get_site_base_url(), '/') . '/admin/entrepot/barre-info.php?c=' . rawurlencode((string) $barre['code_scan']);
}

/**
 * @param int $barre_id
 * @return bool
 */
function generer_barcode_barre($barre_id) {
    if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
        return false;
    }
    require_once __DIR__ . '/../vendor/autoload.php';

    $barre_id = (int) $barre_id;
    $barre = entrepot_get_barre_by_id($barre_id);
    if ($barre === null) {
        return false;
    }
    $code = (string) ($barre['code_scan'] ?? '');
    if ($code === '') {
        $code = entrepot_barre_generer_code_scan($barre_id) ?: '';
    }
    if ($code === '') {
        return false;
    }

    $dir = __DIR__ . '/../upload/barcodes/';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $file = $dir . 'barre_' . $barre_id . '.png';

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
 * @param int $barre_id
 * @return bool
 */
function generer_qrcode_barre($barre_id) {
    if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
        return false;
    }
    require_once __DIR__ . '/../vendor/autoload.php';

    $barre_id = (int) $barre_id;
    $url = entrepot_barre_scan_url($barre_id);
    if ($url === '') {
        return false;
    }

    $dir = __DIR__ . '/../upload/qrcodes/';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $file = $dir . 'barre_' . $barre_id . '.png';

    try {
        $qro = new \chillerlan\QRCode\QROptions([
            'outputType' => \chillerlan\QRCode\Output\QROutputInterface::GDIMAGE_PNG,
            'scale' => 6,
            'imageBase64' => false,
        ]);
        $qr = new \chillerlan\QRCode\QRCode($qro);
        $png = $qr->render($url);
        if (!is_string($png) || $png === '') {
            return false;
        }

        return file_put_contents($file, $png) !== false && is_file($file);
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * @param int $barre_id
 * @return string
 */
function get_barcode_barre_web_path($barre_id) {
    $barre_id = (int) $barre_id;
    $rel = 'barcodes/barre_' . $barre_id . '.png';
    $full = __DIR__ . '/../upload/' . $rel;

    return is_file($full) ? '/upload/' . $rel : '';
}

/**
 * @param int $barre_id
 * @return string
 */
function get_qrcode_barre_web_path($barre_id) {
    $barre_id = (int) $barre_id;
    $rel = 'qrcodes/barre_' . $barre_id . '.png';
    $full = __DIR__ . '/../upload/' . $rel;

    return is_file($full) ? '/upload/' . $rel : '';
}

/**
 * @param int $barre_id
 * @return bool
 */
function entrepot_generer_codes_barre($barre_id) {
    entrepot_barre_generer_code_scan($barre_id);
    $ok1 = generer_barcode_barre($barre_id);
    $ok2 = generer_qrcode_barre($barre_id);

    return $ok1 || $ok2;
}
