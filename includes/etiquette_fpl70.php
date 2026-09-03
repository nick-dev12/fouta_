<?php
/**
 * L'ÉTIQUETTE 70×70 « NOUVEAU DESSIN » (01/09/2026) — le moteur de rendu.
 *
 * La direction a validé un nouveau dessin d'étiquette, produit par son atelier
 * (FPL-100-ME105116-70x70.pdf) : panneau marine en pentagone avec le camion
 * FPL, filigrane camion fondu, grand nom wolof (Anton), nom français
 * (Barlow Condensed), slogan manuscrit, carte des deux références, trame de
 * points, bloc « SCANNEZ », QR encadré et code-barres EAN.
 *
 * CE FICHIER EST UN PORT AU PIXEL du dessin de l'atelier (dessinerEtiquette,
 * canvas 1080×1080) :
 *  - les couches STATIQUES sont des images précalculées et vérifiées par
 *    comparaison Python contre le PDF validé (image/etiquette70/) ;
 *  - les éléments VIVANTS (photo, titres, références, QR, code-barres) se
 *    dessinent ici en GD, aux mêmes coordonnées logiques ×(1654/1080), puis
 *    tout est réduit EN UNE FOIS vers la taille demandée — c'est cette unique
 *    réduction qui donne l'anticrénelage du canvas.
 *  - le texte est posé caractère par caractère avec les avances EXACTES des
 *    polices (tables générées des .ttf, voir etiquette_fpl70_avances.php),
 *    letterSpacing compris — les mêmes chiffres que measureText.
 *
 * Le PDF de sortie reprend le pipeline de l'atelier à l'identique : le dessin
 * en JPEG qualité 0.94, posé seul dans une page à la taille exacte en mm
 * (etiquette70_pdf, port de fabriquerPdf). Une page non carrée reçoit le
 * carré au centre, comme dans l'atelier.
 *
 * Programmation procédurale uniquement.
 */

require_once __DIR__ . '/etiquette_fpl70_avances.php';

/** Le côté du plan de travail : celui des couches précalculées. */
define('ETQ70_BASE', 1654);
/** Le côté logique du dessin de l'atelier. */
define('ETQ70_LOGIQUE', 1080);

/** Le marine du dessin ('#03215D'). */
function etiquette70_marine($img)
{
    return imagecolorallocate($img, 3, 33, 93);
}

/** L'encre marine des titres ('#081A4D'). */
function etiquette70_encre_navy($img)
{
    return imagecolorallocate($img, 8, 26, 77);
}

// ---------------------------------------------------------------------------
// LES POLICES — chemins et avances
// ---------------------------------------------------------------------------

/**
 * @return string chemin absolu du .ttf
 */
function etiquette70_police_chemin($nom)
{
    $fichiers = [
        'anton' => 'anton-400.ttf',
        'barlow_condensed_700' => 'barlow-condensed-700.ttf',
        'barlow_500' => 'barlow-500.ttf',
    ];

    return __DIR__ . '/../fonts/etiquette70/' . $fichiers[$nom];
}

/**
 * L'avance d'UN caractère en pixels, au corps donné. L'espace fine U+2009
 * n'est pas dans les polices : le navigateur de l'atelier la prenait dans sa
 * police de secours (≈ 0,20 em) — on la rend pareil, comme une avance pure.
 *
 * @param string $nom_police clé des tables
 * @param string $car un caractère UTF-8
 * @param float $corps corps en px
 * @return float
 */
function etiquette70_avance($nom_police, $car, $corps)
{
    if ($car === "\u{2009}") {
        return 0.20 * $corps;
    }
    $tables = etiquette70_avances();
    $table = $tables[$nom_police];
    $code = function_exists('mb_ord') ? mb_ord($car, 'UTF-8') : 63;
    if (!isset($table[$code])) {
        // glyphe absent : l'avance de « ? », faute de mieux (cas théorique)
        $code = 63;
    }

    return $table[$code] * $corps;
}

/**
 * measureText : la largeur d'un texte avec letterSpacing — l'espacement suit
 * CHAQUE caractère, y compris le dernier, c'est ce que mesure le canvas.
 *
 * @return float
 */
