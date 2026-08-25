<?php
/**
 * La trace des impressions d'étiquettes (JSON) — le clic « Imprimer » de la
 * fiche enregistre automatiquement : qui, quand. Le jeton CSRF voyage dans
 * le corps JSON (champ `_jeton`, jeton de session admin_csrf).
 *
 * Portage de fpl_natif/admin/ajax_etiquette_imprimee.php.
 */

session_start();

require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../includes/admin_permissions.php';
require_once __DIR__ . '/../../models/model_etiquettes_fpl.php';

header('Content-Type: application/json; charset=utf-8');

$charge = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($charge) || !isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['ok' => false]);
    exit;
}

$jeton = isset($charge['_jeton']) ? (string) $charge['_jeton'] : '';
if (empty($_SESSION['admin_csrf']) || !hash_equals((string) $_SESSION['admin_csrf'], $jeton)) {
    http_response_code(419);
    echo json_encode(['ok' => false]);
    exit;
}

$type_recu = isset($charge['type']) ? (string) $charge['type'] : '';
$type = $type_recu === 'barre' ? 'noeud' : 'produit';
$id = isset($charge['id']) ? (int) $charge['id'] : 0;

if (admin_is_restricted_admin_account() || !in_array($type_recu, ['piece', 'barre'], true) || $id <= 0) {
    http_response_code(403);
    echo json_encode(['ok' => false]);
    exit;
}

$format_id = !empty($charge['format_id']) ? (int) $charge['format_id'] : null;
etiquette_tracer_impression($type, $id, $format_id, (int) $_SESSION['admin_id'], false);

echo json_encode(['ok' => true]);
