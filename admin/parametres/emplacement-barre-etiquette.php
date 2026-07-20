<?php
/**
 * PDF étiquette barre (QR + code-barres).
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

if (!admin_is_full_admin()) {
    header('Location: ../dashboard.php');
    exit;
}

$barre_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
require_once __DIR__ . '/../../models/model_entrepot_referentiel.php';
require_once __DIR__ . '/../../includes/entrepot_barcode_service.php';

$barre = entrepot_get_barre_by_id($barre_id);
if ($barre === null) {
    http_response_code(404);
    echo 'Barre introuvable.';
    exit;
}

entrepot_generer_codes_barre($barre_id);
$barre = entrepot_get_barre_by_id($barre_id);
$chemin = entrepot_build_chemin_barre($barre_id);
$bc_path = __DIR__ . '/../../upload/barcodes/barre_' . $barre_id . '.png';
$qr_path = __DIR__ . '/../../upload/qrcodes/barre_' . $barre_id . '.png';

function ee_label_data_uri($path) {
    if (!is_file($path)) {
        return '';
    }
    $raw = file_get_contents($path);

    return 'data:image/png;base64,' . base64_encode($raw);
}

$bc_uri = ee_label_data_uri($bc_path);
$qr_uri = ee_label_data_uri($qr_path);
$nom = htmlspecialchars($barre['nom'] ?? '', ENT_QUOTES, 'UTF-8');
$code = htmlspecialchars($barre['code_scan'] ?? '', ENT_QUOTES, 'UTF-8');
$chemin_h = htmlspecialchars($chemin, ENT_QUOTES, 'UTF-8');

$html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
body { font-family: DejaVu Sans, sans-serif; text-align: center; padding: 24px; }
h1 { font-size: 18px; margin: 0 0 8px; color: #3564a6; }
p { font-size: 11px; color: #444; margin: 4px 0; }
img { margin: 8px auto; display: block; }
.bar { max-width: 280px; }
.qr { width: 120px; height: 120px; }
</style></head><body>
<h1>' . $nom . '</h1>
<p>' . $chemin_h . '</p>
<p><strong>' . $code . '</strong></p>';
if ($qr_uri !== '') {
    $html .= '<img class="qr" src="' . $qr_uri . '" alt="QR">';
}
if ($bc_uri !== '') {
    $html .= '<img class="bar" src="' . $bc_uri . '" alt="Code-barres">';
}
$html .= '</body></html>';

if (!is_file(__DIR__ . '/../../vendor/autoload.php')) {
    http_response_code(500);
    echo 'Dompdf absent.';
    exit;
}
require_once __DIR__ . '/../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$options = new Options();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A6', 'portrait');
$dompdf->render();
$dompdf->stream('etiquette-barre-' . $barre_id . '.pdf', ['Attachment' => true]);