function etiquette70_largeur_texte($nom_police, $texte, $corps, $espacement = 0.0)
{
    if ($texte === '') {
        return 0.0;
    }
    $l = 0.0;
    $n = 0;
    foreach (preg_split('//u', $texte, -1, PREG_SPLIT_NO_EMPTY) as $car) {
        $l += etiquette70_avance($nom_police, $car, $corps);
        $n++;
    }

    return $l + $espacement * $n;
}

/**
 * ajusterPolice : réduit le corps de 2 en 2 tant que le texte dépasse.
 *
 * @return float corps retenu (px logiques)
 */
function etiquette70_ajuster_corps($nom_police, $texte, $corps, $max_l, $espacement = 0.0)
{
    $taille = $corps;
    while ($taille > 14) {
        if (etiquette70_largeur_texte($nom_police, $texte, $taille, $espacement) <= $max_l) {
            break;
        }
        $taille -= 2;
    }

    return $taille;
}

/**
 * fillText : pose le texte caractère par caractère (ancre = ligne de base à
 * gauche), aux avances des tables. GD reçoit le corps en POINTS : ses tailles
 * sont converties à 96 dpi, d'où le facteur 0,75 (px × 72/96).
 *
 * @param resource|GdImage $img
 * @param float $x ligne de base, bord gauche (px du plan de travail)
 * @param float $y ligne de base (px du plan de travail)
 * @param float $corps corps en px du plan de travail
 * @param string $align 'left' | 'center' | 'right'
 */
function etiquette70_texte($img, $x, $y, $texte, $nom_police, $corps, $couleur, $espacement = 0.0, $align = 'left')
{
    if ($texte === '' || $texte === null) {
        return;
    }
    if ($align === 'right') {
        $x -= etiquette70_largeur_texte($nom_police, $texte, $corps, $espacement) - $espacement;
    } elseif ($align === 'center') {
        $x -= (etiquette70_largeur_texte($nom_police, $texte, $corps, $espacement) - $espacement) / 2.0;
    }
    $chemin = etiquette70_police_chemin($nom_police);
    $pt = $corps * 0.75;
    $cx = (float) $x;
    foreach (preg_split('//u', $texte, -1, PREG_SPLIT_NO_EMPTY) as $car) {
        if ($car !== "\u{2009}") {
            imagettftext($img, $pt, 0, (int) round($cx), (int) round($y), $couleur, $chemin, $car);
        }
        $cx += etiquette70_avance($nom_police, $car, $corps) + $espacement;
    }
}

// ---------------------------------------------------------------------------
// LA PHOTO — détourage (repris du PDF du 24/08, en GD directement)
// ---------------------------------------------------------------------------

/**
 * Charge une photo depuis le disque en GD truecolor+alpha, ou null.
 *
 * @return resource|GdImage|null
 */
