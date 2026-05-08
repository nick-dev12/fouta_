<?php
/**
 * Manifest PWA vitrine — icônes = logo du site (/image/) si présent.
 * Évite manifest.json statique avec chemins d’icônes absents ou incorrects.
 */
declare(strict_types=1);

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=600');

require_once __DIR__ . '/includes/site_url.php';

$script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$suffix = '/manifest.php';
if (strlen($script) >= strlen($suffix) && substr($script, -strlen($suffix)) === $suffix) {
    $base = (string) substr($script, 0, -strlen($suffix));
} else {
    $base = get_public_root_uri_path();
}

$root_fs = __DIR__;
$icon192_fs = $root_fs . DIRECTORY_SEPARATOR . 'icons' . DIRECTORY_SEPARATOR . 'icon-192.png';
$icon512_fs = $root_fs . DIRECTORY_SEPARATOR . 'icons' . DIRECTORY_SEPARATOR . 'icon-512.png';

if (is_file($icon192_fs) && is_file($icon512_fs)) {
    $icon192 = $base . '/icons/icon-192.png';
    $icon512 = $base . '/icons/icon-512.png';
    $logo_mime = 'image/png';
} else {
    $logo_uri = get_site_logo_uri_path_relative_to_webroot();
    if ($logo_uri !== '') {
        $icon192 = $base . $logo_uri;
        $icon512 = $base . $logo_uri;
    } else {
        $icon192 = $base . '/image/logo-fpl.png';
        $icon512 = $base . '/image/logo-fpl.png';
    }
    $logo_mime = 'image/png';
    if (isset($logo_uri) && $logo_uri !== '') {
        $ext = strtolower(pathinfo(get_site_logo_relative_filename(), PATHINFO_EXTENSION));
        if ($ext === 'svg') {
            $logo_mime = 'image/svg+xml';
        } elseif ($ext === 'webp') {
            $logo_mime = 'image/webp';
        }
    }
}

$manifest = [
    'name' => 'FOUTA POIDS LOURDS - Pièces poids lourds',
    'short_name' => 'FOUTA POIDS LOURDS',
    'description' => 'Boutique en ligne FOUTA POIDS LOURDS',
    'id' => $base ? $base . '/' : '/',
    'start_url' => $base . '/index.php',
    'scope' => $base ? ($base . '/') : '/',
    'display' => 'standalone',
    'orientation' => 'portrait-primary',
    'theme_color' => '#3564a6',
    'background_color' => '#ffffff',
    'lang' => 'fr',
    'icons' => [
        [
            'src' => $icon192,
            'sizes' => '192x192',
            'type' => $logo_mime,
            'purpose' => 'any',
        ],
        [
            'src' => $icon512,
            'sizes' => '512x512',
            'type' => $logo_mime,
            'purpose' => 'any',
        ],
    ],
];

echo json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
