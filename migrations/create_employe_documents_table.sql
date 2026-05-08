-- Documents attachés à une fiche employé (hors contrat PDF principal)
CREATE TABLE IF NOT EXISTS `employe_documents` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `employe_id` INT(11) NOT NULL,
  `nature` VARCHAR(255) NOT NULL DEFAULT '',
  `fichier_chemin` VARCHAR(500) NOT NULL COMMENT 'Chemin relatif sous upload/ (employes_documents/)',
  `mime_type` VARCHAR(120) NULL DEFAULT NULL,
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_employe_documents_employe` (`employe_id`),
  CONSTRAINT `fk_employe_documents_employe` FOREIGN KEY (`employe_id`) REFERENCES `employes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
