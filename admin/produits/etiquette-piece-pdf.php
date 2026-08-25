<?php
/**
 * L'ÉTIQUETTE DE PIÈCE EN PDF — le fichier qu'on archive, qu'on envoie, ou
 * qu'on confie à un imprimeur extérieur. Le PDF sort À LA TAILLE EXACTE de
 * l'étiquette (une 70 × 70 fait une page de 70 × 70 mm, sans marge) :
 * imprimé « taille réelle », il colle au support au millimètre.
 * Programmation procédurale uniquement
 *
 * PORTAGE de fpl_natif/admin/etiquette-piece-pdf.php (24/08/2026) — LE
 * DESSIN DU 14/08 : le NOM WOLOF devient le titre, le français passe en
 * traduction. Toutes les cotes viennent du relevé au pixel de la maquette
 * (1254 × 1254 px pour 70 × 70 mm), en POURCENTAGE du modèle 70 × 70,
 * multipliées par les échelles du format demandé.
 *
 * Ce qui vient de CE dépôt : le QR (la page stock-info du produit, comme
 * l'étiquette écran de la fiche), le code-barres (le PNG déjà généré sous
 * upload/barcodes/, même contenu que partout ailleurs), les photos
 * (image_etiquette_fpl puis la principale, sous upload/), et les gardes
 * (require_access + compte restreint écarté).
 *
 * dompdf ne connaît ni flexbox, ni grid, ni variables CSS : chaque élément
 * est un bloc absolu dont le millimètre est calculé en PHP, et les formes
 * (bandeau en biseau, trame de points, pastilles) sont des SVG en data:URI.
 */

require_once __DIR__ . '/../../includes/admin_pdf_response.php';
admin_pdf_request_begin();

session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../includes/admin_permissions.php';
require_once __DIR__ . '/../../includes/fpl_texte.php';
require_once __DIR__ . '/../../includes/fpl_ui.php';
require_once __DIR__ . '/../../models/model_produits.php';
require_once __DIR__ . '/../../models/model_produit_etiquette_parametres.php';
require_once __DIR__ . '/../../models/model_entrepot_hierarchie_libre.php';
require_once __DIR__ . '/../../includes/produit_emplacement_entrepot.php';
require_once __DIR__ . '/../../includes/barcode_fpl.php';
require_once __DIR__ . '/../../vendor/autoload.php';

if (admin_is_restricted_admin_account()) {
    header('Location: index.php');
    exit;
}

/** Le marine de la maquette — plus sombre que le bleu de l'écran, c'est voulu. */
define('ETQ_MARINE', '#001854');
/** Le fond n'est pas blanc pur — la maquette est à #FAFAFA. */
define('ETQ_FOND', '#FAFAFA');
/** Les textes noirs de la maquette. */
define('ETQ_NOIR', '#111111');

/** Une image du disque, encodée pour voyager dans le PDF (null si absente). */
function etiquette_pdf_image_integree($chemin)
{
    if ($chemin === null || !is_file($chemin)) {
        return null;
    }

    switch (strtolower(pathinfo($chemin, PATHINFO_EXTENSION))) {
        case 'png':
            $type = 'image/png';
            break;
        case 'webp':
            $type = 'image/webp';
            break;
        default:
            $type = 'image/jpeg';
    }

    return 'data:' . $type . ';base64,' . base64_encode((string) file_get_contents($chemin));
}

/**
 * DÉTOURER LA PHOTO — la maquette pose la pièce à même le fond. Deux temps :
 * rogner les bandes unies du pourtour, puis rendre le fond blanc transparent
 * — SEULEMENT si les quatre coins de l'image rognée sont blancs.
 */