function etiquette70_photo_charger($chemin)
{
    if ($chemin === null || !is_file($chemin)) {
        return null;
    }
    $taille = @getimagesize($chemin);
    if (!$taille || $taille[0] < 4 || $taille[1] < 4) {
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
    imagesavealpha($src, true);

    return $src;
}

/**
 * DÉTOURER LA PHOTO — le dessin pose la pièce à même le fond.
 *
 * DEPUIS LE 03/09 : on essaie D'ABORD le détourage COULEUR porté de l'atelier
 * (includes/fpl_detourage.php) — bien meilleur (fond dominant, contour, bords
 * doux, garde-fou), et mis en cache. S'il décline (garde-fou : il aurait mangé
 * la pièce, cas des fonds chargés), on retombe sur l'ancienne méthode (rogner
 * les bords unis + blanc→transparent si coins blancs).
 *
 * @return array{img: resource|GdImage, detouree: bool}|null
 */
function etiquette70_photo_detouree($chemin)
{
    // 1) l'algorithme couleur de l'atelier (le meilleur), avec cache
    if (is_file(__DIR__ . '/fpl_detourage.php')) {
        require_once __DIR__ . '/fpl_detourage.php';
        if (function_exists('fpl_detourage_fichier')) {
            $bon = fpl_detourage_fichier($chemin);
            if ($bon !== null) {
                return $bon;
            }
        }
    }

    // 2) repli : l'ancienne méthode (rogner unies + blanc→transparent)
    $src = etiquette70_photo_charger($chemin);
    if ($src === null) {
        return null;
    }

    $lsrc = imagesx($src);
    $hsrc = imagesy($src);

    $unie = function ($fixe, $horizontale) use ($src, $lsrc, $hsrc) {
        $min = 255;
        $max = 0;
        $long = $horizontale ? $lsrc : $hsrc;
        $pas = max(1, (int) ($long / 40));
        for ($i = 0; $i < $long; $i += $pas) {
            $c = $horizontale ? imagecolorat($src, $i, $fixe) : imagecolorat($src, $fixe, $i);
            $lum = 0.299 * (($c >> 16) & 255) + 0.587 * (($c >> 8) & 255) + 0.114 * ($c & 255);
            $min = min($min, $lum);
            $max = max($max, $lum);
        }

        return ($max - $min) < 14;
    };

    $haut = 0;
    while ($haut < $hsrc / 2 && $unie($haut, true)) { $haut++; }
    $bas = $hsrc - 1;
    while ($bas > $hsrc / 2 && $unie($bas, true)) { $bas--; }
    $gauche = 0;
    while ($gauche < $lsrc / 2 && $unie($gauche, false)) { $gauche++; }
    $droite = $lsrc - 1;
    while ($droite > $lsrc / 2 && $unie($droite, false)) { $droite--; }

    $marge = max(4, (int) round(min($lsrc, $hsrc) * 0.012));
    $gauche = min($gauche + $marge, (int) ($lsrc / 2) - 1);
    $droite = max($droite - $marge, (int) ($lsrc / 2) + 1);
    $haut = min($haut + $marge, (int) ($hsrc / 2) - 1);
    $bas = max($bas - $marge, (int) ($hsrc / 2) + 1);

    $l = $droite - $gauche + 1;
    $h = $bas - $haut + 1;
    if ($l < 8 || $h < 8) {
        // image trop petite une fois rognée : on la rend telle quelle
        return ['img' => $src, 'detouree' => false];
    }

    $coins_blancs = true;
    foreach ([[$gauche, $haut], [$droite, $haut], [$gauche, $bas], [$droite, $bas]] as $coin) {
        $c = imagecolorat($src, $coin[0], $coin[1]);
        $lum = 0.299 * (($c >> 16) & 255) + 0.587 * (($c >> 8) & 255) + 0.114 * ($c & 255);
        if ($lum < 235) {
            $coins_blancs = false;
            break;
        }
    }
    if (!$coins_blancs) {
        return ['img' => $src, 'detouree' => false];
    }

    $out = imagecreatetruecolor($l, $h);
    imagealphablending($out, false);
    imagesavealpha($out, true);
    imagefilledrectangle($out, 0, 0, $l, $h, imagecolorallocatealpha($out, 255, 255, 255, 127));
    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $l; $x++) {
            $c = imagecolorat($src, $gauche + $x, $haut + $y);
            $r = ($c >> 16) & 255;
            $v = ($c >> 8) & 255;
            $b = $c & 255;
            $lum = 0.299 * $r + 0.587 * $v + 0.114 * $b;
            if ($lum >= 246) {
                continue;
            }
            $alpha = $lum > 232 ? (int) round(127 * ($lum - 232) / 14) : 0;
            imagesetpixel($out, $x, $y, imagecolorallocatealpha($out, $r, $v, $b, $alpha));
        }
    }
    imagedestroy($src);

    return ['img' => $out, 'detouree' => true];
}

/**
 * L'ombre portée d'une photo détourée : la silhouette (alpha) teintée marine à
 * 20 %, floutée. Calculée au quart pour rester légère, comme le canvas le fait
 * en une passe GPU.
 *
 * @param resource|GdImage $img la cible (plan de travail)
 * @param resource|GdImage $photo la photo détourée
 * @param int $dx, $dy, $dw, $dh la boîte de destination de la photo
 */
