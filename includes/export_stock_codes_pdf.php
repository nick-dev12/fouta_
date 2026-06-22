<?php
/**
 * Export PDF (HTML → PDF via Dompdf) : code-barres FPL et QR code stock.
 */

require_once __DIR__ . '/barcode_fpl.php';
require_once __DIR__ . '/site_url.php';
require_once __DIR__ . '/produit_emplacement_entrepot.php';

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
 * @param string $raw
 * @return bool
 */
function stock_code_is_png_bytes($raw) {
    return is_string($raw) && strlen($raw) >= 8 && substr($raw, 0, 8) === "\x89PNG\r\n\x1a\n";
}

/**
 * @param string $data_uri
 * @return string PNG binaire ou chaîne vide
 */
function stock_code_png_bytes_from_data_uri($data_uri) {
    $data_uri = trim((string) $data_uri);
    if ($data_uri === '' || strpos($data_uri, 'data:') !== 0) {
        return '';
    }

    $comma = strrpos($data_uri, ',');
    if ($comma === false) {
        return '';
    }

    $decoded = base64_decode(substr($data_uri, $comma + 1), true);
    if ($decoded === false || $decoded === '') {
        return '';
    }

    if (stock_code_is_png_bytes($decoded)) {
        return $decoded;
    }

    // Corrigé : double encodage (data URI encodée une seconde fois en base64)
    if (strpos($decoded, 'data:') === 0) {
        return stock_code_png_bytes_from_data_uri($decoded);
    }

    return '';
}

/**
 * Normalise la sortie de chillerlan/QRCode (data URI, PNG binaire ou SVG).
 *
 * @param string $output
 * @return array{bytes: string, data_uri: string, format: string, svg: string}
 */
function stock_code_normalize_qr_library_output($output) {
    $output = (string) $output;
    if ($output === '') {
        return ['bytes' => '', 'data_uri' => '', 'format' => '', 'svg' => ''];
    }

    if (strpos($output, 'data:') === 0) {
        $png = stock_code_png_bytes_from_data_uri($output);
        if ($png !== '') {
            return [
                'bytes' => $png,
                'data_uri' => stock_code_bytes_to_data_uri($png, 'image/png'),
                'format' => 'png',
                'svg' => '',
            ];
        }
        if (stripos($output, 'image/svg') !== false || stripos($output, 'svg+xml') !== false) {
            $comma = strrpos($output, ',');
            $svg = $comma !== false ? base64_decode(substr($output, $comma + 1), true) : '';
            if ($svg === false) {
                $svg = '';
            }

            return ['bytes' => '', 'data_uri' => $output, 'format' => 'svg', 'svg' => (string) $svg];
        }

        return ['bytes' => '', 'data_uri' => $output, 'format' => 'png', 'svg' => ''];
    }

    if (stock_code_is_png_bytes($output)) {
        return [
            'bytes' => $output,
            'data_uri' => stock_code_bytes_to_data_uri($output, 'image/png'),
            'format' => 'png',
            'svg' => '',
        ];
    }

    if (stripos(ltrim($output), '<svg') === 0) {
        return [
            'bytes' => '',
            'data_uri' => stock_code_svg_to_data_uri($output),
            'format' => 'svg',
            'svg' => $output,
        ];
    }

    return ['bytes' => '', 'data_uri' => '', 'format' => '', 'svg' => ''];
}

/**
 * PNG QR en data URI pour Dompdf (évite les chemins fichiers non résolus).
 *
 * @param int $produit_id
 * @param string $stock_info_url
 * @return string
 */
