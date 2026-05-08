-- Autorisations d’absence (période autorisée par la RH pour une fiche employé)
CREATE TABLE IF NOT EXISTS `employe_autorisations_absence` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `employe_id` INT(11) NOT NULL,
  `date_debut` DATE NOT NULL,
  `date_fin` DATE NOT NULL COMMENT 'Inclus dans la période autorisée',
  `motif` TEXT NOT NULL COMMENT 'Objet / motif de l’autorisation',
  `commentaire` TEXT NULL DEFAULT NULL COMMENT 'Précisions internes RH (optionnel)',
  `admin_id` INT(11) NULL DEFAULT NULL COMMENT 'Administrateur ayant enregistré l’autorisation',
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_autoris_abs_employe` (`employe_id`),
  KEY `idx_autoris_abs_debut` (`date_debut`),
  CONSTRAINT `fk_autoris_abs_employe` FOREIGN KEY (`employe_id`) REFERENCES `employes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_autoris_abs_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
