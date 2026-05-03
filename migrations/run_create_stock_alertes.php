<?php
/**
 * Crée stock_alertes_regles
 * php migrations/run_create_stock_alertes.php
 */
require_once __DIR__ . '/../conn/conn.php';

try {
    $sql = file_get_contents(__DIR__ . '/create_stock_alertes.sql');
    if ($sql !== false && trim($sql) !== '') {
        $db->exec($sql);
    }
    echo "+ stock_alertes_regles OK\n";
} catch (PDOException $e) {
    if (stripos($e->getMessage(), 'already exists') !== false) {
        echo "— stock_alertes_regles existe déjà\n";
    } else {
        echo 'Erreur: ' . $e->getMessage() . "\n";
        exit(1);
    }
}
