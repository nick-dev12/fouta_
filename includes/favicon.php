<?php
/**
 * Favicon / icône d'onglet pour toutes les pages
 * Apple Touch : préfère icons/apple-touch-icon.png (généré par tools/generate_pwa_icons.php).
 */
$__favicon_root = dirname(__DIR__);
$__apple_touch = '/image/logo-fpl.png';
if (is_file($__favicon_root . DIRECTORY_SEPARATOR . 'icons' . DIRECTORY_SEPARATOR . 'apple-touch-icon.png')) {
    $__apple_touch = '/icons/apple-touch-icon.png';
}
?>
<link rel="icon" type="image/png" href="/image/logo-fpl.png">
<link rel="shortcut icon" type="image/png" href="/image/logo-fpl.png">
<link rel="apple-touch-icon" href="<?php echo htmlspecialchars($__apple_touch, ENT_QUOTES, 'UTF-8'); ?>">
