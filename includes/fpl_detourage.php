<?php
/**
 * DÉTOURAGE COULEUR v4 (03/09/2026) — refonte « image de l'entreprise ».
 *
 * Pourquoi la refonte : la mesure sur 2 236 photos réelles (planche de preuve)
 * a montré deux mondes. 28 % de photos studio (fond clair uni) que l'ancien
 * moteur détourait déjà bien ; 32 % de photos d'atelier sur CARRELAGE (carreaux
 * gris à JOINTS sombres) où la propagation par « marches de lumière » se
 * faisait bloquer par chaque joint — des carreaux entiers restaient collés à la
 * pièce (jusqu'à 47 % de résidus, 192 taches sur une seule photo) ; 40 % de
 * scènes chargées (étagères, autres pièces) où aucun détourage de couleur ne
 * peut être propre.
 *
 * Principe v4 :
 *  1. MODÈLE DE FOND MULTI-TEINTES — le pourtour (anneau de 3 px) est regroupé
 *     en quelques teintes dominantes (carreau + joint + ombre + papier…). Un
 *     fond de carrelage n'est plus « une » couleur mais une petite famille.
 *  2. PORTE D'ENTRÉE — si ces teintes ne couvrent pas au moins 55 % du
 *     pourtour, la scène est chargée : on DÉCLINE tout de suite (l'étiquette
 *     garde la photo d'origine — mieux qu'une image à moitié mangée).
 *  3. CROISSANCE EN DEUX TEMPS — d'abord, depuis les bords, tout pixel voisin
 *     APPARTENANT à une teinte du fond (les joints sombres sont une teinte :
 *     on traverse le quadrillage), sans jamais admettre de saut brutal (le
 *     reflet blanc d'une pièce vernie ne s'attrape pas depuis un carreau
 *     sombre) ; ensuite, une RELAXATION BORNÉE (franges, petites marches
 *     locales) au ras de la pièce. Les ombres portées détachées partent au
 *     ménage des composantes (teinte du fond assombrie + PLATE — une vraie
 *     ombre n'a pas de relief, une pièce grise en a).
 *  4. MÉNAGE DES COMPOSANTES — parmi ce qui reste : on jette les miettes, les
 *     morceaux couleur-de-fond (carreaux orphelins), les cadres/lignes creuses
 *     (collages), les parasites périphériques (chaussure au coin de l'image),
 *     et on ne garde que les 4 plus gros morceaux (pièce ou paire de pièces).
 *  5. PORTES DE QUALITÉ — part gardée plausible, centre de l'image encore
 *     habité, faible contact avec le bord, contour pas déchiqueté. Un seul
 *     échec → on décline : JAMAIS de résultat sale sur une étiquette.
 *  6. BORD À LA NORME — lissage majoritaire (anti-escaliers), érosion d'1 px
 *     (mange le liseré clair qui a bavé), anti-crénelage par carte de distance
 *     (fondu ~1,5 px), et DÉCONTAMINATION : la part de couleur du fond encore
 *     mêlée aux pixels du bord est retirée (fini le halo gris).
 *
 * Traitement plafonné à 720 px (mémoire/temps), rendu à cette taille — la boîte
 * photo de l'étiquette fait ~520 px. Programmation procédurale uniquement.
 */

if (!defined('FPL_DETOURAGE_MAX')) {
    define('FPL_DETOURAGE_MAX', 720);
}

/**
 * Le pourtour est-il d'une seule teinte (photo studio, fond uni) ? ≥ 70 %.
 * (Conservé pour compatibilité — le moteur v4 fait sa propre analyse.)
 *
 * @param resource|GdImage $im
 * @return bool
 */
function fpl_detourage_fond_uni($im)
{
    $L = imagesx($im);
    $H = imagesy($im);
    if ($L < 4 || $H < 4) {
        return false;
    }
    $bacs = [];
    $total = 0;
    $noter = function ($x, $y) use ($im, &$bacs, &$total) {
        $c = imagecolorat($im, $x, $y);
        $cle = ((($c >> 16) & 0xF0) << 4) | (($c >> 8) & 0xF0) | ((($c) & 0xF0) >> 4);
        $bacs[$cle] = ($bacs[$cle] ?? 0) + 1;
        $total++;
    };
    for ($x = 0; $x < $L; $x += 2) {
        $noter($x, 0);
        $noter($x, $H - 1);
    }
    for ($y = 0; $y < $H; $y += 2) {
        $noter(0, $y);
        $noter($L - 1, $y);
    }
    $max = 0;
    foreach ($bacs as $n) {
        if ($n > $max) {
            $max = $n;
        }
    }
    return $total ? ($max / $total) >= 0.70 : false;
}

/**
 * Convertit une limite mémoire php.ini (« 128M », « 2G », « -1 ») en octets.
 * -1 (illimité) et les valeurs illisibles rendent 0 (= pas de contrainte connue).
 *
 * @param string|false $v
 * @return int
 */
function fpl_detour_memoire_octets($v)
{
    $v = trim((string) $v);
    if ($v === '' || $v === '-1') {
        return 0;
    }
    $n = (float) $v;
    switch (strtoupper(substr($v, -1))) {
        case 'G': $n *= 1073741824; break;
        case 'M': $n *= 1048576; break;
        case 'K': $n *= 1024; break;
    }
    return (int) $n;
}

/**
 * Un pixel appartient-il à une TEINTE du fond (écart ≤ $tol par canal) ?
 *
 * (L'« ombre d'une teinte » ne se juge JAMAIS ici, au pixel : un volant moteur
 * gris est, couleur pour couleur, « du blanc assombri » — le test pixel mangeait
 * les pièces grises entières. L'ombre se juge au niveau de la COMPOSANTE, où la
 * platitude fait la différence : voir le ménage des composantes.)
 *
 * @return bool
 */
function fpl_detour_membre_fond($rr, $vv, $bb, $mR, $mV, $mB, $mSom, $nbModes, $tol, $plafondSombre = true)
{
    for ($k = 0; $k < $nbModes; $k++) {
        // teinte SOMBRE (joint de carrelage, ombre) : tolérance plafonnée à
        // 18 pour la CROISSANCE — à 30, « proche du joint » avalait la chair
        // OMBRÉE des pièces (les plis d'un filtre jaune à l'ombre passent à
        // 25 du joint, le vrai joint est à moins de 12). Même plafond, à 20,
        // pour les teintes TRÈS CLAIRES : un fond studio blanc est uniforme à
        // ±10, alors qu'à 30 « proche du blanc » avalait les zones claires
        // d'un pare-choc crème (photo DZ97259624070, pièce rendue fendue).
        // Les DIAGNOSTICS (« la pièce garde du fond ») passent
        // $plafondSombre = false : un carton kraft collé à la pièce doit
        // compter comme du fond, lui.
        $t = $tol;
        if ($plafondSombre) {
            if ($mSom[$k] < 460 && $t > 18) {
                $t = 18;
            } elseif ($mSom[$k] >= 660 && $t > 20) {
                $t = 20;
            }
        }
        $d1 = $rr > $mR[$k] ? $rr - $mR[$k] : $mR[$k] - $rr;
        $d2 = $vv > $mV[$k] ? $vv - $mV[$k] : $mV[$k] - $vv;
        $d3 = $bb > $mB[$k] ? $bb - $mB[$k] : $mB[$k] - $bb;
        if ($d1 <= $t && $d2 <= $t && $d3 <= $t) {
            return true;
        }
    }
    return false;
}

/**
 * Détoure une image GD (fond → transparent). Rend une NOUVELLE image GD
 * truecolor+alpha, ou null si le détourage ne serait pas propre (fond chargé,
 * pièce mangée, contour déchiqueté…) — l'appelant garde alors la photo
 * d'origine.
 *
 * @param resource|GdImage $src  image source (non modifiée)
 * @param int $force  0..100, agressivité (défaut 45)
 * @return array{img: resource|GdImage, detouree: bool}|null
 */
