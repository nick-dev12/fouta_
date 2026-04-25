-- Migration: ajouter zone_livraison_id et frais_livraison à la table commandes
-- Exécuter après create_table_zones_livraison.sql

ALTER TABLE `commandes` 
ADD COLUMN `zone_livraison_id` INT(11) NULL DEFAULT NULL AFTER `adresse_livraison`,
ADD COLUMN `frais_livraison` DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER `zone_livraison_id`;