function etiquette70_ombre_photo($img, $photo, $dx, $dy, $dw, $dh)
{
    $q = 4;
    $ol = max(2, (int) ceil($dw / $q));
    $oh = max(2, (int) ceil($dh / $q));
    $marge = 14; // de la place pour le flou
    $sil = imagecreatetruecolor($ol + 2 * $marge, $oh + 2 * $marge);
    imagealphablending($sil, false);
    imagesavealpha($sil, true);
    imagefilledrectangle($sil, 0, 0, imagesx($sil), imagesy($sil), imagecolorallocatealpha($sil, 16, 49, 111, 127));
    imagealphablending($sil, true);
    imagecopyresampled($sil, $photo, $marge, $marge, 0, 0, $ol, $oh, imagesx($photo), imagesy($photo));

    // silhouette : chaque pixel devient du marine, alpha = alpha × 0,20
    imagealphablending($sil, false);
    for ($y = 0; $y < imagesy($sil); $y++) {
        for ($x = 0; $x < imagesx($sil); $x++) {
            $a = (imagecolorat($sil, $x, $y) >> 24) & 127;
            $na = 127 - (int) round((127 - $a) * 0.20);
            imagesetpixel($sil, $x, $y, imagecolorallocatealpha($sil, 16, 49, 111, $na));
        }
    }
    // flou : itérations du noyau gaussien 3×3 (σ ≈ 0,85 chacune)
    for ($i = 0; $i < 22; $i++) {
        imagefilter($sil, IMG_FILTER_GAUSSIAN_BLUR);
    }

    imagealphablending($img, true);
    $ody = (int) round(12 * ETQ70_BASE / ETQ70_LOGIQUE); // shadowOffsetY 12
    imagecopyresampled(
        $img, $sil,
        $dx - $marge * $q, $dy - $marge * $q + $ody,
        0, 0,
        ($ol + 2 * $marge) * $q, ($oh + 2 * $marge) * $q,
        imagesx($sil), imagesy($sil)
    );
    imagedestroy($sil);
}

// ---------------------------------------------------------------------------
// LE QR — la matrice depuis chillerlan (indépendant de la version : on relit
// les pixels d'un rendu à l'échelle 1)
// ---------------------------------------------------------------------------

/**
 * @param string $contenu
 * @return array{n: int, cases: array<int, array<int, bool>>}|null
 */
function etiquette70_qr_matrice($contenu)
{
    if ($contenu === '' || !is_file(__DIR__ . '/../vendor/autoload.php')) {
        return null;
    }
    require_once __DIR__ . '/../vendor/autoload.php';
    try {
        $qro = new \chillerlan\QRCode\QROptions([
            'outputType' => \chillerlan\QRCode\Output\QROutputInterface::GDIMAGE_PNG,
            'eccLevel' => \chillerlan\QRCode\Common\EccLevel::M,
            'scale' => 1,
            'addQuietzone' => false,
            'outputBase64' => false,
        ]);
        $png = (new \chillerlan\QRCode\QRCode($qro))->render($contenu);
        $im = @imagecreatefromstring((string) $png);
        if (!$im) {
            return null;
        }
        $n = imagesx($im);
        $cases = [];
        for ($r = 0; $r < $n; $r++) {
            $ligne = [];
            for ($c = 0; $c < $n; $c++) {
                $ligne[$c] = ((imagecolorat($im, $c, $r) >> 8) & 255) < 128;
            }
            $cases[$r] = $ligne;
        }
        imagedestroy($im);

        return ['n' => $n, 'cases' => $cases];
    } catch (Throwable $e) {
        return null;
    }
}

// ---------------------------------------------------------------------------
// LE CODE-BARRES EAN-13 — port exact de l'atelier
// ---------------------------------------------------------------------------

/**
 * @return array{chiffres: string, barres: string} 13 chiffres + 95 modules
 */
