-- =============================================================================
-- Tables dépenses & catégories (comptabilité) — exécuter une fois si absentes
-- Compatible avec migration_admin_b2b_structure.sql (ne pas dupliquer si déjà créées)
-- =============================================================================

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
