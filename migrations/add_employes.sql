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
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_statut` (`statut`),
  KEY `idx_admin` (`admin_id`),
  CONSTRAINT `fk_employes_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
