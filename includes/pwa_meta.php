<?php
/**
 * Meta tags et liens pour l'installation PWA (Progressive Web App)
 * À inclure dans le <head> des pages client
 */
if (!function_exists('get_asset_version')) {
    require_once __DIR__ . '/asset_version.php';
}
$asset_version = get_asset_version();
?>
<?php include __DIR__ . '/favicon.php'; ?>
<meta name="theme-color" content="#E5488A">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="FOUTA POIDS LOURDS">
<link rel="manifest" href="/manifest.json">
<script>
(function() {
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function() {
            navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(function() {});
        });
    }
})();
</script>
