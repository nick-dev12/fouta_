<?php
/**
 * Téléchargement PDF : code-barres ou QR code d'un produit (admin).
 * GET id=…&type=barcode|qrcode
 */

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
    echo 'Paramètres invalides.';
    exit;
}

require_once __DIR__ . '/../../models/model_produits.php';
require_once __DIR__ . '/../../includes/export_stock_codes_pdf.php';

$produit = get_produit_by_id($produit_id);
if (!$produit) {
    http_response_code(404);
    echo 'Produit introuvable.';
    exit;
}

$ok = false;
if ($type === 'barcode') {
    $ok = stock_send_barcode_pdf($produit);
} else {
    $stock_info_url = get_site_base_url() . '/stock-info.php?id=' . $produit_id;
    $ok = stock_send_qrcode_pdf($produit, $stock_info_url);
}

if (!$ok) {
    http_response_code(500);
    echo 'Impossible de générer le PDF. Vérifiez que le code-barres ou le QR code est disponible.';
    exit;
}
