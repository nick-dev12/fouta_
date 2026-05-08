-- Sous-catégories + prix d'achat + lien produit (types alignés sur categories.id INT)
CREATE TABLE IF NOT EXISTS `sous_categories` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `categorie_id` INT(11) NOT NULL,
  `nom` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sous_categories_categorie` (`categorie_id`),
  CONSTRAINT `fk_sous_categories_categorie` FOREIGN KEY (`categorie_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `produits` ADD COLUMN `prix_achat` DECIMAL(10,2) NULL DEFAULT NULL;
ALTER TABLE `produits` ADD COLUMN `sous_categorie_id` INT(11) NULL DEFAULT NULL;
ALTER TABLE `produits` ADD KEY `idx_produits_sous_categorie` (`sous_categorie_id`);
ALTER TABLE `produits` ADD CONSTRAINT `fk_produits_sous_categorie` FOREIGN KEY (`sous_categorie_id`) REFERENCES `sous_categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
