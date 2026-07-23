-- Éléments nommés des champs structurels personnalisés (par étage)

CREATE TABLE IF NOT EXISTS `entrepot_champ_element` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `etage_id` INT UNSIGNED NOT NULL,
  `champ_id` INT UNSIGNED NOT NULL,
  `numero` SMALLINT UNSIGNED NOT NULL,
  `nom` VARCHAR(100) NOT NULL,
  `date_modification` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_entrepot_champ_element` (`etage_id`, `champ_id`, `numero`),
  KEY `idx_entrepot_champ_element_etage` (`etage_id`),
  KEY `idx_entrepot_champ_element_champ` (`champ_id`),
  CONSTRAINT `fk_entrepot_champ_element_etage` FOREIGN KEY (`etage_id`) REFERENCES `entrepot_etage` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_entrepot_champ_element_champ` FOREIGN KEY (`champ_id`) REFERENCES `entrepot_structure_champ` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