function etiquette_pdf_photo_detouree($chemin)
{
    if (!function_exists('imagecreatetruecolor')) {
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
        imagedestroy($src);

        return null;
    }

    foreach ([[$gauche, $haut], [$droite, $haut],
              [$gauche, $bas], [$droite, $bas]] as $coin) {
        $c = imagecolorat($src, $coin[0], $coin[1]);
        $lum = 0.299 * (($c >> 16) & 255) + 0.587 * (($c >> 8) & 255) + 0.114 * ($c & 255);
        if ($lum < 235) {
            imagedestroy($src);

            return null;
        }
    }

    $out = imagecreatetruecolor($l, $h);
    imagealphablending($out, false);
    imagesavealpha($out, true);
    imagefilledrectangle($out, 0, 0, $l, $h, imagecolorallocatealpha($out, 255, 255, 255, 127));
    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $l; $x++) {
            $c = imagecolorat($src, $gauche + $x, $haut + $y);
            $r = ($c >> 16) & 255; $v = ($c >> 8) & 255; $b = $c & 255;
            $lum = 0.299 * $r + 0.587 * $v + 0.114 * $b;
            if ($lum >= 246) {
                continue;
            }
            $alpha = $lum > 232 ? (int) round(127 * ($lum - 232) / 14) : 0;
            imagesetpixel($out, $x, $y, imagecolorallocatealpha($out, $r, $v, $b, $alpha));
        }
    }
    imagedestroy($src);

    ob_start();
    imagepng($out);
    $png = (string) ob_get_clean();
    imagedestroy($out);

    return [
        'data' => 'data:image/png;base64,' . base64_encode($png),
        'ratio' => $h / $l,
    ];
}

/** Un SVG passé en data:URI — dompdf le rend en vectoriel. */
function etiquette_pdf_svg($contenu)
{
    return 'data:image/svg+xml;base64,' . base64_encode($contenu);
}

/** Le bandeau du coin — un quadrilatère en biseau doublé d'un filet. */
function etiquette_pdf_bandeau($marine)
{
    return etiquette_pdf_svg(
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none">'
        . '<path d="M0,0 L30.14,0 L21.42,31.24 Q20.48,34.64 17.08,35.83 L0,41.85 Z"'
        . ' fill="' . $marine . '"/>'
        . '<path d="M31.06,0 L22.34,31.24 Q21.40,35.56 17.60,36.75 L0,42.77" fill="none"'
        . ' stroke="' . $marine . '" stroke-width="0.36"/>'
        . '</svg>'
    );
}

/** La trame de points du pied — les disques maigrissent vers la droite et le haut. */
function etiquette_pdf_trame($largeur, $hauteur)
{
    $pas = 1.20;
    $rmax = 0.63;
    $points = '';
    for ($x = 0.0; $x < $largeur; $x += $pas) {
        for ($y = 0.2; $y < $hauteur; $y += $pas) {
            $portee = $largeur * (0.55 + 0.80 * ($y / $hauteur));
            $part = 1 - $x / $portee;
            if ($part <= 0.04) {
                continue;
            }
            $r = round($rmax * pow($part, 0.80) * (0.34 + 0.66 * ($y / $hauteur)), 3);
            $points .= '<circle cx="' . round($x, 2) . '" cy="' . round($y, 2) . '" r="' . $r . '" fill="#8C8C8C"/>';
        }
    }

    return etiquette_pdf_svg(
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $largeur . ' ' . $hauteur . '">'
        . $points . '</svg>'
    );
}

/** Les pastilles du bloc de références — étiquette de prix et engrenage. */
function etiquette_pdf_pastille($quoi, $marine)
{
    $glyphe = '';
    if ($quoi === 'tag') {
        $glyphe = '<path d="M52 20 L80 20 L80 48 L46 82 L18 54 Z" fill="#FFFFFF"/>'
            . '<circle cx="67" cy="33" r="6.5" fill="' . $marine . '"/>';
    } elseif ($quoi === 'engrenage') {
        $dents = '';
        for ($i = 0; $i < 8; $i++) {
            $a = $i * 45;
            $dents .= '<rect x="43" y="8" width="14" height="22" rx="3" fill="#FFFFFF"'
                . ' transform="rotate(' . $a . ' 50 50)"/>';
        }
        $glyphe = $dents . '<circle cx="50" cy="50" r="30" fill="#FFFFFF"/>'
            . '<circle cx="50" cy="50" r="12" fill="' . $marine . '"/>';
    }

    return etiquette_pdf_svg(
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">'
        . '<rect x="0" y="0" width="100" height="100" rx="13" fill="' . $marine . '"/>'
        . $glyphe . '</svg>'
    );
}

$produit = get_produit_by_id(isset($_GET['id']) ? (int) $_GET['id'] : 0);
if ($produit === false) {
    $_SESSION['success_message'] = 'Cette pièce n\'existe pas.';
    header('Location: etiquettes.php');
    exit;
}
$produit_id = (int) $produit['id'];

