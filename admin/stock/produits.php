<?php
/**
 * Redirection : les produits ne sont plus liés aux articles en stock.
 * La gestion du stock utilise désormais la table produits et la colonne stock.
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';

header('Location: index.php');
exit;
