-- Registre des champs du formulaire produit (ajout / modification)

CREATE TABLE IF NOT EXISTS `produit_formulaire_champ` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(50) NOT NULL,
  `label` VARCHAR(100) NOT NULL,
  `icon` VARCHAR(50) NOT NULL DEFAULT 'fa-cube',
  `section` ENUM('info','prix','ref','variantes','options','media') NOT NULL DEFAULT 'info',
  `type_champ` ENUM('systeme','texte','textarea','nombre','select') NOT NULL DEFAULT 'systeme',
  `colonne_db` VARCHAR(64) NULL DEFAULT NULL,
  `ordre` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `est_systeme` TINYINT(1) NOT NULL DEFAULT 0,
  `verrouille` TINYINT(1) NOT NULL DEFAULT 0,
  `actif` TINYINT(1) NOT NULL DEFAULT 1,
  `obligatoire` TINYINT(1) NOT NULL DEFAULT 0,
  `options_json` TEXT NULL,
  `date_creation` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_produit_formulaire_champ_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `produit_formulaire_champ_droit` (
  `admin_id` INT NOT NULL,
  `peut_gerer` TINYINT(1) NOT NULL DEFAULT 1,
  `date_modification` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`admin_id`),
  CONSTRAINT `fk_pfcd_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `produit_champ_valeur` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `produit_id` INT NOT NULL,
  `champ_id` INT UNSIGNED NOT NULL,
  `valeur` TEXT NULL,
  `date_modification` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_produit_champ_valeur` (`produit_id`, `champ_id`),
  KEY `idx_produit_champ_valeur_champ` (`champ_id`),
  CONSTRAINT `fk_pcv_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_pcv_champ` FOREIGN KEY (`champ_id`) REFERENCES `produit_formulaire_champ` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