// Le format demandé, sinon celui dont les mm sont ceux du réglage, sinon le premier.
$format = !empty($_GET['format']) ? fpl_etiquette_format_get((int) $_GET['format'], 'piece') : false;
if ($format === false) {
    // La liste des formats ne se lit qu'UNE fois : elle sert au repérage par mm
    // puis au repli « premier format ».
    $formats_pieces = fpl_etiquette_formats_pieces();
    $reglage = fpl_etiquette_dims();
    foreach ($formats_pieces as $fx) {
        if (abs((float) $fx['largeur_mm'] - (float) $reglage['largeur_mm']) < 0.01
            && abs((float) $fx['hauteur_mm'] - (float) $reglage['hauteur_mm']) < 0.01) {
            $format = $fx;
            break;
        }
    }
    if ($format === false) {
        $format = $formats_pieces[0] ?? false;
    }
}
if ($format === false) {
    // Sans table de formats : la taille du réglage fait une « format » de fortune.
    $reglage = fpl_etiquette_dims();
    $format = ['id' => 0, 'nom' => fpl_etiquette_dims_label_short($reglage),
        'largeur_mm' => $reglage['largeur_mm'], 'hauteur_mm' => $reglage['hauteur_mm']];
}

// Le moteur est créé avant le dessin : ses métriques MESURENT les textes.
$dompdf = new \Dompdf\Dompdf([
    'isRemoteEnabled' => false,
    'chroot' => [realpath(__DIR__ . '/../..')],
]);

$polices = [
    ['FPL Script', 'normal', 'normal', 'script.ttf'],
    ['FPL Script', 'italic', 'normal', 'script.ttf'],
    ['FPL Etroite', 'normal', 'normal', 'condense.ttf'],
    ['FPL Etroite', 'normal', 'bold', 'condense-gras.ttf'],
];
foreach ($polices as $police) {
    $fichier = __DIR__ . '/../../fonts/' . $police[3];
    if (is_file($fichier)) {
        $dompdf->getFontMetrics()->registerFont(
            ['family' => $police[0], 'style' => $police[1], 'weight' => $police[2]], $fichier);
    }
}
$a_etroite_dispo = is_file(__DIR__ . '/../../fonts/condense-gras.ttf');

$corps_pour_tenir = function ($texte, $famille, $graisse, $largeur_mm, $corps_max_pt)
    use ($dompdf) {
    $metriques = $dompdf->getFontMetrics();
    $police = $metriques->getFont($famille, $graisse);
    if ($police === null) {
        return $corps_max_pt;
    }
    $largeur_a_100 = $metriques->getTextWidth($texte, $police, 100.0);
    if ($largeur_a_100 <= 0) {
        return $corps_max_pt;
    }

    return min($corps_max_pt, 100.0 * ($largeur_mm * 2.834645) / $largeur_a_100);
};

// ── LES TROIS ÉCHELLES (modèle 70 × 70) ──────────────────────────────
$L = (float) $format['largeur_mm'];
$H = (float) $format['hauteur_mm'];
$uw = $L / 70;
$uv = $H / 70;
$u = min($L, $H) / 70;

$px = function ($pourcent) use ($uw) {
    return round($pourcent * 0.7 * $uw, 2);
};
$py = function ($pourcent) use ($uv) {
    return round($pourcent * 0.7 * $uv, 2);
};
$pt_texte = function ($pourcent_casse) use ($u) {
    return round($pourcent_casse * 0.7 * $u * 2.834645 / 0.72, 2);
};
$mm = function ($v) {
    return round($v, 2);
};

// LE QR — le même contenu que l'étiquette écran de la fiche : la page
// stock-info du produit. Le fichier déjà généré d'abord, la volée sinon.
$qr_data_uri = '';
$qr_file = __DIR__ . '/../../upload/qrcodes/produit_' . $produit_id . '.png';
if (is_file($qr_file)) {
    $qr_data_uri = 'data:image/png;base64,' . base64_encode((string) file_get_contents($qr_file));
} else {
    try {
        $qro = new \chillerlan\QRCode\QROptions([
            'outputType' => \chillerlan\QRCode\Output\QROutputInterface::GDIMAGE_PNG,
            'scale' => 8,
            'outputBase64' => true,
        ]);
        $qr_data_uri = (new \chillerlan\QRCode\QRCode($qro))
            ->render(produit_emplacement_stock_info_url($produit_id, $produit));
    } catch (Throwable $e) {
        $qr_data_uri = '';
    }
}

