<?php
/**
 * Chargement des assets design FPL (admin, public, user)
 */

require_once __DIR__ . '/asset_version.php';

/**
 * URI absolue d'un asset statique (compatible sous-dossier WAMP, ex. /Fouta/css/…)
 */
function fpl_asset_uri($path) {
    if (!function_exists('get_public_root_uri_path')) {
        require_once __DIR__ . '/site_url.php';
    }
    $root = rtrim(get_public_root_uri_path(), '/');
    $path = '/' . ltrim(str_replace('\\', '/', $path), '/');

    return $root . $path;
}

function fpl_css_link($relative_path) {
    if (!function_exists('asset_version_query')) {
        require_once __DIR__ . '/asset_version.php';
    }
    $path = ltrim(str_replace('\\', '/', (string) $relative_path), '/');
    if (strpos($path, 'css/') !== 0) {
        $path = 'css/' . $path;
    }
    $v = asset_version_query();
    $href = fpl_asset_uri($path);
    echo '<link rel="stylesheet" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . $v . '">' . "\n";
}

/**
 *
 * @param array<int, string> $extra_css Chemins relatifs css/… ou /css/…
 */
function fpl_admin_styles(array $extra_css = []) {
    $v = asset_version_query();
    echo '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,300..700;1,14..32,300..700&display=swap">' . "\n";
    echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">' . "\n";
    echo '<link rel="stylesheet" href="' . htmlspecialchars(fpl_asset_uri('css/variables.css'), ENT_QUOTES, 'UTF-8') . $v . '">' . "\n";
    echo '<link rel="stylesheet" href="' . htmlspecialchars(fpl_asset_uri('css/fpl.css'), ENT_QUOTES, 'UTF-8') . $v . '">' . "\n";
    echo '<link rel="stylesheet" href="' . htmlspecialchars(fpl_asset_uri('css/fpl-admin-compat.css'), ENT_QUOTES, 'UTF-8') . $v . '">' . "\n";
    echo '<link rel="stylesheet" href="' . htmlspecialchars(fpl_asset_uri('css/admin-dashboard.css'), ENT_QUOTES, 'UTF-8') . $v . '">' . "\n";
    echo '<link rel="stylesheet" href="' . htmlspecialchars(fpl_asset_uri('css/fpl-admin-overrides.css'), ENT_QUOTES, 'UTF-8') . $v . '">' . "\n";
    foreach ($extra_css as $css) {
        if (strpos($css, '/css/') === 0) {
            $href = fpl_asset_uri(ltrim($css, '/'));
        } elseif (strpos($css, 'css/') === 0) {
            $href = fpl_asset_uri($css);
        } else {
            $href = fpl_asset_uri('css/' . ltrim($css, '/'));
        }
        echo '<link rel="stylesheet" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . $v . '">' . "\n";
    }
    echo '<script src="' . htmlspecialchars(fpl_asset_uri('js/fpl-select.js'), ENT_QUOTES, 'UTF-8') . $v . '"></script>' . "\n";
}

function fpl_script_src($relative_path) {
    $path = ltrim(str_replace('\\', '/', (string) $relative_path), '/');
    if (strpos($path, 'js/') !== 0) {
        $path = 'js/' . $path;
    }
    return fpl_asset_uri($path);
}

/**
 *
 * @param array<int, string> $extra_css
 */
function fpl_public_styles(array $extra_css = []) {
    $v = asset_version_query();
    echo '<link rel="stylesheet" href="' . htmlspecialchars(fpl_asset_uri('css/variables.css'), ENT_QUOTES, 'UTF-8') . $v . '">' . "\n";
    echo '<link rel="stylesheet" href="' . htmlspecialchars(fpl_asset_uri('css/fpl-public.css'), ENT_QUOTES, 'UTF-8') . $v . '">' . "\n";
    foreach ($extra_css as $css) {
        if (strpos($css, '/css/') === 0) {
            $href = fpl_asset_uri(ltrim($css, '/'));
        } else {
            $href = fpl_asset_uri('css/' . ltrim(str_replace('/css/', '', $css), '/'));
        }
        echo '<link rel="stylesheet" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . $v . '">' . "\n";
    }
}

/**
 * Affiche les feuilles CSS espace client connecté
 *
 * @param array<int, string> $extra_css
 */
function fpl_user_styles(array $extra_css = []) {
    $v = asset_version_query();
    echo '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,300..700;1,14..32,300..700&display=swap">' . "\n";
    echo '<link rel="stylesheet" href="' . htmlspecialchars(fpl_asset_uri('css/variables.css'), ENT_QUOTES, 'UTF-8') . $v . '">' . "\n";
    echo '<link rel="stylesheet" href="' . htmlspecialchars(fpl_asset_uri('css/fpl.css'), ENT_QUOTES, 'UTF-8') . $v . '">' . "\n";
    echo '<link rel="stylesheet" href="' . htmlspecialchars(fpl_asset_uri('css/fpl-admin-compat.css'), ENT_QUOTES, 'UTF-8') . $v . '">' . "\n";
    echo '<link rel="stylesheet" href="' . htmlspecialchars(fpl_asset_uri('css/admin-dashboard.css'), ENT_QUOTES, 'UTF-8') . $v . '">' . "\n";
    echo '<link rel="stylesheet" href="' . htmlspecialchars(fpl_asset_uri('css/fpl-admin-overrides.css'), ENT_QUOTES, 'UTF-8') . $v . '">' . "\n";
    echo '<link rel="stylesheet" href="' . htmlspecialchars(fpl_asset_uri('css/fpl-user-compat.css'), ENT_QUOTES, 'UTF-8') . $v . '">' . "\n";
    foreach ($extra_css as $css) {
        if (strpos($css, '/css/') === 0) {
            $href = fpl_asset_uri(ltrim($css, '/'));
        } else {
            $href = fpl_asset_uri('css/' . ltrim(str_replace('/css/', '', $css), '/'));
        }
        echo '<link rel="stylesheet" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . $v . '">' . "\n";
    }
}
