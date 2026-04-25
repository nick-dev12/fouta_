-- Table pour les commandes personnalisées (demandes sur mesure des clients)
-- À exécuter dans votre base de données (phpMyAdmin ou client MySQL)

CREATE TABLE IF NOT EXISTS `commandes_personnalisees` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NULL DEFAULT NULL,
  `nom` VARCHAR(100) NOT NULL,
  `prenom` VARCHAR(100) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `telephone` VARCHAR(50) NOT NULL,
  `description` TEXT NOT NULL,
  `image_reference` VARCHAR(255) NULL DEFAULT NULL,
  `type_produit` VARCHAR(255) NULL DEFAULT NULL,
  `quantite` VARCHAR(100) NULL DEFAULT NULL,
  `date_souhaitee` DATE NULL DEFAULT NULL,
  `statut` ENUM('en_attente', 'confirmee', 'en_preparation', 'devis_envoye', 'acceptee', 'refusee', 'terminee', 'annulee') NOT NULL DEFAULT 'en_attente',
  `notes_admin` TEXT NULL DEFAULT NULL,
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_statut` (`statut`),
  KEY `idx_date_creation` (`date_creation`),
  CONSTRAINT `fk_cp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
