-- Migration hiérarchie CRUD entrepôt : Niveau → Zone → Rayon → Étagère → Barre → Position
-- Les ALTER conditionnels sont exécutés par run_migrate_entrepot_hierarchie_crud.php

CREATE TABLE IF NOT EXISTS `entrepot_etagere` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `etage_id` INT UNSIGNED NOT NULL,
  `zone_id` INT UNSIGNED NULL DEFAULT NULL,
  `rayon_id` INT UNSIGNED NOT NULL,
  `numero` SMALLINT UNSIGNED NOT NULL,
  `nom` VARCHAR(100) NOT NULL,
  `date_modification` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_entrepot_etagere_rayon_num` (`rayon_id`, `numero`),
  KEY `idx_entrepot_etagere_etage` (`etage_id`),
  KEY `idx_entrepot_etagere_zone` (`zone_id`),
  CONSTRAINT `fk_entrepot_etagere_etage` FOREIGN KEY (`etage_id`) REFERENCES `entrepot_etage` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_entrepot_etagere_zone` FOREIGN KEY (`zone_id`) REFERENCES `entrepot_zone` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_entrepot_etagere_rayon` FOREIGN KEY (`rayon_id`) REFERENCES `entrepot_rayon` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `entrepot_structure_champ_archive` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `champ_id_origine` INT UNSIGNED NULL DEFAULT NULL,
  `slug` VARCHAR(60) NOT NULL,
  `slug_canonique` VARCHAR(80) NOT NULL,
  `label` VARCHAR(100) NOT NULL,
  `icon` VARCHAR(40) NOT NULL DEFAULT 'fa-cube',
  `colonne_db` VARCHAR(60) NOT NULL,
  `niveau_hierarchie` ENUM('zone','rayon','etagere','barre','position') NULL DEFAULT NULL,
  `lie_barre` TINYINT(1) NOT NULL DEFAULT 0,
  `max_valeur` SMALLINT UNSIGNED NOT NULL DEFAULT 50,
  `config_json` TEXT NULL,
  `date_archivage` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_esc_archive_slug_canonique` (`slug_canonique`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
