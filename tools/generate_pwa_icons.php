<?php
/**
 * Génère les PNG carrés pour la PWA à partir de image/logo_pwa_fpl.png
 * (recadrage centré « cover », transparence conservée).
 *
 * Usage : php tools/generate_pwa_icons.php
 * Requiert l’extension PHP GD.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$srcPath = $root . DIRECTORY_SEPARATOR . 'image' . DIRECTORY_SEPARATOR . 'logo_pwa_fpl.png';
$outDir = $root . DIRECTORY_SEPARATOR . 'icons';

if (!extension_loaded('gd')) {
    fwrite(STDERR, "Erreur : extension PHP « gd » requise.\n");
    exit(1);
}

if (!is_readable($srcPath)) {
    fwrite(STDERR, "Fichier source introuvable ou illisible : $srcPath\n");
    exit(1);
}

if (!is_dir($outDir) && !@mkdir($outDir, 0755, true)) {
    fwrite(STDERR, "Impossible de créer le dossier : $outDir\n");
    exit(1);
}

/**
 * Redimensionne en carré taille $size (cover + crop centré).
 */
function pwa_build_square_icon(GdImage $srcIm, int $size): GdImage
{
    $sw = imagesx($srcIm);
    $sh = imagesy($srcIm);
    if ($sw < 1 || $sh < 1) {
        throw new RuntimeException('Image source invalide.');
    }
    $scale = max($size / $sw, $size / $sh);
    $nw = (int) round($sw * $scale);
    $nh = (int) round($sh * $scale);

    $tmp = imagecreatetruecolor($nw, $nh);
    imagealphablending($tmp, false);
    imagesavealpha($tmp, true);
    $transparent = imagecolorallocatealpha($tmp, 0, 0, 0, 127);
    imagefill($tmp, 0, 0, $transparent);
    imagealphablending($tmp, true);
    imagecopyresampled($tmp, $srcIm, 0, 0, 0, 0, $nw, $nh, $sw, $sh);

    $dst = imagecreatetruecolor($size, $size);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    imagefill($dst, 0, 0, $transparent);
    $sx = (int) floor(($nw - $size) / 2);
    $sy = (int) floor(($nh - $size) / 2);
    imagecopy($dst, $tmp, 0, 0, $sx, $sy, $size, $size);
    imagedestroy($tmp);

    return $dst;
}

function pwa_save_png(GdImage $im, string $path): bool
{
    return imagepng($im, $path, 6);
}

$src = @imagecreatefrompng($srcPath);
if ($src === false) {
    fwrite(STDERR, "Impossible de lire le PNG : $srcPath\n");
    exit(1);
}

$sizes = [
    'icon-192.png' => 192,
    'icon-512.png' => 512,
    'apple-touch-icon.png' => 180,
];

foreach ($sizes as $filename => $px) {
    $icon = pwa_build_square_icon($src, $px);
    $dest = $outDir . DIRECTORY_SEPARATOR . $filename;
    if (!pwa_save_png($icon, $dest)) {
        imagedestroy($icon);
        fwrite(STDERR, "Échec écriture : $dest\n");
        exit(1);
    }
    imagedestroy($icon);
    echo "OK  $filename ({$px}×{$px})\n";
}

imagedestroy($src);
echo "+ Icônes PWA générées dans /icons/ à partir de image/logo_pwa_fpl.png\n";
exit(0);
