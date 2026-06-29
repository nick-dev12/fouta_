<?php
/**
 * Crée les tables bons_retour et bons_retour_lignes.
 * Usage : php migrations/run_create_bons_retour_tables.php
 */
require_once __DIR__ . '/../conn/conn.php';

if (!$db) {
    fwrite(STDERR, "Connexion BDD impossible.\n");
    exit(1);
}

try {
    $db->query('SELECT 1 FROM bons_livraison LIMIT 1');
    $db->query('SELECT 1 FROM bl_lignes LIMIT 1');
} catch (PDOException $e) {
    fwrite(STDERR, "Prérequis : tables bons_livraison et bl_lignes doivent exister.\n");
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

try {
    $db->exec('SET NAMES utf8mb4');
    $db->exec('SET FOREIGN_KEY_CHECKS = 0');

    $db->exec("
CREATE TABLE IF NOT EXISTS `bons_retour` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `numero_br` varchar(32) NOT NULL,
  `bl_id` int(11) NOT NULL,
  `admin_createur_id` int(11) DEFAULT NULL,
  `date_retour` datetime NOT NULL,
  `notes` text DEFAULT NULL,
  `total_ht_retour` decimal(12,2) NOT NULL DEFAULT 0.00,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_bons_retour_numero` (`numero_br`),
  KEY `idx_bons_retour_bl` (`bl_id`),
  KEY `idx_bons_retour_date` (`date_retour`),
  CONSTRAINT `fk_bons_retour_bl` FOREIGN KEY (`bl_id`) REFERENCES `bons_livraison` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "+ table bons_retour\n";

    $db->exec("
CREATE TABLE IF NOT EXISTS `bons_retour_lignes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bon_retour_id` int(11) NOT NULL,
  `bl_ligne_id` int(11) NOT NULL,
  `produit_id` int(11) DEFAULT NULL,
  `designation` varchar(512) NOT NULL,
  `quantite_retour` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `prix_unitaire_ht` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `total_ligne_ht` decimal(12,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_brl_bon_retour` (`bon_retour_id`),
  KEY `idx_brl_bl_ligne` (`bl_ligne_id`),
  CONSTRAINT `fk_brl_bon_retour` FOREIGN KEY (`bon_retour_id`) REFERENCES `bons_retour` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_brl_bl_ligne` FOREIGN KEY (`bl_ligne_id`) REFERENCES `bl_lignes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "+ table bons_retour_lignes\n";

    $db->exec('SET FOREIGN_KEY_CHECKS = 1');
    echo "\nMigration bons_retour terminée.\n";
} catch (PDOException $e) {
    try {
        $db->exec('SET FOREIGN_KEY_CHECKS = 1');
    } catch (PDOException $ignored) {
    }
    fwrite(STDERR, "Erreur : " . $e->getMessage() . "\n");
    exit(1);
}
