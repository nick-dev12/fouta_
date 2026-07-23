<?php
/**
 * PDF / impression étiquette nœud hiérarchie libre 90×30 mm (fond jaune, libellé + QR).
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

if (!admin_can_gestion_stock_etendue()) {
    header('Location: ../dashboard.php');
    exit;
}

$noeud_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
require_once __DIR__ . '/../../models/model_entrepot_hierarchie_libre.php';

$payload = entrepot_noeud_etiquette_payload($noeud_id);
if ($payload === null) {
    http_response_code(404);
    echo 'Étiquette introuvable pour cet élément.';
    exit;
}

$libelle = (string) ($payload['libelle'] ?? '');
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

$html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
@page { size: 90mm 30mm; margin: 0; }
html, body { margin: 0; padding: 0; width: 90mm; height: 30mm; overflow: hidden; }
.ee-barre-etiq {
  width: 90mm; height: 30mm; box-sizing: border-box;
  padding: 2mm 3.5mm 2mm 4mm;
  display: flex; align-items: center; justify-content: space-between;
  gap: 2.5mm;
  background: #ffe600; font-family: DejaVu Sans, Arial, sans-serif;
}
.ee-barre-etiq__text { font-size: 22pt; font-weight: bold; color: #000; white-space: nowrap; line-height: 1; }
.ee-barre-etiq__qr { width: 24mm; height: 24mm; }
</style></head><body>
<div class="ee-barre-etiq">
  <span class="ee-barre-etiq__text">' . $libelle_h . '</span>';
if ($qr_uri !== '') {
    $html .= '<img class="ee-barre-etiq__qr" src="' . $qr_uri . '" alt="QR">';
}
$html .= '</div></body></html>';

if (!is_file(__DIR__ . '/../../vendor/autoload.php')) {
    http_response_code(500);
    echo 'Dompdf absent.';
    exit;
}
require_once __DIR__ . '/../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$options = new Options();
$options->set('isRemoteEnabled', false);
$options->set('defaultFont', 'DejaVu Sans');
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper([0, 0, 255.118, 85.039], 'landscape');
$dompdf->render();
$dompdf->stream('etiquette-noeud-' . $noeud_id . '.pdf', ['Attachment' => true]);