// LE CODE-BARRES — le PNG déjà généré (même contenu que partout : le
// composite FPL + emplacement de ce dépôt). Absent : on le fabrique.
$vals_emplacement = produit_emplacement_from_produit($produit);
$code_barres_contenu = produit_emplacement_barcode_payload((string) $produit['identifiant_interne'], $vals_emplacement);
$barcode = null;
$barcode_file = __DIR__ . '/../../upload/barcodes/produit_' . $produit_id . '.png';
if (!is_file($barcode_file) && function_exists('generer_barcode_produit_fpl')) {
    generer_barcode_produit_fpl($produit_id);
}
if (is_file($barcode_file)) {
    $barcode = 'data:image/png;base64,' . base64_encode((string) file_get_contents($barcode_file));
} else {
    try {
        $generateur = new \Picqer\Barcode\BarcodeGeneratorPNG();
        $barcode = 'data:image/png;base64,' . base64_encode(
            $generateur->getBarcode($code_barres_contenu, $generateur::TYPE_CODE_128, 2, 60));
    } catch (Throwable $e) {
        $barcode = null;
    }
}

// ── LA MATIÈRE ────────────────────────────────────────────────────────
// LE TITRE : le wolof commande ; sans lui, le français reprend sa place.
$nom_fr = fpl_texte((string) $produit['nom']);
$nom_wo = trim(fpl_texte((string) ($produit['nom_wolof'] ?? '')));
$titre = $nom_wo !== '' ? $nom_wo : $nom_fr;
$sous_titre = $nom_wo !== '' ? $nom_fr : null;

$logo = etiquette_pdf_image_integree(__DIR__ . '/../../img/etiquette/logo-fpl-etiquette.png');
$camion = etiquette_pdf_image_integree(__DIR__ . '/../../img/etiquette/camion-filigrane.png');

// La photo DÉDIÉE à l'étiquette si la pièce en a une, la principale sinon.
$photo = null;
$photo_rel = trim((string) ($produit['image_etiquette_fpl'] ?? ''));
if ($photo_rel === '') {
    $imgs = json_decode((string) ($produit['images'] ?? ''), true);
    if (is_array($imgs) && !empty($imgs[0])) {
        $photo_rel = (string) $imgs[0];
    } elseif (!empty($produit['image_principale'])) {
        $photo_rel = (string) $produit['image_principale'];
    }
}
if ($photo_rel !== '') {
    $chemin_photo = __DIR__ . '/../../upload/' . ltrim($photo_rel, '/');
    $detouree = is_file($chemin_photo) ? etiquette_pdf_photo_detouree($chemin_photo) : null;
    if ($detouree !== null) {
        $photo = $detouree;
    } else {
        $donnees_photo = etiquette_pdf_image_integree($chemin_photo);
        if ($donnees_photo !== null) {
            $taille_photo = @getimagesize($chemin_photo);
            $photo = [
                'data' => $donnees_photo,
                'ratio' => $taille_photo && $taille_photo[0] > 0 ? $taille_photo[1] / $taille_photo[0] : 1.0,
            ];
        }
    }
}

$bandeau = etiquette_pdf_bandeau(ETQ_MARINE);
$trame = etiquette_pdf_trame(20.7, 13.4);
$swash = etiquette_pdf_image_integree(__DIR__ . '/../../img/etiquette/swash-slogan.png');
$swash_solution = etiquette_pdf_image_integree(__DIR__ . '/../../img/etiquette/swash-solution.png');
$past_tag = etiquette_pdf_pastille('tag', ETQ_MARINE);
$past_gear = etiquette_pdf_pastille('engrenage', ETQ_MARINE);

$famille_titre = $a_etroite_dispo ? 'FPL Etroite' : 'Helvetica';
$laisse_titre_mm = 30.3 * 0.7 * $uw;
$titre_pt = $corps_pour_tenir(mb_strtoupper($titre), $famille_titre, 'bold',
    $laisse_titre_mm, 5.50 * 0.7 * $u * 2.834645 / 0.72);
$sous_titre_pt = $sous_titre !== null
    ? $corps_pour_tenir(mb_strtoupper($sous_titre), $famille_titre, 'bold',
        $laisse_titre_mm, 2.55 * 0.7 * $u * 2.834645 / 0.72)
    : 0;

