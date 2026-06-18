<?php
/**
 * Contrôle d'accès par rôle pour endpoints JSON (pas de redirection HTML).
 */
if (defined('ADMIN_ROUTE_ENFORCED')) {
    return;
}
require_once dirname(__DIR__, 2) . '/includes/admin_route_access.php';

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Non authentifié'], JSON_UNESCAPED_UNICODE);
    exit;
}

$role = admin_normalize_role_for_route($_SESSION['admin_role'] ?? 'admin');
$_SESSION['admin_role'] = $role;
$rel = admin_route_relative_path();

if (!admin_route_is_allowed($role, $rel)) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Accès refusé pour votre rôle.'], JSON_UNESCAPED_UNICODE);
    exit;
}

define('ADMIN_ROUTE_ENFORCED', true);
