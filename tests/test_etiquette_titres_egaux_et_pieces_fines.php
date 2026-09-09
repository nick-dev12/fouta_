<?php
/**
 * L'ÉTIQUETTE DE PIÈCE, RETOURS DE LA DIRECTION DU 09/09/2026 — le banc.
 *
 * Trois demandes, faites sur trois pièces mais valables pour TOUTES :
 *   1. « l'appellation et le nom français doivent avoir la même taille » —
 *      en diminuant un peu l'appellation, en agrandissant un peu le français ;
 *   2. « le détourage a touché les bords du miroir, le contour noir n'apparaît
 *      pas sur certains côtés » (FCS-BZAX-016-2, HR1151) ;
 *   3. « l'image de la pièce est un peu grande » (750903736).
 *
 * Ce que le banc vérifie, du plus mesurable au plus visible :
 *   - « même taille » se mesure en HAUTEUR DE CAPITALE, et les deux titres
 *     sortent à la même hauteur dès que la place le permet ;
 *   - la boîte de la matière visible d'une image détourée est exacte ;
 *   - les deux rétroviseurs de la direction (en fixtures) ne sont PLUS refusés
 *     comme « squelettiques » et gardent leur cadre jusqu'aux quatre bords ;
 *   - une pièce vraiment dévorée, elle, reste refusée ;
 *   - la boîte photo a maigri, même centre, et le cache est reparti.
 *
 * À jouer :  php tests/test_etiquette_titres_egaux_et_pieces_fines.php
 */

$RACINE = dirname(__DIR__);
require_once $RACINE . '/includes/etiquette_fpl70.php';
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
        echo "  KO  $libelle (attendu " . var_export($attendu, true) . ', obtenu ' . var_export($obtenu, true) . ")\n";
    }
}
function vrai($libelle, $cond)
{
    verifie($libelle, true, (bool) $cond);
}

echo "— « même taille » = même hauteur de capitale —\n";
$anton100 = etiquette70_cap_hauteur('anton', 100);
$barlow100 = etiquette70_cap_hauteur('barlow_condensed_700', 100);
vrai('Anton monte plus haut que Barlow à corps égal', $anton100 > $barlow100 * 1.15);
foreach ([['anton', 56.0], ['barlow_condensed_700', 56.0], ['anton', 30.0]] as $c) {
    $corps = etiquette70_corps_pour_cap($c[0], $c[1]);
    $cap = etiquette70_cap_hauteur($c[0], $corps);
    vrai("aller-retour corps ↔ capitale ($c[0], $c[1] px) à 1,5 px près", abs($cap - $c[1]) <= 1.5);
}

echo "— les deux titres sortent à la même hauteur —\n";
$cas = [
    ['SETTU', "RÉTROVISEUR D'ANTÉVISON MERCEDES BENZ"],
    ['COUPE RÉTROVISEUR', 'COQUE RÉTROVISEUR GRAND ANGLE NOIR RENAULT'],
    ['SETTU', 'CÂBLE DE VITESSE'],
    ['FILTRE', 'VASE D\'EXPANSION IVECO'],
];
foreach ($cas as $c) {
    $t = etiquette70_titres_disposer($c[0], $c[1]);
    vrai("« $c[0] » / « " . mb_substr($c[1], 0, 28) . "… » : mêmes hauteurs à 1 px", abs($t['cap_appel'] - $t['cap_fr']) <= 1.0);
    vrai("  … et l'appellation ne dépasse plus 40 px (elle faisait 79, puis 56)", $t['cap_appel'] <= 40.5);
    vrai("  … et le nom français dépasse 30 px (il faisait 30)", $t['cap_fr'] > 30.5);
    vrai('  … le nom français tient dans sa largeur (730)',
        etiquette70_largeur_texte('barlow_condensed_700', $c[1], $t['corps_fr'], 0.9) <= 730.0);
    vrai('  … l\'appellation tient dans sa largeur (730)',
        etiquette70_largeur_texte('anton', $c[0], $t['corps_appel'], 5.3) <= 730.0);
    vrai('  … le bloc commence toujours au même endroit (haut à 310)', abs(($t['base_appel'] - $t['cap_appel']) - 310.0) <= 0.5);
    vrai('  … le nom français est SOUS l\'appellation, sans se toucher', $t['base_fr'] - $t['cap_fr'] >= $t['base_appel'] + 17.5);
}
// un nom français interminable : l'appellation ne le suit pas jusqu'au ridicule
$t = etiquette70_titres_disposer('SETTU', 'FILTRE À AIR EN FER AVEC COUVERCLE EN CAOUTCHOUC POUR CAMION RENAULT PREMIUM');
vrai('nom interminable : l\'appellation garde au moins 26 px', $t['cap_appel'] >= 25.5);
vrai('nom interminable : le français tient quand même dans 730', etiquette70_largeur_texte('barlow_condensed_700', 'FILTRE À AIR EN FER AVEC COUVERCLE EN CAOUTCHOUC POUR CAMION RENAULT PREMIUM', $t['corps_fr'], 0.9) <= 730.0);
// sans appellation : le nom français prend la première ligne
$t = etiquette70_titres_disposer('', 'PARE-CHOC AVANT SHACMAN');
vrai('sans appellation : le nom français commence en haut du bloc', abs(($t['base_fr'] - $t['cap_fr']) - 310.0) <= 0.5);

