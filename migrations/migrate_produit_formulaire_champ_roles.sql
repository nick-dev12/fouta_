-- Accès par rôle (type de compte admin) aux champs produit

CREATE TABLE IF NOT EXISTS `produit_formulaire_champ_role` (
  `champ_id` INT UNSIGNED NOT NULL,
  `role` VARCHAR(32) NOT NULL,
  `date_modification` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`champ_id`, `role`),
  KEY `idx_pfcr_role` (`role`),
  CONSTRAINT `fk_pfcr_champ` FOREIGN KEY (`champ_id`) REFERENCES `produit_formulaire_champ` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
