<?php
/**
 * Redirection vers la page Stock (gestion des catégories)
 * Le contenu des catégories a été déplacé dans admin/stock/index.php
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';

header('Location: ../stock/index.php');
exit;