function fpl_detourage_gd($src, $force = 45, &$motif = null)
{
    if (!function_exists('imagecreatetruecolor')) {
        return null;
    }
    $L0 = imagesx($src);
    $H0 = imagesy($src);
    if ($L0 < 8 || $H0 < 8) {
        return null;
    }
    // PHOTO MINUSCULE : en dessous de ~120 px il n'y a plus de matière du
    // tout — on décline. Entre 120 et 300 px (vignette du pare-choc
    // DZ97259624070 : 259×194), la photo est AGRANDIE à la taille de travail
    // avant le détourage : l'érosion d'1 px et les tolérances retrouvent
    // leurs proportions de 720 px au lieu de peser 3× trop lourd.
    if (max($L0, $H0) < 120) {
        $motif = sprintf('photo trop petite pour un detourage propre (%dx%d)', $L0, $H0);
        return null;
    }

    // MÉMOIRE : le moteur veut ~200 Mo en pointe à 720 px, or PHP sous Apache
    // est souvent limité à 128M (mesuré sur foutasvr : la page étiquette d'une
    // photo pas encore en cache serait morte en fatale). On tente de relever
    // la limite ; si elle reste basse, on RÉTRÉCIT le travail à 520 px — la
    // boîte photo de l'étiquette fait ~520 px, la qualité ne bouge pas.
    $tailleMax720 = FPL_DETOURAGE_MAX;
    $lim = fpl_detour_memoire_octets(ini_get('memory_limit'));
    if ($lim > 0 && $lim < 512 * 1048576) {
        @ini_set('memory_limit', '512M');
        $lim = fpl_detour_memoire_octets(ini_get('memory_limit'));
        if ($lim > 0 && $lim < 384 * 1048576) {
            $tailleMax720 = min($tailleMax720, 520);
        }
    }

    // travail à taille plafonnée — et taille PLANCHER : une petite photo est
    // agrandie (interpolation lisse) pour que le pipeline travaille toujours
    // aux proportions prévues
    $ech = $tailleMax720 / max($L0, $H0);
    if ($ech > 1.0 && max($L0, $H0) >= 300) {
        $ech = 1.0; // une photo déjà correcte n'est jamais agrandie
    }
    // les rayons du ménage de bord (érosion, ouverture) suivent
    // l'agrandissement : sur une vignette agrandie x3, une frange d'1 px
    // d'origine fait 3 px de travail
    $morpho = $ech > 1.0 ? max(1, (int) round($ech)) : 1;
    $L = max(8, (int) round($L0 * $ech));
    $H = max(8, (int) round($H0 * $ech));
    $im = imagecreatetruecolor($L, $H);
    imagealphablending($im, false);
    imagesavealpha($im, true);
    imagecopyresampled($im, $src, 0, 0, 0, 0, $L, $H, $L0, $H0);

    $N = $L * $H;
    $r = new SplFixedArray($N);
    $v = new SplFixedArray($N);
    $b = new SplFixedArray($N);
    $a = new SplFixedArray($N);
    $fond = new SplFixedArray($N); // 1 = pixel de fond (à effacer)
    for ($y = 0, $p = 0; $y < $H; $y++) {
        for ($x = 0; $x < $L; $x++, $p++) {
            $c = imagecolorat($im, $x, $y);
            $r[$p] = ($c >> 16) & 255;
            $v[$p] = ($c >> 8) & 255;
            $b[$p] = $c & 255;
            $a[$p] = ($c >> 24) & 127; // 0 opaque … 127 transparent (GD)
            $fond[$p] = 0;
        }
    }

    // réglages dérivés de la force
    $tolMode   = (int) round(22 + 0.18 * $force); // appartenance à une teinte du fond (45 → 30)
    $pente     = 8;   // marche locale de la relaxation des rives (une ombre est douce)
    $sautMax   = 60;  // saut maximal admis au niveau 1, même entre teintes (bloque
                      // l'entrée directe fond → reflet blanc d'une pièce vernie)
    $tolFusion = 28;  // deux bacs plus proches que ça = même teinte
    $tolResidu = 16;  // composante gardée dont la couleur moyenne colle au fond = résidu
    $profRive  = 60;  // profondeur (px) de la relaxation des franges — les ombres
                      // portées sont traitées à part (ombre d'une teinte du fond)

    $profOmbre = 18;  // profondeur (px) des admissions « ombre d'une teinte
                      // claire » dans la rive — une bande, jamais une invasion

    // sur une photo AGRANDIE, les seuils de pente se divisent par le facteur :
    // l'interpolation étire les gradients (un bord de 20/px natif devient 7/px
    // une fois agrandi ×3) — à pente 8 fixe, la rive traversait le bord ombré
    // du pare-choc DZ97259624070 et mordait la pièce en dents de scie
    if ($ech > 1.0) {
        $pente = max(3, (int) round($pente / $ech));
        $sautMax = max(24, (int) round($sautMax / $ech));
        $profOmbre = (int) round($profOmbre * $ech);
    }

    // ------------------------------------------------------------------
    // 1) MODÈLE DE FOND : teintes dominantes de l'anneau de pourtour (3 px)
    // ------------------------------------------------------------------
    $ep = (int) min(3, floor(min($L, $H) / 4));
    if ($ep < 1) {
        $ep = 1;
    }
    $hist = [];
    $ringTot = 0;   // pixels opaques de l'anneau
    $transRing = 0; // pixels déjà transparents (photo pré-détourée)
    $sideTot = [0, 0, 0, 0]; // haut, bas, gauche, droite
    for ($y = 0; $y < $H; $y++) {
        $bande = ($y < $ep || $y >= $H - $ep);
        for ($x = 0; $x < $L; $x++) {
            if (!$bande && $x >= $ep && $x < $L - $ep) {
                $x = $L - $ep - 1; // saute l'intérieur de la ligne
                continue;
            }
            $p = $y * $L + $x;
            if ($a[$p] >= 100) {
                $transRing++;
                continue;
            }
            // côté d'appartenance (un vrai fond apparaît sur PLUSIEURS côtés ;
            // un objet posé dans un coin ne teinte qu'un côté — il ne doit pas
            // devenir « couleur de fond » et faire manger une pièce assortie)
            if ($y < $ep) { $cote = 0; }
            elseif ($y >= $H - $ep) { $cote = 1; }
            elseif ($x < $ep) { $cote = 2; }
            else { $cote = 3; }
            $sideTot[$cote]++;
            $cle = (((int) $r[$p] >> 3) << 10) | (((int) $v[$p] >> 3) << 5) | ((int) $b[$p] >> 3);
            if (!isset($hist[$cle])) {
                $hist[$cle] = [0, 0, 0, 0, 0, 0, 0, 0]; // n, sommes r v b, côtés×4
            }
            $hist[$cle][0]++;
            $hist[$cle][1] += $r[$p];
            $hist[$cle][2] += $v[$p];
            $hist[$cle][3] += $b[$p];
            $hist[$cle][4 + $cote]++;
            $ringTot++;
        }
    }
    uasort($hist, function ($x, $y) {
        return $y[0] <=> $x[0];
    });
    // fusion gloutonne des bacs voisins en « teintes » (max 8 en construction)
    $modes = []; // chaque teinte : [n, r, v, b, côtés×4] (moyennes + présence)
    foreach ($hist as $bac) {
        $mr = $bac[1] / $bac[0];
        $mv = $bac[2] / $bac[0];
        $mb = $bac[3] / $bac[0];
        $trouve = -1;
        foreach ($modes as $k => $m) {
            $d1 = abs($mr - $m[1]);
            $d2 = abs($mv - $m[2]);
            $d3 = abs($mb - $m[3]);
            $d = $d1 > $d2 ? ($d1 > $d3 ? $d1 : $d3) : ($d2 > $d3 ? $d2 : $d3);
            if ($d <= $tolFusion) {
                $trouve = $k;
                break;
            }
        }
        if ($trouve >= 0) {
            $m = $modes[$trouve];
            $nt = $m[0] + $bac[0];
            $modes[$trouve] = [
                $nt,
                ($m[1] * $m[0] + $mr * $bac[0]) / $nt,
                ($m[2] * $m[0] + $mv * $bac[0]) / $nt,
                ($m[3] * $m[0] + $mb * $bac[0]) / $nt,
                $m[4] + $bac[4], $m[5] + $bac[5], $m[6] + $bac[6], $m[7] + $bac[7],
            ];
        } elseif (count($modes) < 8) {
            $modes[] = [$bac[0], $mr, $mv, $mb, $bac[4], $bac[5], $bac[6], $bac[7]];
        }
    }
    // on ne garde que les teintes qui pèsent (≥ 2,5 % de l'anneau) ET qui sont
    // présentes sur au moins 2 côtés de l'image (≥ 8 % du côté), 5 max
    $seuilMode = 0.025 * max(1, $ringTot);
    $modes = array_values(array_filter($modes, function ($m) use ($seuilMode, $sideTot) {
        if ($m[0] < $seuilMode) {
            return false;
        }
        $cotes = 0;
        for ($c = 0; $c < 4; $c++) {
            if ($sideTot[$c] > 0 && ($m[4 + $c] / $sideTot[$c]) >= 0.08) {
                $cotes++;
            }
        }
        return $cotes >= 2;
    }));
    usort($modes, function ($x, $y) {
        return $y[0] <=> $x[0];
    });
    $modes = array_slice($modes, 0, 5);
    $nbModes = count($modes);
    $mR = [];
    $mV = [];
    $mB = [];
    $mSom = [];
    foreach ($modes as $m) {
        $mR[] = (int) round($m[1]);
        $mV[] = (int) round($m[2]);
        $mB[] = (int) round($m[3]);
        $mSom[] = (int) round($m[1] + $m[2] + $m[3]);
    }

    // ------------------------------------------------------------------
    // 2) PORTE D'ENTRÉE : les teintes couvrent-elles le pourtour ?
    // ------------------------------------------------------------------
    $couverts = $transRing;
    if ($nbModes > 0) {
        for ($y = 0; $y < $H; $y++) {
            $bande = ($y < $ep || $y >= $H - $ep);
            for ($x = 0; $x < $L; $x++) {
                if (!$bande && $x >= $ep && $x < $L - $ep) {
                    $x = $L - $ep - 1;
                    continue;
                }
                $p = $y * $L + $x;
                if ($a[$p] >= 100) {
                    continue;
                }
                if (fpl_detour_membre_fond($r[$p], $v[$p], $b[$p], $mR, $mV, $mB, $mSom, $nbModes, $tolMode)) {
                    $couverts++;
                }
            }
        }
    }
    $denomRing = $ringTot + $transRing;
    if ($denomRing < 8 || ($couverts / $denomRing) < 0.55) {
        $motif = sprintf('fond charge (pourtour couvert %.0f %%)', $denomRing ? 100 * $couverts / $denomRing : 0);
        imagedestroy($im);
        return null; // on décline : la photo d'origine restera
    }

    // ------------------------------------------------------------------
    // 3) CROISSANCE NIVEAU 1 : depuis les bords, par APPARTENANCE aux teintes
    //    (le joint sombre du carrelage est une teinte : le quadrillage se
    //    traverse ; la pièce, étrangère aux teintes, fait barrage)
    // ------------------------------------------------------------------
    $file = [];
    $graine = function ($p) use (&$fond, &$file, $r, $v, $b, $a, $mR, $mV, $mB, $mSom, $nbModes, $tolMode) {
        if ($fond[$p]) {
            return;
        }
        if ($a[$p] >= 100
            || fpl_detour_membre_fond($r[$p], $v[$p], $b[$p], $mR, $mV, $mB, $mSom, $nbModes, $tolMode)) {
            $fond[$p] = 1;
            $file[] = $p;
        }
    };
    for ($x = 0; $x < $L; $x++) {
        $graine($x);
        $graine(($H - 1) * $L + $x);
        if ($H > 2) {
            $graine($L + $x);
            $graine(($H - 2) * $L + $x);
        }
    }
    for ($y = 0; $y < $H; $y++) {
        $graine($y * $L);
        $graine($y * $L + $L - 1);
        if ($L > 2) {
            $graine($y * $L + 1);
            $graine($y * $L + $L - 2);
        }
    }
    $tete = 0;
    while ($tete < count($file)) {
        $p = $file[$tete++];
        $x = $p % $L;
        $y = ($p - $x) / $L;
        for ($dir = 0; $dir < 4; $dir++) {
            if ($dir === 0) { if ($x <= 0) { continue; } $q = $p - 1; }
            elseif ($dir === 1) { if ($x >= $L - 1) { continue; } $q = $p + 1; }
            elseif ($dir === 2) { if ($y <= 0) { continue; } $q = $p - $L; }
            else { if ($y >= $H - 1) { continue; } $q = $p + $L; }
            if ($fond[$q]) {
                continue;
            }
            if ($a[$q] >= 100) {
                $fond[$q] = 1;
                $file[] = $q;
                continue;
            }
            $rr = $r[$q];
            $vv = $v[$q];
            $bb = $b[$q];
            // jamais de saut brutal, même vers une teinte du fond : un reflet
            // blanc SUR une pièce ne s'attrape pas depuis un carreau sombre
            // (carreau → joint reste un petit saut, lui passe)
            $d1 = $rr > $r[$p] ? $rr - $r[$p] : $r[$p] - $rr;
            $d2 = $vv > $v[$p] ? $vv - $v[$p] : $v[$p] - $vv;
            $d3 = $bb > $b[$p] ? $bb - $b[$p] : $b[$p] - $bb;
            $saut = $d1 > $d2 ? ($d1 > $d3 ? $d1 : $d3) : ($d2 > $d3 ? $d2 : $d3);
            if ($saut > $sautMax) {
                continue;
            }
            if (fpl_detour_membre_fond($rr, $vv, $bb, $mR, $mV, $mB, $mSom, $nbModes, $tolMode)) {
                $fond[$q] = 1;
                $file[] = $q;
            }
        }
    }
    $file = null;

    // ------------------------------------------------------------------
    // 4) CROISSANCE NIVEAU 2 : les RIVES — ombres et franges au ras du fond.
    //    Petites marches locales seulement, pas trop loin des teintes du fond,
    //    et profondeur bornée : impossible de traverser la pièce.
    // ------------------------------------------------------------------
    // resserrée exprès : à +30 elle grignotait un filtre jaune paille posé sur
    // du carrelage gris (écart 50) — les franges vraies sont bien plus proches
    $tolRive = $tolMode + 12;
    $fq = [];
    $fd = [];
    for ($p = 0; $p < $N; $p++) {
        if (!$fond[$p]) {
            continue;
        }
        $x = $p % $L;
        $y = ($p - $x) / $L;
        if (($x > 0 && !$fond[$p - 1]) || ($x < $L - 1 && !$fond[$p + 1])
            || ($y > 0 && !$fond[$p - $L]) || ($y < $H - 1 && !$fond[$p + $L])) {
            $fq[] = $p;
            $fd[] = 0;
        }
    }
    $tete = 0;
    while ($tete < count($fq)) {
        $p = $fq[$tete];
        $prof = $fd[$tete];
        $tete++;
        if ($prof >= $profRive) {
            continue;
        }
        $x = $p % $L;
        $y = ($p - $x) / $L;
        $rp = $r[$p];
        $vp = $v[$p];
        $bp = $b[$p];
        for ($dir = 0; $dir < 4; $dir++) {
            if ($dir === 0) { if ($x <= 0) { continue; } $q = $p - 1; }
            elseif ($dir === 1) { if ($x >= $L - 1) { continue; } $q = $p + 1; }
            elseif ($dir === 2) { if ($y <= 0) { continue; } $q = $p - $L; }
            else { if ($y >= $H - 1) { continue; } $q = $p + $L; }
            if ($fond[$q] || $a[$q] >= 100) {
                continue;
            }
            $rr = $r[$q];
            $vv = $v[$q];
            $bb = $b[$q];
            // marche locale douce ?
            $d1 = $rr > $rp ? $rr - $rp : $rp - $rr;
            $d2 = $vv > $vp ? $vv - $vp : $vp - $vv;
            $d3 = $bb > $bp ? $bb - $bp : $bp - $bb;
            $marche = $d1 > $d2 ? ($d1 > $d3 ? $d1 : $d3) : ($d2 > $d3 ? $d2 : $d3);
            if ($marche > $pente) {
                continue;
            }
            // pas trop loin des teintes du fond ? La marge de rive respecte
            // les PLAFONDS par teinte (sombre 18, très claire 20) : à
            // tolMode+12 uniforme, la rive grignotait la chair OMBRÉE d'une
            // pièce crème (l'ondulation au bas du pare-choc DZ97259624070).
            // OU BIEN : l'OMBRE PORTÉE d'une teinte claire — la même teinte
            // assombrie en gardant ses proportions (rapport de canaux ±12).
            // Un gris neutre sur fond blanc passe (l'ombre sous un
            // rétroviseur), le crème ou le jaune d'une pièce NON (leurs
            // proportions diffèrent du blanc) ; et il faut être ARRIVÉ là par
            // petites marches depuis le fond — le bord franc d'une pièce
            // grise (volant moteur : marche de 25-50) bloque la rive avant.
            $pres = ($nbModes === 0);
            for ($k = 0; $k < $nbModes; $k++) {
                $tk = $tolRive;
                if ($mSom[$k] < 460 && $tk > 30) {
                    $tk = 30;
                } elseif ($mSom[$k] >= 660 && $tk > 32) {
                    $tk = 32;
                }
                $e1 = $rr > $mR[$k] ? $rr - $mR[$k] : $mR[$k] - $rr;
                if ($e1 <= $tk) {
                    $e2 = $vv > $mV[$k] ? $vv - $mV[$k] : $mV[$k] - $vv;
                    if ($e2 <= $tk) {
                        $e3 = $bb > $mB[$k] ? $bb - $mB[$k] : $mB[$k] - $bb;
                        if ($e3 <= $tk) {
                            $pres = true;
                            break;
                        }
                    }
                }
                if ($mSom[$k] >= 660 && $prof < $profOmbre) {
                    // fenêtre 0,50-0,92 : l'ombre portée réelle est à
                    // 0,55-0,80 du fond. À 0,40 le flanc ombré d'une coque
                    // gris sombre se faisait mordre ; au-delà de 0,92 c'est le
                    // corps ÉCLAIRÉ d'une pièce blanche (0,95-1,0) — et la
                    // PROFONDEUR est bornée : l'ombre est une BANDE au ras du
                    // contour, pas un droit d'entrer dans la pièce (le
                    // pare-choc blanc #2779 s'est fait dévorer sans la borne)
                    $ks = ($rr + $vv + $bb) / $mSom[$k];
                    if ($ks >= 0.50 && $ks <= 0.92) {
                        $o1 = $rr - $ks * $mR[$k];
                        $o2 = $vv - $ks * $mV[$k];
                        $o3 = $bb - $ks * $mB[$k];
                        if ($o1 <= 12 && $o1 >= -12 && $o2 <= 12 && $o2 >= -12 && $o3 <= 12 && $o3 >= -12) {
                            $pres = true;
                            break;
                        }
                    }
                }
            }
            if (!$pres) {
                continue;
            }
            $fond[$q] = 1;
            $fq[] = $q;
            $fd[] = $prof + 1;
        }
    }
    $fq = null;
    $fd = null;

    // ------------------------------------------------------------------
    // 5) MÉNAGE DES COMPOSANTES gardées (étiquetage 4-connexe + règles)
    // ------------------------------------------------------------------
    $label = new SplFixedArray($N);
    for ($p = 0; $p < $N; $p++) {
        $label[$p] = 0;
    }
    $comps = []; // par étiquette : n, sommes rvb, bbox, somme x/y (centroïde)
    $lab = 0;
    for ($p0 = 0; $p0 < $N; $p0++) {
        if ($fond[$p0] || $label[$p0] !== 0) {
            continue;
        }
        $lab++;
        $n = 0;
        $sr = 0;
        $sv = 0;
        $sb = 0;
        $sx = 0;
        $sy = 0;
        $sl = 0;
        $sl2 = 0;
        $x0 = $L;
        $x1 = -1;
        $y0 = $H;
        $y1 = -1;
        $pile = [$p0];
        $label[$p0] = $lab;
        $toucheBord = false;
        while ($pile) {
            $p = array_pop($pile);
            $n++;
            $x = $p % $L;
            $y = ($p - $x) / $L;
            if ($x === 0 || $x === $L - 1 || $y === 0 || $y === $H - 1) {
                $toucheBord = true;
            }
            $sr += $r[$p];
            $sv += $v[$p];
            $sb += $b[$p];
            $sx += $x;
            $sy += $y;
            $lu = ($r[$p] + $v[$p] + $b[$p]) / 3;
            $sl += $lu;
            $sl2 += $lu * $lu;
            if ($x < $x0) { $x0 = $x; }
            if ($x > $x1) { $x1 = $x; }
            if ($y < $y0) { $y0 = $y; }
            if ($y > $y1) { $y1 = $y; }
            if ($x > 0)      { $q = $p - 1;  if (!$fond[$q] && $label[$q] === 0) { $label[$q] = $lab; $pile[] = $q; } }
            if ($x < $L - 1) { $q = $p + 1;  if (!$fond[$q] && $label[$q] === 0) { $label[$q] = $lab; $pile[] = $q; } }
            if ($y > 0)      { $q = $p - $L; if (!$fond[$q] && $label[$q] === 0) { $label[$q] = $lab; $pile[] = $q; } }
            if ($y < $H - 1) { $q = $p + $L; if (!$fond[$q] && $label[$q] === 0) { $label[$q] = $lab; $pile[] = $q; } }
        }
        $moy = $sl / $n;
        $comps[$lab] = [
            'n' => $n, 'r' => $sr / $n, 'v' => $sv / $n, 'b' => $sb / $n,
            'cx' => $sx / $n, 'cy' => $sy / $n,
            'x0' => $x0, 'x1' => $x1, 'y0' => $y0, 'y1' => $y1,
            'ect' => sqrt(max(0, $sl2 / $n - $moy * $moy)), // écart-type de luminance
            'bord' => $toucheBord,
        ];
    }
    if (!$comps) {
        $motif = 'rien a garder (tout est fond)';
        imagedestroy($im);
        return null;
    }
    $tailleMax = 0;
    $principal = null; // la plus grosse composante = la pièce
    foreach ($comps as $k => $c) {
        if ($c['n'] > $tailleMax) {
            $tailleMax = $c['n'];
            $principal = $k;
        }
    }
    $tailleMin = max(140, (int) round($N * 0.002));
    $margeX = 0.12 * $L;
    $margeY = 0.12 * $H;
    $diag = sqrt($L * $L + $H * $H);
    $jeter = [];
    foreach ($comps as $k => $c) {
        // miette
        if ($c['n'] < $tailleMin) {
            $jeter[$k] = 'miette';
            continue;
        }
        // morceau couleur-de-fond (carreau orphelin, pan de papier)
        if ($nbModes > 0 && fpl_detour_membre_fond(
            (int) round($c['r']), (int) round($c['v']), (int) round($c['b']),
            $mR, $mV, $mB, $mSom, $nbModes, $tolResidu
        )) {
            $jeter[$k] = 'couleur-de-fond';
            continue;
        }
        // ombre portée échouée : une teinte claire du fond ASSOMBRIE (50 à
        // 90 %) et PLATE (écart-type de luminance ≤ 18 — une ombre n'a pas de
        // relief, même rayée par un joint de carrelage ; un volant moteur
        // gris, plein de reflets et de dents, en a bien plus)
        if ($nbModes > 0 && $c['n'] < 0.3 * $tailleMax && $c['ect'] <= 18) {
            $somC = $c['r'] + $c['v'] + $c['b'];
            for ($m = 0; $m < $nbModes; $m++) {
                if ($mSom[$m] < 270) {
                    continue;
                }
                $ks = $somC / $mSom[$m];
                if ($ks < 0.50 || $ks > 0.90) {
                    continue;
                }
                $e1 = abs($c['r'] - $ks * $mR[$m]);
                $e2 = abs($c['v'] - $ks * $mV[$m]);
                $e3 = abs($c['b'] - $ks * $mB[$m]);
                if ($e1 <= 10 && $e2 <= 10 && $e3 <= 10) {
                    $jeter[$k] = 'ombre-plate';
                    break;
                }
            }
            if (isset($jeter[$k])) {
                continue;
            }
        }
        // reflet plat : le symétrique de l'ombre — une bande de sol ÉCLAIRÉE
        // (rayure de lumière, joint brillant), teinte du fond éclaircie et
        // sans relief. Une pièce claire détachée a du relief (ect plus grand).
        if ($nbModes > 0 && $c['n'] < 0.3 * $tailleMax && $c['ect'] <= 12) {
            $somC = $c['r'] + $c['v'] + $c['b'];
            for ($m = 0; $m < $nbModes; $m++) {
                if ($mSom[$m] < 120) {
                    continue;
                }
                $ks = $somC / $mSom[$m];
                if ($ks < 1.08 || $ks > 1.70) {
                    continue;
                }
                $e1 = abs($c['r'] - $ks * $mR[$m]);
                $e2 = abs($c['v'] - $ks * $mV[$m]);
                $e3 = abs($c['b'] - $ks * $mB[$m]);
                if ($e1 <= 10 && $e2 <= 10 && $e3 <= 10) {
                    $jeter[$k] = 'reflet-plat';
                    break;
                }
            }
            if (isset($jeter[$k])) {
                continue;
            }
        }
        if ($k === $principal) {
            continue; // la pièce principale ne peut être ni cadre, ni parasite
        }
        // cadre / ligne creuse (collage de photos) : boîte immense presque vide
        $aire = max(1, ($c['x1'] - $c['x0'] + 1) * ($c['y1'] - $c['y0'] + 1));
        if ($c['n'] / $aire < 0.10 && $c['n'] < 0.035 * $N) {
            $jeter[$k] = 'cadre-creux';
            continue;
        }
        // parasite périphérique (objet au ras du bord : chaussure, carton…)
        // — SAUF si ce morceau chevauche la boîte de la pièce et lui ressemble :
        // c'est alors un BOUT DE LA PIÈCE que la découpe a détaché (pare-choc
        // #3636 : le tiers droit, tué ici en silence, rendait une pièce coupée).
        // Cette preuve de coupe doit DÉCLINER la photo, pas être jetée.
        $peripherique = ($c['cx'] < $margeX || $c['cx'] > $L - $margeX
            || $c['cy'] < $margeY || $c['cy'] > $H - $margeY);
        if ($peripherique && $c['n'] < 0.8 * $tailleMax) {
            if ($principal !== null && $c['n'] >= 0.04 * $tailleMax) {
                $cp = $comps[$principal];
                $chevauche = !($c['x1'] < $cp['x0'] || $c['x0'] > $cp['x1']
                    || $c['y1'] < $cp['y0'] || $c['y0'] > $cp['y1']);
                $dCoul = max(abs($c['r'] - $cp['r']), abs($c['v'] - $cp['v']), abs($c['b'] - $cp['b']));
                if ($chevauche && $dCoul <= 40) {
                    $motif = 'un bout de la piece a ete detache par la decoupe';
                    imagedestroy($im);
                    return null;
                }
            }
            $jeter[$k] = 'peripherique';
            continue;
        }
        // petit morceau ACCROCHÉ AU BORD : un objet étranger qui entre dans le
        // cadre (barre d'étagère, tuyau) — la pièce principale, elle, a le
        // droit d'être cadrée serré, et les vraies paires sont de tailles
        // comparables (le second filtre d'un duo ne meurt pas ici)
        if ($c['bord'] && $c['n'] < 0.25 * $tailleMax) {
            $jeter[$k] = 'accroche-au-bord';
            continue;
        }
        // morceau orphelin : nettement plus petit que la pièce ET loin d'elle
        // (bout de papier, rayure claire du sol, éclat) — une vraie paire de
        // pièces est posée ensemble, et de tailles comparables
        if ($principal !== null && $c['n'] < 0.25 * $tailleMax) {
            $cp = $comps[$principal];
            $gx = max(0, max($cp['x0'], $c['x0']) - min($cp['x1'], $c['x1']));
            $gy = max(0, max($cp['y0'], $c['y0']) - min($cp['y1'], $c['y1']));
            if (sqrt($gx * $gx + $gy * $gy) > 0.08 * $diag) {
                $jeter[$k] = 'orphelin';
            }
        }
    }
    // au plus 4 morceaux (pièce seule ou petit lot posé ensemble)
    $survivants = [];
    foreach ($comps as $k => $c) {
        if (!isset($jeter[$k])) {
            $survivants[$k] = $c['n'];
        }
    }
    arsort($survivants);
    if (count($survivants) === 0) {
        $motif = 'aucune composante utile (que des miettes ou des parasites)';
        imagedestroy($im);
        return null;
    }
    // scène éclatée : si le ménage laisse encore une poignée de morceaux
    // épars, le fond n'a pas été compris — on décline plutôt que de choisir
    if (count($survivants) > 6) {
        $motif = sprintf('scene eclatee (%d morceaux apres menage)', count($survivants));
        imagedestroy($im);
        return null;
    }
    // pièce FENDUE ou scène double : deux gros morceaux qui SE TOUCHENT, c'est
    // une pièce coupée en deux par un reflet (garde-boue vernis) ou un collage
    // de deux photos — jamais un beau détourage. Une vraie paire de pièces est
    // posée avec du jour entre les deux.
    if (count($survivants) >= 2) {
        $cles = array_keys($survivants);
        $c1 = $comps[$cles[0]];
        $c2 = $comps[$cles[1]];
        $gx = max(0, max($c1['x0'], $c2['x0']) - min($c1['x1'], $c2['x1']));
        $gy = max(0, max($c1['y0'], $c2['y0']) - min($c1['y1'], $c2['y1']));
        $ecart = sqrt($gx * $gx + $gy * $gy);
        if ($c2['n'] >= 0.22 * $c1['n'] && $ecart <= 0.01 * $diag) {
            $motif = 'piece fendue ou scene double (deux gros morceaux qui se touchent)';
            imagedestroy($im);
            return null;
        }
        // même à distance : deux morceaux ALIGNÉS, de MÊME MATIÈRE, séparés
        // par une bande mangée = une pièce amputée en son milieu (calandre
        // blanche sur fond blanc : il reste deux bandes noires superposées).
        // Une vraie paire côte à côte diffère par la couleur (filtre jaune +
        // filtre bleu) et n'est pas concernée.
        if ($c2['n'] >= 0.07 * $c1['n'] && $ecart <= 0.05 * $diag) {
            $dc1 = abs($c1['r'] - $c2['r']);
            $dc2 = abs($c1['v'] - $c2['v']);
            $dc3 = abs($c1['b'] - $c2['b']);
            $dCouleur = max($dc1, $dc2, $dc3);
            // recouvrement sur l'axe PERPENDICULAIRE à la séparation
            if ($gy > 0 && $gx == 0) {
                $rec = min($c1['x1'], $c2['x1']) - max($c1['x0'], $c2['x0']) + 1;
                $petit = min($c1['x1'] - $c1['x0'], $c2['x1'] - $c2['x0']) + 1;
            } elseif ($gx > 0 && $gy == 0) {
                $rec = min($c1['y1'], $c2['y1']) - max($c1['y0'], $c2['y0']) + 1;
                $petit = min($c1['y1'] - $c1['y0'], $c2['y1'] - $c2['y0']) + 1;
            } else {
                $rec = 0;
                $petit = 1;
            }
            if ($dCouleur <= 40 && $rec >= 0.5 * $petit) {
                $motif = 'piece amputee en son milieu (deux bandes de meme matiere)';
                imagedestroy($im);
                return null;
            }
        }
    }
    $rang = 0;
    foreach ($survivants as $k => $n) {
        $rang++;
        if ($rang > 4) {
            $jeter[$k] = 'au-dela-des-4';
        }
    }
    if ($jeter) {
        for ($p = 0; $p < $N; $p++) {
            if (!$fond[$p] && isset($jeter[$label[$p]])) {
                $fond[$p] = 1;
            }
        }
    }
    if (getenv('FPL_DETOUR_DEBUG')) {
        fwrite(STDERR, sprintf("[detour] %dx%d teintes=%s
", $L, $H, json_encode(array_map(function ($k) use ($mR, $mV, $mB) {
            return [$mR[$k], $mV[$k], $mB[$k]];
        }, array_keys($mR)))));
        foreach ($comps as $k => $c) {
            fwrite(STDERR, sprintf("[detour] comp %d n=%d rgb=(%d,%d,%d) ect=%.1f centre=(%d,%d) boite=%d..%d,%d..%d => %s
",
                $k, $c['n'], $c['r'], $c['v'], $c['b'], $c['ect'], $c['cx'], $c['cy'],
                $c['x0'], $c['x1'], $c['y0'], $c['y1'], $jeter[$k] ?? 'garde'));
        }
    }
    // la boîte du plus gros morceau survivant servira à la porte « centre »
    $bbPrincipal = $comps[array_key_first($survivants)];
    $label = null;
    $comps = null;

    // ------------------------------------------------------------------
    // 6) BORD À LA NORME : lissage majoritaire ×2, érosion 1 px,
    //    anti-crénelage par carte de distance (fondu ~1,5 px)
    // ------------------------------------------------------------------
    $garde = new SplFixedArray($N);
    for ($p = 0; $p < $N; $p++) {
        $garde[$p] = $fond[$p] ? 0 : 1;
    }
    $fond = null;
    for ($passe = 0; $passe < 2; $passe++) {
        $lisse = new SplFixedArray($N);
        for ($p = 0; $p < $N; $p++) {
            $lisse[$p] = $garde[$p];
        }
        for ($y = 1; $y < $H - 1; $y++) {
            $base = $y * $L;
            for ($x = 1; $x < $L - 1; $x++) {
                $p = $base + $x;
                $s = $garde[$p - $L - 1] + $garde[$p - $L] + $garde[$p - $L + 1]
                   + $garde[$p - 1]      + $garde[$p]      + $garde[$p + 1]
                   + $garde[$p + $L - 1] + $garde[$p + $L] + $garde[$p + $L + 1];
                $lisse[$p] = $s >= 5 ? 1 : 0;
            }
        }
        $garde = $lisse;
    }
    // érosion 1 px d'origine (le liseré clair du contour part avec) — le bord
    // de l'image compte comme « gardé » : une pièce cadrée serré garde son
    // bord droit. Sur une photo agrandie, autant de passes que le facteur.
    for ($pe = 0; $pe < $morpho; $pe++) {
        $erode = new SplFixedArray($N);
        for ($y = 0; $y < $H; $y++) {
            $base = $y * $L;
            for ($x = 0; $x < $L; $x++) {
                $p = $base + $x;
                if (!$garde[$p]) {
                    $erode[$p] = 0;
                    continue;
                }
                $ok = 1;
                if ($x > 0 && !$garde[$p - 1]) { $ok = 0; }
                elseif ($x < $L - 1 && !$garde[$p + 1]) { $ok = 0; }
                elseif ($y > 0 && !$garde[$p - $L]) { $ok = 0; }
                elseif ($y < $H - 1 && !$garde[$p + $L]) { $ok = 0; }
                $erode[$p] = $ok;
            }
        }
        $garde = $erode;
    }

    // OUVERTURE (érosion puis dilatation, rayon 2 px d'origine) — SEULEMENT
    // sur les photos AGRANDIES : leurs lambeaux d'ombre portée (1-3 px
    // d'origine devenus 3-8 px de travail) échappent à la couleur comme aux
    // composantes ; l'ouverture les efface et restaure le corps de la pièce.
    // Les photos de taille normale n'en ont pas besoin (mesuré sur les
    // bancs) : on ne touche pas à ce qui est déjà propre.
    for ($passe = 0; $passe < ($morpho > 1 ? 2 * $morpho : 0); $passe++) {
        $ero = new SplFixedArray($N);
        for ($y = 0; $y < $H; $y++) {
            $base = $y * $L;
            for ($x = 0; $x < $L; $x++) {
                $p = $base + $x;
                if (!$garde[$p]) {
                    $ero[$p] = 0;
                    continue;
                }
                $ok = 1;
                if ($x > 0 && !$garde[$p - 1]) { $ok = 0; }
                elseif ($x < $L - 1 && !$garde[$p + 1]) { $ok = 0; }
                elseif ($y > 0 && !$garde[$p - $L]) { $ok = 0; }
                elseif ($y < $H - 1 && !$garde[$p + $L]) { $ok = 0; }
                $ero[$p] = $ok;
            }
        }
        $garde = $ero;
    }
    for ($passe = 0; $passe < ($morpho > 1 ? 2 * $morpho : 0); $passe++) {
        $dil = new SplFixedArray($N);
        for ($y = 0; $y < $H; $y++) {
            $base = $y * $L;
            for ($x = 0; $x < $L; $x++) {
                $p = $base + $x;
                $ok = $garde[$p];
                if (!$ok) {
                    if (($x > 0 && $garde[$p - 1]) || ($x < $L - 1 && $garde[$p + 1])
                        || ($y > 0 && $garde[$p - $L]) || ($y < $H - 1 && $garde[$p + $L])) {
                        $ok = 1;
                    }
                }
                $dil[$p] = $ok;
            }
        }
        $garde = $dil;
    }

    // FERMETURE (dilatation puis érosion, même rayon) — toujours réservée aux
    // photos AGRANDIES : sur une vignette, la compression rend l'ombre et la
    // chair ombrée pareillement grises et le contour ressort RONGÉ par
    // échancrures ; la fermeture comble les morsures de moins de ~2×rayon
    // (le bas du pare-choc DZ97259624070 retrouve son bord franc). Les creux
    // réels d'une pièce, bien plus larges, ne sont pas touchés.
    for ($passe = 0; $passe < ($morpho > 1 ? 2 * $morpho : 0); $passe++) {
        $dil = new SplFixedArray($N);
        for ($y = 0; $y < $H; $y++) {
            $base = $y * $L;
            for ($x = 0; $x < $L; $x++) {
                $p = $base + $x;
                $ok = $garde[$p];
                if (!$ok) {
                    if (($x > 0 && $garde[$p - 1]) || ($x < $L - 1 && $garde[$p + 1])
                        || ($y > 0 && $garde[$p - $L]) || ($y < $H - 1 && $garde[$p + $L])) {
                        $ok = 1;
                    }
                }
                $dil[$p] = $ok;
            }
        }
        $garde = $dil;
    }
    for ($passe = 0; $passe < ($morpho > 1 ? 2 * $morpho : 0); $passe++) {
        $ero2 = new SplFixedArray($N);
        for ($y = 0; $y < $H; $y++) {
            $base = $y * $L;
            for ($x = 0; $x < $L; $x++) {
                $p = $base + $x;
                if (!$garde[$p]) {
                    $ero2[$p] = 0;
                    continue;
                }
                $ok = 1;
                if ($x > 0 && !$garde[$p - 1]) { $ok = 0; }
                elseif ($x < $L - 1 && !$garde[$p + 1]) { $ok = 0; }
                elseif ($y > 0 && !$garde[$p - $L]) { $ok = 0; }
                elseif ($y < $H - 1 && !$garde[$p + $L]) { $ok = 0; }
                $ero2[$p] = $ok;
            }
        }
        $garde = $ero2;
    }

    // le lissage et l'érosion peuvent avoir DÉTACHÉ des miettes (fins ponts
    // coupés) : on les balaie, et on recompte les morceaux — un masque qui
    // s'émiette en une poignée de morceaux est un fond mal compris : on décline
    $labF = new SplFixedArray($N);
    for ($p = 0; $p < $N; $p++) {
        $labF[$p] = 0;
    }
    $morceaux = 0; // morceaux « qui comptent » (≥ tailleMin)
    $nf = 0;
    for ($p0 = 0; $p0 < $N; $p0++) {
        if (!$garde[$p0] || $labF[$p0] !== 0) {
            continue;
        }
        $nf++;
        $taille = 0;
        $membres = [$p0];
        $pile = [$p0];
        $labF[$p0] = $nf;
        while ($pile) {
            $p = array_pop($pile);
            $taille++;
            $x = $p % $L;
            $y = ($p - $x) / $L;
            if ($x > 0)      { $q = $p - 1;  if ($garde[$q] && $labF[$q] === 0) { $labF[$q] = $nf; $pile[] = $q; $membres[] = $q; } }
            if ($x < $L - 1) { $q = $p + 1;  if ($garde[$q] && $labF[$q] === 0) { $labF[$q] = $nf; $pile[] = $q; $membres[] = $q; } }
            if ($y > 0)      { $q = $p - $L; if ($garde[$q] && $labF[$q] === 0) { $labF[$q] = $nf; $pile[] = $q; $membres[] = $q; } }
            if ($y < $H - 1) { $q = $p + $L; if ($garde[$q] && $labF[$q] === 0) { $labF[$q] = $nf; $pile[] = $q; $membres[] = $q; } }
        }
        if ($taille < $tailleMin) {
            foreach ($membres as $p) {
                $garde[$p] = 0; // miette de découpe
            }
        } else {
            $morceaux++;
        }
    }
    $labF = null;
    if ($morceaux > 6) {
        $motif = sprintf('scene eclatee (%d morceaux au masque final)', $morceaux);
        imagedestroy($im);
        return null;
    }

    // ------------------------------------------------------------------
    // 7) PORTES DE QUALITÉ (sur le masque final) — au moindre doute : null
    // ------------------------------------------------------------------
    $nbGarde = 0;
    $perim = 0;
    $chair = 0; // bords où l'AUTRE côté n'est pas du fond : on a coupé la pièce
    for ($y = 0; $y < $H; $y++) {
        $base = $y * $L;
        for ($x = 0; $x < $L; $x++) {
            $p = $base + $x;
            if (!$garde[$p]) {
                continue;
            }
            $auBord = false;
            $dehors = -1; // un pixel effacé à ~4 px du bord (au-delà de la frange)
            if ($x > 0 && !$garde[$p - 1]) { $auBord = true; $xx = $x - 4; $dehors = $base + ($xx < 0 ? 0 : $xx); }
            elseif ($x < $L - 1 && !$garde[$p + 1]) { $auBord = true; $xx = $x + 4; $dehors = $base + ($xx > $L - 1 ? $L - 1 : $xx); }
            elseif ($y > 0 && !$garde[$p - $L]) { $auBord = true; $yy = $y - 4; $dehors = ($yy < 0 ? 0 : $yy) * $L + $x; }
            elseif ($y < $H - 1 && !$garde[$p + $L]) { $auBord = true; $yy = $y + 4; $dehors = ($yy > $H - 1 ? $H - 1 : $yy) * $L + $x; }
            $nbGarde++;
            if ($auBord) {
                $perim++;
                if ($dehors >= 0 && !$garde[$dehors] && $a[$dehors] < 100 && $nbModes > 0
                    && !fpl_detour_membre_fond($r[$dehors], $v[$dehors], $b[$dehors], $mR, $mV, $mB, $mSom, $nbModes, $tolMode + 25, false)) {
                    $chair++;
                }
            }
        }
    }
    $part = $nbGarde / $N;
    if ($part < 0.02 || $part > 0.85) {
        $motif = sprintf('part gardee hors gabarit (%.0f %%)', 100 * $part);
        imagedestroy($im);
        return null; // pièce mangée, ou presque rien retiré
    }
    // la pièce doit TOUCHER le tiers central de la photo (une calandre cadrée
    // dans le bas de l'image chevauche le tiers central sans contenir le
    // milieu exact — exiger le milieu la faisait décliner à tort) ET le cœur
    // de l'image ne doit pas être déserté (plancher bas, pièces fines).
    if ($bbPrincipal['x0'] > 0.667 * $L || $bbPrincipal['x1'] < 0.333 * $L
        || $bbPrincipal['y0'] > 0.667 * $H || $bbPrincipal['y1'] < 0.333 * $H) {
        $motif = 'piece hors du centre de la photo';
        imagedestroy($im);
        return null;
    }
    // pièce SQUELETTIQUE : le morceau principal ne remplit presque pas sa
    // boîte — c'est ce qui reste d'une pièce blanche mangée sur fond blanc
    // (il ne survit que ses garnitures sombres). On décline.
    $aireP = max(1, ($bbPrincipal['x1'] - $bbPrincipal['x0'] + 1) * ($bbPrincipal['y1'] - $bbPrincipal['y0'] + 1));
    if ($bbPrincipal['n'] / $aireP < 0.30) {
        $motif = sprintf('piece squelettique (%.0f %% de sa boite)', 100 * $bbPrincipal['n'] / $aireP);
        imagedestroy($im);
        return null;
    }
    $cx0 = (int) (0.35 * $L);
    $cx1 = (int) (0.65 * $L);
    $cy0 = (int) (0.35 * $H);
    $cy1 = (int) (0.65 * $H);
    $centreTot = 0;
    $centreGarde = 0;
    for ($y = $cy0; $y < $cy1; $y++) {
        $base = $y * $L;
        for ($x = $cx0; $x < $cx1; $x++) {
            $centreTot++;
            if ($garde[$base + $x]) {
                $centreGarde++;
            }
        }
    }
    if ($centreTot > 0 && ($centreGarde / $centreTot) < 0.08) {
        $motif = sprintf('centre vide (%.0f %% garde)', $centreTot ? 100 * $centreGarde / $centreTot : 0);
        imagedestroy($im);
        return null;
    }
    // peu de contact avec le bord de l'image (sinon : scène, pas une pièce)
    $bordTot = 2 * $L + 2 * ($H - 2);
    $bordGarde = 0;
    for ($x = 0; $x < $L; $x++) {
        $bordGarde += $garde[$x] + $garde[($H - 1) * $L + $x];
    }
    for ($y = 1; $y < $H - 1; $y++) {
        $bordGarde += $garde[$y * $L] + $garde[$y * $L + $L - 1];
    }
    if (($bordGarde / max(1, $bordTot)) > 0.22) {
        $motif = sprintf('trop de contact avec le bord (%.0f %%)', 100 * $bordGarde / max(1, $bordTot));
        imagedestroy($im);
        return null;
    }
    // contour pas déchiqueté — mesuré : une calandre grillagée fait ~29, un
    // volant moteur denté ~28 (ils passent) ; la dentelle d'un phare chromé
    // mal séparé du carrelage fait ~267 (elle décline). Seuil à 60.
    $compacite = ($perim * $perim) / (4 * M_PI * max(1, $nbGarde));
    if ($compacite > 60) {
        $motif = sprintf('contour dechiquete (compacite %.0f)', $compacite);
        imagedestroy($im);
        return null;
    }
    // la pièce ne doit pas GARDER du fond : si une grosse part des pixels
    // gardés colle aux teintes du pourtour, un pan de sol ou le carton-support
    // est resté soudé à la pièce — on décline (le carton torpille l'étiquette)
    // Cette porte a été calibrée sur des supports MI-TEINTE restés soudés à
    // la pièce (carton kraft 34 %, carrelage 41 %, chrome sale 27 % — les
    // bonnes découpes font ≤ 10 %). Les teintes TRÈS CLAIRES n'y comptent
    // pas : une pièce claire garde légitimement ses reflets quasi blancs et
    // ses ouvertures laissent voir du blanc enclavé — les compter déclinait
    // à tort le pare-choc DZ97259624070 ; le blanc massif reste tenu par les
    // portes part/bord/fendue.
    $presFond = 0;
    for ($p = 0; $p < $N; $p++) {
        if (!$garde[$p]) {
            continue;
        }
        $rr = $r[$p];
        $vv = $v[$p];
        $bb = $b[$p];
        for ($k = 0; $k < $nbModes; $k++) {
            if ($mSom[$k] >= 660) {
                continue;
            }
            $t = $tolMode + 8;
            $d1 = $rr > $mR[$k] ? $rr - $mR[$k] : $mR[$k] - $rr;
            if ($d1 > $t) {
                continue;
            }
            $d2 = $vv > $mV[$k] ? $vv - $mV[$k] : $mV[$k] - $vv;
            if ($d2 > $t) {
                continue;
            }
            $d3 = $bb > $mB[$k] ? $bb - $mB[$k] : $mB[$k] - $bb;
            if ($d3 <= $t) {
                $presFond++;
                break;
            }
        }
    }
    // mesuré sur les planches : les sorties sales (phare chromé en lambeaux,
    // carton-support soudé) font 0,23 à 0,28 ; toutes les bonnes ≤ 0,10
    if ($nbGarde > 0 && ($presFond / $nbGarde) > 0.20) {
        $motif = sprintf('la piece garde du fond (%.0f %% des pixels gardes)', 100 * $presFond / $nbGarde);
        imagedestroy($im);
        return null;
    }
    if (getenv('FPL_DETOUR_DEBUG')) {
        fwrite(STDERR, sprintf("[detour] portes: part=%.3f bord=%.3f compacite=%.1f presFond=%.3f chair=%.3f morceaux=%d\n",
            $part, $bordGarde / max(1, $bordTot), $compacite, $nbGarde ? $presFond / $nbGarde : 0,
            $perim ? $chair / $perim : 0, $morceaux));
    }

    // ------------------------------------------------------------------
    // 8) CARTE DE DISTANCE (chanfrein 3-4) → alpha du bord en fondu
    // ------------------------------------------------------------------
    $INF = 1 << 20;
    $dist = new SplFixedArray($N);
    for ($p = 0; $p < $N; $p++) {
        $dist[$p] = $garde[$p] ? $INF : 0;
    }
    for ($y = 0; $y < $H; $y++) {
        $base = $y * $L;
        for ($x = 0; $x < $L; $x++) {
            $p = $base + $x;
            $d = $dist[$p];
            if ($d === 0) {
                continue;
            }
            if ($x > 0 && $dist[$p - 1] + 3 < $d) { $d = $dist[$p - 1] + 3; }
            if ($y > 0) {
                if ($dist[$p - $L] + 3 < $d) { $d = $dist[$p - $L] + 3; }
                if ($x > 0 && $dist[$p - $L - 1] + 4 < $d) { $d = $dist[$p - $L - 1] + 4; }
                if ($x < $L - 1 && $dist[$p - $L + 1] + 4 < $d) { $d = $dist[$p - $L + 1] + 4; }
            }
            $dist[$p] = $d;
        }
    }
    for ($y = $H - 1; $y >= 0; $y--) {
        $base = $y * $L;
        for ($x = $L - 1; $x >= 0; $x--) {
            $p = $base + $x;
            $d = $dist[$p];
            if ($d === 0) {
                continue;
            }
            if ($x < $L - 1 && $dist[$p + 1] + 3 < $d) { $d = $dist[$p + 1] + 3; }
            if ($y < $H - 1) {
                if ($dist[$p + $L] + 3 < $d) { $d = $dist[$p + $L] + 3; }
                if ($x < $L - 1 && $dist[$p + $L + 1] + 4 < $d) { $d = $dist[$p + $L + 1] + 4; }
                if ($x > 0 && $dist[$p + $L - 1] + 4 < $d) { $d = $dist[$p + $L - 1] + 4; }
            }
            $dist[$p] = $d;
        }
    }

    // ------------------------------------------------------------------
    // 9) APPLICATION + DÉCONTAMINATION du bord (le fondu retire la part de
    //    couleur du fond encore mêlée au pixel : fini le halo)
    // ------------------------------------------------------------------
    imagealphablending($im, false);
    imagesavealpha($im, true);
    $transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);
    for ($y = 0, $p = 0; $y < $H; $y++) {
        for ($x = 0; $x < $L; $x++, $p++) {
            $d = $dist[$p];
            if ($d >= 6) {
                continue; // cœur de la pièce : pixel d'origine intact
            }
            if ($d <= 1) {
                imagesetpixel($im, $x, $y, $transparent);
                continue;
            }
            // fondu : d = 3 (pixel de bord) → 0,33 ; d = 6 → 1
            $ap = ($d - 1.5) / 4.5;
            if ($ap <= 0) {
                imagesetpixel($im, $x, $y, $transparent);
                continue;
            }
            if ($ap > 1) {
                $ap = 1;
            }
            $rr = (int) $r[$p];
            $vv = (int) $v[$p];
            $bb = (int) $b[$p];
            // teinte du fond la plus proche de ce pixel (pour la retirer)
            if ($nbModes > 0) {
                $meilleur = 0;
                $dmin = $INF;
                for ($k = 0; $k < $nbModes; $k++) {
                    $d1 = abs($rr - $mR[$k]);
                    $d2 = abs($vv - $mV[$k]);
                    $d3 = abs($bb - $mB[$k]);
                    $dd = $d1 > $d2 ? ($d1 > $d3 ? $d1 : $d3) : ($d2 > $d3 ? $d2 : $d3);
                    if ($dd < $dmin) {
                        $dmin = $dd;
                        $meilleur = $k;
                    }
                }
                $fr = $mR[$meilleur];
                $fv = $mV[$meilleur];
                $fb = $mB[$meilleur];
            } else {
                $fr = 255;
                $fv = 255;
                $fb = 255;
            }
            $rr = (int) round(($rr - (1 - $ap) * $fr) / $ap);
            $vv = (int) round(($vv - (1 - $ap) * $fv) / $ap);
            $bb = (int) round(($bb - (1 - $ap) * $fb) / $ap);
            if ($rr < 0) { $rr = 0; } elseif ($rr > 255) { $rr = 255; }
            if ($vv < 0) { $vv = 0; } elseif ($vv > 255) { $vv = 255; }
            if ($bb < 0) { $bb = 0; } elseif ($bb > 255) { $bb = 255; }
            $opac = (127 - (int) $a[$p]) / 127; // opacité d'origine (PNG déjà troué)
            $na = 127 - (int) round(127 * $opac * $ap);
            if ($na < 0) { $na = 0; } elseif ($na > 127) { $na = 127; }
            imagesetpixel($im, $x, $y, imagecolorallocatealpha($im, $rr, $vv, $bb, $na));
        }
    }

    return ['img' => $im, 'detouree' => true];
}

