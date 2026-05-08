-- Sanctions et mesures disciplinaires liées à une fiche employé
CREATE TABLE IF NOT EXISTS `employe_sanctions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `employe_id` INT(11) NOT NULL,
  `date_constat` DATE NOT NULL COMMENT 'Date du constat ou de la décision',
  `type_sanction` VARCHAR(64) NOT NULL,
  `motif` TEXT NOT NULL COMMENT 'Description des faits / motif',
  `mesure` TEXT NOT NULL COMMENT 'Mesure ou décision appliquée',
  `commentaire` TEXT NULL DEFAULT NULL COMMENT 'Notes internes RH (optionnel)',
  `admin_id` INT(11) NULL DEFAULT NULL COMMENT 'Administrateur ayant enregistré l’entrée',
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_employe_sanctions_employe` (`employe_id`),
  KEY `idx_employe_sanctions_date` (`date_constat`),
  CONSTRAINT `fk_employe_sanctions_employe` FOREIGN KEY (`employe_id`) REFERENCES `employes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_employe_sanctions_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
