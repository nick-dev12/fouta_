<?php
/**
 * Redirect legacy page étage → onglet niveau sur emplacement-entrepot.php
 */
session_start();
/* LE GARDE-BARRIÈRE (31/08/2026) : cette page ne demandait jamais si le
 * compte connecté avait le droit d'être là. La règle existe depuis
 * toujours dans includes/admin_route_access.php ; il manquait l'appel. */
require_once __DIR__ . '/../includes/require_access.php';


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
