-- Matricules RH au format FPLxxxxxx — une ligne unique par employé
CREATE TABLE IF NOT EXISTS `employes_matricules` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `employe_id` INT NOT NULL,
  `matricule` VARCHAR(12) NOT NULL COMMENT 'Ex. FPL123456 — prefixe + 6 chiffres',
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_employes_matricules_matricule` (`matricule`),
  UNIQUE KEY `uq_employes_matricules_employe` (`employe_id`),
  CONSTRAINT `fk_employes_matricules_employe` FOREIGN KEY (`employe_id`) REFERENCES `employes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
