<?php
/**
 * DÉTOURAGE COULEUR (03/09/2026) — port PHP/GD de l'algorithme de l'atelier
 * (atelier-etiquettes-fpl.html : appliquerDetourage / fondEstUni).
 *
 * But : découper proprement le fond d'une photo de pièce, bien mieux que
 * l'ancien détourage de Fouta (qui rognait les bords unis puis rendait le
 * blanc transparent seulement si les 4 coins étaient blancs).
 *
 * Principe (identique à l'atelier) :
 *  - couleur de fond = teinte DOMINANTE du pourtour (robuste aux bandeaux) ;
 *  - propagation depuis les bords où le COÛT d'un chemin est LA PLUS GRANDE
 *    marche de lumière (pas la somme) : un fond, même dégradé, se parcourt par
 *    petites marches ; le contour d'une pièce est une marche haute qui bloque ;
 *  - un PLAFOND de couleur calculé sur l'image (95e centile de l'écart du
 *    pourtour) empêche d'effacer une pièce grise sur fond blanc ;
 *  - bords doux (dégradé d'alpha), et GARDE-FOU : si presque tout est effacé,
 *    on rend la photo intacte (mieux vaut un fond visible qu'une pièce mangée).
 *
 * Traitement plafonné à 720 px (mémoire/temps) : bien assez pour une photo
 * d'étiquette (la boîte fait ~520 px) ; le résultat est rendu À CETTE TAILLE,
 * sans repasser en pleine résolution (ce qui écroulait le temps du lot).
 * Programmation procédurale uniquement.
 */

if (!defined('FPL_DETOURAGE_MAX')) {
    define('FPL_DETOURAGE_MAX', 720);
}

/**
 * Le pourtour est-il d'une seule teinte (photo studio, fond uni) ? ≥ 70 %.
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
 * Détoure une image GD (fond → transparent). Rend une NOUVELLE image GD
 * truecolor+alpha, ou null si le détourage n'a rien donné de fiable (l'appelant
 * garde alors la photo d'origine).
 *
 * @param resource|GdImage $src  image source (non modifiée)
 * @param int $force  0..100, agressivité (défaut 45, comme l'atelier)
 * @return array{img: resource|GdImage, detouree: bool}|null
 */