function etiquette70_ean13($douze)
{
    $L = ['0' => '0001101', '1' => '0011001', '2' => '0010011', '3' => '0111101', '4' => '0100011', '5' => '0110001', '6' => '0101111', '7' => '0111011', '8' => '0110111', '9' => '0001011'];
    $G = ['0' => '0100111', '1' => '0110011', '2' => '0011011', '3' => '0100001', '4' => '0011101', '5' => '0111001', '6' => '0000101', '7' => '0010001', '8' => '0001001', '9' => '0010111'];
    $R = ['0' => '1110010', '1' => '1100110', '2' => '1101100', '3' => '1000010', '4' => '1011100', '5' => '1001110', '6' => '1010000', '7' => '1000100', '8' => '1001000', '9' => '1110100'];
    $PAR = ['0' => 'LLLLLL', '1' => 'LLGLGG', '2' => 'LLGGLG', '3' => 'LLGGGL', '4' => 'LGLLGG', '5' => 'LGGLLG', '6' => 'LGGGLL', '7' => 'LGLGLG', '8' => 'LGLGGL', '9' => 'LGGLGL'];

    $d = preg_replace('/\D/', '', (string) $douze);
    $d = substr($d . '000000000000', 0, 12);
    $somme = 0;
    for ($i = 0; $i < 12; $i++) {
        $somme += ((int) $d[$i]) * ($i % 2 === 0 ? 1 : 3);
    }
    $complet = $d . (string) ((10 - $somme % 10) % 10);
    $barres = '101';
    $par = $PAR[$complet[0]];
    for ($i = 1; $i <= 6; $i++) {
        $barres .= ($par[$i - 1] === 'L' ? $L : $G)[$complet[$i]];
    }
    $barres .= '01010';
    for ($i = 7; $i <= 12; $i++) {
        $barres .= $R[$complet[$i]];
    }
    $barres .= '101';

    return ['chiffres' => $complet, 'barres' => $barres];
}

/**
 * LES 12 CHIFFRES DE LA MAISON : préfixe 200 (la plage 20–29 est réservée par
 * GS1 à l'usage interne) + les 9 chiffres de l'identifiant FPL. Le scan d'une
 * étiquette EAN retombe ainsi exactement sur la pièce (voir
 * produit_emplacement_extraire_fpl_du_scan).
 *
 * @return string 12 chiffres
 */
function etiquette70_ean12_pour_identifiant($identifiant, $produit_id = 0)
{
    $id = strtoupper(trim((string) $identifiant));
    if (preg_match('/^FPL(\d{6}|\d{9})$/', $id, $m)) {
        return '200' . str_pad($m[1], 9, '0', STR_PAD_LEFT);
    }

    return '200' . str_pad((string) max(0, (int) $produit_id), 9, '0', STR_PAD_LEFT);
}

// ---------------------------------------------------------------------------
// LES DONNÉES D'UNE PIÈCE → LES CHAMPS DU DESSIN
// ---------------------------------------------------------------------------

/**
 * Prépare tout ce que le dessin affiche, avec les replis d'une base où le nom
 * wolof et l'OEM ne sont pas encore remplis partout :
 *  - grand titre = nom wolof, sinon le nom français prend sa place ;
 *  - sous-titre = nom français (sauf s'il est déjà le grand titre) ;
 *  - référence FPL affichée en groupes de 3 (FPL 907 008 429) ;
 *  - OEM affiché tel quel, « — » si vide (le geste de l'atelier) ;
 *  - QR = la page stock-info (le même contenu que l'étiquette d'avant) ;
 *  - EAN = 200 + les 9 chiffres de l'identifiant.
 *
 * @param array<string, mixed> $produit
 * @return array<string, mixed>
 */
