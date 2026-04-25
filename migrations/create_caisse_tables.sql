-- Caisse magasin : ventes au comptoir et historique des tickets
-- Exécuter manuellement sur la base de production si besoin.

CREATE TABLE IF NOT EXISTS `caisse_ventes` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `admin_id` INT(11) NOT NULL,
  `numero_ticket` VARCHAR(32) NOT NULL,
  `montant_total` DECIMAL(12,2) NOT NULL,
  `remise_globale_pct` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `mode_paiement` ENUM('especes','carte','mobile_money','cheque','mixte','autre') NOT NULL DEFAULT 'especes',
  `montant_especes` DECIMAL(12,2) NULL DEFAULT NULL,
  `montant_carte` DECIMAL(12,2) NULL DEFAULT NULL,
  `montant_mobile_money` DECIMAL(12,2) NULL DEFAULT NULL,
  `montant_recu` DECIMAL(12,2) NULL DEFAULT NULL,
  `monnaie_rendue` DECIMAL(12,2) NULL DEFAULT NULL,
  `notes` TEXT NULL,
  `date_vente` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_numero_ticket` (`numero_ticket`),
  KEY `idx_admin` (`admin_id`),
  KEY `idx_date` (`date_vente`),
  CONSTRAINT `fk_caisse_ventes_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `caisse_vente_lignes` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `vente_id` INT(11) NOT NULL,
  `produit_id` INT(11) NOT NULL,
  `designation` VARCHAR(500) NOT NULL,
  `quantite` INT(11) NOT NULL,
  `prix_unitaire` DECIMAL(12,2) NOT NULL,
  `remise_ligne_pct` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `total_ligne` DECIMAL(12,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_vente` (`vente_id`),
  KEY `idx_produit` (`produit_id`),
  CONSTRAINT `fk_cvl_vente` FOREIGN KEY (`vente_id`) REFERENCES `caisse_ventes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cvl_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
