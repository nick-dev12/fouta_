<?php
/**
 * Export PDF (HTML → PDF via Dompdf) : code-barres FPL et QR code stock.
 */

require_once __DIR__ . '/barcode_fpl.php';
require_once __DIR__ . '/site_url.php';

/**
 * @return string
 */
function stock_code_pdf_escape($text)
{
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

/**
 * @return string data:image/png;base64,… ou chaîne vide
 */
function stock_code_png_to_data_uri($png_path)
{
    if (!is_file($png_path)) {
        return '';
    }
    $raw = @file_get_contents($png_path);
    if ($raw === false || $raw === '') {
        return '';
    }
    return 'data:image/png;base64,' . base64_encode($raw);
}

/**
 * @return string Chemin absolu du PNG QR (généré si absent)
 */
function stock_ensure_qr_png_path($produit_id, $stock_info_url)
{
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

    if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
        return '';
    }
    require_once __DIR__ . '/../vendor/autoload.php';

    try {
        $qro = new \chillerlan\QRCode\QROptions([
            'outputType' => \chillerlan\QRCode\Output\QROutputInterface::GDIMAGE_PNG,
            'scale'      => 8,
        ]);
        $qr = new \chillerlan\QRCode\QRCode($qro);
        $png = $qr->render($stock_info_url);
        if ($png !== false && $png !== '' && @file_put_contents($file, $png) !== false) {
            return $file;
        }
    } catch (Throwable $e) {
        return '';
    }

    return is_file($file) ? $file : '';
}

/**
 * @return string
 */
function stock_code_pdf_safe_filename($base, $ext = 'pdf')
{
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
function stock_code_pdf_image_only_styles($kind)
{
    $img_rule = $kind === 'qrcode'
        ? '.code-img { width: 70mm; height: 70mm; }'
        : '.code-img { width: 140mm; max-width: 100%; height: auto; }';

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
function stock_build_image_only_pdf_html($data_uri, $kind)
{
    $kind = $kind === 'qrcode' ? 'qrcode' : 'barcode';
    $styles = stock_code_pdf_image_only_styles($kind);
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
 * @return string
 */
function stock_build_barcode_pdf_html($produit, $barcode_data_uri)
{
    return stock_build_image_only_pdf_html($barcode_data_uri, 'barcode');
}

/**
 * @return string
 */
function stock_build_qrcode_pdf_html($produit, $qr_data_uri, $stock_info_url)
{
    return stock_build_image_only_pdf_html($qr_data_uri, 'qrcode');
}

/**
 * Génère et envoie un PDF en téléchargement à partir d’un document HTML.
 */
function stock_send_html_as_pdf($html, $filename)
{
    if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
        return false;
    }
    require_once __DIR__ . '/../vendor/autoload.php';

    $options = new \Dompdf\Options();
    $options->set('isRemoteEnabled', false);
    $options->set('isHtml5ParserEnabled', true);
    $options->set('defaultFont', 'DejaVu Sans');

    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $safe_name = stock_code_pdf_safe_filename(pathinfo((string) $filename, PATHINFO_FILENAME));
    $dompdf->stream($safe_name, ['Attachment' => true]);

    return true;
}

/**
 * @param array<string, mixed> $produit
 */
function stock_send_barcode_pdf($produit)
{
    $produit_id = (int) ($produit['id'] ?? 0);
    if ($produit_id <= 0) {
        return false;
    }

    if (get_barcode_produit_web_path($produit_id) === '') {
        generer_barcode_produit_fpl($produit_id);
    }

    $png = __DIR__ . '/../upload/barcodes/produit_' . $produit_id . '.png';
    $data_uri = stock_code_png_to_data_uri($png);
    if ($data_uri === '') {
        return false;
    }

    $code = trim((string) ($produit['identifiant_interne'] ?? ''));
    $html = stock_build_barcode_pdf_html($produit, $data_uri);
    $filename = stock_code_pdf_safe_filename('code-barres-' . ($code !== '' ? $code : 'produit-' . $produit_id));

    return stock_send_html_as_pdf($html, $filename);
}

/**
 * @param array<string, mixed> $produit
 */
function stock_send_qrcode_pdf($produit, $stock_info_url)
{
    $produit_id = (int) ($produit['id'] ?? 0);
    if ($produit_id <= 0) {
        return false;
    }

    $png = stock_ensure_qr_png_path($produit_id, $stock_info_url);
    $data_uri = stock_code_png_to_data_uri($png);
    if ($data_uri === '') {
        return false;
    }

    $code = trim((string) ($produit['identifiant_interne'] ?? ''));
    $html = stock_build_qrcode_pdf_html($produit, $data_uri, $stock_info_url);
    $slug = $code !== '' ? $code : ('produit-' . $produit_id);
    $filename = stock_code_pdf_safe_filename('qrcode-' . $slug);

    return stock_send_html_as_pdf($html, $filename);
}
