-- Script SQL pour créer les tables supplémentaires pour les fonctionnalités utilisateur
-- À exécuter dans votre base de données

-- Table: produits_visites (Produits visités par les utilisateurs)
CREATE TABLE IF NOT EXISTS `produits_visites` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `produit_id` INT(11) NOT NULL,
  `date_visite` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_produit_id` (`produit_id`),
  KEY `idx_date_visite` (`date_visite`),
  UNIQUE KEY `idx_user_produit` (`user_id`, `produit_id`),
  CONSTRAINT `fk_visites_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_visites_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: favoris (Produits favoris des utilisateurs)
CREATE TABLE IF NOT EXISTS `favoris` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `produit_id` INT(11) NOT NULL,
  `date_ajout` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_produit_id` (`produit_id`),
  UNIQUE KEY `idx_user_produit` (`user_id`, `produit_id`),
  CONSTRAINT `fk_favoris_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_favoris_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: commandes (si elle n'existe pas déjà)
CREATE TABLE IF NOT EXISTS `commandes` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `numero_commande` VARCHAR(50) NOT NULL UNIQUE,
  `montant_total` DECIMAL(10,2) NOT NULL,
  `adresse_livraison` TEXT NOT NULL,
  `telephone_livraison` VARCHAR(50) NOT NULL,
  `statut` ENUM('en_attente', 'confirmee', 'en_preparation', 'expediee', 'livree', 'annulee') NOT NULL DEFAULT 'en_attente',
  `date_commande` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_livraison` DATETIME NULL DEFAULT NULL,
  `notes` TEXT NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_statut` (`statut`),
  KEY `idx_date_commande` (`date_commande`),
  CONSTRAINT `fk_commandes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: commande_produits (Détails des commandes)
CREATE TABLE IF NOT EXISTS `commande_produits` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `commande_id` INT(11) NOT NULL,
  `produit_id` INT(11) NOT NULL,
  `quantite` INT(11) NOT NULL,
  `prix_unitaire` DECIMAL(10,2) NOT NULL,
  `prix_total` DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_commande_id` (`commande_id`),
  KEY `idx_produit_id` (`produit_id`),
  CONSTRAINT `fk_commande_produits_commande` FOREIGN KEY (`commande_id`) REFERENCES `commandes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_commande_produits_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