/**
 * Détoure une image depuis un fichier disque, avec CACHE (résultat gardé tant
 * que le fichier source ne change pas). Les refus sont aussi mis en cache
 * (marqueur .non) : une photo à fond chargé ne se recalcule pas à chaque
 * étiquette.
 *
 * @param string $chemin
 * @return array{img: resource|GdImage, detouree: bool}|null
 */
function fpl_detourage_fichier($chemin)
{
    if ($chemin === null || !is_file($chemin)) {
        return null;
    }
    $taille = @getimagesize($chemin);
    if (!$taille) {
        return null;
    }

    // cache disque (v4 : nouveau moteur → nouvelles clés)
    $dir = __DIR__ . '/../upload/detour_cache';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $cle = md5(realpath($chemin) . '|' . filemtime($chemin) . '|v7');
    $cache = $dir . '/' . $cle . '.png';
    $refus = $dir . '/' . $cle . '.non';
    if (is_file($cache)) {
        $im = @imagecreatefrompng($cache);
        if ($im) {
            imagesavealpha($im, true);
            return ['img' => $im, 'detouree' => true];
        }
    }
    if (is_file($refus)) {
        return null; // déjà jugé indéteourable (fond chargé)
    }

    switch ($taille[2]) {
        case IMAGETYPE_PNG:  $src = @imagecreatefrompng($chemin); break;
        case IMAGETYPE_WEBP: $src = @imagecreatefromwebp($chemin); break;
        case IMAGETYPE_JPEG: $src = @imagecreatefromjpeg($chemin); break;
        default: return null;
    }
    if (!$src) {
        return null;
    }
    if (!imageistruecolor($src)) {
        imagepalettetotruecolor($src);
    }

    $res = fpl_detourage_gd($src);
    imagedestroy($src);
    if ($res === null) {
        @file_put_contents($refus, '1');
        return null;
    }
    // on grave le cache (transparence conservée)
    imagesavealpha($res['img'], true);
    @imagepng($res['img'], $cache);

    return $res;
}
