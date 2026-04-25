<?php
/**
 * Migration: ajout du champ prix sur les commandes personnalisées
 * Exécuter: php migrations/run_add_prix_commandes_personnalisees.php
 */

require_once __DIR__ . '/../conn/conn.php';

global $db;

try {
    if (!$db instanceof PDO) {
        echo "Connexion a la base indisponible depuis le PHP CLI. Lancez cette migration depuis un environnement PHP ayant acces a MySQL.\n";
        exit(1);
    }

    $table_exists_stmt = $db->query("SHOW TABLES LIKE 'commandes_personnalisees'");
    $table_exists = $table_exists_stmt && $table_exists_stmt->fetchColumn();

    if (!$table_exists) {
        echo "La table commandes_personnalisees n'existe pas.\n";
        exit;
    }

    $column_stmt = $db->query("SHOW COLUMNS FROM commandes_personnalisees LIKE 'prix'");
    $column_exists = $column_stmt && $column_stmt->fetchColumn();

    if ($column_exists) {
        echo "La colonne prix existe deja.\n";
        exit;
    }

    $db->exec("
        ALTER TABLE commandes_personnalisees
        ADD COLUMN prix DECIMAL(10,2) NULL DEFAULT NULL AFTER date_souhaitee
    ");

    echo "Colonne prix ajoutee a commandes_personnalisees.\n";
} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
