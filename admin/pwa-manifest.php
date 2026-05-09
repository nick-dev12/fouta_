<?php
/**
 * Manifest PWA — espace administration (start_url = tableau de bord).
 * Chemins compatibles sous-dossier WAMP (get_public_root_uri_path()).
 */
declare(strict_types=1);

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=600');

require_once dirname(__DIR__) . '/includes/site_url.php';

$root = get_public_root_uri_path();
$base = $root === '' ? '' : $root;

$root_fs = dirname(__DIR__);
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
        $icon192 = $base . '/image/logo_pwa_fpl.png';
        $icon512 = $base . '/image/logo_pwa_fpl.png';
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

$admin_scope = $base . '/admin/';
$manifest = [
    'id' => $admin_scope,
    'name' => 'FOUTA POIDS LOURDS — Administration',
    'short_name' => 'FPL Admin',
    'description' => 'Tableau de bord et gestion FOUTA POIDS LOURDS.',
    'start_url' => $base . '/admin/dashboard.php',
    'scope' => $admin_scope,
    'display' => 'standalone',
    'orientation' => 'any',
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
