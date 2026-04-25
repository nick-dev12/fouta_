-- =============================================================================
-- SCHÉMA COMPLET - SUGAR PAPER (Production)
-- =============================================================================
-- Ce fichier crée toutes les tables nécessaires pour une installation en production.
-- Exécuter sur une base vide : mysql -u user -p database < migrations/schema_production_complet.sql
--
-- Ordre des tables respectant les dépendances (clés étrangères).
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- 1. USERS (utilisateurs clients)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `nom` VARCHAR(100) NOT NULL,
  `prenom` VARCHAR(100) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `telephone` VARCHAR(50) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `statut` ENUM('actif', 'inactif') NOT NULL DEFAULT 'actif',
  `accepte_conditions` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Acceptation des conditions (0=non, 1=oui)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2. CATEGORIES
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `nom` VARCHAR(255) NOT NULL UNIQUE,
  `description` TEXT NULL,
  `image` VARCHAR(255) NULL,
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_nom` (`nom`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 3. ADMIN (administrateurs)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admin` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `nom` VARCHAR(100) NOT NULL,
  `prenom` VARCHAR(100) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `date_creation` DATETIME NOT NULL,
  `derniere_connexion` DATETIME NULL DEFAULT NULL,
  `statut` ENUM('actif', 'inactif') NOT NULL DEFAULT 'actif',
  `role` ENUM('admin', 'utilisateur') NOT NULL DEFAULT 'admin',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 4. ZONES_LIVRAISON (avant produits pour devis)
-- -----------------------------------------------------------------------------
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

-- -----------------------------------------------------------------------------
-- 5. STOCK_ARTICLES (avant produits, dépend de categories)
-- -----------------------------------------------------------------------------
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