echo "— la boîte de la matière visible —\n";
$im = imagecreatetruecolor(100, 100);
imagealphablending($im, false);
imagesavealpha($im, true);
imagefilledrectangle($im, 0, 0, 99, 99, imagecolorallocatealpha($im, 0, 0, 0, 127));
imagefilledrectangle($im, 20, 30, 60, 70, imagecolorallocatealpha($im, 10, 10, 10, 0));
imagesetpixel($im, 75, 75, imagecolorallocatealpha($im, 10, 10, 10, 60)); // bord à demi transparent : compte
verifie('la boîte suit la matière, demi-transparence comprise', ['x0' => 20, 'y0' => 30, 'x1' => 75, 'y1' => 75], etiquette70_boite_visible($im));
imagedestroy($im);
$vide = imagecreatetruecolor(10, 10);
imagealphablending($vide, false);
imagesavealpha($vide, true);
imagefilledrectangle($vide, 0, 0, 9, 9, imagecolorallocatealpha($vide, 0, 0, 0, 127));
verifie('une image vide rend null', null, etiquette70_boite_visible($vide));
imagedestroy($vide);

echo "— les rétroviseurs de la direction ne sont plus refusés, et gardent leurs bords —\n";
$fixtures = [
    // [fichier, x0 max, x1 min, y0 max, y1 min] : où la pièce DOIT aller, dans les pixels de travail du moteur.
    // Bornes = mesure du 09/09 (88/615/202/511 et 34/707/21/434), résultat vérifié à l'œil sur fond magenta :
    // le cadre de la glace est entier sur ses quatre côtés. Six pixels de marge : c'est un garde-fou de régression.
    ['retroviseur_bras_hr1151_2209.png', 94, 609, 208, 505, 'HR1151 (bras + glace, 720×720)'],
    ['retroviseur_glace_blanche_fcsbzax0162_2189.png', 40, 701, 27, 428, 'FCS-BZAX-016-2 (glace blanche, 1200×750 → 720×450)'],
];
foreach ($fixtures as $f) {
    $chemin = __DIR__ . '/fixtures/' . $f[0];
    vrai("la fixture existe : $f[0]", is_file($chemin));
    if (!is_file($chemin)) {
        continue;
    }
    $src = imagecreatefrompng($chemin);
    $motif = null;
    $res = fpl_detourage_gd($src, 45, $motif);
    vrai("$f[5] : ACCEPTÉ (motif : " . ($motif ?: 'aucun') . ')', $res !== null);
    if ($res === null) {
        imagedestroy($src);
        continue;
    }
    $b = etiquette70_boite_visible($res['img']);
    vrai("  … le bord GAUCHE de la pièce est là (x0 ≤ $f[1], obtenu " . $b['x0'] . ')', $b['x0'] <= $f[1]);
    vrai("  … le bord DROIT est là (x1 ≥ $f[2], obtenu " . $b['x1'] . ')', $b['x1'] >= $f[2]);
    vrai("  … le HAUT est là (y0 ≤ $f[3], obtenu " . $b['y0'] . ')', $b['y0'] <= $f[3]);
    vrai("  … le BAS est là (y1 ≥ $f[4], obtenu " . $b['y1'] . ')', $b['y1'] >= $f[4]);
    imagedestroy($res['img']);
    imagedestroy($src);
}

