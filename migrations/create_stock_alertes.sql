-- Seuils d'alerte stock (un enregistrement par niveau : standard, moyen, haut)
CREATE TABLE IF NOT EXISTS `stock_alertes_regles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `niveau` enum('standard','moyen','haut') NOT NULL,
  `seuil` int unsigned NOT NULL COMMENT 'Alerte lorsque stock <= seuil',
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_stock_alertes_niveau` (`niveau`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
