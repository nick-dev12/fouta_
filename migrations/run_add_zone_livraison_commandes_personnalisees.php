<?php
/**
 * Migration: ajout du champ zone_livraison_id sur les commandes personnalisées
 * Exécuter: php migrations/run_add_zone_livraison_commandes_personnalisees.php
 */

require_once __DIR__ . '/../conn/conn.php';

global $db;

try {
    if (!$db instanceof PDO) {
        echo "Connexion a la base indisponible.\n";
        exit(1);
    }

    $table_exists_stmt = $db->query("SHOW TABLES LIKE 'commandes_personnalisees'");
    if (!$table_exists_stmt || !$table_exists_stmt->fetchColumn()) {
        echo "La table commandes_personnalisees n'existe pas.\n";
        exit(1);
    }

    $column_stmt = $db->query("SHOW COLUMNS FROM commandes_personnalisees LIKE 'zone_livraison_id'");
    if ($column_stmt && $column_stmt->fetchColumn()) {
        echo "La colonne zone_livraison_id existe deja.\n";
        exit(0);
    }

    $db->exec("
        ALTER TABLE commandes_personnalisees
        ADD COLUMN zone_livraison_id INT(11) NULL DEFAULT NULL AFTER date_souhaitee,
        ADD KEY idx_zone_livraison (zone_livraison_id)
    ");

    $tables_stmt = $db->query("SHOW TABLES LIKE 'zones_livraison'");
    if ($tables_stmt && $tables_stmt->fetchColumn()) {
        try {
            $db->exec("
                ALTER TABLE commandes_personnalisees
                ADD CONSTRAINT fk_cp_zone_livraison
                FOREIGN KEY (zone_livraison_id) REFERENCES zones_livraison(id) ON DELETE SET NULL ON UPDATE CASCADE
            ");
        } catch (PDOException $e) {
            echo "Colonne ajoutee. Contrainte FK optionnelle non ajoutee: " . $e->getMessage() . "\n";
        }
    }

    echo "Colonne zone_livraison_id ajoutee a commandes_personnalisees.\n";
} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
    exit(1);
}
