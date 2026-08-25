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

$qr_uri = ee_noeud_label_data_uri($qr_path);
$libelle_h = htmlspecialchars($libelle, ENT_QUOTES, 'UTF-8');

/* Le dessin de FPL natif : libellé CENTRÉ dans la place restante, QR au
 * bord (à gauche si la disposition le dit), décalages appliqués. Fond
 * blanc : le support Zebra est jaune, on n'imprime que le noir. */
$html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
@page { size: ' . $mm_w . 'mm ' . $mm_h . 'mm; margin: 0; }
html, body { margin: 0; padding: 0; width: ' . $mm_w . 'mm; height: ' . $mm_h . 'mm; overflow: hidden; }
.ee-barre-etiq {
  width: ' . $mm_w . 'mm; height: ' . $mm_h . 'mm; box-sizing: border-box;
  padding: ' . $mm_pad . 'mm;
  display: flex; align-items: center; justify-content: space-between;
  gap: ' . $mm_gap . 'mm;
  font-family: DejaVu Sans, Arial, sans-serif;
}
.ee-barre-etiq__text {
  flex: 1; text-align: center;
  font-size: ' . $pt_tx . 'pt; font-weight: bold; color: #000;
  white-space: nowrap; line-height: 1;
  position: relative; left: ' . $mm_decx . 'mm; top: ' . $mm_decy . 'mm;
}
.ee-barre-etiq__qr {
  width: ' . $mm_qr . 'mm; height: ' . $mm_qr . 'mm;
  position: relative; left: ' . $mm_decx . 'mm; top: ' . $mm_decy . 'mm;
}
</style></head><body>
<div class="ee-barre-etiq">';
$bloc_texte = '<span class="ee-barre-etiq__text">' . $libelle_h . '</span>';
$bloc_qr = $qr_uri !== '' ? '<img class="ee-barre-etiq__qr" src="' . $qr_uri . '" alt="QR">' : '';
$html .= $qr_a_gauche ? ($bloc_qr . $bloc_texte) : ($bloc_texte . $bloc_qr);
$html .= '</div></body></html>';

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
