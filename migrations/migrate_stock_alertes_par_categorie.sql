-- Alertes stock : périmètre par catégorie / sous-catégorie
-- Un même niveau peut avoir plusieurs règles (périmètres différents).

-- Retirer la contrainte « un seul seuil par niveau » (global)
ALTER TABLE `stock_alertes_regles` DROP INDEX `uq_stock_alertes_niveau`;

CREATE TABLE IF NOT EXISTS `stock_alertes_regles_categories` (
  `regle_id` int NOT NULL,
  `categorie_id` int NOT NULL,
  PRIMARY KEY (`regle_id`, `categorie_id`),
  KEY `idx_sar_cat_categorie` (`categorie_id`),
  CONSTRAINT `fk_sar_cat_regle` FOREIGN KEY (`regle_id`) REFERENCES `stock_alertes_regles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_sar_cat_categorie` FOREIGN KEY (`categorie_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `stock_alertes_regles_sous_categories` (
  `regle_id` int NOT NULL,
  `sous_categorie_id` int NOT NULL,
  PRIMARY KEY (`regle_id`, `sous_categorie_id`),
  KEY `idx_sar_sc_sous_cat` (`sous_categorie_id`),
  CONSTRAINT `fk_sar_sc_regle` FOREIGN KEY (`regle_id`) REFERENCES `stock_alertes_regles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_sar_sc_sous_categorie` FOREIGN KEY (`sous_categorie_id`) REFERENCES `sous_categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
