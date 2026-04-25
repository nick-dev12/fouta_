<?php
/**
 * Script pour générer les icônes PWA (192x192 et 512x512) depuis image/logo-fpl.png
 * À exécuter si vous modifiez le logo : php generate_pwa_icons.php
 */

$source = __DIR__ . '/image/logo-fpl.png';
$iconsDir = __DIR__ . '/icons';

if (!file_exists($source)) {
    die("Erreur : image/logo-fpl.png introuvable.\n");
}

if (!extension_loaded('gd')) {
    die("Erreur : l'extension GD de PHP est requise.\n");
}

$image = @imagecreatefromjpeg($source);
if (!$image) {
    die("Erreur : impossible de charger l'image source.\n");
}

$sizes = [192, 512];

foreach ($sizes as $size) {
    $dest = $iconsDir . "/icon-{$size}.png";
    $resized = imagecreatetruecolor($size, $size);
    
    if (!$resized) {
        imagedestroy($image);
        die("Erreur : impossible de créer l'image {$size}x{$size}.\n");
    }
    
    $srcWidth = imagesx($image);
    $srcHeight = imagesy($image);
    
    imagecopyresampled($resized, $image, 0, 0, 0, 0, $size, $size, $srcWidth, $srcHeight);
    
    if (!imagepng($resized, $dest, 9)) {
        imagedestroy($image);
        imagedestroy($resized);
        die("Erreur : impossible d'enregistrer {$dest}.\n");
    }
    
    imagedestroy($resized);
    echo "Icône créée : icon-{$size}.png\n";
}

imagedestroy($image);
echo "Terminé. Les icônes PWA ont été générées avec succès.\n";
