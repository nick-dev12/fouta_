-- ============================================
-- Script SQL pour simplifier la table `videos`
-- ============================================
-- Ce script supprime les colonnes liées aux liens YouTube/Vimeo
-- et garde uniquement : id, titre, fichier_video, statut, date_creation, date_modification
-- ============================================
-- IMPORTANT : Exécutez ces commandes UNE PAR UNE dans phpMyAdmin
-- MySQL ne supporte pas "IF EXISTS" pour DROP COLUMN
-- ============================================

-- 1. Supprimer la colonne url_video
ALTER TABLE `videos` DROP COLUMN `url_video`;

-- 2. Supprimer la colonne type_video
ALTER TABLE `videos` DROP COLUMN `type_video`;

-- 3. Supprimer l'index idx_type_video (s'il existe)
-- Note : Si l'index n'existe pas, vous pouvez ignorer l'erreur
ALTER TABLE `videos` DROP INDEX `idx_type_video`;

-- 4. Modifier la colonne fichier_video pour qu'elle soit NOT NULL
ALTER TABLE `videos` MODIFY COLUMN `fichier_video` VARCHAR(255) NOT NULL COMMENT 'Nom du fichier vidéo uploadé';

-- ============================================
-- Vérification de la structure finale
-- ============================================
-- La table devrait maintenant avoir uniquement :
-- - id (INT, PRIMARY KEY, AUTO_INCREMENT)
-- - titre (VARCHAR(255), NOT NULL)
-- - fichier_video (VARCHAR(255), NOT NULL)
-- - statut (ENUM('actif', 'inactif'), NOT NULL, DEFAULT 'actif')
-- - date_creation (DATETIME, NOT NULL, DEFAULT CURRENT_TIMESTAMP)
-- - date_modification (DATETIME, NULL, DEFAULT NULL, ON UPDATE CURRENT_TIMESTAMP)
-- ============================================
