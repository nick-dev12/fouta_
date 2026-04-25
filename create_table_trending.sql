-- Script SQL pour créer la table `trending_config`
-- Cette table stocke la configuration de la section trending de la page d'accueil

CREATE TABLE IF NOT EXISTS `trending_config` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `label` VARCHAR(255) NOT NULL DEFAULT 'categories',
  `titre` VARCHAR(255) NOT NULL DEFAULT 'Enhance Your Music Experience',
  `bouton_texte` VARCHAR(255) NOT NULL DEFAULT 'Buy Now!',
  `bouton_lien` VARCHAR(255) NULL DEFAULT '#',
  `image` VARCHAR(255) NULL DEFAULT 'speaker.png',
  `date_modification` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insérer une configuration par défaut
INSERT INTO `trending_config` (`label`, `titre`, `bouton_texte`, `bouton_lien`, `image`) 
VALUES ('categories', 'Enhance Your Music Experience', 'Buy Now!', '#', 'speaker.png')
ON DUPLICATE KEY UPDATE `label` = VALUES(`label`);

