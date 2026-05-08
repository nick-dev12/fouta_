ALTER TABLE `bulletin_paie_parametres`
  ADD COLUMN `conges_annuels_global` SMALLINT UNSIGNED NULL DEFAULT NULL
  COMMENT 'Quota annuel global de jours de congé par employé';

CREATE TABLE IF NOT EXISTS `employe_conges` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `employe_id` INT(11) NOT NULL,
  `mois_conge` CHAR(7) NOT NULL COMMENT 'Format YYYY-MM',
  `nb_jours` SMALLINT UNSIGNED NOT NULL,
  `notes` VARCHAR(1000) NULL DEFAULT NULL,
  `admin_id` INT(11) NULL DEFAULT NULL,
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_employe_conges_employe` (`employe_id`),
  KEY `idx_employe_conges_mois` (`mois_conge`),
  CONSTRAINT `fk_employe_conges_employe` FOREIGN KEY (`employe_id`) REFERENCES `employes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_employe_conges_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
