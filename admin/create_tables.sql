-- Script SQL pour créer les tables categories et produits
-- À exécuter dans votre base de données avant d'utiliser l'application

-- Table categories
CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `nom` VARCHAR(255) NOT NULL UNIQUE,
  `description` TEXT NULL,
  `image` VARCHAR(255) NULL,
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_nom` (`nom`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table produits
CREATE TABLE IF NOT EXISTS `produits` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `nom` VARCHAR(255) NOT NULL,
  `description` TEXT NOT NULL,
  `prix` DECIMAL(10,2) NOT NULL,
  `prix_promotion` DECIMAL(10,2) NULL DEFAULT NULL,
  `stock` INT(11) NOT NULL DEFAULT 0,
  `categorie_id` INT(11) NOT NULL,
  `image_principale` VARCHAR(255) NULL,
  `images` TEXT NULL,
  `poids` VARCHAR(50) NULL,
  `unite` VARCHAR(20) NOT NULL DEFAULT 'unité',
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `statut` ENUM('actif', 'inactif', 'rupture_stock') NOT NULL DEFAULT 'actif',
  PRIMARY KEY (`id`),
  KEY `idx_categorie` (`categorie_id`),
  KEY `idx_statut` (`statut`),
  CONSTRAINT `fk_produits_categorie` FOREIGN KEY (`categorie_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insérer les catégories par défaut
INSERT IGNORE INTO `categories` (`nom`, `description`) VALUES
('Les Noix', 'Noix diverses (noix de cajou, noix de coco, amandes, etc.)'),
('Les Feuilles', 'Feuilles médicinales et aromatiques'),
('Les Fruits', 'Fruits naturels de saison'),
('Les Huiles', 'Huiles végétales naturelles (huile de palme, huile de coco, etc.)'),
('Les Céréales', 'Céréales diverses (riz, mil, maïs, etc.)'),
('Les Racines', 'Racines médicinales et comestibles'),
('Les Cosmétiques', 'Produits cosmétiques naturels (savons, crèmes, baumes, etc.)');

