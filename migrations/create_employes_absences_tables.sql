-- Absences employés et justificatifs (RH)
CREATE TABLE IF NOT EXISTS `employe_absences` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `employe_id` INT(11) NULL DEFAULT NULL COMMENT 'Legacy — fiche RH optionnelle',
  `subject_admin_id` INT(11) NULL DEFAULT NULL COMMENT 'Compte admin concerné (hors rôle admin)',
  `date_absence` DATE NOT NULL,
  `motif` VARCHAR(4000) NOT NULL,
  `penalite_montant` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT 'FCFA — optionnel',
  `penalite_retenir_salaire` TINYINT(1) NOT NULL DEFAULT 0,
  `penalite_deduite_bulletin_id` INT(11) NULL DEFAULT NULL,
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by_admin_id` INT(11) NULL DEFAULT NULL COMMENT 'Administrateur ayant saisi l''absence',
  PRIMARY KEY (`id`),
  KEY `idx_employe_absence` (`employe_id`),
  KEY `idx_date_absence` (`date_absence`),
  KEY `idx_subject_admin` (`subject_admin_id`),
  CONSTRAINT `fk_employe_absences_employe` FOREIGN KEY (`employe_id`) REFERENCES `employes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_employe_absences_subject_admin` FOREIGN KEY (`subject_admin_id`) REFERENCES `admin` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_employe_absences_admin` FOREIGN KEY (`created_by_admin_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `employe_absence_justificatifs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `absence_id` INT(11) NOT NULL COMMENT 'Une seule justification par absence',
  `texte` TEXT NULL DEFAULT NULL,
  `fichier_chemin` VARCHAR(500) NULL DEFAULT NULL COMMENT 'Chemin relatif sous /upload/',
  `fichier_nom_original` VARCHAR(255) NULL DEFAULT NULL,
  `fichier_mime` VARCHAR(120) NULL DEFAULT NULL,
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by_admin_id` INT(11) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_justificatif_absence` (`absence_id`),
  CONSTRAINT `fk_justificatif_absence` FOREIGN KEY (`absence_id`) REFERENCES `employe_absences` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_justificatif_admin` FOREIGN KEY (`created_by_admin_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