echo "— le garde-fou « squelettique » : vrai vide accepté, chair mangée refusée —\n";
/* Deux images jumelles. Un cadre noir épais (20 px) sur fond blanc, qui
   remplit 27 % de sa boîte — sous le seuil de 30 % qui déclenchait le refus.
   Dedans :
     - du BLANC PUR (255) : c'est le vide entre les montants d'un cadre, le cas
       du bras de rétroviseur → le garde-fou doit ACCEPTER ;
     - une PLAQUE GRIS CLAIR (238) : c'est la chair d'une pièce claire que la
       croissance a prise pour du fond → le garde-fou doit REFUSER, car ce que
       le masque a jeté n'est PAS le fond (à 17 du blanc, hors des ±10). */
function cadre_epais($interieur)
{
    $im = imagecreatetruecolor(400, 400);
    imagefilledrectangle($im, 0, 0, 399, 399, imagecolorallocate($im, 255, 255, 255));
    imagefilledrectangle($im, 60, 60, 339, 339, imagecolorallocate($im, 18, 18, 18));
    imagefilledrectangle($im, 80, 80, 319, 319, imagecolorallocate($im, $interieur, $interieur, $interieur));

    return $im;
}
$motif = null;
$res = fpl_detourage_gd(cadre_epais(255), 45, $motif);
vrai('cadre épais autour de BLANC PUR : ACCEPTÉ (motif : ' . ($motif ?: 'aucun') . ')', $res !== null);
if ($res !== null) {
    $b = etiquette70_boite_visible($res['img']);
    vrai('  … et le cadre est là jusqu\'à ses quatre bords', $b !== null && $b['x0'] <= 62 && $b['y0'] <= 62 && $b['x1'] >= 337 && $b['y1'] >= 337);
    imagedestroy($res['img']);
}
$motif = null;
$res = fpl_detourage_gd(cadre_epais(238), 45, $motif);
vrai('cadre épais autour d\'une PLAQUE GRIS CLAIR : REFUSÉ (motif : ' . ($motif ?: 'aucun') . ')', $res === null);
vrai('  … et le motif dit pourquoi (squelettique, vide qui n\'est pas le fond)', $res === null && strpos((string) $motif, 'squelettique') !== false);
if ($res !== null) {
    imagedestroy($res['img']);
}

echo "— la glace d'un rétroviseur reste une glace, un trou reste un trou —\n";
/* Retour de la direction (9408107516) : « tu as pratiquement coupé le miroir ».
   La glace blanche des deux rétroviseurs était vidée — l'une ouverte comme un
   trou, l'autre inondée par la brèche d'un liseré perdu à la réduction. On
   sonde le CENTRE de chaque glace (il doit être opaque) et le centre du vrai
   vide entre les tiges du bras (il doit rester transparent). Coordonnées dans
   les pixels de travail du moteur, relevées le 09/09. */
$alpha_en = function ($img, $x, $y) {
    return (imagecolorat($img, $x, $y) >> 24) & 127; // 0 opaque … 127 transparent
};
$sondes = [
    ['retroviseur_bras_hr1151_2209.png', 'HR1151', [175, 428], [362, 372]],
    ['retroviseur_glace_blanche_fcsbzax0162_2189.png', '9408107516', [100, 200], [378, 248]],
    /* 9608103016 : glace GRISE avec un reflet en dégradé (5 % de pixels lisses) ;
       le second point sonde l'intérieur de la boucle du câble (x 357..437,
       y 204..241), qui doit rester transparent : une boucle est fine partout,
       une glace tient à un boîtier. */
    ['retroviseur_glace_grise_9608103016_2202.png', '9608103016', [200, 200], [397, 222]],
];
foreach ($sondes as $sd) {
    $chemin = __DIR__ . '/fixtures/' . $sd[0];
    if (!is_file($chemin)) {
        continue;
    }
    $src = imagecreatefrompng($chemin);
    $motif = null;
    $res = fpl_detourage_gd($src, 45, $motif);
    vrai("$sd[1] : accepté", $res !== null);
    if ($res !== null) {
        $ag = $alpha_en($res['img'], $sd[2][0], $sd[2][1]);
        $av = $alpha_en($res['img'], $sd[3][0], $sd[3][1]);
        vrai("  … le centre de la GLACE est opaque (alpha $ag sur 127)", $ag < 40);
        vrai("  … le vide entre les tiges reste transparent (alpha $av sur 127)", $av >= 100);
        imagedestroy($res['img']);
    }
    imagedestroy($src);
}

