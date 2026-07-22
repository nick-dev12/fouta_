<?php
/**
 * Régénère les QR barres avec l’URL publique barre-info.php
 * Usage : php migrations/run_regenerer_qrcodes_barres.php
 */
require_once __DIR__ . '/../includes/entrepot_barcode_service.php';

$res = entrepot_regenerer_qrcodes_toutes_barres();
echo ($res['success'] ? 'OK: ' : 'Erreur: ') . $res['message'] . "\n";
exit($res['success'] ? 0 : 1);
