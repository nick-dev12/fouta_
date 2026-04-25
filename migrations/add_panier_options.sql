-- Migration: Ajouter couleur, poids, taille à la table panier
-- À exécuter dans la base de données

ALTER TABLE `panier`
ADD COLUMN `couleur` VARCHAR(255) NULL DEFAULT NULL AFTER `quantite`,
ADD COLUMN `poids` VARCHAR(100) NULL DEFAULT NULL AFTER `couleur`,
ADD COLUMN `taille` VARCHAR(100) NULL DEFAULT NULL AFTER `poids`;
