-- Table des logos partenaires (affichés en carrousel sur la page d'accueil)
CREATE TABLE IF NOT EXISTS `logos` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `image` VARCHAR(255) NOT NULL,
  `ordre` INT(11) NOT NULL DEFAULT 0,
  `statut` ENUM('actif', 'inactif') NOT NULL DEFAULT 'actif',
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_statut` (`statut`),
  KEY `idx_ordre` (`ordre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
