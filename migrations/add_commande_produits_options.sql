-- Migration: Ajouter couleur, poids, taille aux détails de commande
-- À exécuter dans la base de données

ALTER TABLE `commande_produits`
ADD COLUMN `couleur` VARCHAR(255) NULL DEFAULT NULL AFTER `prix_total`,
ADD COLUMN `poids` VARCHAR(100) NULL DEFAULT NULL AFTER `couleur`,
ADD COLUMN `taille` VARCHAR(100) NULL DEFAULT NULL AFTER `poids`;