-- -----------------------------------------------------------------------------
-- 6. PRODUITS (dépend de categories, stock_articles)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `produits` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `nom` VARCHAR(255) NOT NULL,
  `description` TEXT NOT NULL,
  `prix` DECIMAL(10,2) NOT NULL,
  `prix_promotion` DECIMAL(10,2) NULL DEFAULT NULL,
  `stock` INT(11) NOT NULL DEFAULT 0,
  `categorie_id` INT(11) NOT NULL,
  `stock_article_id` INT(11) NULL DEFAULT NULL,
  `image_principale` VARCHAR(255) NULL,
  `images` TEXT NULL,
  `poids` VARCHAR(50) NULL,
  `unite` VARCHAR(20) NOT NULL DEFAULT 'unité',
  `couleurs` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Couleurs disponibles',
  `taille` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Tailles disponibles',
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `statut` ENUM('actif', 'inactif', 'rupture_stock') NOT NULL DEFAULT 'actif',
  PRIMARY KEY (`id`),
  KEY `idx_categorie` (`categorie_id`),
  KEY `idx_statut` (`statut`),
  KEY `idx_stock_article` (`stock_article_id`),
  CONSTRAINT `fk_produits_categorie` FOREIGN KEY (`categorie_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_produits_stock_article` FOREIGN KEY (`stock_article_id`) REFERENCES `stock_articles` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 7. PRODUITS_VARIANTES (variantes de produits)
-- -----------------------------------------------------------------------------
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

-- -----------------------------------------------------------------------------
-- 8. COMMANDES (user_id nullable pour commandes manuelles)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `commandes` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NULL DEFAULT NULL,
  `client_nom` VARCHAR(255) NULL DEFAULT NULL,
  `client_prenom` VARCHAR(255) NULL DEFAULT NULL,
  `client_email` VARCHAR(255) NULL DEFAULT NULL,
  `client_telephone` VARCHAR(50) NULL DEFAULT NULL,
  `numero_commande` VARCHAR(50) NOT NULL UNIQUE,
  `montant_total` DECIMAL(10,2) NOT NULL,
  `adresse_livraison` TEXT NOT NULL,
  `zone_livraison_id` INT(11) NULL DEFAULT NULL,
  `frais_livraison` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `telephone_livraison` VARCHAR(50) NOT NULL,
  `statut` ENUM('en_attente', 'confirmee', 'prise_en_charge', 'en_preparation', 'livraison_en_cours', 'expediee', 'livree', 'annulee') NOT NULL DEFAULT 'en_attente',
  `date_commande` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_livraison` DATETIME NULL DEFAULT NULL,
  `notes` TEXT NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_zone_livraison_id` (`zone_livraison_id`),
  KEY `idx_statut` (`statut`),
  KEY `idx_date_commande` (`date_commande`),
  CONSTRAINT `fk_commandes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_commandes_zone` FOREIGN KEY (`zone_livraison_id`) REFERENCES `zones_livraison` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 9. COMMANDE_PRODUITS (détails des commandes)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `commande_produits` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `commande_id` INT(11) NOT NULL,
  `produit_id` INT(11) NOT NULL,
  `nom_produit` VARCHAR(255) NULL DEFAULT NULL,
  `quantite` INT(11) NOT NULL,
  `prix_unitaire` DECIMAL(10,2) NOT NULL,
  `prix_total` DECIMAL(10,2) NOT NULL,
  `couleur` VARCHAR(255) NULL DEFAULT NULL,
  `poids` VARCHAR(100) NULL DEFAULT NULL,
  `taille` VARCHAR(100) NULL DEFAULT NULL,
  `variante_id` INT(11) NULL DEFAULT NULL,
  `variante_nom` VARCHAR(255) NULL DEFAULT NULL,
  `surcout_poids` DECIMAL(10,2) NULL DEFAULT 0,
  `surcout_taille` DECIMAL(10,2) NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_commande_id` (`commande_id`),
  KEY `idx_produit_id` (`produit_id`),
  CONSTRAINT `fk_commande_produits_commande` FOREIGN KEY (`commande_id`) REFERENCES `commandes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_commande_produits_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 10. FACTURES (liées aux commandes)
-- -----------------------------------------------------------------------------
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

-- -----------------------------------------------------------------------------
-- 11. PANIER (avec options couleur, poids, taille, variantes)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `panier` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `produit_id` INT(11) NOT NULL,
  `quantite` INT(11) NOT NULL DEFAULT 1,
  `couleur` VARCHAR(255) NULL DEFAULT NULL,
  `poids` VARCHAR(100) NULL DEFAULT NULL,
  `taille` VARCHAR(100) NULL DEFAULT NULL,
  `variante_id` INT(11) NULL DEFAULT NULL,
  `variante_nom` VARCHAR(255) NULL DEFAULT NULL,
  `variante_image` VARCHAR(255) NULL DEFAULT NULL,
  `surcout_poids` DECIMAL(10,2) NULL DEFAULT 0,
  `surcout_taille` DECIMAL(10,2) NULL DEFAULT 0,
  `prix_unitaire` DECIMAL(10,2) NULL DEFAULT NULL,
  `date_ajout` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_produit_id` (`produit_id`),
  KEY `idx_date_ajout` (`date_ajout`),
  CONSTRAINT `fk_panier_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_panier_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 12. PRODUITS_VISITES
-- -----------------------------------------------------------------------------
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

-- -----------------------------------------------------------------------------
-- 13. FAVORIS
-- -----------------------------------------------------------------------------
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

-- -----------------------------------------------------------------------------
-- 14. COMMANDES_PERSONNALISEES
-- -----------------------------------------------------------------------------
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

-- -----------------------------------------------------------------------------
-- 15. STOCK_MOUVEMENTS
-- -----------------------------------------------------------------------------
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

-- -----------------------------------------------------------------------------
-- 16. DEVIS
-- -----------------------------------------------------------------------------
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

-- -----------------------------------------------------------------------------
-- 17. DEVIS_PRODUITS
-- -----------------------------------------------------------------------------
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

-- -----------------------------------------------------------------------------
-- 18. FACTURES_DEVIS
-- -----------------------------------------------------------------------------
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

-- -----------------------------------------------------------------------------
-- 19. CONTACTS
-- -----------------------------------------------------------------------------
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

-- -----------------------------------------------------------------------------
-- 20. SLIDER (carrousel page d'accueil)
-- -----------------------------------------------------------------------------
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

-- -----------------------------------------------------------------------------
-- 21. SECTION4_CONFIG (config section page d'accueil)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `section4_config` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `titre` VARCHAR(255) NOT NULL DEFAULT 'Bienvenue au Sugar Paper',
  `texte` VARCHAR(255) NOT NULL DEFAULT 'Tous les produits a petit prix',
  `image_fond` VARCHAR(255) NULL DEFAULT NULL,
  `date_modification` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 22. TRENDING_CONFIG
-- -----------------------------------------------------------------------------
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

-- -----------------------------------------------------------------------------
-- 23. VIDEOS (carrousel vidéo page d'accueil)
-- -----------------------------------------------------------------------------
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

-- -----------------------------------------------------------------------------
-- DONNÉES INITIALES (optionnel)
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO `categories` (`nom`, `description`) VALUES
('Les Noix', 'Noix diverses (noix de cajou, noix de coco, amandes, etc.)'),
('Les Feuilles', 'Feuilles médicinales et aromatiques'),
('Les Fruits', 'Fruits naturels de saison'),
('Les Huiles', 'Huiles végétales naturelles (huile de palme, huile de coco, etc.)'),
('Les Céréales', 'Céréales diverses (riz, mil, maïs, etc.)'),
('Les Racines', 'Racines médicinales et comestibles'),
('Les Cosmétiques', 'Produits cosmétiques naturels (savons, crèmes, baumes, etc.)');

INSERT IGNORE INTO `section4_config` (`id`, `titre`, `texte`, `image_fond`) 
VALUES (1, 'Bienvenue au Sugar Paper', 'Tous les produits a petit prix', 'market.png');

INSERT IGNORE INTO `trending_config` (`id`, `label`, `titre`, `bouton_texte`, `bouton_lien`, `image`) 
VALUES (1, 'categories', 'Enhance Your Music Experience', 'Buy Now!', '#', 'speaker.png');

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- FIN DU SCHÉMA
-- =============================================================================
