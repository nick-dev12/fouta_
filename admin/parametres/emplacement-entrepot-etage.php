<?php
/**
 * Redirect legacy page étage → onglet niveau sur emplacement-entrepot.php
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

$numero = isset($_GET['etage']) ? (int) $_GET['etage'] : (isset($_GET['niveau']) ? (int) $_GET['niveau'] : 0);
$target = 'emplacement-entrepot.php';
if ($numero > 0) {
    $target .= '?niveau=' . $numero;
}
header('Location: ' . $target);
exit;
