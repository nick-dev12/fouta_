<?php
/**
 * Compatibilité : URL historique ou erronée /admin/comptes/comptes/index.php
 * La page réelle est admin/comptes/index.php (un seul segment « comptes »).
 */
$qs = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== ''
    ? ('?' . $_SERVER['QUERY_STRING'])
    : '';
header('Location: ../index.php' . $qs, true, 301);
exit;
