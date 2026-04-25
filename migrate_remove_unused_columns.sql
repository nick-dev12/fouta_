-- ============================================
-- Script de migration pour supprimer les colonnes inutiles
-- ============================================
-- À exécuter UNIQUEMENT si la table videos existe déjà
-- et que vous voulez supprimer les colonnes : description, image_preview, overlay_texte, ordre
-- ============================================

-- ============================================
-- IMPORTANT : Exécutez ces commandes UNE PAR UNE
-- Si vous obtenez une erreur "Unknown column", 
-- c'est que la colonne n'existe pas - ignorez l'erreur et continuez
-- ============================================

-- Étape 1 : Supprimer la colonne description (si elle existe)
ALTER TABLE `videos` 
DROP COLUMN `description`;

-- Étape 2 : Supprimer la colonne image_preview (si elle existe)
ALTER TABLE `videos` 
DROP COLUMN `image_preview`;

-- Étape 3 : Supprimer la colonne overlay_texte (si elle existe)
ALTER TABLE `videos` 
DROP COLUMN `overlay_texte`;

-- Étape 4 : Supprimer la colonne ordre (si elle existe)
ALTER TABLE `videos` 
DROP COLUMN `ordre`;

-- Étape 5 : Supprimer l'index idx_ordre (si il existe)
-- Note : Cette commande peut échouer si l'index n'existe pas, c'est normal
ALTER TABLE `videos` 
DROP INDEX `idx_ordre`;

