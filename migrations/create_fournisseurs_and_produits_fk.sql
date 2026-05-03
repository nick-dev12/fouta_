-- Table fournisseurs + liaison produits.fournisseur_id
-- Exécuter via migrations/run_create_fournisseurs.php

CREATE TABLE IF NOT EXISTS `fournisseurs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(255) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fournisseurs_nom` (`nom`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Colonne FK (sans contrainte ici si elle existe déjà ; le script PHP gère ALTER)
