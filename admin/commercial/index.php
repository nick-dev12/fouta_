<?php
/**
 * Espace Commercial — redirection vers Devis & BL
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/includes/require_access.php';

header('Location: ../devis/index.php', true, 302);
exit;
