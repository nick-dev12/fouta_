-- Script SQL pour créer la table admin
-- À exécuter dans votre base de données avant d'utiliser l'application
-- 
-- Si vous avez déjà une table admin avec des erreurs, utilisez le fichier
-- create_table_admin_fix.sql qui supprime et recrée la table

CREATE TABLE IF NOT EXISTS `admin` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `nom` VARCHAR(100) NOT NULL,
  `prenom` VARCHAR(100) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `date_creation` DATETIME NOT NULL,
  `derniere_connexion` DATETIME NULL DEFAULT NULL,
  `statut` ENUM('actif', 'inactif') NOT NULL DEFAULT 'actif',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