/* Les jumeaux de la règle, trois images de 400 × 400 sur fond blanc :
     - une GLACE : liseré fin (4 px) sur trois côtés, et un BOÎTIER épais
       (60 px) sur le quatrième — l'intérieur est rendu à la pièce ;
     - une OUVERTURE : cadre épais (30 px) tout autour — l'intérieur reste
       transparent ;
     - une BOUCLE : liseré fin (4 px) tout autour, sans boîtier — l'intérieur
       n'est PAS rendu (un câble replié n'est pas une glace). */
function cadre_autour_de_blanc($epaisseur, $boitier = 0)
{
    $im = imagecreatetruecolor(400, 400);
    imagefilledrectangle($im, 0, 0, 399, 399, imagecolorallocate($im, 255, 255, 255));
    imagefilledrectangle($im, 100 - $epaisseur, 100 - $epaisseur, 299 + $epaisseur + $boitier, 299 + $epaisseur, imagecolorallocate($im, 18, 18, 18));
    imagefilledrectangle($im, 100, 100, 299, 299, imagecolorallocate($im, 255, 255, 255));

    return $im;
}
$motif = null;
$res = fpl_detourage_gd(cadre_autour_de_blanc(4, 60), 45, $motif);
vrai('GLACE : liseré fin + boîtier épais : accepté (motif : ' . ($motif ?: 'aucun') . ')', $res !== null);
if ($res !== null) {
    $a = $alpha_en($res['img'], 200, 200);
    vrai("  … l'intérieur est rendu à la pièce (alpha $a)", $a < 40);
    imagedestroy($res['img']);
}
$motif = null;
$res = fpl_detourage_gd(cadre_autour_de_blanc(30), 45, $motif);
vrai('OUVERTURE : cadre épais tout autour : accepté (motif : ' . ($motif ?: 'aucun') . ')', $res !== null);
if ($res !== null) {
    $a = $alpha_en($res['img'], 200, 200);
    vrai("  … l'intérieur reste transparent (alpha $a)", $a >= 100);
    imagedestroy($res['img']);
}
$motif = null;
$res = fpl_detourage_gd(cadre_autour_de_blanc(4), 45, $motif);
$a = $res !== null ? $alpha_en($res['img'], 200, 200) : 127;
vrai("BOUCLE : liseré fin tout autour, sans boîtier : l'intérieur n'est pas rendu (" . ($res === null ? 'refusée : ' . $motif : "alpha $a") . ')', $res === null || $a >= 100);
if ($res !== null) {
    imagedestroy($res['img']);
}

echo "— une hauteur commune à TOUT le catalogue, et la barre bleue qui suit le texte —\n";
/* Second retour de la direction (7750408447) : « les écritures sont grosses
   et pas à la même taille que les autres ». Un nom court doit sortir à la
   même hauteur qu'un nom moyen : le plafond est commun, 40 px. */
$court = etiquette70_titres_disposer('SETTU', 'CÂBLE DE VITESSE');
$moyen = etiquette70_titres_disposer('SETTU', "RÉTROVISEUR D'ANTÉVISON MERCEDES BENZ");
vrai('un nom court sort à 40 px, pas plus', abs($court['cap_fr'] - 40.0) <= 1.0 && abs($court['cap_appel'] - 40.0) <= 1.0);
vrai('un nom moyen sort à la même hauteur que le court', abs($moyen['cap_fr'] - $court['cap_fr']) <= 1.0);

/* La barre bleue : effacée de la couche fixe, dessinée par le moteur à 35 px
   sous la ligne de base du nom français. On rend une étiquette avec la photo
   d'HR1151 et on sonde les pixels. */
$png = imagecreatefrompng($RACINE . '/image/etiquette70/dessus-1654.png');
$bleu_fixe = 0;
for ($y = 713; $y <= 726; $y++) {
    for ($x = 439; $x <= 566; $x += 8) {
        $cf = imagecolorat($png, $x, $y);
        if (((($cf >> 24) & 127) < 100) && (($cf & 255) > ((($cf >> 16) & 255) + 20)) && ((($cf >> 16) & 255) < 120)) {
            $bleu_fixe++; // un pixel bleu marine opaque : la barre serait encore la
        }
    }
}
verifie('la couche fixe ne porte plus la barre', 0, $bleu_fixe);
imagedestroy($png);

