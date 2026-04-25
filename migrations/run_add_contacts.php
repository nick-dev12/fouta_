<?php
/**
 * Migration: Table contacts (contacts manuels)
 * Exécuter: php migrations/run_add_contacts.php
 */
require_once __DIR__ . '/../conn/conn.php';

global $db;

try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS contacts (
            id INT(11) NOT NULL AUTO_INCREMENT,
            nom VARCHAR(255) NOT NULL,
            prenom VARCHAR(255) NOT NULL DEFAULT '',
            telephone VARCHAR(50) NOT NULL,
            email VARCHAR(255) NULL,
            date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_telephone (telephone),
            KEY idx_nom (nom)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "Table contacts créée.\n";
} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
