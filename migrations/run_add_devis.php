<?php
/**
 * Migration: Tables devis, devis_produits, factures_devis
 * Exécuter: php migrations/run_add_devis.php
 */
require_once __DIR__ . '/../conn/conn.php';

global $db;

$statements = [
    "CREATE TABLE IF NOT EXISTS devis (
        id INT(11) NOT NULL AUTO_INCREMENT,
        numero_devis VARCHAR(50) NOT NULL,
        client_nom VARCHAR(100) NOT NULL,
        client_prenom VARCHAR(100) NOT NULL,
        client_telephone VARCHAR(50) NOT NULL,
        client_email VARCHAR(255) NULL DEFAULT NULL,
        adresse_livraison TEXT NOT NULL,
        zone_livraison_id INT(11) NULL DEFAULT NULL,
        frais_livraison DECIMAL(10,2) NOT NULL DEFAULT 0,
        user_id INT(11) NULL DEFAULT NULL,
        montant_total DECIMAL(10,2) NOT NULL DEFAULT 0,
        notes TEXT NULL DEFAULT NULL,
        statut ENUM('brouillon', 'envoye', 'accepte', 'refuse') NOT NULL DEFAULT 'brouillon',
        date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        date_modification DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY idx_numero_devis (numero_devis),
        KEY idx_user_id (user_id),
        KEY idx_zone_livraison_id (zone_livraison_id),
        KEY idx_statut (statut),
        KEY idx_date_creation (date_creation)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS devis_produits (
        id INT(11) NOT NULL AUTO_INCREMENT,
        devis_id INT(11) NOT NULL,
        produit_id INT(11) NOT NULL,
        nom_produit VARCHAR(255) NULL DEFAULT NULL,
        quantite INT(11) NOT NULL DEFAULT 1,
        prix_unitaire DECIMAL(10,2) NOT NULL,
        prix_total DECIMAL(10,2) NOT NULL,
        PRIMARY KEY (id),
        KEY idx_devis_id (devis_id),
        KEY idx_produit_id (produit_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS factures_devis (
        id INT(11) NOT NULL AUTO_INCREMENT,
        devis_id INT(11) NOT NULL,
        numero_facture VARCHAR(50) NOT NULL,
        date_facture DATE NOT NULL,
        montant_total DECIMAL(10,2) NOT NULL DEFAULT 0,
        token VARCHAR(64) NULL DEFAULT NULL,
        date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY idx_numero_facture (numero_facture),
        UNIQUE KEY idx_devis_id (devis_id),
        KEY idx_token (token)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
];

foreach ($statements as $i => $stmt) {
    try {
        $db->exec($stmt);
        echo "Table créée (" . ($i + 1) . "/3).\n";
    } catch (PDOException $e) {
        echo "Erreur: " . $e->getMessage() . "\n";
    }
}

echo "Migration devis terminée.\n";
