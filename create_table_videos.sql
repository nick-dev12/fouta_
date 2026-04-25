-- ============================================
-- Script SQL pour créer la table `videos`
-- ============================================
-- Cette table stocke les vidéos pour la section carrousel vidéo
-- de la page d'accueil "Ils ont découvert ICON"
-- ============================================

CREATE TABLE IF NOT EXISTS `videos` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `titre` VARCHAR(255) NOT NULL COMMENT 'Titre de la vidéo',
  `fichier_video` VARCHAR(255) NOT NULL COMMENT 'Nom du fichier vidéo uploadé',
  `image_preview` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Nom du fichier image de prévisualisation (thumbnail)',
  `statut` ENUM('actif', 'inactif') NOT NULL DEFAULT 'actif' COMMENT 'Statut de la vidéo (actif = visible, inactif = masquée)',
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date de création de la vidéo',
  `date_modification` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Date de dernière modification',
  PRIMARY KEY (`id`),
  INDEX `idx_statut` (`statut`),
  INDEX `idx_image_preview` (`image_preview`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Table des vidéos pour le carrousel de la page d\'accueil';

-- ============================================
-- Notes importantes :
-- ============================================
-- Les fichiers vidéos uploadés sont stockés dans : /upload/videos/
-- ============================================
