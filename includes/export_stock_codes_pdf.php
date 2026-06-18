<?php
/**
 * Export PDF (HTML → PDF via Dompdf) : code-barres FPL et QR code stock.
 */

require_once __DIR__ . '/barcode_fpl.php';
require_once __DIR__ . '/site_url.php';

/** @var string|null Dernière erreur PDF codes stock (diagnostic admin) */
$GLOBALS['stock_codes_pdf_last_error'] = null;

/**
 * @param string $message
 */
function stock_codes_pdf_set_error($message) {
    $GLOBALS['stock_codes_pdf_last_error'] = (string) $message;
}

/**
 * @return string|null
 */
function stock_codes_pdf_get_last_error() {
    return isset($GLOBALS['stock_codes_pdf_last_error']) ? $GLOBALS['stock_codes_pdf_last_error'] : null;
}

/**
 * @return string
 */
function stock_code_pdf_escape($text) {
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

/**
 * @return string data:image/png;base64,… ou chaîne vide
 */
function stock_code_png_to_data_uri($png_path) {
    if (!is_file($png_path)) {
        return '';
    }
    $raw = @file_get_contents($png_path);
    if ($raw === false || $raw === '') {
        return '';
    }

    return stock_code_bytes_to_data_uri($raw, 'image/png');
}

/**
 * @return string
 */
function stock_code_bytes_to_data_uri($raw, $mime = 'image/png') {
    if ($raw === '' || $raw === false) {
        return '';
    }

    return 'data:' . $mime . ';base64,' . base64_encode($raw);
}

/**
 * @return string data:image/svg+xml;base64,… ou chaîne vide
 */
function stock_code_svg_to_data_uri($svg) {
    $svg = trim((string) $svg);
    if ($svg === '') {
        return '';
    }

    return stock_code_bytes_to_data_uri($svg, 'image/svg+xml');
}

/**
 * Vérifie les prérequis Dompdf / Composer.
 *
 * @return bool
 */
function stock_codes_pdf_vendor_ok() {
    return is_file(__DIR__ . '/../vendor/autoload.php');
}

/**
 * @return string PNG binaire ou chaîne vide
 */
function stock_generate_barcode_png_bytes($produit_id, $produit = null) {
    if (!stock_codes_pdf_vendor_ok()) {
        stock_codes_pdf_set_error('Dépendances Composer absentes (vendor/autoload.php). Exécutez composer install sur le serveur.');
        return '';
    }

    $produit_id = (int) $produit_id;
    if ($produit_id <= 0) {
        stock_codes_pdf_set_error('Identifiant produit invalide.');
        return '';
    }

    $code = '';
    if (is_array($produit) && !empty($produit['identifiant_interne'])) {
        $code = strtoupper(trim((string) $produit['identifiant_interne']));
    }
    if ($code === '') {
        $code = ensure_produit_identifiant_interne($produit_id);
        $code = $code !== null ? strtoupper(trim((string) $code)) : '';
    }
    if ($code === '' || !preg_match('/^FPL(\d{6}|\d{9})$/', $code)) {
        stock_codes_pdf_set_error('Code FPL introuvable ou invalide pour ce produit.');
        return '';
    }

    require_once __DIR__ . '/../vendor/autoload.php';

    try {
        $generator = new \Picqer\Barcode\BarcodeGeneratorPNG();
        if (function_exists('imagecreate')) {
            $generator->useGd();
        }
        $png = $generator->getBarcode($code, $generator::TYPE_CODE_128, 2, 56);
        if ($png === false || $png === '') {
            stock_codes_pdf_set_error('Génération code-barres impossible (extension PHP GD requise).');
            return '';
        }

        $dir = __DIR__ . '/../upload/barcodes/';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        if (is_dir($dir) && is_writable($dir)) {
            @file_put_contents($dir . 'produit_' . $produit_id . '.png', $png);
        }

        return $png;
    } catch (Throwable $e) {
        stock_codes_pdf_set_error('Erreur code-barres : ' . $e->getMessage());
        return '';
    }
}

/**
 * @return array{data_uri: string, format: string}
 */
function stock_generate_qr_render_for_pdf($stock_info_url) {
    $stock_info_url = trim((string) $stock_info_url);
    if ($stock_info_url === '') {
        stock_codes_pdf_set_error('URL du QR code vide.');
        return ['data_uri' => '', 'format' => ''];
    }

    if (!stock_codes_pdf_vendor_ok()) {
        stock_codes_pdf_set_error('Dépendances Composer absentes (vendor/autoload.php).');
        return ['data_uri' => '', 'format' => ''];
    }

    require_once __DIR__ . '/../vendor/autoload.php';

    $attempts = [
        ['type' => \chillerlan\QRCode\Output\QROutputInterface::GDIMAGE_PNG, 'mime' => 'image/png', 'label' => 'png'],
        ['type' => \chillerlan\QRCode\Output\QROutputInterface::MARKUP_SVG, 'mime' => 'image/svg+xml', 'label' => 'svg'],
    ];

    foreach ($attempts as $attempt) {
        try {
            $qro = new \chillerlan\QRCode\QROptions([
                'outputType' => $attempt['type'],
                'scale' => 8,
            ]);
            $qr = new \chillerlan\QRCode\QRCode($qro);
            $out = $qr->render($stock_info_url);
            if ($out === false || $out === '') {
                continue;
            }
            if ($attempt['label'] === 'png') {
                return ['data_uri' => stock_code_bytes_to_data_uri($out, 'image/png'), 'format' => 'png'];
            }

            return ['data_uri' => stock_code_svg_to_data_uri($out), 'format' => 'svg'];
        } catch (Throwable $e) {
            continue;
        }
    }

    stock_codes_pdf_set_error('Génération QR impossible (extension PHP GD recommandée).');
    return ['data_uri' => '', 'format' => ''];
}

/**
 * @return string Chemin absolu du PNG QR (généré si absent)
 */
function stock_ensure_qr_png_path($produit_id, $stock_info_url) {
    $produit_id = (int) $produit_id;
    if ($produit_id <= 0 || trim((string) $stock_info_url) === '') {
        return '';
    }

    $dir = __DIR__ . '/../upload/qrcodes/';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $file = $dir . 'produit_' . $produit_id . '.png';
    if (is_file($file)) {
        return $file;
    }

    $render = stock_generate_qr_render_for_pdf($stock_info_url);
    if ($render['format'] === 'png' && $render['data_uri'] !== '') {
        $raw = base64_decode(substr($render['data_uri'], strpos($render['data_uri'], ',') + 1), true);
        if ($raw !== false && @file_put_contents($file, $raw) !== false) {
            return $file;
        }
    }

    return is_file($file) ? $file : '';
}

/**
 * @return string data URI (PNG ou SVG)
 */
function stock_get_qr_data_uri_for_pdf($produit_id, $stock_info_url) {
    $produit_id = (int) $produit_id;
    $png = stock_ensure_qr_png_path($produit_id, $stock_info_url);
    if ($png !== '' && is_file($png)) {
        return stock_code_png_to_data_uri($png);
    }

    $render = stock_generate_qr_render_for_pdf($stock_info_url);
    return $render['data_uri'];
}

/**
 * @return string
 */
function stock_code_pdf_safe_filename($base, $ext = 'pdf') {
    $base = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) $base);
    $base = trim((string) $base, '-');
    if ($base === '') {
        $base = 'export';
    }

    return $base . '.' . $ext;
}

