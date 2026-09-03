<?php
/**
 * ÉTAT du « tout détourer » (03/09/2026) — le navigateur l'interroge en boucle.
 */

ob_start();
session_start();

require_once __DIR__ . '/../includes/require_access_json.php';

while (ob_get_level() > 0) {
    ob_end_clean();
}
header('Content-Type: application/json; charset=utf-8');

$prog = dirname(__DIR__, 2) . '/upload/detour_cache/_lot.json';
if (!is_file($prog)) {
    echo json_encode(['ok' => true, 'etat' => null], JSON_UNESCAPED_UNICODE);
    exit;
}
$j = json_decode((string) @file_get_contents($prog), true);
if (!is_array($j)) {
    echo json_encode(['ok' => true, 'etat' => null], JSON_UNESCAPED_UNICODE);
    exit;
}

// Worker mort ? (pas terminé mais plus de battement depuis 40 s)
if (empty($j['termine']) && isset($j['battement']) && (time() - (int) $j['battement']) > 40) {
    $j['interrompu'] = true;
}

echo json_encode(['ok' => true, 'etat' => $j], JSON_UNESCAPED_UNICODE);
