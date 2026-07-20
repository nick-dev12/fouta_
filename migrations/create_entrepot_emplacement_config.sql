-- Configuration structure entrepôt (par étage)
CREATE TABLE IF NOT EXISTS `entrepot_emplacement_config` (
  `id` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `nb_etages` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `date_modification` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `entrepot_emplacement_etage` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `numero_etage` TINYINT UNSIGNED NOT NULL,
  `nb_rayons` SMALLINT UNSIGNED NOT NULL DEFAULT 100,
  `nb_allees` TINYINT UNSIGNED NOT NULL DEFAULT 10,
  `nb_zones` TINYINT UNSIGNED NOT NULL DEFAULT 10,
  `nb_positions` TINYINT UNSIGNED NOT NULL DEFAULT 10,
  `nb_barres` TINYINT UNSIGNED NOT NULL DEFAULT 10,
  `date_modification` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_numero_etage` (`numero_etage`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `entrepot_emplacement_config` (`id`, `nb_etages`, `date_modification`)
SELECT 1, 3, NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `entrepot_emplacement_config` WHERE `id` = 1);

INSERT INTO `entrepot_emplacement_etage` (`numero_etage`, `nb_rayons`, `nb_allees`, `nb_zones`, `nb_positions`, `nb_barres`, `date_modification`)
SELECT 1, 100, 10, 10, 10, 10, NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `entrepot_emplacement_etage` WHERE `numero_etage` = 1);

INSERT INTO `entrepot_emplacement_etage` (`numero_etage`, `nb_rayons`, `nb_allees`, `nb_zones`, `nb_positions`, `nb_barres`, `date_modification`)
SELECT 2, 100, 10, 10, 10, 10, NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `entrepot_emplacement_etage` WHERE `numero_etage` = 2);

INSERT INTO `entrepot_emplacement_etage` (`numero_etage`, `nb_rayons`, `nb_allees`, `nb_zones`, `nb_positions`, `nb_barres`, `date_modification`)
SELECT 3, 100, 10, 10, 10, 10, NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `entrepot_emplacement_etage` WHERE `numero_etage` = 3);
