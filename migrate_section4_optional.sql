-- Migration: rendre titre et texte optionnels, ajouter statut actif/inactif

ALTER TABLE `section4_config` 
MODIFY COLUMN `titre` VARCHAR(255) NULL DEFAULT NULL,
MODIFY COLUMN `texte` VARCHAR(255) NULL DEFAULT NULL,
ADD COLUMN `statut` ENUM('actif', 'inactif') NOT NULL DEFAULT 'actif' AFTER `image_fond`;
