-- Registre des champs structurels entrepôt (colonnes dynamiques sur entrepot_emplacement_etage)

CREATE TABLE IF NOT EXISTS `entrepot_structure_champ` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(50) NOT NULL,
  `label` VARCHAR(100) NOT NULL,
  `icon` VARCHAR(50) NOT NULL DEFAULT 'fa-cube',
  `colonne_db` VARCHAR(64) NOT NULL,
  `ordre` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `est_systeme` TINYINT(1) NOT NULL DEFAULT 0,
  `max_valeur` SMALLINT UNSIGNED NOT NULL DEFAULT 50,
  `date_creation` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_entrepot_structure_champ_slug` (`slug`),
  UNIQUE KEY `uniq_entrepot_structure_champ_colonne` (`colonne_db`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `entrepot_structure_champ` (`slug`, `label`, `icon`, `colonne_db`, `ordre`, `est_systeme`, `max_valeur`, `date_creation`)
SELECT 'rayons', 'Rayons', 'fa-th-large', 'nb_rayons', 10, 1, 500, NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `entrepot_structure_champ` WHERE `slug` = 'rayons');

INSERT INTO `entrepot_structure_champ` (`slug`, `label`, `icon`, `colonne_db`, `ordre`, `est_systeme`, `max_valeur`, `date_creation`)
SELECT 'allees', 'Allées', 'fa-road', 'nb_allees', 20, 1, 50, NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `entrepot_structure_champ` WHERE `slug` = 'allees');

INSERT INTO `entrepot_structure_champ` (`slug`, `label`, `icon`, `colonne_db`, `ordre`, `est_systeme`, `max_valeur`, `date_creation`)
SELECT 'zones', 'Zones', 'fa-map-marker-alt', 'nb_zones', 30, 1, 50, NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `entrepot_structure_champ` WHERE `slug` = 'zones');

INSERT INTO `entrepot_structure_champ` (`slug`, `label`, `icon`, `colonne_db`, `ordre`, `est_systeme`, `max_valeur`, `date_creation`)
SELECT 'positions', 'Positions', 'fa-crosshairs', 'nb_positions', 40, 1, 50, NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `entrepot_structure_champ` WHERE `slug` = 'positions');

INSERT INTO `entrepot_structure_champ` (`slug`, `label`, `icon`, `colonne_db`, `ordre`, `est_systeme`, `max_valeur`, `date_creation`)
SELECT 'barres', 'Barres / rayon', 'fa-grip-lines', 'nb_barres', 50, 1, 50, NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `entrepot_structure_champ` WHERE `slug` = 'barres');
