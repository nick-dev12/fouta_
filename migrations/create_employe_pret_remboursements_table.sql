-- Versements / remboursements sur les prêts employés
CREATE TABLE IF NOT EXISTS `employe_pret_remboursements` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `pret_id` INT(11) NOT NULL,
  `montant` DECIMAL(12,2) NOT NULL,
  `date_versement` DATE NOT NULL,
  `commentaire` TEXT NULL DEFAULT NULL,
  `admin_id` INT(11) NULL DEFAULT NULL,
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pret_remb_pret` (`pret_id`),
  KEY `idx_pret_remb_date` (`date_versement`),
  CONSTRAINT `fk_pret_remb_pret` FOREIGN KEY (`pret_id`) REFERENCES `employe_prets` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_pret_remb_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
