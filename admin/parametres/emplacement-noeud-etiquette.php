<?php
/**
 * PDF / impression étiquette nœud hiérarchie libre (dimensions paramétrables).
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

/* Voir et IMPRIMER une étiquette de barre est le travail du rayonniste
 * (24/08) : la page ne fait que lire — l'accès de base suffit, comme le
 * `stock.etiquettes` du même rôle chez FPL natif. */
if (!admin_can_gestion_stock()) {
    header('Location: ../dashboard.php');
    exit;
}

$noeud_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
require_once __DIR__ . '/../../models/model_entrepot_hierarchie_libre.php';
require_once __DIR__ . '/../../models/model_entrepot_etiquette_parametres.php';

$payload = entrepot_noeud_etiquette_payload($noeud_id);
if ($payload === null) {
    http_response_code(404);
    echo 'Étiquette introuvable pour cet élément.';
    exit;
}

$libelle = (string) ($payload['libelle'] ?? '');

/* LA MÊME GÉOMÉTRIE QUE LA PAGE-ÉCRAN (25/08) : si un format est demandé
 * (ou si la table des formats existe), le PDF sort au format choisi, avec
 * la disposition enregistrée — auto-adaptée à la longueur du libellé.
 * Sans la table : les dimensions historiques des réglages d'entrepôt. */
$geo = null;
$mm_decx = 0.0;
$mm_decy = 0.0;
$qr_a_gauche = false;
require_once __DIR__ . '/../../models/model_etiquettes_fpl.php';
if (function_exists('etiquette_geometrie_barre')) {
    $format_pdf = !empty($_GET['format']) ? fpl_etiquette_format_get((int) $_GET['format'], 'barre') : false;
    if ($format_pdf === false) {
        $format_pdf = etiquette_format_barre_defaut();
    }
    if ($format_pdf !== false) {
        $geo = etiquette_geometrie_barre($format_pdf, $libelle);
    }
}
if ($geo !== null) {
    $mm_w = (float) $geo['largeur'];
    $mm_h = (float) $geo['hauteur'];
    $mm_qr = (float) $geo['qr'];
    $mm_pad = (float) $geo['pad'];
    $mm_gap = (float) $geo['gap'];
    $mm_decx = (float) $geo['decal_x'];
    $mm_decy = (float) $geo['decal_y'];
    $qr_a_gauche = ($geo['qr_position'] === 'gauche');
    $pt_tx = round(entrepot_etiquette_mm_to_pt((float) $geo['code']), 2);
} else {
    $dims = entrepot_etiquette_dims();
    $mm_w = (float) $dims['largeur_mm'];
    $mm_h = (float) $dims['hauteur_mm'];
    $mm_qr = (float) $dims['qr_mm'];
    $mm_pad = 2.5;
    $mm_gap = 3.0;
    $pt_tx = round(entrepot_etiquette_mm_to_pt((float) $dims['texte_mm']), 2);
}
$qr_web = (string) ($payload['qr_url'] ?? '');
$qr_path = '';
if ($qr_web !== '' && strpos($qr_web, '/upload/') === 0) {
    $qr_path = __DIR__ . '/../..' . $qr_web;
}

function ee_noeud_label_data_uri($path) {
    if (!is_file($path)) {
        return '';
    }

    return 'data:image/png;base64,' . base64_encode((string) file_get_contents($path));
}

/**
 * LE QR SUR FOND JAUNE (03/09) : le QR stocké a un fond BLANC, qui ferait un
 * carré blanc sur le jaune du PDF. On repeint le blanc en #FFE600 (le jaune de
 * l'étiquette) pour qu'il se fonde — seuls les modules noirs restent visibles,
 * comme le QR SVG de l'écran. Repli sur le PNG brut si GD manque.
 *
 * @param string $path  chemin du PNG stocké
 * @param int[]  $bg     couleur de fond voulue [r,g,b]
 * @return string data:URI ou ''
 */
