<?php
/**
 * Tables retours boutique (commandes livrées / payées).
 * Usage : php migrations/run_create_commandes_retours_tables.php
 */
require_once __DIR__ . '/../conn/conn.php';

if (!$db) {
    fwrite(STDERR, "Connexion BDD impossible.\n");
    exit(1);
}

try {
    $db->query('SELECT 1 FROM commandes LIMIT 1');
    $db->query('SELECT 1 FROM commande_produits LIMIT 1');
} catch (PDOException $e) {
    fwrite(STDERR, "Prérequis : tables commandes et commande_produits.\n");
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

try {
    $db->exec('SET NAMES utf8mb4');
    $db->exec('SET FOREIGN_KEY_CHECKS = 0');

    $db->exec("
CREATE TABLE IF NOT EXISTS `commandes_retours` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `numero_retour` varchar(32) NOT NULL,
  `commande_id` int(11) NOT NULL,
  `admin_createur_id` int(11) DEFAULT NULL,
  `date_retour` datetime NOT NULL,
  `notes` text DEFAULT NULL,
  `montant_total_retour` decimal(12,2) NOT NULL DEFAULT 0.00,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_commandes_retours_numero` (`numero_retour`),
  KEY `idx_commandes_retours_commande` (`commande_id`),
  KEY `idx_commandes_retours_date` (`date_retour`),
  CONSTRAINT `fk_commandes_retours_commande` FOREIGN KEY (`commande_id`) REFERENCES `commandes` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "+ table commandes_retours\n";

    $db->exec("
CREATE TABLE IF NOT EXISTS `commandes_retours_lignes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `retour_commande_id` int(11) NOT NULL,
  `commande_produit_id` int(11) NOT NULL,
  `produit_id` int(11) DEFAULT NULL,
  `designation` varchar(512) NOT NULL,
  `quantite_retour` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `prix_unitaire` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `total_ligne` decimal(12,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_crl_retour` (`retour_commande_id`),
  KEY `idx_crl_cp` (`commande_produit_id`),
  CONSTRAINT `fk_crl_retour` FOREIGN KEY (`retour_commande_id`) REFERENCES `commandes_retours` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_crl_cp` FOREIGN KEY (`commande_produit_id`) REFERENCES `commande_produits` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_crl_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "+ table commandes_retours_lignes\n";

    $db->exec('SET FOREIGN_KEY_CHECKS = 1');
    echo "\nMigration commandes_retours terminée.\n";
} catch (PDOException $e) {
    try {
        $db->exec('SET FOREIGN_KEY_CHECKS = 1');
    } catch (PDOException $ignored) {
    }
    fwrite(STDERR, "Erreur : " . $e->getMessage() . "\n");
    exit(1);
}
