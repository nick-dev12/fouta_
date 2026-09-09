<?php
/**
 * LE PDF D'UN LOT D'ÉTIQUETTES DE PIÈCES — la demande de la direction du
 * 09/09/2026 : sur « Toutes les étiquettes », cocher les étiquettes voulues
 * (n'importe lesquelles, quel que soit le rayon, sur autant de pages de liste
 * qu'on veut), cliquer une fois, et recevoir UN SEUL PDF qui les contient
 * toutes — une étiquette par page, à la taille réelle.
 * Programmation procédurale uniquement.
 *
 * Le dessin est CELUI DE L'ÉTIQUETTE SEULE (includes/etiquette_fpl70.php) :
 * ce PDF n'invente rien, il empile ce que etiquette-piece-pdf.php sort pièce
 * par pièce. La taille de page est la même pour tout le lot — on imprime une
 * planche d'étiquettes identiques, pas un mélange de formats.
 *
 * Comme le PDF d'une seule étiquette, télécharger le lot VAUT IMPRESSION :
 * chaque pièce du lot reçoit sa trace dans `etiquette_impressions`, donc la
 * liste les montre « Imprimées » au retour.
 */

require_once __DIR__ . '/../../includes/admin_pdf_response.php';
admin_pdf_request_begin();

// Un lot, c'est des centaines de dessins à 300 points par pouce : on desserre
// le temps et la mémoire, comme le fait déjà le détourage en lot.
@ini_set('memory_limit', '512M');
if (function_exists('set_time_limit')) {
    @set_time_limit(900);
}

session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../includes/admin_permissions.php';
require_once __DIR__ . '/../../models/model_produits.php';
require_once __DIR__ . '/../../models/model_produit_etiquette_parametres.php';
require_once __DIR__ . '/../../models/model_etiquettes_fpl.php';
require_once __DIR__ . '/../../includes/etiquette_fpl70.php';

// Le compte restreint (infographiste) consulte les étiquettes, il n'imprime pas.
if (admin_is_restricted_admin_account()) {
    header('Location: index.php');
    exit;
}

/** Le plafond d'un lot : au-delà, le PDF pèse trop et l'impression n'est plus un geste. */
define('FPL_LOT_ETIQUETTES_MAX', 300);

$erreur = function ($titre, $message) {
    admin_pdf_send_error_html($titre, $message, 'etiquettes.php?type=pieces');
};

// Le jeton : télécharger ce PDF ÉCRIT (les traces d'impression), il est donc
// protégé comme un formulaire, pas comme une simple lecture.
$jeton = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
if (empty($_SESSION['admin_csrf']) || !hash_equals((string) $_SESSION['admin_csrf'], $jeton)) {
    $erreur('Session expirée', 'Revenez à la liste des étiquettes et relancez l\'impression.');
}

// Les identifiants cochés : une liste séparée par des virgules (le champ caché
// que remplit la page) ou un vrai tableau ids[] — on accepte les deux.
$brut = [];
if (isset($_POST['ids']) && is_array($_POST['ids'])) {
    $brut = $_POST['ids'];
} elseif (isset($_POST['ids'])) {
    $brut = explode(',', (string) $_POST['ids']);
}
$ids = [];
foreach ($brut as $v) {
    $n = (int) trim((string) $v);
    if ($n > 0 && !in_array($n, $ids, true)) {
        $ids[] = $n;
    }
}

if ($ids === []) {
    $erreur('Aucune étiquette cochée', 'Cochez au moins une étiquette dans la liste, puis relancez.');
}
if (count($ids) > FPL_LOT_ETIQUETTES_MAX) {
    $erreur('Lot trop grand',
        'Ce PDF accepte ' . FPL_LOT_ETIQUETTES_MAX . ' étiquettes au maximum ; vous en avez coché '
        . count($ids) . '. Faites-en plusieurs lots.');
}

// La taille : la même pour toute la planche (le format demandé, sinon celui du réglage).
$format = fpl_etiquette_format_ou_reglage(isset($_POST['format']) ? (int) $_POST['format'] : 0);
$L = (float) $format['largeur_mm'];
$H = (float) $format['hauteur_mm'];

// Le dessin, carré au côté court de la page, à 300 points par pouce.
$cote_px = (int) round(min($L, $H) / 25.4 * 300);
$cote_px = max(64, min(ETQ70_BASE, $cote_px));

$pages = [];
$imprimees = [];
$manquantes = 0;
foreach ($ids as $id) {
    /* SANS FILTRE D'ACCÈS : même raison que l'étiquette seule — le filtre par
       rôle retire image_principale et images, et l'étiquette perdrait sa photo. */
    $produit = get_produit_by_id_sans_filtre_acces($id);
    if ($produit === false) {
        $manquantes++;
        continue;
    }

    $img = etiquette70_rendu(etiquette70_donnees_pour_produit($produit), $cote_px);
    ob_start();
    imagejpeg($img, null, 94);
    $jpeg = (string) ob_get_clean();
    imagedestroy($img);
    unset($img);

    if ($jpeg === '') {
        $manquantes++;
        continue;
    }
    $pages[] = ['jpeg' => $jpeg, 'w' => $cote_px, 'h' => $cote_px];
    $imprimees[] = (int) $produit['id'];
}

if ($pages === []) {
    $erreur('Étiquettes indisponibles', 'Aucune des étiquettes cochées n\'a pu être dessinée.');
}

$pdf = etiquette70_pdf_multi($pages, $L, $H);
unset($pages);

if ($pdf === '') {
    $erreur('PDF indisponible', 'Le PDF du lot n\'a pas pu être assemblé.');
}

$cote = function ($mm_valeur) {
    return rtrim(rtrim(number_format((float) $mm_valeur, 2, '.', ''), '0'), '.');
};
$nom_fichier = 'etiquettes-' . count($imprimees) . '-pieces-' . $cote($L) . 'x' . $cote($H) . 'mm.pdf';

// Télécharger le lot, c'est imprimer chacune de ses étiquettes.
foreach ($imprimees as $pid) {
    etiquette_tracer_impression('produit', $pid,
        !empty($format['id']) ? (int) $format['id'] : null, (int) $_SESSION['admin_id'], false);
}

admin_pdf_send_binary($pdf, $nom_fichier);