$nom_maison_pt = $corps_pour_tenir('FOUTA POIDS LOURDS',
    $a_etroite_dispo ? 'FPL Etroite' : 'Helvetica', 'bold', 15.4 * 0.7 * $uw, 60.0);
$signature_pt = $corps_pour_tenir('The Solution', 'FPL Script', 'normal',
    15.3 * 0.7 * $uw, 60.0);

$famille_ref = $a_etroite_dispo ? 'FPL Etroite' : 'Helvetica';
$grand = 60.0;
$ref_libelle_pt = $corps_pour_tenir('RÉFÉRENCE FPL', $famille_ref, 'bold',
    12.7 * 0.79 * 0.7 * $uw, $grand);
$ref_fpl = fpl_code_afficher((string) $produit['identifiant_interne']);
$ref_oem = !empty($produit['reference_oem']) ? fpl_texte((string) $produit['reference_oem']) : '—';
$ref_fpl_pt = $corps_pour_tenir($ref_fpl, $famille_ref, 'bold',
    23.6 * 0.97 * 0.7 * $uw, $grand);
$ref_oem_pt = $corps_pour_tenir($ref_oem, $famille_ref, 'bold',
    23.6 * 0.7 * $uw, $ref_fpl_pt);

$deborde = 1.00;
$plafond_slogan = 4.6 * 0.7 * $u * 2.834645 / 0.72;
$slogan_pt = $corps_pour_tenir('Conduire avec assurance', 'FPL Script', 'normal',
    30.9 * $deborde * 0.7 * $uw, $plafond_slogan);
$slogan2_pt = $corps_pour_tenir('ndakh jombtukay you worr', 'FPL Script', 'normal',
    34.1 * $deborde * 0.7 * $uw, $plafond_slogan);

$photo_boite_l = 34.93;
$photo_boite_h = 36.84;
if ($photo !== null) {
    $ratio_boite = ($photo_boite_h * $uv) / ($photo_boite_l * $uw);
    if ($photo['ratio'] > $ratio_boite) {
        $photo['h'] = $photo_boite_h;
        $photo['l'] = $photo_boite_h * $uv / ($photo['ratio'] * $uw);
    } else {
        $photo['l'] = $photo_boite_l;
        $photo['h'] = $photo_boite_l * $uw * $photo['ratio'] / $uv;
    }
    $photo['dx'] = ($photo_boite_l - $photo['l']) / 2;
    $photo['dy'] = ($photo_boite_h - $photo['h']) / 2;
}