function etiquette70_donnees_pour_produit(array $produit)
{
    require_once __DIR__ . '/produit_emplacement_entrepot.php';

    $produit_id = (int) ($produit['id'] ?? 0);
    $wolof = trim((string) ($produit['nom_wolof'] ?? ''));
    $francais = trim((string) ($produit['nom'] ?? ''));
    if ($wolof === '') {
        $wolof = $francais;
        $francais = '';
    }

    $identifiant = strtoupper(trim((string) ($produit['identifiant_interne'] ?? '')));
    $ref_affichee = $identifiant;
    if (preg_match('/^FPL(\d{9})$/', $identifiant, $m)) {
        $ref_affichee = 'FPL ' . substr($m[1], 0, 3) . ' ' . substr($m[1], 3, 3) . ' ' . substr($m[1], 6, 3);
    } elseif (preg_match('/^FPL(\d{6})$/', $identifiant, $m)) {
        $ref_affichee = 'FPL ' . substr($m[1], 0, 3) . ' ' . substr($m[1], 3, 3);
    }

    /* LA PHOTO DE L'ÉTIQUETTE = LA PHOTO PRINCIPALE D'ABORD (01/09). L'ancien
       ordre préférait la « photo étiquette dédiée » — mesure faite : 121
       pièces en portent une DIFFÉRENTE de la principale, toutes d'anciennes
       images figées (celle de la pièce #2 est même une image parasite),
       pendant que l'équipe entretient la photo principale. L'étiquette
       montre donc la photo vivante ; la dédiée puis la galerie restent des
       replis quand la principale manque ou que son fichier est absent. */
    $photo_chemin = null;
    $imgs = json_decode((string) ($produit['images'] ?? ''), true);
    $candidates = [
        (string) ($produit['image_principale'] ?? ''),
        (string) ($produit['image_etiquette_fpl'] ?? ''),
        is_array($imgs) && !empty($imgs[0]) ? (string) $imgs[0] : '',
    ];
    foreach ($candidates as $photo_rel) {
        $photo_rel = trim($photo_rel);
        if ($photo_rel === '') {
            continue;
        }
        $chemin = __DIR__ . '/../upload/' . ltrim($photo_rel, '/');
        if (is_file($chemin)) {
            $photo_chemin = $chemin;
            break;
        }
    }

    return [
        'nom_wolof' => $wolof,
        'nom_francais' => $francais,
        'ref_affichee' => $ref_affichee,
        'oem' => trim((string) ($produit['reference_oem'] ?? '')),
        'qr_texte' => produit_emplacement_stock_info_url($produit_id, $produit),
        'ean12' => etiquette70_ean12_pour_identifiant($identifiant, $produit_id),
        'photo_chemin' => $photo_chemin,
    ];
}

// ---------------------------------------------------------------------------
// LE DESSIN
// ---------------------------------------------------------------------------

/**
 * Dessine l'étiquette et rend l'image GD au côté demandé.
 *
 * @param array<string, mixed> $donnees voir etiquette70_donnees_pour_produit
 * @param int $cote côté de sortie en px (l'étiquette est carrée)
 * @return resource|GdImage
 */
