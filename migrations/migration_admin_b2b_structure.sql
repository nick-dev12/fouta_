-- =============================================================================
-- Structure B2B : rôles admin, clients B2B, BL, factures mensuelles HT, dépenses,
-- identifiant produit FPL + emplacement (étage / rayon)
-- À exécuter une fois (phpMyAdmin ou mysql CLI)
-- =============================================================================

-- 1) Rôles étendus pour la table admin
ALTER TABLE `admin`
  MODIFY COLUMN `role` ENUM(
    'admin',
    'utilisateur',
    'commercial',
    'comptabilite',
    'rh'
  ) NOT NULL DEFAULT 'admin';

-- 2) Clients B2B (professionnels — facturation / BL)
CREATE TABLE IF NOT EXISTS `clients_b2b` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `raison_sociale` VARCHAR(255) NOT NULL,
  `nom_contact` VARCHAR(120) NULL DEFAULT NULL,
  `prenom_contact` VARCHAR(120) NULL DEFAULT NULL,
  `email` VARCHAR(255) NULL DEFAULT NULL,
  `telephone` VARCHAR(50) NULL DEFAULT NULL,
  `adresse` TEXT NULL DEFAULT NULL,
  `notes` TEXT NULL DEFAULT NULL,
  `statut` ENUM('actif','inactif') NOT NULL DEFAULT 'actif',
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_raison` (`raison_sociale`(100)),
  KEY `idx_statut` (`statut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) Bons de livraison (montants HT uniquement — pas de TVA)
CREATE TABLE IF NOT EXISTS `bons_livraison` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `numero_bl` VARCHAR(50) NOT NULL,
  `client_b2b_id` INT(11) NOT NULL,
  `devis_id` INT(11) NULL DEFAULT NULL COMMENT 'Si issu d une conversion devis',
  `admin_createur_id` INT(11) NULL DEFAULT NULL COMMENT 'Commercial / admin ayant créé le BL',
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
-- Note : pas de FK vers `devis` pour compatibilité ; cohérence devis_id assurée par l application.

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

-- 4) Factures mensuelles (HT uniquement — pas de TVA)
CREATE TABLE IF NOT EXISTS `factures_mensuelles` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `numero_facture` VARCHAR(50) NOT NULL,
  `client_b2b_id` INT(11) NOT NULL,
  `annee` SMALLINT(4) NOT NULL,
  `mois` TINYINT(2) NOT NULL COMMENT '1 à 12',
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

-- Un BL ne peut apparaître que dans une seule facture
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

-- 5) Dépenses (TVA possible uniquement ici)
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
  `taux_tva` DECIMAL(5,2) NULL DEFAULT NULL COMMENT 'Ex: 20.00 pour 20%',
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

-- 6) Produits : identifiant interne FPL + emplacement
ALTER TABLE `produits`
  ADD COLUMN `identifiant_interne` VARCHAR(20) NULL DEFAULT NULL AFTER `id`,
  ADD COLUMN `etage` VARCHAR(50) NULL DEFAULT NULL COMMENT 'Étage entrepôt / magasin' AFTER `unite`,
  ADD COLUMN `numero_rayon` VARCHAR(50) NULL DEFAULT NULL COMMENT 'Numéro ou code rayon' AFTER `etage`;

ALTER TABLE `produits`
  ADD UNIQUE KEY `uniq_identifiant_interne` (`identifiant_interne`);

-- 7) Traçabilité conversion devis → BL (optionnel, si colonne absente)
-- Note : exécuter seulement si la colonne n'existe pas (sinon erreur duplicate)
-- ALTER TABLE `devis` ADD COLUMN `converti_bl_id` INT(11) NULL DEFAULT NULL AFTER `statut`;
-- ALTER TABLE `devis` ADD KEY `idx_converti_bl` (`converti_bl_id`);
