-- Référentiel entrepôt nommé (étage → position)

CREATE TABLE IF NOT EXISTS `entrepot_etage` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `numero_etage` TINYINT UNSIGNED NOT NULL,
  `nom` VARCHAR(100) NOT NULL,
  `code` VARCHAR(20) NOT NULL,
  `actif` TINYINT(1) NOT NULL DEFAULT 1,
  `date_modification` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_entrepot_etage_numero` (`numero_etage`),
  UNIQUE KEY `uniq_entrepot_etage_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `entrepot_rayon` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `etage_id` INT UNSIGNED NOT NULL,
  `numero` SMALLINT UNSIGNED NOT NULL,
  `nom` VARCHAR(100) NOT NULL,
  `code` VARCHAR(20) NULL DEFAULT NULL,
  `date_modification` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_entrepot_rayon_etage_num` (`etage_id`, `numero`),
  KEY `idx_entrepot_rayon_etage` (`etage_id`),
  CONSTRAINT `fk_entrepot_rayon_etage` FOREIGN KEY (`etage_id`) REFERENCES `entrepot_etage` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `entrepot_allee` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `etage_id` INT UNSIGNED NOT NULL,
  `numero` TINYINT UNSIGNED NOT NULL,
  `nom` VARCHAR(100) NOT NULL,
  `date_modification` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_entrepot_allee_etage_num` (`etage_id`, `numero`),
  KEY `idx_entrepot_allee_etage` (`etage_id`),
  CONSTRAINT `fk_entrepot_allee_etage` FOREIGN KEY (`etage_id`) REFERENCES `entrepot_etage` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `entrepot_zone` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `etage_id` INT UNSIGNED NOT NULL,
  `rayon_id` INT UNSIGNED NULL DEFAULT NULL,
  `numero` TINYINT UNSIGNED NOT NULL,
  `nom` VARCHAR(100) NOT NULL,
  `date_modification` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_entrepot_zone_etage_num` (`etage_id`, `numero`),
  KEY `idx_entrepot_zone_etage` (`etage_id`),
  KEY `idx_entrepot_zone_rayon` (`rayon_id`),
  CONSTRAINT `fk_entrepot_zone_etage` FOREIGN KEY (`etage_id`) REFERENCES `entrepot_etage` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_entrepot_zone_rayon` FOREIGN KEY (`rayon_id`) REFERENCES `entrepot_rayon` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `entrepot_barre` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `etage_id` INT UNSIGNED NOT NULL,
  `rayon_id` INT UNSIGNED NULL DEFAULT NULL,
  `allee_id` INT UNSIGNED NULL DEFAULT NULL,
  `zone_id` INT UNSIGNED NULL DEFAULT NULL,
  `numero` TINYINT UNSIGNED NOT NULL,
  `nom` VARCHAR(100) NOT NULL,
  `code_scan` VARCHAR(40) NULL DEFAULT NULL,
  `chemin_libelle` TEXT NULL,
  `date_modification` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_entrepot_barre_rayon_num` (`rayon_id`, `numero`),
  UNIQUE KEY `uniq_entrepot_barre_code_scan` (`code_scan`),
  KEY `idx_entrepot_barre_etage` (`etage_id`),
  CONSTRAINT `fk_entrepot_barre_etage` FOREIGN KEY (`etage_id`) REFERENCES `entrepot_etage` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_entrepot_barre_rayon` FOREIGN KEY (`rayon_id`) REFERENCES `entrepot_rayon` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_entrepot_barre_allee` FOREIGN KEY (`allee_id`) REFERENCES `entrepot_allee` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_entrepot_barre_zone` FOREIGN KEY (`zone_id`) REFERENCES `entrepot_zone` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `entrepot_position` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `barre_id` INT UNSIGNED NOT NULL,
  `numero` TINYINT UNSIGNED NOT NULL,
  `nom` VARCHAR(80) NOT NULL,
  `date_modification` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_entrepot_position_barre_num` (`barre_id`, `numero`),
  KEY `idx_entrepot_position_barre` (`barre_id`),
  CONSTRAINT `fk_entrepot_position_barre` FOREIGN KEY (`barre_id`) REFERENCES `entrepot_barre` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `entrepot_barre_code_seq` (
  `id` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `dernier_numero` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `entrepot_barre_code_seq` (`id`, `dernier_numero`)
SELECT 1, 0 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `entrepot_barre_code_seq` WHERE `id` = 1);