function ee_noeud_qr_data_uri_fond($path, array $bg) {
    if (!is_file($path) || !function_exists('imagecreatefrompng')) {
        return ee_noeud_label_data_uri($path);
    }
    $src = @imagecreatefrompng($path);
    if (!$src) {
        return ee_noeud_label_data_uri($path);
    }
    $w = imagesx($src);
    $h = imagesy($src);
    $out = imagecreatetruecolor($w, $h);
    $jaune = imagecolorallocate($out, $bg[0], $bg[1], $bg[2]);
    imagefilledrectangle($out, 0, 0, $w, $h, $jaune);
    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            $c = imagecolorat($src, $x, $y);
            $r = ($c >> 16) & 255;
            $g = ($c >> 8) & 255;
            $b = $c & 255;
            // les modules SOMBRES sont recopiés ; le clair (blanc) laisse le jaune
            if (0.299 * $r + 0.587 * $g + 0.114 * $b < 128) {
                imagesetpixel($out, $x, $y, imagecolorallocate($out, $r, $g, $b));
            }
        }
    }
    imagedestroy($src);
    ob_start();
    imagepng($out);
    $png = (string) ob_get_clean();
    imagedestroy($out);

    return 'data:image/png;base64,' . base64_encode($png);
}

/**
 * Dessine l'étiquette de barre ENTIÈRE en PNG (fond jaune, libellé noir
 * auto-ajusté pour tenir, QR fondu sur le jaune) et renvoie un data:URI.
 * Vide si GD manque.
 *
 * @return string
 */
function ee_noeud_etiquette_png_data_uri($libelle, $qr_path, $mm_w, $mm_h, $mm_qr, $mm_pad, $mm_gap, $qr_a_gauche, $decx, $decy)
{
    if (!function_exists('imagecreatetruecolor')) {
        return '';
    }
    $ppm = 12.0; // pixels par mm (~305 dpi)
    $W = max(1, (int) round($mm_w * $ppm));
    $H = max(1, (int) round($mm_h * $ppm));
    $pad = (int) round($mm_pad * $ppm);
    $gap = (int) round($mm_gap * $ppm);
    $qr = (int) round($mm_qr * $ppm);
    $dx = (int) round($decx * $ppm);
    $dy = (int) round($decy * $ppm);

    $im = imagecreatetruecolor($W, $H);
    $jaune = imagecolorallocate($im, 255, 230, 0);
    $noir = imagecolorallocate($im, 0, 0, 0);
    imagefilledrectangle($im, 0, 0, $W, $H, $jaune);

    // le QR (si présent) : redimensionné, blanc repeint en jaune, collé au bord
    $qr_pris = 0;
    if ($qr_path !== '' && is_file($qr_path) && function_exists('imagecreatefrompng')) {
        $src = @imagecreatefrompng($qr_path);
        if ($src) {
            $qim = imagecreatetruecolor($qr, $qr);
            imagefilledrectangle($qim, 0, 0, $qr, $qr, $jaune);
            imagecopyresampled($qim, $src, 0, 0, 0, 0, $qr, $qr, imagesx($src), imagesy($src));
            for ($y = 0; $y < $qr; $y++) {
                for ($x = 0; $x < $qr; $x++) {
                    $c = imagecolorat($qim, $x, $y);
                    $lum = 0.299 * (($c >> 16) & 255) + 0.587 * (($c >> 8) & 255) + 0.114 * ($c & 255);
                    if ($lum >= 128) {
                        imagesetpixel($qim, $x, $y, $jaune);
                    }
                }
            }
            $qy = (int) round(($H - $qr) / 2) + $dy;
            $qx = $qr_a_gauche ? ($pad + $dx) : ($W - $pad - $qr + $dx);
            imagecopy($im, $qim, $qx, $qy, 0, 0, $qr, $qr);
            imagedestroy($qim);
            imagedestroy($src);
            $qr_pris = $qr + $gap;
        }
    }

    // le libellé : la plus grande taille qui tient dans la place restante
    $police = __DIR__ . '/../../fonts/etiquette70/barlow-condensed-700.ttf';
    $zone_w = $W - 2 * $pad - $qr_pris;
    $zone_h = $H - 2 * $pad;
    $libelle = trim((string) $libelle);
    if ($libelle !== '' && is_file($police) && function_exists('imagettfbbox')) {
        $taille = (float) min($zone_h, 200);
        while ($taille > 6) {
            $b = imagettfbbox($taille, 0, $police, $libelle);
            $tw = abs($b[2] - $b[0]);
            $th = abs($b[7] - $b[1]);
            if ($tw <= $zone_w * 0.98 && $th <= $zone_h * 0.98) {
                break;
            }
            $taille -= 1;
        }
        $b = imagettfbbox($taille, 0, $police, $libelle);
        $tw = $b[2] - $b[0];
        $th = $b[1] - $b[7];
        $zone_x = $pad + ($qr_a_gauche ? $qr_pris : 0);
        $cx = $zone_x + (int) round(($zone_w - $tw) / 2) - $b[0];
        $cy = (int) round(($H + $th) / 2) - $b[1];
        imagettftext($im, $taille, 0, $cx + $dx, $cy + $dy, $noir, $police, $libelle);
    }

    ob_start();
    imagepng($im);
    $png = (string) ob_get_clean();
    imagedestroy($im);

    return 'data:image/png;base64,' . base64_encode($png);
}

