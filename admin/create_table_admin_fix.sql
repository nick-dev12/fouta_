-- Script SQL pour créer la table admin (version corrigée)
-- Ce script supprime d'abord la table si elle existe, puis la recrée
-- À exécuter dans votre base de données

DROP TABLE IF EXISTS `admin`;

CREATE TABLE `admin` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `nom` VARCHAR(100) NOT NULL,
  `prenom` VARCHAR(100) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `date_creation` DATETIME NOT NULL,
  `derniere_connexion` DATETIME NULL DEFAULT NULL,
  `statut` ENUM('actif', 'inactif') NOT NULL DEFAULT 'actif',
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

