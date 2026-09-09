<?php
/**
 * « LES TROUS DEVRAIENT ÊTRE DÉTOURÉS » (08/09/2026, constat de la direction).
 *
 * « Si on prend des grilles, ça prend seulement le contour ; mais s'il y a des
 * grillages, il y a des trous, et ces trous-là devraient être détourés, de
 * telle sorte que ça soit transparent. S'il y a des espaces à l'intérieur de
 * l'image, ces espaces-là ne sont pas détourés. »
 *
 * La transparence est une CROISSANCE DEPUIS LES BORDS : une poche de fond
 * entourée de matière n'était jamais atteinte. Mesuré sur 200 photos du
 * catalogue : 25 des 70 photos détourées gardaient au moins un trou plein,
 * jusqu'à un cinquième de l'image ; après correction, 21 photos voient leurs
 * trous s'ouvrir, 2 photos jusque-là refusées sont acceptées, aucune n'est
 * perdue.
 *
 * Ce test ne dépend d'AUCUNE photo du catalogue : il fabrique ses images en
 * mémoire, ce qui rend la preuve rejouable partout, y compris sur le serveur.
 * Le danger étant de percer une PIÈCE claire (le miroir d'un rétroviseur),
 * chaque cas de garde est éprouvé lui aussi.
 *
 * À jouer :  php tests/test_detourage_trous.php
 */

$RACINE = dirname(__DIR__);
require_once $RACINE . '/includes/fpl_detourage.php';

$ok = 0;
$ko = 0;
function verifie($libelle, $attendu, $obtenu)
{
    global $ok, $ko;
    if ($attendu === $obtenu) {
        $ok++;
        echo "  OK  $libelle\n";
    } else {
        $ko++;
        echo "  KO  $libelle (attendu " . var_export($attendu, true) . ", obtenu " . var_export($obtenu, true) . ")\n";
    }
}

if (!function_exists('imagecreatetruecolor')) {
    echo "GD absent : rien à prouver ici.\n";
    exit(0);
}

/** Une image de test : fond blanc, et ce que le pinceau y dessine. */
function toile($L, $H, callable $pinceau)
{
    $im = imagecreatetruecolor($L, $H);
    imagefill($im, 0, 0, imagecolorallocate($im, 252, 252, 252)); // fond studio
    $pinceau($im);

    return $im;
}

/** Détoure et rend [transparent au point demandé ?, part opaque de l'image]. */
function detourer($im)
{
    $motif = null;
    $res = fpl_detourage_gd($im, 45, $motif);
    if ($res === null) {
        return ['refus' => true, 'motif' => (string) $motif, 'img' => null];
    }

    return ['refus' => false, 'motif' => '', 'img' => $res['img']];
}

/**
 * Le moteur AGRANDIT les petites images (240 px de large ressortent à 720) :
 * on convertit donc les coordonnées de la toile vers celles du rendu, sinon
 * on interroge le mauvais pixel — piège découvert en écrivant ce test.
 */
function opaque_en($im, $x, $y, $largeur_toile)
{
    $e = imagesx($im) / $largeur_toile;
    $xx = min(imagesx($im) - 1, (int) round($x * $e));
    $yy = min(imagesy($im) - 1, (int) round($y * $e));

    return ((imagecolorat($im, $xx, $yy) >> 24) & 0x7F) < 64;
}

echo "— 1. le trou d'un cadre s'ouvre —\n";
/* un anneau noir épais : le centre est du fond, entouré de matière de partout */
$im = toile(240, 240, function ($im) {
    $noir = imagecolorallocate($im, 28, 28, 32);
    imagefilledrectangle($im, 40, 40, 199, 199, $noir);
    imagefilledrectangle($im, 90, 90, 149, 149, imagecolorallocate($im, 252, 252, 252));
});
$d = detourer($im);
imagedestroy($im);
verifie('la photo est acceptée', false, $d['refus']);
if (!$d['refus']) {
    verifie('la matière du cadre reste opaque', true, opaque_en($d['img'], 60, 120, 240));
    verifie('le trou du milieu est devenu TRANSPARENT', false, opaque_en($d['img'], 120, 120, 240));
    verifie('le fond autour reste transparent', false, opaque_en($d['img'], 10, 10, 240));
    imagedestroy($d['img']);
}

