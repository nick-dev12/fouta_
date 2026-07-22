<?php
/**
 * Scan QR / code-barres d’une barre entrepôt — redirige vers la page publique.
 * Ancienne URL admin conservée pour compatibilité ; les QR pointent vers /barre-info.php
 */
session_start();

require_once __DIR__ . '/../../includes/site_url.php';

$code = '';
if (isset($_GET['c'])) {
    $code = strtoupper(trim((string) $_GET['c']));
} elseif (isset($_POST['c'])) {
    $code = strtoupper(trim((string) $_POST['c']));
}

$public_base = rtrim(get_site_base_url(), '/') . '/barre-info.php';

if ($code !== '') {
    header('Location: ' . $public_base . '?c=' . rawurlencode($code));
    exit;
}

header('Location: ' . $public_base);
exit;
