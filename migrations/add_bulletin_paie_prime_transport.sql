ALTER TABLE `bulletin_paie_parametres`
  ADD COLUMN `prime_transport_mensuelle` DECIMAL(12,2) NULL DEFAULT NULL
  COMMENT 'Montant mensuel de référence de la prime de transport';

CREATE TABLE IF NOT EXISTS `employe_prime_transport_retraits` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `employe_id` INT(11) NOT NULL,
  `mois_paie` CHAR(7) NOT NULL COMMENT 'Format YYYY-MM',
  `nb_jours` SMALLINT UNSIGNED NOT NULL,
  `montant_deduit` DECIMAL(12,2) NOT NULL,
  `commentaire` VARCHAR(500) NULL DEFAULT NULL,
  `admin_id` INT(11) NULL DEFAULT NULL,
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_emp_transport_employe_mois` (`employe_id`, `mois_paie`),
  KEY `idx_emp_transport_date` (`date_creation`),
  CONSTRAINT `fk_emp_transport_employe` FOREIGN KEY (`employe_id`) REFERENCES `employes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_emp_transport_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