function etiquette70_rendu(array $donnees, $cote)
{
    $B = ETQ70_BASE;
    $s = $B / ETQ70_LOGIQUE;

    $img = imagecreatefromjpeg(__DIR__ . '/../image/etiquette70/fond-1654.jpg');
    imagealphablending($img, true);

    // --- la photo (par-dessus le camion, sous tout le reste) ---
    if (!empty($donnees['photo_chemin'])) {
        $ph = etiquette70_photo_detouree($donnees['photo_chemin']);
        if ($ph !== null) {
            /* L'atelier posait la pièce dans une boîte de 650 ; la direction
               l'a réduite deux fois sur les vraies photos (01/09) : 520 puis
               420, même centre (755, 647) — la pièce reste à sa place, en
               nettement plus discret. Écart assumé avec le dessin de
               l'atelier. */
            $boite = [
                'x' => (int) round(545 * $s), 'y' => (int) round(437 * $s),
                'w' => (int) round(420 * $s), 'h' => (int) round(420 * $s),
            ];
            $pl = imagesx($ph['img']);
            $phh = imagesy($ph['img']);
            $e = min($boite['w'] / $pl, $boite['h'] / $phh);
            $w = (int) round($pl * $e);
            $h = (int) round($phh * $e);
            $dx = $boite['x'] + (int) round(($boite['w'] - $w) / 2);
            $dy = $boite['y'] + (int) round(($boite['h'] - $h) / 2);
            if ($ph['detouree']) {
                etiquette70_ombre_photo($img, $ph['img'], $dx, $dy, $w, $h);
            }
            imagecopyresampled($img, $ph['img'], $dx, $dy, 0, 0, $w, $h, $pl, $phh);
            imagedestroy($ph['img']);
        }
    }

    // --- la couche statique du dessus (panneau, slogan, carte, bandes…) ---
    $dessus = imagecreatefrompng(__DIR__ . '/../image/etiquette70/dessus-1654.png');
    imagecopy($img, $dessus, 0, 0, 0, 0, $B, $B);
    imagedestroy($dessus);

    $marine = etiquette70_marine($img);
    $encre = etiquette70_encre_navy($img);
    $noir_titre = imagecolorallocate($img, 16, 17, 20);
    $noir_valeur = imagecolorallocate($img, 12, 13, 16);
    $noir_barres = imagecolorallocate($img, 16, 19, 25);

    // --- les titres ---
    $wolof = mb_strtoupper(trim((string) $donnees['nom_wolof']), 'UTF-8');
    $francais = mb_strtoupper(trim((string) $donnees['nom_francais']), 'UTF-8');
    if ($wolof !== '') {
        $corps = etiquette70_ajuster_corps('anton', $wolof, 68, 520, 5.3);
        etiquette70_texte($img, 285 * $s, 404 * $s, $wolof, 'anton', $corps * $s, $encre, 5.3 * $s);
    }
    if ($francais !== '') {
        $corps = etiquette70_ajuster_corps('barlow_condensed_700', $francais, 32, 560, 0.9);
        etiquette70_texte($img, 285 * $s, 443 * $s, $francais, 'barlow_condensed_700', $corps * $s, $noir_titre, 0.9 * $s);
    }

    // --- la carte des références ---
    etiquette70_texte($img, 183 * $s, 699 * $s, 'RÉFÉRENCE FPL', 'barlow_condensed_700', 23 * $s, $encre, 0.4 * $s);
    $vr = trim((string) $donnees['ref_affichee']);
    if ($vr === '') {
        $vr = '—';
    }
    if (strpos($vr, 'FPL ') === 0) {
        $vr = 'FPL' . "\u{2009}" . ltrim(substr($vr, 4));
    }
    $corps = etiquette70_ajuster_corps('barlow_condensed_700', $vr, 38.5, 268);
    etiquette70_texte($img, 183 * $s, 739 * $s, $vr, 'barlow_condensed_700', $corps * $s, $noir_valeur);

    etiquette70_texte($img, 183 * $s, 796 * $s, 'RÉFÉRENCE OEM', 'barlow_condensed_700', 23 * $s, $encre, 0.4 * $s);
    $vo = mb_strtoupper(trim((string) $donnees['oem']), 'UTF-8');
    if ($vo === '') {
        $vo = '—';
    }
    $corps = etiquette70_ajuster_corps('barlow_condensed_700', $vo, 38.5, 268);
    etiquette70_texte($img, 183 * $s, 838 * $s, $vo, 'barlow_condensed_700', $corps * $s, $noir_valeur);

    // --- le QR dans son cadre (déjà dessiné dans la couche statique) ---
    $mq = etiquette70_qr_matrice((string) ($donnees['qr_texte'] ?? ''));
    if ($mq !== null) {
        $qx = 381 * $s;
        $qy = 919 * $s;
        $qt = 102 * $s;
        $n = $mq['n'];
        $cel = $qt / $n;
        for ($r = 0; $r < $n; $r++) {
            for ($c = 0; $c < $n; $c++) {
                if ($mq['cases'][$r][$c]) {
                    imagefilledrectangle(
                        $img,
                        (int) round($qx + $c * $cel), (int) round($qy + $r * $cel),
                        (int) round($qx + $c * $cel) + (int) ceil($cel) - 1,
                        (int) round($qy + $r * $cel) + (int) ceil($cel) - 1,
                        $noir_barres
                    );
                }
            }
        }
    }

    // --- le code-barres EAN ---
    $cb = etiquette70_ean13((string) $donnees['ean12']);
    $total = strlen($cb['barres']);
    $mod = 267.0 * $s / $total;
    $bx = 737 * $s;
    $by = 920 * $s;
    $bh = 72 * $s;
    $ib = 0;
    while ($ib < $total) {
        if ($cb['barres'][$ib] === '1') {
            $jb = $ib;
            while ($jb < $total && $cb['barres'][$jb] === '1') {
                $jb++;
            }
            $garde = ($ib < 3 || $jb > $total - 3 || ($ib >= 45 && $ib < 50));
            // fillRect x..x+w EXCLUSIF du pixel d'arrivée, GD est inclusif : -1
            imagefilledrectangle(
                $img,
                (int) round($bx + $ib * $mod - 0.35 * $s), (int) round($by),
                (int) round($bx + $ib * $mod - 0.35 * $s + ($jb - $ib) * $mod + 0.7 * $s) - 1,
                (int) round($by + $bh + ($garde ? 19 * $s : 0)) - 1,
                $noir_barres
            );
            $ib = $jb;
        } else {
            $ib++;
        }
    }
    etiquette70_texte($img, $bx - 7 * $s, 1030 * $s, substr($cb['chiffres'], 0, 1), 'barlow_500', 27 * $s, $noir_barres, 0.0, 'right');
    etiquette70_texte($img, $bx + $mod * 24, 1030 * $s, substr($cb['chiffres'], 1, 6), 'barlow_500', 27 * $s, $noir_barres, 0.0, 'center');
    etiquette70_texte($img, $bx + $mod * 71, 1030 * $s, substr($cb['chiffres'], 7), 'barlow_500', 27 * $s, $noir_barres, 0.0, 'center');

    // --- LA réduction unique vers la taille demandée ---
    $cote = max(64, min(2400, (int) $cote));
    if ($cote === $B) {
        return $img;
    }
    $out = imagecreatetruecolor($cote, $cote);
    imagecopyresampled($out, $img, 0, 0, 0, 0, $cote, $cote, $B, $B);
    imagedestroy($img);

    return $out;
}

