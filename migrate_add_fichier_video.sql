-- Script de migration pour ajouter la colonne fichier_video
-- À exécuter UNIQUEMENT si la table videos existe déjà

-- Étape 1 : Ajouter la colonne fichier_video (si elle n'existe pas)
-- Si vous obtenez une erreur "Duplicate column name", c'est que la colonne existe déjà
ALTER TABLE `videos` 
ADD COLUMN `fichier_video` VARCHAR(255) NULL COMMENT 'Nom du fichier vidéo uploadé' AFTER `url_video`;

-- Étape 2 : Modifier url_video pour permettre NULL (si ce n'est pas déjà le cas)
ALTER TABLE `videos` 
MODIFY COLUMN `url_video` VARCHAR(500) NULL COMMENT 'URL de la vidéo (YouTube, Vimeo, ou autre lien)';

-- Étape 3 : Mettre à jour les anciennes valeurs de type_video si nécessaire
-- Si vous avez des valeurs comme 'youtube', 'vimeo', etc., les convertir en 'lien'
UPDATE `videos` 
SET `type_video` = 'lien' 
WHERE `type_video` IN ('youtube', 'vimeo', 'local', 'url');

-- Étape 4 : Modifier l'ENUM de type_video pour utiliser 'lien' et 'upload'
-- ATTENTION : Cette commande peut échouer si des valeurs non conformes existent encore
ALTER TABLE `videos` 
MODIFY COLUMN `type_video` ENUM('lien', 'upload') NOT NULL DEFAULT 'lien';

