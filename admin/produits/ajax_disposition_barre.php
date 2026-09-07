<?php
/**
 * La disposition de l'étiquette de barre (JSON) : enregistrée FORMAT PAR
 * FORMAT depuis le panneau « Régler la disposition » de l'aperçu.
 *
 * Portage de fpl_natif/admin/ajax_disposition_barre.php — le jeton voyage
 * dans le corps (`_jeton` = admin_csrf), et le réglage est l'affaire du
 * Responsable (admin_can_gestion_stock_etendue).
 */

session_start();

require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../includes/admin_permissions.php';
require_once __DIR__ . '/../../models/model_etiquettes_fpl.php';

header('Content-Type: application/json; charset=utf-8');

$charge = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($charge) || !isset($_SESSION['admin_id']) || !admin_can_gestion_stock_etendue()) {
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

$format = fpl_etiquette_format_get(isset($charge['format_id']) ? (int) $charge['format_id'] : 0, 'barre');
if ($format === false) {
    http_response_code(404);
    echo json_encode(['ok' => false]);
    exit;
}

// « Revenir à l'automatique » : le serveur recalcule tout
if (!empty($charge['reinitialiser'])) {
    etiquette_maj_disposition_barre($format['id'], null);
    echo json_encode(['ok' => true]);
    exit;
}

// Mêmes bornes que le PDF (etiquette_disposition_barre_normaliser) : une seule source de vérité.
$disposition = etiquette_disposition_barre_normaliser($charge);

etiquette_maj_disposition_barre($format['id'], $disposition);

echo json_encode(['ok' => true]);
