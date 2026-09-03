<?php
/**
 * L'ÉTIQUETTE DE PIÈCE EN PDF — le fichier qu'on archive, qu'on envoie, ou
 * qu'on confie à un imprimeur extérieur. Le PDF sort À LA TAILLE EXACTE de
 * l'étiquette (une 70 × 70 fait une page de 70 × 70 mm, sans marge) :
 * imprimé « taille réelle », il colle au support au millimètre.
 * Programmation procédurale uniquement.
 *
 * NOUVEAU DESSIN (01/09/2026) : la direction a changé le design de
 * l'étiquette — celui de son atelier (FPL-100-ME105116-70x70.pdf). Le dessin
 * vient du moteur partagé includes/etiquette_fpl70.php, vérifié AU PIXEL
 * contre le PDF validé (comparaison Python, écart moyen ~1 %). Le pipeline
 * PDF est CELUI DE L'ATELIER : le dessin en JPEG qualité 94, posé seul dans
 * une page aux mm exacts — dompdf n'a plus rien à faire ici.
 *
 * Une page non carrée (65 × 100, 100 × 130) reçoit le dessin carré au côté le
 * plus court, centré — le geste exact de l'atelier de la direction.
 *
 * Ce qui vient de CE dépôt : le QR (la page stock-info du produit), la photo
 * (image_etiquette_fpl puis la principale, sous upload/), les gardes
 * (require_access + compte restreint écarté), la trace d'impression.
 * L'EAN encode 200 + les 9 chiffres de l'identifiant FPL : le scan de la
 * douchette retombe sur la pièce (produit_emplacement_extraire_fpl_du_scan).
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
require_once __DIR__ . '/../../models/model_produits.php';
require_once __DIR__ . '/../../models/model_produit_etiquette_parametres.php';
require_once __DIR__ . '/../../includes/etiquette_fpl70.php';

if (admin_is_restricted_admin_account()) {
    header('Location: index.php');
    exit;
}

$produit = get_produit_by_id(isset($_GET['id']) ? (int) $_GET['id'] : 0);
if ($produit === false) {
    $_SESSION['success_message'] = 'Cette pièce n\'existe pas.';
    header('Location: etiquettes.php');
    exit;
}
$produit_id = (int) $produit['id'];

// Le format demandé, sinon celui dont les mm sont ceux du réglage, sinon le
// premier — résolution partagée avec l'image (fpl_etiquette_format_ou_reglage).
$format = fpl_etiquette_format_ou_reglage(isset($_GET['format']) ? (int) $_GET['format'] : 0);

$L = (float) $format['largeur_mm'];
$H = (float) $format['hauteur_mm'];

// LE DESSIN — carré au côté court de la page, à 300 points par pouce,
// plafonné au plan de travail du moteur (1654 px ≈ 140 mm à 300 dpi).
$cote_px = (int) round(min($L, $H) / 25.4 * 300);
$cote_px = max(64, min(ETQ70_BASE, $cote_px));

$img = etiquette70_rendu(etiquette70_donnees_pour_produit($produit), $cote_px);
ob_start();
imagejpeg($img, null, 94);
$jpeg = (string) ob_get_clean();
imagedestroy($img);

if ($jpeg === '') {
    admin_pdf_send_error_html('Étiquette indisponible', 'Le dessin de l\'étiquette n\'a pas pu être produit.',
        'ajuster-stock.php?id=' . $produit_id . '#fpl-etiquette-print-root');
}

$pdf = etiquette70_pdf($jpeg, $cote_px, $cote_px, $L, $H);

$cote = function ($mm_valeur) {
    return rtrim(rtrim(number_format((float) $mm_valeur, 2, '.', ''), '0'), '.');
};
$nom_fichier = 'etiquette-' . preg_replace('/[^A-Za-z0-9]/', '', (string) $produit['identifiant_interne'])
    . '-' . $cote($L) . 'x' . $cote($H) . 'mm.pdf';

// La trace d'impression : télécharger le PDF, c'est imprimer l'étiquette.
require_once __DIR__ . '/../../models/model_etiquettes_fpl.php';
if (function_exists('etiquette_tracer_impression')) {
    etiquette_tracer_impression('produit', $produit_id,
        !empty($format['id']) ? (int) $format['id'] : null, (int) $_SESSION['admin_id'], false);
}

admin_pdf_send_binary($pdf, $nom_fichier);
