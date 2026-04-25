-- Migration: Ajout des colonnes couleurs et taille (optionnelles) à la table produits
-- Exécuter une seule fois. Ignorer l'erreur si les colonnes existent déjà.

ALTER TABLE `produits` ADD COLUMN `couleurs` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Couleurs disponibles (ex: Rouge, Bleu, Vert)';
ALTER TABLE `produits` ADD COLUMN `taille` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Tailles disponibles (ex: S, M, L ou 21cm, 14.8cm)';
