<?php
/**
 * Table marques (référentiel produits / paramètres)
 * php migrations/run_create_marques.php
 */
require_once __DIR__ . '/../conn/conn.php';

$sql = file_get_contents(__DIR__ . '/create_marques.sql');
try {
    $db->exec($sql);
    echo "OK: table marques.\n";
} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
    exit(1);
}
echo "Terminé.\n";