/**
 * @return string
 */
function stock_code_pdf_image_only_styles($kind, $qr_format = 'png') {
    if ($kind === 'qrcode') {
        $img_rule = $qr_format === 'svg'
            ? '.code-img { width: 70mm; height: 70mm; }'
            : '.code-img { width: 70mm; height: 70mm; }';
    } else {
        $img_rule = '.code-img { width: 140mm; max-width: 100%; height: auto; }';
    }

    return <<<'CSS'
@page { margin: 0; }
html, body {
    margin: 0;
    padding: 0;
    width: 100%;
    height: 100%;
    background: #ffffff;
}
.page {
    width: 100%;
    height: 297mm;
    display: table;
}
.center {
    display: table-cell;
    vertical-align: middle;
    text-align: center;
}
.code-img {
    display: inline-block;
    margin: 0;
    padding: 0;
    border: 0;
}
CSS
        . $img_rule;
}

/**
 * @return string
 */
function stock_build_image_only_pdf_html($data_uri, $kind, $qr_format = 'png') {
    $kind = $kind === 'qrcode' ? 'qrcode' : 'barcode';
    $styles = stock_code_pdf_image_only_styles($kind, $qr_format);
    $alt = $kind === 'qrcode' ? 'QR code' : 'Code-barres';

    return '<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>' . stock_code_pdf_escape($alt) . '</title>
<style>' . $styles . '</style>
</head>
<body>
<div class="page">
    <div class="center">
        <img class="code-img" src="' . stock_code_pdf_escape($data_uri) . '" alt="' . stock_code_pdf_escape($alt) . '">
    </div>
</div>
</body>
</html>';
}

