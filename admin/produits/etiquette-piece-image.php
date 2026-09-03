<?php
/**
 * L'ÉTIQUETTE DE PIÈCE EN IMAGE (01/09/2026) — le PNG du nouveau dessin.
 *
 * C'est l'image que la fiche pièce montre à l'écran et que le navigateur
 * imprime : LE MÊME moteur que le PDF (includes/etiquette_fpl70.php), donc ce
 * que l'écran montre est ce que l'imprimante sort, au pixel.
 *
 * DEPUIS LE 03/09 : l'image accepte AUSSI le format (?format=N) et rend alors
 * LA PAGE ENTIÈRE aux mm choisis — le dessin carré posé au côté court, centré
 * sur fond blanc, exactement la géométrie du PDF et de l'atelier de la
 * direction (fabriquerPdf : dx=(L−côté)/2). Avant, l'image ignorait le format
 * et les pastilles de taille de la fiche ne changeaient rien à l'écran.
 *
 * Paramètres :
 *   id     — la pièce ;
 *   format — id de taille (etiquette_formats) ; absent = taille du réglage ;
 *   cote   — le CÔTÉ COURT en px (défaut 1080, borné 64…1654) ;
 *   telecharger=1 — envoie en pièce jointe (le geste « PNG » de l'atelier).
 *
 * Programmation procédurale uniquement.
 */

session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../includes/admin_permissions.php';
require_once __DIR__ . '/../../models/model_produits.php';
require_once __DIR__ . '/../../includes/etiquette_fpl.php';
require_once __DIR__ . '/../../models/model_produit_etiquette_parametres.php';
require_once __DIR__ . '/../../includes/etiquette_fpl70.php';

if (admin_is_restricted_admin_account()) {
    header('Location: index.php');
    exit;
}

$produit = get_produit_by_id(isset($_GET['id']) ? (int) $_GET['id'] : 0);
if ($produit === false) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Cette pièce n\'existe pas.';
    exit;
}

$cote = isset($_GET['cote']) ? (int) $_GET['cote'] : 1080;
$cote = max(64, min(ETQ70_BASE, $cote));

$format = fpl_etiquette_format_ou_reglage(isset($_GET['format']) ? (int) $_GET['format'] : 0);
$L = $format['largeur_mm'];
$H = $format['hauteur_mm'];

$img = etiquette70_rendu(etiquette70_donnees_pour_produit($produit), $cote);

// page non carrée : le carré posé au côté court, centré sur blanc (le PDF
// et l'atelier font exactement ça — dx = (L − côté) / 2)
if (abs($L - $H) >= 0.01) {
    $min = min($L, $H);
    $pw = max($cote, (int) round($cote * $L / $min));
    $ph = max($cote, (int) round($cote * $H / $min));
    $page = imagecreatetruecolor($pw, $ph);
    imagefilledrectangle($page, 0, 0, $pw, $ph, imagecolorallocate($page, 255, 255, 255));
    imagecopy($page, $img, (int) round(($pw - $cote) / 2), (int) round(($ph - $cote) / 2), 0, 0, $cote, $cote);
    imagedestroy($img);
    $img = $page;
}

header('Content-Type: image/png');
header('Cache-Control: private, no-cache, must-revalidate');
if (!empty($_GET['telecharger'])) {
    $mm = function ($v) {
        return rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
    };
    $nom = 'etiquette-' . preg_replace('/[^A-Za-z0-9]/', '', (string) $produit['identifiant_interne'])
        . '-' . $mm($L) . 'x' . $mm($H) . 'mm.png';
    header('Content-Disposition: attachment; filename="' . $nom . '"');
}
imagepng($img);
imagedestroy($img);
exit;
