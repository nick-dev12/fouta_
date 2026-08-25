<?php
/**
 * LES BROUILLONS (JSON) — la sauvegarde au fil de l'eau de js/fpl-draft.js :
 * GET lit le brouillon, PUT l'écrit, DELETE l'abandonne. Le jeton CSRF
 * voyage en en-tête X-CSRF-TOKEN (requêtes JSON) — c'est le jeton de
 * session de ce dépôt ($_SESSION['admin_csrf']), posé dans la page par une
 * balise <meta name="csrf-token">.
 *
 * Portage de fpl_natif/admin/ajax_brouillon.php.
 */

session_start();

require_once __DIR__ . '/../../models/model_brouillons.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['ok' => false]);
    exit;
}
$admin_id = (int) $_SESSION['admin_id'];

$methode = $_SERVER['REQUEST_METHOD'];

if ($methode === 'GET') {
    $cle = isset($_GET['cle']) ? (string) $_GET['cle'] : '';
    $brouillon = $cle !== '' ? brouillon_lire($admin_id, $cle) : null;
    echo json_encode([
        'payload' => $brouillon !== null ? $brouillon['payload'] : null,
        'depuis' => $brouillon !== null ? $brouillon['depuis'] : null,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// PUT et DELETE portent leur corps en JSON — le jeton en en-tête
$jeton = isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? (string) $_SERVER['HTTP_X_CSRF_TOKEN'] : '';
if ($jeton === '' || empty($_SESSION['admin_csrf'])
    || !hash_equals((string) $_SESSION['admin_csrf'], $jeton)) {
    http_response_code(419);
    echo json_encode(['ok' => false]);
    exit;
}

$corps = json_decode((string) file_get_contents('php://input'), true);

if ($methode === 'PUT') {
    $cle = isset($corps['cle']) ? trim((string) $corps['cle']) : '';
    $payload = isset($corps['payload']) && is_array($corps['payload']) ? $corps['payload'] : null;
    if ($cle === '' || mb_strlen($cle) > 120 || $payload === null) {
        http_response_code(422);
        echo json_encode(['ok' => false]);
        exit;
    }
    brouillon_sauver($admin_id, $cle, $payload);
    echo json_encode(['ok' => true]);
    exit;
}

if ($methode === 'DELETE') {
    brouillon_purger($admin_id, isset($corps['cle']) ? (string) $corps['cle'] : '');
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false]);
