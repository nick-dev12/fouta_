<?php
/**
 * Téléchargement PDF : code-barres ou QR code d'un produit (admin).
 * GET id=…&type=barcode|qrcode
 */

require_once __DIR__ . '/../../includes/admin_pdf_response.php';
admin_pdf_request_begin();

session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';

$produit_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$type = isset($_GET['type']) ? strtolower(trim((string) $_GET['type'])) : '';

if ($produit_id <= 0 || !in_array($type, ['barcode', 'qrcode'], true)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Paramètres invalides.';
    exit;
}

require_once __DIR__ . '/../../models/model_produits.php';
require_once __DIR__ . '/../../includes/export_stock_codes_pdf.php';
require_once __DIR__ . '/../../includes/site_url.php';
require_once __DIR__ . '/../../includes/produit_emplacement_entrepot.php';

$produit = get_produit_by_id($produit_id);
if (!$produit) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Produit introuvable.';
    exit;
}

$code_fpl = ensure_produit_identifiant_interne($produit_id);
if ($code_fpl !== null && $code_fpl !== '') {
    $produit['identifiant_interne'] = $code_fpl;
}

$ok = false;
if ($type === 'barcode') {
    $ok = stock_send_barcode_pdf($produit);
} else {
    $stock_info_url = produit_emplacement_stock_info_url($produit_id, $produit);
    $ok = stock_send_qrcode_pdf($produit, $stock_info_url);
}

if (!$ok) {
    admin_pdf_send_error_html(
        'Export PDF impossible',
        stock_codes_pdf_get_last_error() ?: 'Impossible de générer le PDF.',
        'ajuster-stock.php?id=' . (int) $produit_id,
        'Retour à l’ajustement stock'
    );
}
