<?php
/**
 * Meta tags, manifest et enregistrement du service worker PWA.
 *
 * Avant inclusion, optionnel :
 *   $pwa_mode = 'admin' — manifest dédié (start_url tableau de bord admin).
 *   défaut ou 'public' — manifest vitrine (manifest.php, icônes = logo site).
 */
if (!function_exists('get_asset_version')) {
    require_once __DIR__ . '/asset_version.php';
}
require_once __DIR__ . '/site_url.php';

$pwa_mode = isset($pwa_mode) && $pwa_mode === 'admin' ? 'admin' : 'public';
$public_root = get_public_root_uri_path();
$path_prefix = $public_root === '' ? '' : $public_root;

$theme_color = '#3564a6';
$sw_url = $path_prefix . '/sw.js';
$scope = $path_prefix === '' ? '/' : $path_prefix . '/';

if ($pwa_mode === 'admin') {
    $manifest_href = $path_prefix . '/admin/pwa-manifest.php';
    $apple_title = 'FPL Admin';
} else {
    $manifest_href = $path_prefix . '/manifest.php';
    $apple_title = 'FOUTA POIDS LOURDS';
}
?>
<?php include __DIR__ . '/favicon.php'; ?>
<meta name="theme-color" content="<?php echo htmlspecialchars($theme_color); ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="<?php echo htmlspecialchars($apple_title); ?>">
<link rel="manifest" href="<?php echo htmlspecialchars($manifest_href); ?>">
<?php if ($pwa_mode !== 'admin'): ?>
<script>
(function() {
    var swUrl = <?php echo json_encode($sw_url, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var scope = <?php echo json_encode($scope, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function() {
            navigator.serviceWorker.register(swUrl, { scope: scope }).catch(function() {});
        });
    }
})();
</script>
<?php endif; 