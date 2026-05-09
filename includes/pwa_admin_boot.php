<?php
/**
 * PWA espace admin : manifest + enregistrement du service worker depuis le body
 * (inclus depuis admin/includes/nav.php — après ouverture de <head> côté client).
 */
declare(strict_types=1);

if (defined('FOUTA_PWA_ADMIN_BOOT_INCL')) {
    return;
}
define('FOUTA_PWA_ADMIN_BOOT_INCL', true);

require_once __DIR__ . '/site_url.php';

$__p = get_public_root_uri_path();
$__b = $__p === '' ? '' : $__p;
$__manifest_href = $__b . '/admin/pwa-manifest.php';
$__sw_url = $__b . '/sw.js';
$__scope = $__b === '' ? '/' : $__b . '/';
?>
<script>
(function () {
    var manifestHref = <?php echo json_encode($__manifest_href, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var swUrl = <?php echo json_encode($__sw_url, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var scope = <?php echo json_encode($__scope, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    function hasAdminManifest() {
        var links = document.querySelectorAll('link[rel="manifest"]');
        for (var i = 0; i < links.length; i++) {
            var h = links[i].getAttribute('href') || '';
            if (h.indexOf('pwa-manifest') !== -1) return true;
        }
        return false;
    }
    if (!hasAdminManifest()) {
        var lnk = document.createElement('link');
        lnk.rel = 'manifest';
        lnk.href = manifestHref;
        document.head.appendChild(lnk);
    }
    if (!document.querySelector('meta[name="theme-color"]')) {
        var tc = document.createElement('meta');
        tc.name = 'theme-color';
        tc.content = '#3564a6';
        document.head.appendChild(tc);
    }
    if (window.__foutaAdminPwaSw) return;
    window.__foutaAdminPwaSw = true;
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register(swUrl, { scope: scope }).catch(function (err) {
            if (typeof console !== 'undefined' && console.warn) {
                console.warn('[PWA admin] Service worker:', err);
            }
        });
    }
})();
</script>
