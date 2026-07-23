-- Hiérarchie entrepôt libre : définitions de niveaux + nœuds génériques

CREATE TABLE IF NOT EXISTS `entrepot_hierarchie_niveau` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(60) NOT NULL,
  `label` VARCHAR(100) NOT NULL,
  `icon` VARCHAR(40) NOT NULL DEFAULT 'fa-cube',
  `ordre` INT NOT NULL DEFAULT 10,
  `actif` TINYINT(1) NOT NULL DEFAULT 1,
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_entrepot_hierarchie_niveau_slug` (`slug`),
  KEY `idx_entrepot_hierarchie_niveau_ordre` (`ordre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `entrepot_hierarchie_noeud` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `etage_id` INT UNSIGNED NOT NULL,
  `niveau_id` INT UNSIGNED NOT NULL,
  `parent_id` INT UNSIGNED NULL DEFAULT NULL,
  `numero` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `nom` VARCHAR(100) NOT NULL,
  `legacy_table` VARCHAR(40) NULL DEFAULT NULL,
  `legacy_id` INT UNSIGNED NULL DEFAULT NULL,
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_entrepot_noeud_parent_num` (`etage_id`, `niveau_id`, `parent_id`, `numero`),
  KEY `idx_entrepot_noeud_etage` (`etage_id`),
  KEY `idx_entrepot_noeud_niveau` (`niveau_id`),
  KEY `idx_entrepot_noeud_parent` (`parent_id`),
  KEY `idx_entrepot_noeud_legacy` (`legacy_table`, `legacy_id`),
  CONSTRAINT `fk_entrepot_noeud_etage` FOREIGN KEY (`etage_id`) REFERENCES `entrepot_etage` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_entrepot_noeud_niveau` FOREIGN KEY (`niveau_id`) REFERENCES `entrepot_hierarchie_niveau` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_entrepot_noeud_parent` FOREIGN KEY (`parent_id`) REFERENCES `entrepot_hierarchie_noeud` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
