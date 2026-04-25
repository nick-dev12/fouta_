<?php
/**
 * Migration: Table factures
 * Exécuter: php migrations/run_add_factures.php
 */
require_once __DIR__ . '/../conn/conn.php';

global $db;

try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS factures (
            id INT(11) NOT NULL AUTO_INCREMENT,
            commande_id INT(11) NOT NULL,
            numero_facture VARCHAR(50) NOT NULL,
            date_facture DATE NOT NULL,
            montant_total DECIMAL(10,2) NOT NULL,
            date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_commande (commande_id),
            KEY idx_numero (numero_facture),
            CONSTRAINT fk_factures_commande FOREIGN KEY (commande_id) REFERENCES commandes (id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "Table factures créée.\n";
} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