ob_start();
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<style>
  @page { margin: 0; }
  body {
    margin: 0; padding: 0;
    width: <?php echo $mm($L); ?>mm; height: <?php echo $mm($H); ?>mm;
    background: <?php echo ETQ_FOND; ?>;
    font-family: Helvetica, Arial, sans-serif;
    color: <?php echo ETQ_NOIR; ?>;
  }
  .abs { position: absolute; }
  .marine { color: <?php echo ETQ_MARINE; ?>; }

  .cadre {
    left: 0; top: 0;
    width: <?php echo $mm($L - 0.5 * $u); ?>mm; height: <?php echo $mm($H - 0.5 * $u); ?>mm;
    border: <?php echo $mm(0.25 * $u); ?>mm solid <?php echo ETQ_MARINE; ?>;
    border-radius: <?php echo $mm(1.7 * $u); ?>mm;
  }

  .nom-maison {
    font-family: <?php echo $a_etroite_dispo ? "'FPL Etroite', " : ''; ?>Helvetica, Arial, sans-serif;
    color: #FFFFFF; font-weight: bold; text-align: center;
    letter-spacing: <?php echo $mm(0.10 * $u); ?>mm; white-space: nowrap;
  }
  .signature {
    color: #FFFFFF; text-align: center; white-space: nowrap;
    font-family: 'FPL Script', 'DejaVu Serif', serif; font-style: italic;
  }

  .titre {
    font-family: <?php echo $a_etroite_dispo ? "'FPL Etroite', " : ''; ?>Helvetica, Arial, sans-serif;
    font-weight: bold; color: <?php echo ETQ_MARINE; ?>;
    white-space: nowrap; line-height: 1;
  }
  .sous-titre {
    font-family: <?php echo $a_etroite_dispo ? "'FPL Etroite', " : ''; ?>Helvetica, Arial, sans-serif;
    font-weight: bold; color: <?php echo ETQ_NOIR; ?>;
    white-space: nowrap; line-height: 1;
  }
  .trait-titre { background: <?php echo ETQ_MARINE; ?>; }

  .slogan {
    color: <?php echo ETQ_MARINE; ?>; white-space: nowrap; line-height: 1;
    font-family: 'FPL Script', 'DejaVu Serif', serif; font-style: italic;
  }

  .refs { border: <?php echo $mm(0.2 * $u); ?>mm solid <?php echo ETQ_MARINE; ?>;
          border-radius: <?php echo $mm(0.6 * $u); ?>mm; }
  .refs-sep { background: <?php echo ETQ_MARINE; ?>; }
  .ref-libelle {
    color: <?php echo ETQ_MARINE; ?>; font-weight: bold; white-space: nowrap;
    letter-spacing: <?php echo $mm(0.04 * $u); ?>mm;
  }
  .ref-valeur {
    color: <?php echo ETQ_NOIR; ?>; font-weight: bold; white-space: nowrap;
    font-family: <?php echo $a_etroite_dispo ? "'FPL Etroite', " : ''; ?>Helvetica, Arial, sans-serif;
    letter-spacing: <?php echo $mm(0.05 * $u); ?>mm;
  }

  .filet-pied { background: <?php echo ETQ_MARINE; ?>; }
  .separateur { background: <?php echo ETQ_MARINE; ?>; }
  .qr-cadre { border: <?php echo $mm(0.2 * $u); ?>mm solid <?php echo ETQ_MARINE; ?>;
              border-radius: <?php echo $mm(0.5 * $u); ?>mm; }
  .scannez { color: <?php echo ETQ_MARINE; ?>; font-weight: bold; white-space: nowrap;
             letter-spacing: <?php echo $mm(0.05 * $u); ?>mm; }
  .scannez-sous { color: #000000; white-space: nowrap; }
  .cb-chiffres {
    color: #000000; white-space: nowrap; text-align: center;
    font-family: 'DejaVu Sans Mono', Courier, monospace;
    letter-spacing: <?php echo $mm(0.35 * $u); ?>mm;
  }
</style>
</head>
<body>

<?php if ($camion) : ?>
  <img class="abs" src="<?php echo $camion; ?>" alt=""
       style="left: <?php echo $px(58.5); ?>mm; top: <?php echo $py(0); ?>mm;
              width: <?php echo $px(41.5); ?>mm; height: <?php echo $py(47.5); ?>mm">
<?php endif; ?>

<img class="abs" src="<?php echo $bandeau; ?>" alt=""
     style="left: 0; top: 0; width: <?php echo $mm($L); ?>mm; height: <?php echo $mm($H); ?>mm">

<?php if ($logo) : ?>
  <img class="abs" src="<?php echo $logo; ?>" alt=""
       style="left: <?php echo $px(5.26); ?>mm; top: <?php echo $py(4.63); ?>mm;
              width: <?php echo $px(14.04); ?>mm; height: <?php echo $py(15.63); ?>mm">
<?php endif; ?>
<div class="abs nom-maison"
     style="left: <?php echo $px(1.9); ?>mm; top: <?php echo $py(21.2); ?>mm;
            width: <?php echo $px(20.7); ?>mm; font-size: <?php echo round($nom_maison_pt, 2); ?>pt">
  FOUTA POIDS LOURDS
</div>
<div class="abs signature"
     style="left: <?php echo $px(1.9); ?>mm; top: <?php echo $py(24.2); ?>mm;
            width: <?php echo $px(20.7); ?>mm; font-size: <?php echo round($signature_pt, 2); ?>pt">
  The Solution
</div>
<?php if ($swash_solution) : ?>
  <img class="abs" src="<?php echo $swash_solution; ?>" alt=""
       style="left: <?php echo $px(6.38); ?>mm; top: <?php echo $py(29.9); ?>mm;
              width: <?php echo $px(10.8); ?>mm; height: <?php echo $py(1.95); ?>mm">
<?php endif; ?>

<?php if ($photo !== null) : ?>
  <img class="abs" src="<?php echo $photo['data']; ?>" alt=""
       style="left: <?php echo $px(55.02 + $photo['dx']); ?>mm; top: <?php echo $py(40.59 + $photo['dy']); ?>mm;
              width: <?php echo $px($photo['l']); ?>mm; height: <?php echo $py($photo['h']); ?>mm">
<?php endif; ?>

<div class="abs titre"
     style="left: <?php echo $px(25.2); ?>mm; top: <?php echo $py(32.0); ?>mm;
            font-size: <?php echo round($titre_pt, 2); ?>pt">
  <?php echo e(mb_strtoupper($titre)); ?>
</div>
<?php if ($sous_titre !== null) : ?>
  <div class="abs sous-titre"
       style="left: <?php echo $px(25.2); ?>mm; top: <?php echo $py(39.0); ?>mm;
              font-size: <?php echo round($sous_titre_pt, 2); ?>pt">
    <?php echo e(mb_strtoupper($sous_titre)); ?>
  </div>
  <div class="abs trait-titre"
       style="left: <?php echo $px(25.2); ?>mm; top: <?php echo $py(43.78); ?>mm;
              width: <?php echo $px(6.62); ?>mm; height: <?php echo $py(0.56); ?>mm"></div>
<?php endif; ?>

<div class="abs slogan"
     style="left: <?php echo $px(17.7); ?>mm; top: <?php echo $py(44.9); ?>mm;
            width: <?php echo $px(36.0); ?>mm; font-size: <?php echo round($slogan_pt, 2); ?>pt">
  Conduire avec assurance
</div>
<div class="abs slogan"
     style="left: <?php echo $px(17.7); ?>mm; top: <?php echo $py(48.9); ?>mm;
            width: <?php echo $px(36.0); ?>mm; font-size: <?php echo round($slogan2_pt, 2); ?>pt">
  ndakh jombtukay you worr
</div>
<?php if ($swash) : ?>
  <img class="abs" src="<?php echo $swash; ?>" alt=""
       style="left: <?php echo $px(23.7); ?>mm; top: <?php echo $py(54.8); ?>mm;
              width: <?php echo $px(26.8); ?>mm; height: <?php echo $py(4.2); ?>mm">
<?php endif; ?>

<div class="abs refs"
     style="left: <?php echo $px(5.74); ?>mm; top: <?php echo $py(61.32); ?>mm;
            width: <?php echo $px(36.4); ?>mm; height: <?php echo $py(17.9); ?>mm"></div>
<div class="abs refs-sep"
     style="left: <?php echo $px(7.58); ?>mm; top: <?php echo $py(70.1); ?>mm;
            width: <?php echo $px(33.09); ?>mm; height: <?php echo $py(0.24); ?>mm"></div>

<img class="abs" src="<?php echo $past_tag; ?>" alt=""
     style="left: <?php echo $px(7.42); ?>mm; top: <?php echo $py(62.76); ?>mm;
            width: <?php echo $px(6.3); ?>mm; height: <?php echo $py(6.3); ?>mm">
<div class="abs ref-libelle"
     style="left: <?php echo $px(16.03); ?>mm; top: <?php echo $py(63.0); ?>mm;
            font-size: <?php echo round($ref_libelle_pt, 2); ?>pt">RÉFÉRENCE FPL</div>
<div class="abs ref-valeur"
     style="left: <?php echo $px(16.03); ?>mm; top: <?php echo $py(65.0); ?>mm;
            font-size: <?php echo round($ref_fpl_pt, 2); ?>pt">
  <?php echo e($ref_fpl); ?>
</div>

<img class="abs" src="<?php echo $past_gear; ?>" alt=""
     style="left: <?php echo $px(7.42); ?>mm; top: <?php echo $py(71.53); ?>mm;
            width: <?php echo $px(6.3); ?>mm; height: <?php echo $py(6.3); ?>mm">
<div class="abs ref-libelle"
     style="left: <?php echo $px(16.03); ?>mm; top: <?php echo $py(71.7); ?>mm;
            font-size: <?php echo round($ref_libelle_pt, 2); ?>pt">RÉFÉRENCE OEM</div>
<div class="abs ref-valeur"
     style="left: <?php echo $px(16.03); ?>mm; top: <?php echo $py(73.9); ?>mm;
            font-size: <?php echo round($ref_oem_pt, 2); ?>pt">
  <?php echo e($ref_oem); ?>
</div>

<div class="abs filet-pied"
     style="left: 0; top: <?php echo $py(81.1); ?>mm;
            width: <?php echo $mm($L); ?>mm; height: <?php echo $py(0.32); ?>mm"></div>
<img class="abs" src="<?php echo $trame; ?>" alt=""
     style="left: 0; top: <?php echo $py(81.7); ?>mm;
            width: <?php echo $px(20.7); ?>mm; height: <?php echo $py(13.4); ?>mm">
<div class="abs separateur"
     style="left: <?php echo $px(31.1); ?>mm; top: <?php echo $py(83.0); ?>mm;
            width: <?php echo $px(0.18); ?>mm; height: <?php echo $py(11.3); ?>mm"></div>
<div class="abs separateur"
     style="left: <?php echo $px(61.88); ?>mm; top: <?php echo $py(83.0); ?>mm;
            width: <?php echo $px(0.18); ?>mm; height: <?php echo $py(11.3); ?>mm"></div>

<div class="abs qr-cadre"
     style="left: <?php echo $px(34.0); ?>mm; top: <?php echo $py(83.0); ?>mm;
            width: <?php echo $px(10.2); ?>mm; height: <?php echo $py(11.2); ?>mm"></div>
<?php if ($qr_data_uri !== '') : ?>
<img class="abs" src="<?php echo $qr_data_uri; ?>" alt=""
     style="left: <?php echo $px(34.7); ?>mm; top: <?php echo $py(83.6); ?>mm;
            width: <?php echo $px(8.8); ?>mm; height: <?php echo $py(10.0); ?>mm">
<?php endif; ?>

<div class="abs scannez"
     style="left: <?php echo $px(46.3); ?>mm; top: <?php echo $py(84.9); ?>mm;
            font-size: <?php echo $pt_texte(1.52); ?>pt">SCANNEZ</div>
<div class="abs scannez-sous"
     style="left: <?php echo $px(46.3); ?>mm; top: <?php echo $py(87.9); ?>mm;
            font-size: <?php echo $pt_texte(1.15); ?>pt">POUR PLUS</div>
<div class="abs scannez-sous"
     style="left: <?php echo $px(46.3); ?>mm; top: <?php echo $py(90.1); ?>mm;
            font-size: <?php echo $pt_texte(1.15); ?>pt">D'INFOS PRODUIT</div>
<div class="abs separateur"
     style="left: <?php echo $px(46.3); ?>mm; top: <?php echo $py(92.9); ?>mm;
            width: <?php echo $px(7.0); ?>mm; height: <?php echo $py(0.22); ?>mm"></div>

<div class="abs filet-pied"
     style="left: 0; top: <?php echo $py(96.25); ?>mm;
            width: <?php echo $mm($L); ?>mm; height: <?php echo $py(3.75); ?>mm"></div>

<?php if ($barcode !== null) : ?>
<img class="abs" src="<?php echo $barcode; ?>" alt=""
     style="left: <?php echo $px(64.5); ?>mm; top: <?php echo $py(83.2); ?>mm;
            width: <?php echo $px(32.5); ?>mm; height: <?php echo $py(8.4); ?>mm">
<?php endif; ?>
<div class="abs cb-chiffres"
     style="left: <?php echo $px(64.5); ?>mm; top: <?php echo $py(92.0); ?>mm;
            width: <?php echo $px(32.5); ?>mm; font-size: <?php echo $pt_texte(1.55); ?>pt">
  <?php echo e($code_barres_contenu); ?>
</div>

</body>
</html>
<?php
$html = ob_get_clean();

$pt = function ($mm_valeur) {
    return round($mm_valeur * 2.834645, 2);
};

$cote = function ($mm_valeur) {
    return rtrim(rtrim(number_format((float) $mm_valeur, 2, '.', ''), '0'), '.');
};
$nom_fichier = 'etiquette-' . preg_replace('/[^A-Za-z0-9]/', '', (string) $produit['identifiant_interne'])
    . '-' . $cote($format['largeur_mm']) . 'x' . $cote($format['hauteur_mm']) . 'mm.pdf';

// La trace d'impression : télécharger le PDF, c'est imprimer l'étiquette.
require_once __DIR__ . '/../../models/model_etiquettes_fpl.php';
if (function_exists('etiquette_tracer_impression')) {
    etiquette_tracer_impression('produit', $produit_id,
        !empty($format['id']) ? (int) $format['id'] : null, (int) $_SESSION['admin_id'], false);
}

$dompdf->loadHtml($html);
$dompdf->setPaper([0, 0, $pt($L), $pt($H)]);
$dompdf->render();
$dompdf->stream($nom_fichier, ['Attachment' => true]);
exit;
