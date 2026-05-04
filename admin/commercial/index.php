<?php
/**
 * Espace Commercial — redirection selon le rôle
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';

require_once dirname(__DIR__, 2) . '/includes/admin_route_access.php';
$role = admin_normalize_role_for_route($_SESSION['admin_role'] ?? '');

if ($role === 'commercial_general' || $role === 'admin' || $role === 'informaticien') {
    header('Location: ../devis/index.php', true, 302);
    exit;
}

header('Location: ../commandes/index.php', true, 302);
exit;
