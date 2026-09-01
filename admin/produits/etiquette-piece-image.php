<?php
/**
 * L'ÉTIQUETTE DE PIÈCE EN IMAGE (01/09/2026) — le PNG du nouveau dessin.
 *
 * C'est l'image que la fiche pièce montre à l'écran et que le navigateur
 * imprime : LE MÊME moteur que le PDF (includes/etiquette_fpl70.php), donc ce
 * que l'écran montre est ce que l'imprimante sort, au pixel.
 *
 * Paramètres :
 *   id    — la pièce ;
 *   cote  — le côté en px (défaut 1080, borné 64…1654) ;
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

$img = etiquette70_rendu(etiquette70_donnees_pour_produit($produit), $cote);

header('Content-Type: image/png');
header('Cache-Control: private, no-cache, must-revalidate');
if (!empty($_GET['telecharger'])) {
    $nom = 'etiquette-' . preg_replace('/[^A-Za-z0-9]/', '', (string) $produit['identifiant_interne']) . '.png';
    header('Content-Disposition: attachment; filename="' . $nom . '"');
}
imagepng($img);
imagedestroy($img);
exit;
