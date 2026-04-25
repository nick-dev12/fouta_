-- Migration: Table stock_articles et colonne stock_article_id sur produits
-- À exécuter via: php migrations/run_add_stock_articles.php

-- Table stock_articles (articles en stock physique)
CREATE TABLE IF NOT EXISTS `stock_articles` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `nom` VARCHAR(255) NOT NULL,
  `image_principale` VARCHAR(255) NULL,
  `quantite` INT(11) NOT NULL DEFAULT 0,
  `categorie_id` INT(11) NOT NULL,
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_categorie` (`categorie_id`),
  CONSTRAINT `fk_stock_articles_categorie` FOREIGN KEY (`categorie_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ajouter la colonne stock_article_id à produits (lien optionnel)
-- Note: MySQL ne supporte pas IF NOT EXISTS pour ADD COLUMN, exécuter via run_add_stock_articles.php
