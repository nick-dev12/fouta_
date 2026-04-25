<?php
/**
 * Contrôle d'accès par rôle (liste blanche). Idempotent.
 */
if (defined('ADMIN_ROUTE_ENFORCED')) {
    return;
}
require_once dirname(__DIR__, 2) . '/includes/admin_route_access.php';
admin_route_enforce();
define('ADMIN_ROUTE_ENFORCED', true);
