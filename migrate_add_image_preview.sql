-- ============================================
-- Script SQL pour ajouter la colonne image_preview
-- à la table videos
-- ============================================
-- Cette migration ajoute un champ pour stocker
-- l'image de prévisualisation (thumbnail) de la vidéo
-- ============================================

-- Ajouter la colonne image_preview
ALTER TABLE `videos` 
ADD COLUMN `image_preview` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Nom du fichier image de prévisualisation (thumbnail)' AFTER `fichier_video`;

-- Créer un index pour améliorer les performances (optionnel)
CREATE INDEX `idx_image_preview` ON `videos` (`image_preview`);