/* LE DESSIN EN IMAGE (03/09) — dompdf ne pose fiablement NI le flex NI un
 * tableau ici : le libellé débordait à gauche et le QR partait sur une 2ᵉ
 * page. On dessine donc TOUTE l'étiquette en GD (fond jaune #FFE600, libellé
 * noir dont la taille s'AJUSTE pour tenir dans la place restante, QR fondu sur
 * le jaune), et dompdf ne fait plus que poser cette unique image sur une page
 * aux mm exacts. Une seule page, aucun débordement, jaune fidèle à l'écran. */
$img_uri = ee_noeud_etiquette_png_data_uri(
    $libelle, $qr_path, (float) $mm_w, (float) $mm_h, (float) $mm_qr,
    (float) $mm_pad, (float) $mm_gap, (bool) $qr_a_gauche, (float) $mm_decx, (float) $mm_decy
);

$html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>'
    . '@page { size: ' . $mm_w . 'mm ' . $mm_h . 'mm; margin: 0; }'
    . 'html, body { margin: 0; padding: 0; }'
    . 'img { display: block; width: ' . $mm_w . 'mm; height: ' . $mm_h . 'mm; }'
    . '</style></head><body>'
    . ($img_uri !== '' ? '<img src="' . $img_uri . '" alt="">' : '')
    . '</body></html>';

if (!is_file(__DIR__ . '/../../vendor/autoload.php')) {
    http_response_code(500);
    echo 'Dompdf absent.';
    exit;
}
require_once __DIR__ . '/../../vendor/autoload.php';

/* La trace d'impression (24/08) : sortir ce PDF, c'est imprimer l'étiquette
 * de la barre — « Toutes les étiquettes » saura qui, quand. Tolérant : sans
 * la table des traces, le PDF sort quand même. (model_etiquettes_fpl.php est
 * déjà chargé plus haut — le require d'ici, redondant, a été retiré.) */
if (function_exists('etiquette_tracer_impression') && !admin_is_restricted_admin_account()) {
    etiquette_tracer_impression('noeud', (int) $noeud_id, null, (int) $_SESSION['admin_id'], false);
}

use Dompdf\Dompdf;
use Dompdf\Options;

$options = new Options();
$options->set('isRemoteEnabled', false);
$options->set('defaultFont', 'DejaVu Sans');
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper([0, 0, entrepot_etiquette_mm_to_pt($mm_w), entrepot_etiquette_mm_to_pt($mm_h)]);
$dompdf->render();
$dompdf->stream('etiquette-noeud-' . $noeud_id . '.pdf', ['Attachment' => true]);