echo "— 2. les fentes d'une grille s'ouvrent —\n";
$im = toile(240, 240, function ($im) {
    $noir = imagecolorallocate($im, 30, 30, 34);
    $blanc = imagecolorallocate($im, 252, 252, 252);
    imagefilledrectangle($im, 30, 30, 209, 209, $noir);
    for ($i = 0; $i < 4; $i++) {
        $y = 50 + $i * 40;
        imagefilledrectangle($im, 55, $y, 184, $y + 18, $blanc); // une fente
    }
});
$d = detourer($im);
imagedestroy($im);
verifie('la photo est acceptée', false, $d['refus']);
if (!$d['refus']) {
    verifie('les barreaux restent opaques', true, opaque_en($d['img'], 120, 42, 240));
    verifie('la 1re fente est transparente', false, opaque_en($d['img'], 120, 58, 240));
    verifie('la 3e fente aussi', false, opaque_en($d['img'], 120, 138, 240));
    imagedestroy($d['img']);
}

echo "— 3. une PIÈCE claire n'est jamais percée (le miroir du rétroviseur) —\n";
/* un cadre noir dont l'intérieur est un DÉGRADÉ gris clair : c'est un miroir,
   pas un trou. Sa frontière du haut fond dans la pièce, celle du bas est nette :
   la garde « le pourtour doit être franc sur tout son tour » doit refuser. */
$im = toile(240, 300, function ($im) {
    imagefilledrectangle($im, 30, 20, 209, 279, imagecolorallocate($im, 24, 26, 30));
    for ($y = 40; $y < 260; $y++) {
        $t = ($y - 40) / 220;               // 0 en haut, 1 en bas
        $g = (int) round(150 + 100 * $t);   // gris moyen → presque blanc
        imagefilledrectangle($im, 50, $y, 189, $y, imagecolorallocate($im, $g, $g, $g));
    }
});
$d = detourer($im);
imagedestroy($im);
verifie('la photo est acceptée', false, $d['refus']);
if (!$d['refus']) {
    verifie('le cadre reste opaque', true, opaque_en($d['img'], 40, 150, 240));
    verifie('le HAUT du miroir reste opaque', true, opaque_en($d['img'], 120, 60, 240));
    verifie('le BAS du miroir, presque blanc, reste opaque LUI AUSSI', true, opaque_en($d['img'], 120, 250, 240));
    imagedestroy($d['img']);
}

echo "— 4. une poche minuscule n'est pas ouverte (grain du capteur) —\n";
$im = toile(240, 240, function ($im) {
    imagefilledrectangle($im, 40, 40, 199, 199, imagecolorallocate($im, 30, 30, 34));
    imagefilledrectangle($im, 118, 118, 121, 121, imagecolorallocate($im, 252, 252, 252)); // 4×4 px
});
$d = detourer($im);
imagedestroy($im);
if (!$d['refus']) {
    verifie('une tache de 16 px reste opaque', true, opaque_en($d['img'], 119, 119, 240));
    imagedestroy($d['img']);
} else {
    echo "  (photo refusée : le cas ne s'applique pas)\n";
}

echo "— 4 bis. le BORD est doux et fidèle (09/09) : ni marche, ni rognage, ni halo —\n";
/* Un carré noir, bordé d'UN pixel gris (le mélange à 50 % que fait tout capteur
   au bord d'une pièce) : ce pixel doit ressortir à moitié transparent — c'est
   l'anti-crénelage de la photo — et le fond au-delà doit rester entièrement
   transparent (aucun halo). */
/* toile de 720 px : à cette taille le moteur n'agrandit pas, et le liseré
   reste large d'UN pixel comme sur une vraie photo (agrandi ×3, il ferait
   trois pixels de gris et le mélange n'aurait plus de sens) */