function stock_qr_pdf_image_data_uri($produit_id, $stock_info_url) {
    $produit_id = (int) $produit_id;
    $stock_info_url = trim((string) $stock_info_url);
    if ($produit_id <= 0 || $stock_info_url === '') {
        return '';
    }

    $dir = __DIR__ . '/../upload/qrcodes/';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $file_abs = $dir . 'produit_' . $produit_id . '.png';
    $png_bytes = '';

    if (is_file($file_abs)) {
        $existing = @file_get_contents($file_abs);
        if (stock_code_is_png_bytes($existing)) {
            $png_bytes = $existing;
        } else {
            @unlink($file_abs);
        }
    }

    if ($png_bytes === '') {
        $render = stock_generate_qr_render_for_pdf($stock_info_url);
        if ($render['bytes'] !== '') {
            $png_bytes = $render['bytes'];
        } elseif ($render['data_uri'] !== '') {
            $png_bytes = stock_code_png_bytes_from_data_uri($render['data_uri']);
        }
        if ($png_bytes !== '') {
            @file_put_contents($file_abs, $png_bytes);
        }
    }

    if ($png_bytes === '') {
        return '';
    }

    return stock_code_bytes_to_data_uri($png_bytes, 'image/png');
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

    $vals = [];
    if (is_array($produit)) {
        $vals = produit_emplacement_from_produit($produit);
    } else {
        $row = get_produit_by_id($produit_id);
        if ($row) {
            $vals = produit_emplacement_from_produit($row);
        }
    }
    $payload = produit_emplacement_barcode_payload($code, $vals);

    require_once __DIR__ . '/../vendor/autoload.php';

    try {
        $generator = new \Picqer\Barcode\BarcodeGeneratorPNG();
        if (function_exists('imagecreate')) {
            $generator->useGd();
        }
        $png = $generator->getBarcode($payload, $generator::TYPE_CODE_128, 2, 56);
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

            $normalized = stock_code_normalize_qr_library_output($out);
            if ($normalized['format'] === '') {
                continue;
            }

            return [
                'data_uri' => $normalized['data_uri'],
                'format' => $normalized['format'],
                'bytes' => $normalized['bytes'],
                'svg' => $normalized['svg'],
            ];
        } catch (Throwable $e) {
            continue;
        }
    }

    stock_codes_pdf_set_error('Génération QR impossible (extension PHP GD recommandée).');
    return ['data_uri' => '', 'format' => '', 'bytes' => '', 'svg' => ''];
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
        $existing = @file_get_contents($file);
        if (stock_code_is_png_bytes($existing)) {
            return $file;
        }
        @unlink($file);
    }

    $render = stock_generate_qr_render_for_pdf($stock_info_url);
    if ($render['bytes'] !== '') {
        if (@file_put_contents($file, $render['bytes']) !== false) {
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
    unset($kind, $qr_format);

    return <<<'CSS'
@page { margin: 0; size: A4 portrait; }
html, body {
    margin: 0;
    padding: 0;
    width: 100%;
    height: 100%;
    background: #ffffff;
    font-family: DejaVu Sans, sans-serif;
}
.page-table {
    width: 100%;
    border-collapse: collapse;
    border-spacing: 0;
    border: 0;
}
.page-table td {
    width: 100%;
    height: 297mm;
    padding: 0;
    margin: 0;
    border: 0;
    text-align: center;
    vertical-align: middle;
}
.code-img {
    display: block;
    margin: 0 auto;
    padding: 0;
    border: 0;
}
.code-img--barcode {
    width: 140mm;
    max-width: 90%;
    height: auto;
}
.code-img--qrcode {
    width: 70mm;
    height: 70mm;
}
CSS;
}

/**
 * @param string $img_src data URI ou chemin relatif sous la racine projet
 * @return string
 */
function stock_build_image_only_pdf_html($img_src, $kind, $qr_format = 'png') {
    $kind = $kind === 'qrcode' ? 'qrcode' : 'barcode';
    $styles = stock_code_pdf_image_only_styles($kind, $qr_format);
    $alt = $kind === 'qrcode' ? 'QR code' : 'Code-barres';
    $img_class = $kind === 'qrcode' ? 'code-img code-img--qrcode' : 'code-img code-img--barcode';
    $src = stock_code_pdf_escape((string) $img_src);

    return '<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>' . stock_code_pdf_escape($alt) . '</title>
<style>' . $styles . '</style>
</head>
<body>
<table class="page-table" role="presentation" cellpadding="0" cellspacing="0">
<tr>
<td align="center" valign="middle" height="297mm">
    <img class="' . stock_code_pdf_escape($img_class) . '" src="' . $src . '" alt="">
</td>
</tr>
</table>
</body>
</html>';
}

/**
 * PDF QR via SVG inline (secours si PNG indisponible).
 *
 * @param string $svg_markup
 * @return string
 */
function stock_build_qrcode_svg_pdf_html($svg_markup) {
    $styles = stock_code_pdf_image_only_styles('qrcode', 'svg');
    $svg_markup = trim((string) $svg_markup);
    if ($svg_markup === '') {
        return '';
    }

    return '<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>QR code</title>
<style>' . $styles . '
.qrcode-svg-wrap svg { width: 70mm; height: 70mm; display: block; margin: 0 auto; }
</style>
</head>
<body>
<table class="page-table" role="presentation" cellpadding="0" cellspacing="0">
<tr>
<td align="center" valign="middle" height="297mm">
    <div class="qrcode-svg-wrap">' . $svg_markup . '</div>
</td>
</tr>
</table>
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

        if (!admin_pdf_send_binary($pdf_output, $safe_name)) {
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

    $data_uri = stock_qr_pdf_image_data_uri($produit_id, $stock_info_url);
    $html = '';
    if ($data_uri !== '') {
        $html = stock_build_image_only_pdf_html($data_uri, 'qrcode', 'png');
    } else {
        $render = stock_generate_qr_render_for_pdf($stock_info_url);
        if (!empty($render['svg'])) {
            $html = stock_build_qrcode_svg_pdf_html($render['svg']);
        }
    }

    if ($html === '') {
        if (stock_codes_pdf_get_last_error() === null) {
            stock_codes_pdf_set_error('Impossible de produire le QR code.');
        }
        return false;
    }

    $code = trim((string) ($produit['identifiant_interne'] ?? ''));
    $slug = $code !== '' ? $code : ('produit-' . $produit_id);
    $filename = stock_code_pdf_safe_filename('qrcode-' . $slug);

    return stock_send_html_as_pdf($html, $filename);
}
