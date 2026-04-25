-- =============================================================================
-- MIGRATION PRODUCTION - AJOUTS UNIQUEMENT (aucune suppression de données)
-- =============================================================================
-- Ce fichier ajoute les tables et colonnes manquantes à une base existante.
-- Aucune donnée existante n'est modifiée ou supprimée.
--
-- Prérequis : MySQL 8.0.29+ (pour ADD COLUMN IF NOT EXISTS)
-- Si MySQL < 8.0.29 : utiliser le script PHP à la place :
--   php migrations/run_migration_production_ajouts.php
--
-- Exécution : mysql -u user -p database < migrations/migration_production_ajouts.sql
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =============================================================================
-- PARTIE 1 : CRÉATION DES TABLES MANQUANTES (CREATE TABLE IF NOT EXISTS)
-- =============================================================================

-- zones_livraison
CREATE TABLE IF NOT EXISTS `zones_livraison` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `ville` VARCHAR(100) NOT NULL,
  `quartier` VARCHAR(150) NOT NULL,
  `prix_livraison` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `description` VARCHAR(255) NULL DEFAULT NULL,
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `statut` ENUM('actif', 'inactif') NOT NULL DEFAULT 'actif',
  PRIMARY KEY (`id`),
  KEY `idx_ville` (`ville`),
  KEY `idx_statut` (`statut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- stock_articles
CREATE TABLE IF NOT EXISTS `stock_articles` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `nom` VARCHAR(255) NOT NULL,
  `image_principale` VARCHAR(255) NULL,
  `quantite` INT(11) NOT NULL DEFAULT 0,
  `categorie_id` INT(11) NOT NULL,
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_categorie` (`categorie_id`),
  CONSTRAINT `fk_stock_articles_categorie` FOREIGN KEY (`categorie_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- produits_variantes
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

-- factures
CREATE TABLE IF NOT EXISTS `factures` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `commande_id` INT(11) NOT NULL,
  `numero_facture` VARCHAR(50) NOT NULL,
  `date_facture` DATE NOT NULL,
  `montant_total` DECIMAL(10,2) NOT NULL,
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `token` VARCHAR(64) NULL DEFAULT NULL UNIQUE,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_commande` (`commande_id`),
  KEY `idx_numero` (`numero_facture`),
  KEY `idx_token` (`token`),
  CONSTRAINT `fk_factures_commande` FOREIGN KEY (`commande_id`) REFERENCES `commandes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- stock_mouvements
CREATE TABLE IF NOT EXISTS `stock_mouvements` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `type` ENUM('entree', 'sortie', 'inventaire') NOT NULL,
  `stock_article_id` INT(11) NULL DEFAULT NULL,
  `produit_id` INT(11) NULL DEFAULT NULL,
  `quantite` INT(11) NOT NULL,
  `quantite_avant` INT(11) NULL DEFAULT NULL,
  `quantite_apres` INT(11) NULL DEFAULT NULL,
  `reference_type` VARCHAR(50) NULL DEFAULT NULL,
  `reference_id` INT(11) NULL DEFAULT NULL,
  `reference_numero` VARCHAR(100) NULL DEFAULT NULL,
  `date_mouvement` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `notes` TEXT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_stock_article` (`stock_article_id`),
  KEY `idx_produit` (`produit_id`),
  KEY `idx_type` (`type`),
  KEY `idx_date` (`date_mouvement`),
  CONSTRAINT `fk_mouvements_stock_article` FOREIGN KEY (`stock_article_id`) REFERENCES `stock_articles` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_mouvements_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- devis
CREATE TABLE IF NOT EXISTS `devis` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `numero_devis` VARCHAR(50) NOT NULL UNIQUE,
  `client_nom` VARCHAR(100) NOT NULL,
  `client_prenom` VARCHAR(100) NOT NULL,
  `client_telephone` VARCHAR(50) NOT NULL,
  `client_email` VARCHAR(255) NULL DEFAULT NULL,
  `adresse_livraison` TEXT NOT NULL,
  `zone_livraison_id` INT(11) NULL DEFAULT NULL,
  `frais_livraison` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `user_id` INT(11) NULL DEFAULT NULL,
  `montant_total` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `notes` TEXT NULL DEFAULT NULL,
  `statut` ENUM('brouillon', 'envoye', 'accepte', 'refuse') NOT NULL DEFAULT 'brouillon',
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_numero_devis` (`numero_devis`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_zone_livraison_id` (`zone_livraison_id`),
  KEY `idx_statut` (`statut`),
  KEY `idx_date_creation` (`date_creation`),
  CONSTRAINT `fk_devis_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_devis_zone` FOREIGN KEY (`zone_livraison_id`) REFERENCES `zones_livraison` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- devis_produits
CREATE TABLE IF NOT EXISTS `devis_produits` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `devis_id` INT(11) NOT NULL,
  `produit_id` INT(11) NOT NULL,
  `nom_produit` VARCHAR(255) NULL DEFAULT NULL,
  `quantite` INT(11) NOT NULL DEFAULT 1,
  `prix_unitaire` DECIMAL(10,2) NOT NULL,
  `prix_total` DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_devis_id` (`devis_id`),
  KEY `idx_produit_id` (`produit_id`),
  CONSTRAINT `fk_devis_produits_devis` FOREIGN KEY (`devis_id`) REFERENCES `devis` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_devis_produits_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- factures_devis
CREATE TABLE IF NOT EXISTS `factures_devis` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `devis_id` INT(11) NOT NULL,
  `numero_facture` VARCHAR(50) NOT NULL,
  `date_facture` DATE NOT NULL,
  `montant_total` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `token` VARCHAR(64) NULL DEFAULT NULL UNIQUE,
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_numero_facture` (`numero_facture`),
  UNIQUE KEY `idx_devis_id` (`devis_id`),
  KEY `idx_token` (`token`),
  CONSTRAINT `fk_factures_devis_devis` FOREIGN KEY (`devis_id`) REFERENCES `devis` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- commandes_personnalisees
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

-- contacts
CREATE TABLE IF NOT EXISTS `contacts` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `nom` VARCHAR(255) NOT NULL,
  `prenom` VARCHAR(255) NOT NULL DEFAULT '',
  `telephone` VARCHAR(50) NOT NULL,
  `email` VARCHAR(255) NULL,
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_telephone` (`telephone`),
  KEY `idx_nom` (`nom`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- produits_visites
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

-- favoris
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

-- slider
CREATE TABLE IF NOT EXISTS `slider` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `titre` VARCHAR(255) NOT NULL,
  `paragraphe` TEXT NULL DEFAULT NULL,
  `image` VARCHAR(255) NOT NULL,
  `bouton_texte` VARCHAR(100) NULL DEFAULT NULL,
  `bouton_lien` VARCHAR(255) NULL DEFAULT NULL,
  `ordre` INT(11) NOT NULL DEFAULT 0,
  `statut` ENUM('actif', 'inactif') NOT NULL DEFAULT 'actif',
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_statut` (`statut`),
  KEY `idx_ordre` (`ordre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- section4_config
CREATE TABLE IF NOT EXISTS `section4_config` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `titre` VARCHAR(255) NOT NULL DEFAULT 'Bienvenue au Sugar Paper',
  `texte` VARCHAR(255) NOT NULL DEFAULT 'Tous les produits a petit prix',
  `image_fond` VARCHAR(255) NULL DEFAULT NULL,
  `date_modification` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- trending_config
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

-- videos
CREATE TABLE IF NOT EXISTS `videos` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `titre` VARCHAR(255) NOT NULL COMMENT 'Titre de la vidéo',
  `fichier_video` VARCHAR(255) NOT NULL COMMENT 'Nom du fichier vidéo uploadé',
  `image_preview` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Image de prévisualisation',
  `statut` ENUM('actif', 'inactif') NOT NULL DEFAULT 'actif',
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_statut` (`statut`),
  KEY `idx_image_preview` (`image_preview`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- PARTIE 2 : AJOUT DES COLONNES MANQUANTES (ALTER TABLE ADD COLUMN IF NOT EXISTS)
-- MySQL 8.0.29+ requis pour IF NOT EXISTS. Sinon, ignorer les erreurs "Duplicate column".
-- =============================================================================

-- users
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `accepte_conditions` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Acceptation des conditions (0=non, 1=oui)' AFTER `statut`;

-- admin
ALTER TABLE `admin` ADD COLUMN IF NOT EXISTS `role` ENUM('admin', 'utilisateur') NOT NULL DEFAULT 'admin' AFTER `statut`;

-- produits
ALTER TABLE `produits` ADD COLUMN IF NOT EXISTS `couleurs` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Couleurs disponibles' AFTER `unite`;
ALTER TABLE `produits` ADD COLUMN IF NOT EXISTS `taille` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Tailles disponibles' AFTER `couleurs`;
ALTER TABLE `produits` ADD COLUMN IF NOT EXISTS `stock_article_id` INT(11) NULL DEFAULT NULL AFTER `categorie_id`;

-- commandes (zone_livraison_id, frais_livraison, client_*)
ALTER TABLE `commandes` ADD COLUMN IF NOT EXISTS `zone_livraison_id` INT(11) NULL DEFAULT NULL AFTER `adresse_livraison`;
ALTER TABLE `commandes` ADD COLUMN IF NOT EXISTS `frais_livraison` DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER `zone_livraison_id`;
ALTER TABLE `commandes` ADD COLUMN IF NOT EXISTS `client_nom` VARCHAR(255) NULL DEFAULT NULL AFTER `user_id`;
ALTER TABLE `commandes` ADD COLUMN IF NOT EXISTS `client_prenom` VARCHAR(255) NULL DEFAULT NULL AFTER `client_nom`;
ALTER TABLE `commandes` ADD COLUMN IF NOT EXISTS `client_email` VARCHAR(255) NULL DEFAULT NULL AFTER `client_prenom`;
ALTER TABLE `commandes` ADD COLUMN IF NOT EXISTS `client_telephone` VARCHAR(50) NULL DEFAULT NULL AFTER `client_email`;

-- commande_produits
ALTER TABLE `commande_produits` ADD COLUMN IF NOT EXISTS `nom_produit` VARCHAR(255) NULL DEFAULT NULL AFTER `produit_id`;
ALTER TABLE `commande_produits` ADD COLUMN IF NOT EXISTS `couleur` VARCHAR(255) NULL DEFAULT NULL AFTER `prix_total`;
ALTER TABLE `commande_produits` ADD COLUMN IF NOT EXISTS `poids` VARCHAR(100) NULL DEFAULT NULL AFTER `couleur`;
ALTER TABLE `commande_produits` ADD COLUMN IF NOT EXISTS `taille` VARCHAR(100) NULL DEFAULT NULL AFTER `poids`;
ALTER TABLE `commande_produits` ADD COLUMN IF NOT EXISTS `variante_id` INT(11) NULL DEFAULT NULL AFTER `taille`;
ALTER TABLE `commande_produits` ADD COLUMN IF NOT EXISTS `variante_nom` VARCHAR(255) NULL DEFAULT NULL AFTER `variante_id`;
ALTER TABLE `commande_produits` ADD COLUMN IF NOT EXISTS `surcout_poids` DECIMAL(10,2) NULL DEFAULT 0 AFTER `variante_nom`;
ALTER TABLE `commande_produits` ADD COLUMN IF NOT EXISTS `surcout_taille` DECIMAL(10,2) NULL DEFAULT 0 AFTER `surcout_poids`;

-- panier
ALTER TABLE `panier` ADD COLUMN IF NOT EXISTS `couleur` VARCHAR(255) NULL DEFAULT NULL AFTER `quantite`;
ALTER TABLE `panier` ADD COLUMN IF NOT EXISTS `poids` VARCHAR(100) NULL DEFAULT NULL AFTER `couleur`;
ALTER TABLE `panier` ADD COLUMN IF NOT EXISTS `taille` VARCHAR(100) NULL DEFAULT NULL AFTER `poids`;
ALTER TABLE `panier` ADD COLUMN IF NOT EXISTS `variante_id` INT(11) NULL DEFAULT NULL AFTER `taille`;
ALTER TABLE `panier` ADD COLUMN IF NOT EXISTS `variante_nom` VARCHAR(255) NULL DEFAULT NULL AFTER `variante_id`;
ALTER TABLE `panier` ADD COLUMN IF NOT EXISTS `variante_image` VARCHAR(255) NULL DEFAULT NULL AFTER `variante_nom`;
ALTER TABLE `panier` ADD COLUMN IF NOT EXISTS `surcout_poids` DECIMAL(10,2) NULL DEFAULT 0 AFTER `variante_image`;
ALTER TABLE `panier` ADD COLUMN IF NOT EXISTS `surcout_taille` DECIMAL(10,2) NULL DEFAULT 0 AFTER `surcout_poids`;
ALTER TABLE `panier` ADD COLUMN IF NOT EXISTS `prix_unitaire` DECIMAL(10,2) NULL DEFAULT NULL AFTER `surcout_taille`;

-- factures (si table existe sans token)
ALTER TABLE `factures` ADD COLUMN IF NOT EXISTS `token` VARCHAR(64) NULL DEFAULT NULL UNIQUE AFTER `date_creation`;

-- commandes_personnalisees (si table existe sans image_reference)
ALTER TABLE `commandes_personnalisees` ADD COLUMN IF NOT EXISTS `image_reference` VARCHAR(255) NULL DEFAULT NULL AFTER `description`;

-- =============================================================================
-- PARTIE 3 : MODIFICATION DU STATUT COMMANDES (nouveaux statuts)
-- Exécuter uniquement si votre ENUM actuel n'inclut pas ces valeurs.
-- =============================================================================
ALTER TABLE `commandes` MODIFY COLUMN `statut` ENUM(
  'en_attente', 'confirmee', 'prise_en_charge', 'en_preparation',
  'livraison_en_cours', 'expediee', 'livree', 'annulee'
) NOT NULL DEFAULT 'en_attente';

-- =============================================================================
-- PARTIE 4 : CONTRAINTES FK OPTIONNELLES (à exécuter manuellement si besoin)
-- Ces commandes peuvent échouer si les FK existent déjà ou si les tables
-- référencées n'existent pas. Ignorer les erreurs si c'est le cas.
-- =============================================================================
-- ALTER TABLE `produits` ADD CONSTRAINT `fk_produits_stock_article` FOREIGN KEY (`stock_article_id`) REFERENCES `stock_articles` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
-- ALTER TABLE `commandes` ADD CONSTRAINT `fk_commandes_zone` FOREIGN KEY (`zone_livraison_id`) REFERENCES `zones_livraison` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- =============================================================================
-- PARTIE 5 : COMMANDES MANUELLES (user_id nullable)
-- À exécuter manuellement si vous avez besoin de commandes sans utilisateur.
-- Décommenter et adapter le nom de la FK (fk_commandes_user peut varier).
-- =============================================================================
-- Étape 1 : Supprimer la FK user_id
-- ALTER TABLE `commandes` DROP FOREIGN KEY `fk_commandes_user`;
-- Étape 2 : Rendre user_id nullable
-- ALTER TABLE `commandes` MODIFY `user_id` INT(11) NULL DEFAULT NULL;
-- Étape 3 : Réajouter la FK
-- ALTER TABLE `commandes` ADD CONSTRAINT `fk_commandes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- FIN DE LA MIGRATION
-- =============================================================================
