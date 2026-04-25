-- Script SQL pour créer la table slider
-- À exécuter dans votre base de données

CREATE TABLE IF NOT EXISTS `slider` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `titre` VARCHAR(255) NOT NULL,
  `paragraphe` TEXT NULL DEFAULT NULL,
  `image` VARCHAR(255) NOT NULL,
  `bouton_texte` VARCHAR(100) NULL DEFAULT NULL,
  `bouton_lien` VARCHAR(255) NULL DEFAULT NULL,
  `ordre` INT(11) NOT NULL DEFAULT 0,
  `statut` ENUM('actif', 'inactif') NOT NULL DEFAULT 'actif',
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_statut` (`statut`),
  KEY `idx_ordre` (`ordre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

