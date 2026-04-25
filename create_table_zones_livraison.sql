-- Script SQL pour créer la table des zones de livraison
-- Permet à l'admin de définir les lieux (ville/quartier) et les prix de livraison

CREATE TABLE IF NOT EXISTS `zones_livraison` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `ville` VARCHAR(100) NOT NULL,
  `quartier` VARCHAR(150) NOT NULL,
  `prix_livraison` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `description` VARCHAR(255) NULL DEFAULT NULL,
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `statut` ENUM('actif', 'inactif') NOT NULL DEFAULT 'actif',
  PRIMARY KEY (`id`),
  KEY `idx_ville` (`ville`),
  KEY `idx_statut` (`statut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
