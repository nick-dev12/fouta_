-- Script SQL pour créer la table panier
-- À exécuter dans votre base de données

CREATE TABLE IF NOT EXISTS `panier` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `produit_id` INT(11) NOT NULL,
  `quantite` INT(11) NOT NULL DEFAULT 1,
  `date_ajout` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_produit_id` (`produit_id`),
  KEY `idx_date_ajout` (`date_ajout`),
  UNIQUE KEY `idx_user_produit` (`user_id`, `produit_id`),
  CONSTRAINT `fk_panier_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_panier_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