$donnees = [
    'nom_wolof' => 'SETTU', 'nom_francais' => "Rétroviseur D'Antévison MERCEDES BENZ",
    'ref_affichee' => 'FPL 150 ME 105116', 'oem' => 'A9438105116',
    'qr_texte' => 'https://e.foutapoidslourds.com/p/2000010051953', 'ean12' => '200001005195',
    'photo_chemin' => __DIR__ . '/fixtures/retroviseur_bras_hr1151_2209.png',
];
$rendu = etiquette70_rendu($donnees, ETQ70_BASE);
$s = ETQ70_BASE / ETQ70_LOGIQUE;
$attendu_y = (int) round($moyen['base_fr'] * $s) + 35;
$xm = (int) ((439 + 566) / 2);
$c = imagecolorat($rendu, $xm, $attendu_y + 6);
$r_ = ($c >> 16) & 255; $g_ = ($c >> 8) & 255; $b_ = $c & 255;
vrai("la barre est dessinée 35 px sous la ligne de base du nom français (y $attendu_y, couleur $r_,$g_,$b_)", $b_ > $r_ + 20 && $r_ < 120);
$c2 = imagecolorat($rendu, $xm, $attendu_y - 12);
vrai('… et rien de bleu juste au-dessus d\'elle', !((($c2 & 255) > ((($c2 >> 16) & 255) + 20)) && (($c2 >> 16) & 255) < 120));
vrai('… la barre est plus haute qu\'avant (elle était à y 713)', $attendu_y < 713);
imagedestroy($rendu);

echo "— la boîte photo et le cache —\n";
$moteur = (string) file_get_contents($RACINE . '/includes/etiquette_fpl70.php');
vrai('la case photo fait 360 (elle faisait 440)', strpos($moteur, "'w' => (int) round(360 * \$s), 'h' => (int) round(360 * \$s)") !== false);
vrai('… au même centre (785, 647) : x = 605, y = 467', strpos($moteur, "'x' => (int) round(605 * \$s), 'y' => (int) round(467 * \$s)") !== false);
/* La taille se lit sur la DIAGONALE de la matière (troisième retour de la
   direction : les bras de rétroviseur, allongés, paraissaient petits). */
list($wc, $hc) = etiquette70_photo_taille(620, 635);   // la coque 750903736
vrai("une pièce presque carrée garde ses 360 (obtenu {$wc} × {$hc})", abs(max($wc, $hc) - 360) <= 4);
list($wb, $hb) = etiquette70_photo_taille(674, 414);   // le bras 9408107516
vrai("un bras allongé dépasse la case en largeur (obtenu {$wb} × {$hb}, il faisait 360 × 221)", $wb > 400 && $wb <= 420);
vrai('… et grandit d\'environ 17 %', $wb >= 1.15 * 360 && $wb <= 1.20 * 360);
list($wb2, $hb2) = etiquette70_photo_taille(528, 310); // le bras A9438105116
vrai("l'autre bras aussi (obtenu {$wb2} × {$hb2})", $wb2 > 400 && $wb2 <= 420);
list($wt, $ht) = etiquette70_photo_taille(310, 720);   // une pièce haute
vrai("une pièce haute s'arrête à 400 de haut, sous la barre bleue (obtenu {$wt} × {$ht})", $ht <= 400 && $ht >= 396);
list($wl2, $hl2) = etiquette70_photo_taille(720, 100); // une courroie posée à plat
vrai("une pièce très allongée s'arrête à 420 de large (obtenu {$wl2} × {$hl2})", $wl2 === 420);
vrai('la photo est recadrée sur sa matière avant d\'être posée', strpos($moteur, '$vis = etiquette70_boite_visible($ph[\'img\']);') !== false);
$detour = (string) file_get_contents($RACINE . '/includes/fpl_detourage.php');
vrai('le cache du détourage est reparti (clé v14)', strpos($detour, "'|v14'") !== false);
vrai('le sauvetage des glaces est branché avant les portes', strpos($detour, '$diag_glaces = fpl_detour_sauver_glaces(') !== false);
vrai('le garde-fou « squelettique » regarde le vide avant de refuser', strpos($detour, '$vrai_vide = ($remplissage >= 0.12 && $partIntrus < 0.10);') !== false);
vrai('… à la tolérance SERRÉE du fond de studio (±10), pas à celle des diagnostics', strpos($detour, '$mR, $mV, $mB, $mSom, $nbModes, 10, true)) {') !== false);

echo "\n$ok OK / $ko KO\n";
exit($ko === 0 ? 0 : 1);