/**
 * Génère et envoie un PDF en téléchargement à partir d’un document HTML.
 */
function stock_send_html_as_pdf($html, $filename) {
    stock_codes_pdf_set_error(null);

    if (!stock_codes_pdf_vendor_ok()) {
        stock_codes_pdf_set_error('Bibliothèque Dompdf absente. Déployez le dossier vendor/ (composer install).');
        return false;
    }

    require_once __DIR__ . '/admin_pdf_response.php';

    require_once __DIR__ . '/../vendor/autoload.php';

    $root = realpath(__DIR__ . '/..');
    if ($root === false) {
        stock_codes_pdf_set_error('Chemin racine du projet introuvable.');
        return false;
    }

    try {
        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('chroot', $root);

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $safe_name = stock_code_pdf_safe_filename(pathinfo((string) $filename, PATHINFO_FILENAME));
        $pdf_output = $dompdf->output();

        if ($pdf_output === '' || $pdf_output === false) {
            stock_codes_pdf_set_error('Dompdf n’a produit aucun contenu PDF.');
            return false;
        }

        if (!admin_pdf_send_binary($pdf_output, $safe_name . '.pdf')) {
            stock_codes_pdf_set_error('Impossible d’envoyer le PDF (en-têtes déjà envoyés).');
            return false;
        }

        return true;
    } catch (Throwable $e) {
        stock_codes_pdf_set_error('Dompdf : ' . $e->getMessage());
        return false;
    }
}

/**
 * @param array<string, mixed> $produit
 */
function stock_send_barcode_pdf($produit) {
    stock_codes_pdf_set_error(null);
    $produit_id = (int) ($produit['id'] ?? 0);
    if ($produit_id <= 0) {
        stock_codes_pdf_set_error('Produit invalide.');
        return false;
    }

    $png_bytes = stock_generate_barcode_png_bytes($produit_id, $produit);
    $data_uri = stock_code_bytes_to_data_uri($png_bytes, 'image/png');
    if ($data_uri === '') {
        if (stock_codes_pdf_get_last_error() === null) {
            stock_codes_pdf_set_error('Impossible de produire le code-barres.');
        }
        return false;
    }

    $code = trim((string) ($produit['identifiant_interne'] ?? ''));
    $html = stock_build_image_only_pdf_html($data_uri, 'barcode');
    $filename = stock_code_pdf_safe_filename('code-barres-' . ($code !== '' ? $code : 'produit-' . $produit_id));

    return stock_send_html_as_pdf($html, $filename);
}

/**
 * @param array<string, mixed> $produit
 */
function stock_send_qrcode_pdf($produit, $stock_info_url) {
    stock_codes_pdf_set_error(null);
    $produit_id = (int) ($produit['id'] ?? 0);
    if ($produit_id <= 0) {
        stock_codes_pdf_set_error('Produit invalide.');
        return false;
    }

    $stock_info_url = trim((string) $stock_info_url);
    if ($stock_info_url === '') {
        stock_codes_pdf_set_error('URL publique du produit introuvable (config/site.php).');
        return false;
    }

    $render = stock_generate_qr_render_for_pdf($stock_info_url);
    $data_uri = $render['data_uri'];
    if ($data_uri === '') {
        $data_uri = stock_get_qr_data_uri_for_pdf($produit_id, $stock_info_url);
        $render['format'] = 'png';
    }
    if ($data_uri === '') {
        if (stock_codes_pdf_get_last_error() === null) {
            stock_codes_pdf_set_error('Impossible de produire le QR code.');
        }
        return false;
    }

    if ($render['format'] === 'png' && is_dir(__DIR__ . '/../upload/qrcodes')) {
        $raw = base64_decode(substr($data_uri, strpos($data_uri, ',') + 1), true);
        if ($raw !== false) {
            @file_put_contents(__DIR__ . '/../upload/qrcodes/produit_' . $produit_id . '.png', $raw);
        }
    }

    $code = trim((string) ($produit['identifiant_interne'] ?? ''));
    $html = stock_build_image_only_pdf_html($data_uri, 'qrcode', $render['format'] ?? 'png');
    $slug = $code !== '' ? $code : ('produit-' . $produit_id);
    $filename = stock_code_pdf_safe_filename('qrcode-' . $slug);

    return stock_send_html_as_pdf($html, $filename);
}
