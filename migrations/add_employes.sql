-- Fiches employés (RH) — liées optionnellement à un compte admin
CREATE TABLE IF NOT EXISTS `employes` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `nom` VARCHAR(120) NOT NULL,
  `prenom` VARCHAR(120) NOT NULL,
  `email` VARCHAR(255) NULL DEFAULT NULL,
  `telephone` VARCHAR(50) NULL DEFAULT NULL,
  `poste` VARCHAR(150) NULL DEFAULT NULL,
  `service` VARCHAR(150) NULL DEFAULT NULL,
  `date_embauche` DATE NULL DEFAULT NULL,
  `statut` ENUM('actif','inactif','suspendu') NOT NULL DEFAULT 'actif',
  `notes` TEXT NULL DEFAULT NULL,
  `admin_id` INT(11) NULL DEFAULT NULL COMMENT 'Compte d accès interne lié (optionnel)',
  `qr_chemin` VARCHAR(500) NULL DEFAULT NULL COMMENT 'Chemin relatif PNG sous upload/ (ex employes_qr/employe_1.png)',
  `qr_payload` VARCHAR(2048) NULL DEFAULT NULL COMMENT 'Donnees encodees dans le QR (audit)',
  `photo_chemin` VARCHAR(400) NULL DEFAULT NULL COMMENT 'Photo RH (chemin sous upload/, ex employes_photos/...)',
  `matricule` VARCHAR(12) NULL DEFAULT NULL COMMENT 'Dénormalisé depuis employes_matricules (FPLxxxxxx)',
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_employes_matricule` (`matricule`),
  KEY `idx_statut` (`statut`),
  KEY `idx_admin` (`admin_id`),
  CONSTRAINT `fk_employes_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
