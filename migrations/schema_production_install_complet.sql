-- =============================================================================
-- INSTALLATION PRODUCTION COMPLÈTE — POIDS LOURD / Boutique + Caisse + B2B
-- =============================================================================
-- Base vide recommandée. Crée toutes les tables, index et clés étrangères.
-- Charset : utf8mb4. Stock boutique : colonne produits.stock uniquement (pas stock_articles).
-- Rôles admin : admin, gestion_stock, commercial, comptabilite, rh, caissier
-- BL : statut brouillon | valide (aligné model_bl.php / bl_statut_unify_valide.sql)
-- =============================================================================
-- Usage : mysql -u USER -p NOM_BASE < schema_production_install_complet.sql
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

-- -----------------------------------------------------------------------------
-- 1. USERS
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
  `accepte_conditions` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0=non, 1=oui',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2. CATEGORIES
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `nom` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `image` VARCHAR(255) NULL,
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_nom` (`nom`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 3. ADMIN (rôles applicatifs complets)
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
  `role` ENUM(
    'admin',
    'gestion_stock',
    'commercial',
    'comptabilite',
    'rh',
    'caissier'
  ) NOT NULL DEFAULT 'admin',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 4. ZONES_LIVRAISON
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
-- 5. EMPLOYES (RH)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `employes` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `nom` VARCHAR(120) NOT NULL,
  `prenom` VARCHAR(120) NOT NULL,
  `email` VARCHAR(255) NULL DEFAULT NULL,
  `telephone` VARCHAR(50) NULL DEFAULT NULL,
  `poste` VARCHAR(150) NULL DEFAULT NULL,
  `service` VARCHAR(150) NULL DEFAULT NULL,
  `date_embauche` DATE NULL DEFAULT NULL,
  `statut` ENUM('actif','inactif','suspendu') NOT NULL DEFAULT 'actif',
  `notes` TEXT NULL DEFAULT NULL,
  `admin_id` INT(11) NULL DEFAULT NULL COMMENT 'Compte admin lié (optionnel)',
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_statut` (`statut`),
  KEY `idx_admin` (`admin_id`),
  CONSTRAINT `fk_employes_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 6. CLIENTS B2B
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `clients_b2b` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `raison_sociale` VARCHAR(255) NOT NULL,
  `nom_contact` VARCHAR(120) NULL DEFAULT NULL,
  `prenom_contact` VARCHAR(120) NULL DEFAULT NULL,
  `email` VARCHAR(255) NULL DEFAULT NULL,
  `telephone` VARCHAR(50) NULL DEFAULT NULL,
  `adresse` TEXT NULL DEFAULT NULL,
  `notes` TEXT NULL DEFAULT NULL,
  `admin_createur_id` INT(11) NULL DEFAULT NULL,
  `statut` ENUM('actif','inactif') NOT NULL DEFAULT 'actif',
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_raison` (`raison_sociale`(100)),
  KEY `idx_statut` (`statut`),
  KEY `idx_cb2b_admin_createur` (`admin_createur_id`),
  CONSTRAINT `fk_cb2b_admin_createur` FOREIGN KEY (`admin_createur_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 7. PRODUITS
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `produits` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `identifiant_interne` VARCHAR(20) NULL DEFAULT NULL COMMENT 'Réf. FPL / code interne',
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
  `couleurs` VARCHAR(255) NULL DEFAULT NULL,
  `taille` VARCHAR(255) NULL DEFAULT NULL,
  `etage` VARCHAR(50) NULL DEFAULT NULL COMMENT 'Étage magasin / entrepôt',
  `numero_rayon` VARCHAR(50) NULL DEFAULT NULL,
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `statut` ENUM('actif', 'inactif', 'rupture_stock') NOT NULL DEFAULT 'actif',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_identifiant_interne` (`identifiant_interne`),
  KEY `idx_categorie` (`categorie_id`),
  KEY `idx_statut` (`statut`),
  CONSTRAINT `fk_produits_categorie` FOREIGN KEY (`categorie_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 8. PRODUITS_VARIANTES
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
-- 9. COMMANDES (boutique + manuel, traçabilité admin)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `commandes` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NULL DEFAULT NULL,
  `admin_createur_id` INT(11) NULL DEFAULT NULL COMMENT 'Saisie manuelle admin',
  `admin_dernier_traitement_id` INT(11) NULL DEFAULT NULL COMMENT 'Dernier changement statut',
  `client_nom` VARCHAR(255) NULL DEFAULT NULL,
  `client_prenom` VARCHAR(255) NULL DEFAULT NULL,
  `client_email` VARCHAR(255) NULL DEFAULT NULL,
  `client_telephone` VARCHAR(50) NULL DEFAULT NULL,
  `numero_commande` VARCHAR(50) NOT NULL,
  `montant_total` DECIMAL(10,2) NOT NULL,
  `adresse_livraison` TEXT NOT NULL,
  `zone_livraison_id` INT(11) NULL DEFAULT NULL,
  `frais_livraison` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `telephone_livraison` VARCHAR(50) NOT NULL,
  `statut` ENUM('en_attente', 'confirmee', 'prise_en_charge', 'en_preparation', 'livraison_en_cours', 'expediee', 'livree', 'paye', 'annulee') NOT NULL DEFAULT 'en_attente',
  `date_commande` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_livraison` DATETIME NULL DEFAULT NULL,
  `notes` TEXT NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_numero_commande` (`numero_commande`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_zone_livraison_id` (`zone_livraison_id`),
  KEY `idx_statut` (`statut`),
  KEY `idx_date_commande` (`date_commande`),
  KEY `idx_cmd_admin_createur` (`admin_createur_id`),
  KEY `idx_cmd_admin_traitement` (`admin_dernier_traitement_id`),
  CONSTRAINT `fk_commandes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_commandes_zone` FOREIGN KEY (`zone_livraison_id`) REFERENCES `zones_livraison` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_cmd_admin_createur` FOREIGN KEY (`admin_createur_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_cmd_admin_traitement` FOREIGN KEY (`admin_dernier_traitement_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 10. COMMANDE_PRODUITS
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
-- 11. FACTURES (commandes boutique)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `factures` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `commande_id` INT(11) NOT NULL,
  `numero_facture` VARCHAR(50) NOT NULL,
  `date_facture` DATE NOT NULL,
  `montant_total` DECIMAL(10,2) NOT NULL,
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `token` VARCHAR(64) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_commande` (`commande_id`),
  KEY `idx_numero` (`numero_facture`),
  KEY `idx_token` (`token`),
  CONSTRAINT `fk_factures_commande` FOREIGN KEY (`commande_id`) REFERENCES `commandes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 12. PANIER
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
-- 13. PRODUITS_VISITES / FAVORIS
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
  `prix` DECIMAL(10,2) NULL DEFAULT NULL,
  `zone_livraison_id` INT(11) NULL DEFAULT NULL,
  `statut` ENUM('en_attente', 'confirmee', 'en_preparation', 'devis_envoye', 'acceptee', 'refusee', 'terminee', 'annulee') NOT NULL DEFAULT 'en_attente',
  `notes_admin` TEXT NULL DEFAULT NULL,
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_statut` (`statut`),
  KEY `idx_date_creation` (`date_creation`),
  KEY `idx_zone_livraison` (`zone_livraison_id`),
  CONSTRAINT `fk_cp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_cp_zone_livraison` FOREIGN KEY (`zone_livraison_id`) REFERENCES `zones_livraison` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 15. STOCK_MOUVEMENTS
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `stock_mouvements` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `type` ENUM('entree', 'sortie', 'inventaire') NOT NULL,
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
  KEY `idx_produit` (`produit_id`),
  KEY `idx_type` (`type`),
  KEY `idx_date` (`date_mouvement`),
  CONSTRAINT `fk_mouvements_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 16. DEVIS (admin_createur_id)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `devis` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `numero_devis` VARCHAR(50) NOT NULL,
  `client_nom` VARCHAR(100) NOT NULL,
  `client_prenom` VARCHAR(100) NOT NULL,
  `client_telephone` VARCHAR(50) NOT NULL,
  `client_email` VARCHAR(255) NULL DEFAULT NULL,
  `adresse_livraison` TEXT NOT NULL,
  `zone_livraison_id` INT(11) NULL DEFAULT NULL,
  `frais_livraison` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `user_id` INT(11) NULL DEFAULT NULL,
  `admin_createur_id` INT(11) NULL DEFAULT NULL COMMENT 'Admin ayant créé le devis',
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
  KEY `idx_devis_admin_createur` (`admin_createur_id`),
  CONSTRAINT `fk_devis_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_devis_zone` FOREIGN KEY (`zone_livraison_id`) REFERENCES `zones_livraison` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_devis_admin_createur` FOREIGN KEY (`admin_createur_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
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
  `token` VARCHAR(64) NULL DEFAULT NULL,
  `admin_createur_id` INT(11) NULL DEFAULT NULL COMMENT 'Admin ayant généré la facture',
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_numero_facture` (`numero_facture`),
  UNIQUE KEY `idx_devis_id` (`devis_id`),
  KEY `idx_token` (`token`),
  KEY `idx_factures_devis_admin` (`admin_createur_id`),
  CONSTRAINT `fk_factures_devis_devis` FOREIGN KEY (`devis_id`) REFERENCES `devis` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_factures_devis_admin` FOREIGN KEY (`admin_createur_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 19. BONS DE LIVRAISON (devis_id sans FK — cohérence applicative)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `bons_livraison` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `numero_bl` VARCHAR(50) NOT NULL,
  `client_b2b_id` INT(11) NOT NULL,
  `devis_id` INT(11) NULL DEFAULT NULL COMMENT 'Si conversion devis',
  `admin_createur_id` INT(11) NULL DEFAULT NULL,
  `statut` ENUM('brouillon','valide') NOT NULL DEFAULT 'brouillon',
  `date_bl` DATE NOT NULL,
  `total_ht` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `notes` TEXT NULL DEFAULT NULL,
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_numero_bl` (`numero_bl`),
  KEY `idx_client` (`client_b2b_id`),
  KEY `idx_statut_date` (`statut`,`date_bl`),
  KEY `idx_devis` (`devis_id`),
  CONSTRAINT `fk_bl_client` FOREIGN KEY (`client_b2b_id`) REFERENCES `clients_b2b` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_bl_admin` FOREIGN KEY (`admin_createur_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 20. BL_LIGNES
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `bl_lignes` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `bl_id` INT(11) NOT NULL,
  `produit_id` INT(11) NULL DEFAULT NULL,
  `designation` VARCHAR(500) NOT NULL,
  `quantite` DECIMAL(12,3) NOT NULL DEFAULT 1.000,
  `prix_unitaire_ht` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total_ligne_ht` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `ordre` INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_bl` (`bl_id`),
  KEY `idx_produit` (`produit_id`),
  CONSTRAINT `fk_bl_lignes_bl` FOREIGN KEY (`bl_id`) REFERENCES `bons_livraison` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_bl_lignes_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 21. FACTURES_MENSUELLES + LIAISON BL (CASCADE si BL supprimé)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `factures_mensuelles` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `numero_facture` VARCHAR(50) NOT NULL,
  `client_b2b_id` INT(11) NOT NULL,
  `annee` SMALLINT(4) NOT NULL,
  `mois` TINYINT(2) NOT NULL COMMENT '1-12',
  `statut` ENUM('brouillon','validee','payee') NOT NULL DEFAULT 'brouillon',
  `total_ht` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `date_emission` DATE NULL DEFAULT NULL,
  `date_paiement` DATE NULL DEFAULT NULL,
  `notes` TEXT NULL DEFAULT NULL,
  `admin_createur_id` INT(11) NULL DEFAULT NULL,
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_numero_facture` (`numero_facture`),
  UNIQUE KEY `uniq_client_mois` (`client_b2b_id`,`annee`,`mois`),
  KEY `idx_statut` (`statut`),
  CONSTRAINT `fk_fm_client` FOREIGN KEY (`client_b2b_id`) REFERENCES `clients_b2b` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_fm_admin` FOREIGN KEY (`admin_createur_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `facture_mensuelle_bl` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `facture_mensuelle_id` INT(11) NOT NULL,
  `bl_id` INT(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_bl` (`bl_id`),
  KEY `idx_facture` (`facture_mensuelle_id`),
  CONSTRAINT `fk_fmb_facture` FOREIGN KEY (`facture_mensuelle_id`) REFERENCES `factures_mensuelles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_fmb_bl` FOREIGN KEY (`bl_id`) REFERENCES `bons_livraison` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 22. DÉPENSES COMPTA
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories_depenses` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `nom` VARCHAR(150) NOT NULL,
  `type_tva` ENUM('sans_tva','avec_tva','mixte') NOT NULL DEFAULT 'mixte',
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_nom` (`nom`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `depenses` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `categorie_id` INT(11) NULL DEFAULT NULL,
  `type_depense` ENUM('sans_tva','avec_tva') NOT NULL,
  `libelle` VARCHAR(255) NOT NULL,
  `montant_ht` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `taux_tva` DECIMAL(5,2) NULL DEFAULT NULL,
  `montant_tva` DECIMAL(12,2) NULL DEFAULT NULL,
  `montant_ttc` DECIMAL(12,2) NULL DEFAULT NULL,
  `date_depense` DATE NOT NULL,
  `notes` TEXT NULL DEFAULT NULL,
  `admin_createur_id` INT(11) NULL DEFAULT NULL,
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_date` (`date_depense`),
  KEY `idx_type` (`type_depense`),
  KEY `idx_categorie` (`categorie_id`),
  CONSTRAINT `fk_dep_cat` FOREIGN KEY (`categorie_id`) REFERENCES `categories_depenses` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_dep_admin` FOREIGN KEY (`admin_createur_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 23. CAISSE (ventes + tickets en attente / payés)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `caisse_ventes` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `admin_id` INT(11) NOT NULL COMMENT 'Vendeur / préparateur',
  `caissier_id` INT(11) NULL DEFAULT NULL COMMENT 'Caissier si encaissé',
  `numero_ticket` VARCHAR(32) NOT NULL,
  `reference` VARCHAR(5) NULL DEFAULT NULL COMMENT 'Ref. caisse 5 chiffres, ticket non payé',
  `montant_total` DECIMAL(12,2) NOT NULL,
  `remise_globale_pct` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `mode_paiement` ENUM('especes','carte','mobile_money','cheque','mixte','autre') NOT NULL DEFAULT 'especes',
  `montant_especes` DECIMAL(12,2) NULL DEFAULT NULL,
  `montant_carte` DECIMAL(12,2) NULL DEFAULT NULL,
  `montant_mobile_money` DECIMAL(12,2) NULL DEFAULT NULL,
  `montant_recu` DECIMAL(12,2) NULL DEFAULT NULL,
  `monnaie_rendue` DECIMAL(12,2) NULL DEFAULT NULL,
  `notes` TEXT NULL,
  `statut` ENUM('en_attente', 'paye') NOT NULL DEFAULT 'paye',
  `date_vente` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_encaissement` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_numero_ticket` (`numero_ticket`),
  UNIQUE KEY `uk_caisse_ventes_reference` (`reference`),
  KEY `idx_admin` (`admin_id`),
  KEY `idx_caissier` (`caissier_id`),
  KEY `idx_date` (`date_vente`),
  KEY `idx_statut` (`statut`),
  CONSTRAINT `fk_caisse_ventes_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_caisse_ventes_caissier` FOREIGN KEY (`caissier_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `caisse_vente_lignes` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `vente_id` INT(11) NOT NULL,
  `produit_id` INT(11) NOT NULL,
  `designation` VARCHAR(500) NOT NULL,
  `quantite` INT(11) NOT NULL,
  `prix_unitaire` DECIMAL(12,2) NOT NULL,
  `remise_ligne_pct` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `total_ligne` DECIMAL(12,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_vente` (`vente_id`),
  KEY `idx_produit` (`produit_id`),
  CONSTRAINT `fk_cvl_vente` FOREIGN KEY (`vente_id`) REFERENCES `caisse_ventes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cvl_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 24. CMS LÉGER / CONTACTS / LOGOS
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `contacts` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `nom` VARCHAR(255) NOT NULL,
  `prenom` VARCHAR(255) NOT NULL DEFAULT '',
  `telephone` VARCHAR(50) NOT NULL,
  `email` VARCHAR(255) NULL DEFAULT NULL,
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_telephone` (`telephone`),
  KEY `idx_nom` (`nom`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS `section4_config` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `titre` VARCHAR(255) NOT NULL DEFAULT 'Bienvenue',
  `texte` VARCHAR(255) NOT NULL DEFAULT 'Tous les produits',
  `image_fond` VARCHAR(255) NULL DEFAULT NULL,
  `date_modification` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS `videos` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `titre` VARCHAR(255) NOT NULL,
  `fichier_video` VARCHAR(255) NOT NULL,
  `image_preview` VARCHAR(255) NULL DEFAULT NULL,
  `statut` ENUM('actif', 'inactif') NOT NULL DEFAULT 'actif',
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_statut` (`statut`),
  KEY `idx_image_preview` (`image_preview`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 25. RÉINITIALISATION MOT DE PASSE / FCM
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_password_reset` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(255) NOT NULL,
  `token` VARCHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used` TINYINT(1) NOT NULL DEFAULT 0,
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_token` (`token`),
  KEY `idx_email` (`email`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `admin_password_reset` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(255) NOT NULL,
  `token` VARCHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used` TINYINT(1) NOT NULL DEFAULT 0,
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_token` (`token`),
  KEY `idx_email` (`email`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fcm_tokens` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NULL DEFAULT NULL,
  `admin_id` INT(11) NULL DEFAULT NULL,
  `token` VARCHAR(500) NOT NULL,
  `type` ENUM('user', 'admin') NOT NULL DEFAULT 'user',
  `user_agent` VARCHAR(500) NULL DEFAULT NULL,
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_token` (`token`(191)),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_admin_id` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- FIN — Aucune donnée métier insérée. Créer un compte admin via l’application.
-- Option recalcul numéros tickets anciens : normalize_caisse_ticket_numeros.sql
-- =============================================================================