// ---------------------------------------------------------------------------
// LE PDF — port de fabriquerPdf : la page aux mm exacts, le dessin JPEG posé
// en carré centré (une page non carrée garde ses marges de centrage)
// ---------------------------------------------------------------------------

/**
 * @param string $jpeg octets JPEG du dessin (carré, $wpx × $hpx)
 * @param int $wpx largeur pixels de l'image
 * @param int $hpx hauteur pixels de l'image
 * @param float $lmm largeur page en mm
 * @param float $hmm hauteur page en mm
 * @return string le fichier PDF
 */
function etiquette70_pdf($jpeg, $wpx, $hpx, $lmm, $hmm)
{
    $MM = 72 / 25.4;
    $L = round($lmm * $MM, 2);
    $H = round($hmm * $MM, 2);
    $cote = min($L, $H);
    $dx = round(($L - $cote) / 2, 2);
    $dy = round(($H - $cote) / 2, 2);

    $flux = 'q ' . $cote . ' 0 0 ' . $cote . ' ' . $dx . ' ' . $dy . " cm /Im0 Do Q\n";
    $objets = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . $L . ' ' . $H . ']'
            . ' /Resources << /XObject << /Im0 5 0 R >> >> /Contents 4 0 R >>',
    ];

    $pdf = '';
    $offs = [];
    $pdf .= "%PDF-1.4\n";
    foreach ($objets as $i => $o) {
        $offs[$i] = strlen($pdf);
        $pdf .= ($i + 1) . " 0 obj\n" . $o . "\nendobj\n";
    }
    $offs[3] = strlen($pdf);
    $pdf .= "4 0 obj\n<< /Length " . strlen($flux) . " >>\nstream\n" . $flux . "endstream\nendobj\n";
    $offs[4] = strlen($pdf);
    $pdf .= "5 0 obj\n<< /Type /XObject /Subtype /Image /Width " . $wpx . ' /Height ' . $hpx
        . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen($jpeg) . " >>\nstream\n";
    $pdf .= $jpeg;
    $pdf .= "\nendstream\nendobj\n";
    $xref = strlen($pdf);
    $table = "xref\n0 6\n0000000000 65535 f \n";
    for ($i = 0; $i < 5; $i++) {
        $table .= str_pad((string) $offs[$i], 10, '0', STR_PAD_LEFT) . " 00000 n \n";
    }
    $pdf .= $table;
    $pdf .= "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";

    return $pdf;
}
