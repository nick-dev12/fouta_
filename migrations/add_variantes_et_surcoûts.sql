-- Migration: Variantes produit + surcoûts poids/taille
-- Exécuter une seule fois

-- Table variantes produit (nom, prix, image différents du produit de base)
CREATE TABLE IF NOT EXISTS `produits_variantes` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `produit_id` INT(11) NOT NULL,
  `nom` VARCHAR(255) NOT NULL,
  `prix` DECIMAL(10,2) NOT NULL,
  `prix_promotion` DECIMAL(10,2) NULL DEFAULT NULL,
  `image` VARCHAR(255) NULL DEFAULT NULL,
  `ordre` INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_produit_id` (`produit_id`),
  CONSTRAINT `fk_variantes_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Panier: variante + prix unitaire final + surcoûts
ALTER TABLE `panier` ADD COLUMN `variante_id` INT(11) NULL DEFAULT NULL;
ALTER TABLE `panier` ADD COLUMN `variante_nom` VARCHAR(255) NULL DEFAULT NULL;
ALTER TABLE `panier` ADD COLUMN `variante_image` VARCHAR(255) NULL DEFAULT NULL;
ALTER TABLE `panier` ADD COLUMN `surcout_poids` DECIMAL(10,2) NULL DEFAULT 0;
ALTER TABLE `panier` ADD COLUMN `surcout_taille` DECIMAL(10,2) NULL DEFAULT 0;
ALTER TABLE `panier` ADD COLUMN `prix_unitaire` DECIMAL(10,2) NULL DEFAULT NULL;

-- Commande_produits: variante + surcoûts (prix_unitaire existe déjà)
ALTER TABLE `commande_produits` ADD COLUMN `variante_id` INT(11) NULL DEFAULT NULL;
ALTER TABLE `commande_produits` ADD COLUMN `variante_nom` VARCHAR(255) NULL DEFAULT NULL;
ALTER TABLE `commande_produits` ADD COLUMN `surcout_poids` DECIMAL(10,2) NULL DEFAULT 0;
ALTER TABLE `commande_produits` ADD COLUMN `surcout_taille` DECIMAL(10,2) NULL DEFAULT 0;