function fpl_detourage_gd($src, $force = 45)
{
    if (!function_exists('imagecreatetruecolor')) {
        return null;
    }
    $L0 = imagesx($src);
    $H0 = imagesy($src);
    if ($L0 < 8 || $H0 < 8) {
        return null;
    }

    // travail à taille plafonnée
    $ech = min(1.0, FPL_DETOURAGE_MAX / max($L0, $H0));
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
    $lum = new SplFixedArray($N);
    for ($y = 0, $p = 0; $y < $H; $y++) {
        for ($x = 0; $x < $L; $x++, $p++) {
            $c = imagecolorat($im, $x, $y);
            $rr = ($c >> 16) & 255;
            $vv = ($c >> 8) & 255;
            $bb = $c & 255;
            $al = ($c >> 24) & 127; // 0 opaque … 127 transparent (GD)
            $r[$p] = $rr;
            $v[$p] = $vv;
            $b[$p] = $bb;
            $a[$p] = $al;
            $lum[$p] = 0.3 * $rr + 0.6 * $vv + 0.1 * $bb;
        }
    }

    // couleur de fond = teinte dominante du pourtour
    $bacs = [];
    $noter = function ($p) use ($r, $v, $b, &$bacs) {
        $cle = (((int) $r[$p] >> 4) << 8) | (((int) $v[$p] >> 4) << 4) | ((int) $b[$p] >> 4);
        if (!isset($bacs[$cle])) {
            $bacs[$cle] = ['n' => 0, 'r' => 0, 'v' => 0, 'b' => 0];
        }
        $bacs[$cle]['n']++;
        $bacs[$cle]['r'] += $r[$p];
        $bacs[$cle]['v'] += $v[$p];
        $bacs[$cle]['b'] += $b[$p];
    };
    for ($x = 0; $x < $L; $x += 2) {
        $noter($x);
        $noter(($H - 1) * $L + $x);
    }
    for ($y = 0; $y < $H; $y += 2) {
        $noter($y * $L);
        $noter($y * $L + $L - 1);
    }
    $dom = null;
    foreach ($bacs as $bac) {
        if ($dom === null || $bac['n'] > $dom['n']) {
            $dom = $bac;
        }
    }
    if ($dom === null) {
        return null;
    }
    $fr = $dom['r'] / $dom['n'];
    $fv = $dom['v'] / $dom['n'];
    $fb = $dom['b'] / $dom['n'];

    $ecart = function ($p) use ($r, $v, $b, $fr, $fv, $fb) {
        $d1 = abs($r[$p] - $fr);
        $d2 = abs($v[$p] - $fv);
        $d3 = abs($b[$p] - $fb);
        return $d1 > $d2 ? ($d1 > $d3 ? $d1 : $d3) : ($d2 > $d3 ? $d2 : $d3);
    };

    // plafond de couleur = 95e centile de l'écart du pourtour
    $ecartsBord = [];
    for ($x = 0; $x < $L; $x += 2) {
        $ecartsBord[] = $ecart($x);
        $ecartsBord[] = $ecart(($H - 1) * $L + $x);
    }
    for ($y = 0; $y < $H; $y += 2) {
        $ecartsBord[] = $ecart($y * $L);
        $ecartsBord[] = $ecart($y * $L + $L - 1);
    }
    sort($ecartsBord);
    $centile95 = $ecartsBord[(int) floor(count($ecartsBord) * 0.95)] ?? 0;
    $plafond = $centile95 + 8 + $force * 0.22;

    $penteMax = max(3, (int) round(3 + $force * 0.06));
    $cout = new SplFixedArray($N);
    for ($p = 0; $p < $N; $p++) {
        $cout[$p] = 127;
    }
    $seaux = [];
    for ($i = 0; $i <= $penteMax; $i++) {
        $seaux[$i] = [];
    }
    $depart = function ($p) use (&$cout, &$seaux, $ecart, $plafond) {
        if ($cout[$p] === 0 || $ecart($p) > $plafond) {
            return;
        }
        $cout[$p] = 0;
        $seaux[0][] = $p;
    };
    for ($x = 0; $x < $L; $x++) {
        $depart($x);
        $depart(($H - 1) * $L + $x);
    }
    for ($y = 0; $y < $H; $y++) {
        $depart($y * $L);
        $depart($y * $L + $L - 1);
    }

    for ($c = 0; $c <= $penteMax; $c++) {
        // IMPORTANT : parcourir $seaux[$c] EN DIRECT (pas une copie) — un voisin
        // de même coût y est ajouté pendant le parcours et DOIT être traité,
        // comme la référence de tableau du JS d'origine. Une copie tuait la
        // propagation (le fond n'était presque pas retiré).
        for ($k = 0; $k < count($seaux[$c]); $k++) {
            $p = $seaux[$c][$k];
            if ($cout[$p] !== $c) {
                continue;
            }
            $lp = $lum[$p];
            $x = $p % $L;
            $y = ($p - $x) / $L;
            $visiter = function ($q) use (&$cout, &$seaux, $ecart, $plafond, $lum, $lp, $c, $penteMax) {
                if ($cout[$q] <= $c) {
                    return;
                }
                if ($ecart($q) > $plafond) {
                    return;
                }
                $marche = (int) ceil(abs($lum[$q] - $lp));
                $nc = $marche > $c ? $marche : $c;
                if ($nc > $penteMax || $nc >= $cout[$q]) {
                    return;
                }
                $cout[$q] = $nc;
                $seaux[$nc][] = $q;
            };
            if ($x > 0) {
                $visiter($p - 1);
            }
            if ($x < $L - 1) {
                $visiter($p + 1);
            }
            if ($y > 0) {
                $visiter($p - $L);
            }
            if ($y < $H - 1) {
                $visiter($p + $L);
            }
        }
    }

    // alpha du fond (dégradé de bord) + garde-fou
    $franc = max(1, $penteMax - 2);
    $efface = new SplFixedArray($N); // part de fond (0..1) à retirer
    $restant = 0;
    for ($p = 0; $p < $N; $p++) {
        $c = $cout[$p];
        $al = 0.0;
        if ($c <= $penteMax) {
            $al = $c <= $franc ? 1.0 : max(0.0, 1 - ($c - $franc) / ($penteMax - $franc + 1));
        }
        $efface[$p] = $al;
        if ($al < 0.5) {
            $restant++;
        }
    }
    if ($restant < $N * 0.04) {
        return null; // aurait mangé la pièce
    }

    // application du masque À LA TAILLE DE TRAVAIL ($im, ≤ plafond) : on modifie
    // directement les pixels déjà en mémoire, SANS la boucle pleine résolution
    // qui traitait des millions de pixels un par un (le lot passait de quelques
    // minutes à plus d'une heure). La boîte photo de l'étiquette est petite : la
    // taille de travail suffit largement.
    imagealphablending($im, false);
    imagesavealpha($im, true);
    for ($p = 0; $p < $N; $p++) {
        $al = $efface[$p];
        if ($al <= 0) {
            continue; // pixel d'origine gardé tel quel
        }
        $sa = (int) $a[$p]; // alpha d'origine (0 opaque … 127 transparent)
        $na = (int) round($sa + (127 - $sa) * $al);
        if ($na > 127) {
            $na = 127;
        }
        $x = $p % $L;
        $y = ($p - $x) / $L;
        imagesetpixel($im, $x, (int) $y, imagecolorallocatealpha($im, (int) $r[$p], (int) $v[$p], (int) $b[$p], $na));
    }

    return ['img' => $im, 'detouree' => true];
}

/**
 * Détoure une image depuis un fichier disque, avec CACHE (le résultat est
 * gardé et réutilisé tant que le fichier source ne change pas).
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

    // cache disque
    $dir = __DIR__ . '/../upload/detour_cache';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $cle = md5(realpath($chemin) . '|' . filemtime($chemin) . '|v2');
    $cache = $dir . '/' . $cle . '.png';
    if (is_file($cache)) {
        $im = @imagecreatefrompng($cache);
        if ($im) {
            imagesavealpha($im, true);
            imagedestroy($src);
            return ['img' => $im, 'detouree' => true];
        }
    }

    $res = fpl_detourage_gd($src);
    imagedestroy($src);
    if ($res === null) {
        return null;
    }
    // on grave le cache (transparence conservée)
    imagesavealpha($res['img'], true);
    @imagepng($res['img'], $cache);

    return $res;
}