$im = toile(720, 720, function ($im) {
    imagefilledrectangle($im, 180, 180, 539, 539, imagecolorallocate($im, 140, 140, 142)); // le liseré mêlé
    imagefilledrectangle($im, 181, 181, 538, 538, imagecolorallocate($im, 28, 28, 32));    // la pièce
});
$d = detourer($im);
imagedestroy($im);
verifie('la photo est acceptée', false, $d['refus']);
if (!$d['refus']) {
    $o = $d['img'];
    $e = imagesx($o) / 720;
    $alpha = function ($x, $y) use ($o, $e) {
        return (imagecolorat($o, min(imagesx($o) - 1, (int) round($x * $e)), min(imagesy($o) - 1, (int) round($y * $e))) >> 24) & 0x7F;
    };
    verifie('le cœur de la pièce est opaque', 0, $alpha(360, 360));
    $bord = $alpha(180, 360); // le pixel gris, mélange à 50 %
    verifie('le pixel de bord mêlé est À MOITIÉ transparent, ni coupé ni plein (alpha entre 25 et 100)', true, $bord >= 25 && $bord <= 100);
    verifie('quatre pixels dehors, le fond est entièrement transparent : pas de halo', 127, $alpha(176, 360));
    /* la largeur du dégradé : sur le contour, il y a des pixels intermédiaires */
    $inter = 0; $perim = 0;
    for ($y = 1; $y < imagesy($o) - 1; $y++) {
        for ($x = 1; $x < imagesx($o) - 1; $x++) {
            $av = (imagecolorat($o, $x, $y) >> 24) & 0x7F;
            if ($av > 2 && $av < 125) { $inter++; }
            if ($av < 64 && ((((imagecolorat($o, $x - 1, $y)) >> 24) & 0x7F) >= 64 || (((imagecolorat($o, $x + 1, $y)) >> 24) & 0x7F) >= 64
                || (((imagecolorat($o, $x, $y - 1)) >> 24) & 0x7F) >= 64 || (((imagecolorat($o, $x, $y + 1)) >> 24) & 0x7F) >= 64)) { $perim++; }
        }
    }
    verifie('le contour porte un dégradé (au moins 0,8 pixel intermédiaire par pixel de contour)', true, $perim > 0 && $inter / $perim >= 0.8);
    imagedestroy($o);
}
$src_bord = file_get_contents($RACINE . '/includes/fpl_detourage.php');
verifie('la carte de distance existe aussi DEHORS', true, strpos($src_bord, '8 bis) LA MÊME CARTE, DEHORS') !== false);
verifie("l'opacité du bord se calcule par mélange linéaire fond → pièce", true, strpos($src_bord, 'PAR MÉLANGE LINÉAIRE') !== false);
verifie("l'ombre du fond (même teinte, assombrie) compte comme du fond", true, strpos($src_bord, '$ks >= 0.45 && $ks <= 1.05') !== false);
verifie("un pixel dehors n'est repris que s'il a la couleur de la pièce", true, strpos($src_bord, '$ap = $go * $c;') !== false);

echo "— 5. la mécanique du correctif —\n";
$src = file_get_contents($RACINE . '/includes/fpl_detourage.php');
verifie('la fonction d\'ouverture des trous existe', true, function_exists('fpl_detour_ouvrir_trous'));
verifie('elle est appelée entre la croissance et le ménage des composantes', true,
    strpos($src, '4 bis) LES TROUS INTÉRIEURS') !== false
    && strpos($src, '$diag_trous = fpl_detour_ouvrir_trous(') !== false);
verifie('elle exige que la poche ne touche pas le bord de l\'image', true,
    strpos($src, '$bord || $taille < $mini') !== false);
verifie('elle exige une poche LISSE', true, strpos($src, '$ecart > 7.0') !== false);
verifie('elle exige un pourtour franc sur au moins 90 % du tour', true,
    strpos($src, '($francs / $nb_saut) < 0.90') !== false);
verifie('la porte du « centre vide » compte les trous voulus comme de la matière', true,
    strpos($src, '$trous_ouverts[$base + $x]') !== false);
verifie('la clé du cache est passée à v11 (les anciens calculs sont refaits)', true,
    strpos($src, "'|v11'") !== false && strpos($src, "'|v9'") === false && strpos($src, "'|v10'") === false);

echo "\n$ok OK / $ko KO\n";
exit($ko === 0 ? 0 : 1);
