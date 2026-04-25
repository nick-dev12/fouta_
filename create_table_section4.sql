-- Script SQL pour créer la table `section4_config`
-- Cette table stocke la configuration de la section4 de la page d'accueil

CREATE TABLE IF NOT EXISTS `section4_config` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `titre` VARCHAR(255) NOT NULL DEFAULT 'Bienvenue au Sugar Paper',
  `texte` VARCHAR(255) NOT NULL DEFAULT 'Tous les produits a petit prix',
  `image_fond` VARCHAR(255) NULL DEFAULT NULL,
  `date_modification` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insérer une configuration par défaut
INSERT INTO `section4_config` (`titre`, `texte`, `image_fond`) 
VALUES ('Bienvenue au Sugar Paper', 'Tous les produits a petit prix', 'market.png')
ON DUPLICATE KEY UPDATE `titre` = VALUES(`titre`);

