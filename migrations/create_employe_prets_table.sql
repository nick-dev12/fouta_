-- Prêts / avances sur salaire liés à une fiche employé
CREATE TABLE IF NOT EXISTS `employe_prets` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `employe_id` INT(11) NOT NULL,
  `montant` DECIMAL(12,2) NOT NULL COMMENT 'Montant total du prêt',
  `date_octroi` DATE NOT NULL,
  `date_fin_prevue` DATE NULL DEFAULT NULL COMMENT 'Échéance ou fin de remboursement prévue',
  `mensualite` DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'Mensualité ou versement prévu (optionnel)',
  `motif` TEXT NOT NULL,
  `statut` VARCHAR(32) NOT NULL DEFAULT 'en_cours',
  `commentaire` TEXT NULL DEFAULT NULL,
  `admin_id` INT(11) NULL DEFAULT NULL,
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_employe_prets_employe` (`employe_id`),
  KEY `idx_employe_prets_statut` (`statut`),
  KEY `idx_employe_prets_octroi` (`date_octroi`),
  CONSTRAINT `fk_employe_prets_employe` FOREIGN KEY (`employe_id`) REFERENCES `employes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_employe_prets_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
