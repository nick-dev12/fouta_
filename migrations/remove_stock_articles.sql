-- Migration: Supprimer stock_articles et utiliser uniquement produits.stock
-- Exécuter via: php migrations/run_remove_stock_articles.php
-- ATTENTION: Sauvegardez la base avant d'exécuter.

-- 1. Synchroniser produits.stock depuis stock_articles (avant suppression)
UPDATE produits p
INNER JOIN stock_articles s ON p.stock_article_id = s.id
SET p.stock = s.quantite, p.date_modification = NOW()
WHERE p.stock_article_id IS NOT NULL;

-- 2. Supprimer la FK stock_mouvements -> stock_articles
ALTER TABLE stock_mouvements DROP FOREIGN KEY fk_mouvements_stock_article;

-- 3. Supprimer la colonne stock_article_id de stock_mouvements
ALTER TABLE stock_mouvements DROP COLUMN stock_article_id;

-- 4. Supprimer la FK produits -> stock_articles
ALTER TABLE produits DROP FOREIGN KEY fk_produits_stock_article;

-- 5. Supprimer la colonne stock_article_id de produits
ALTER TABLE produits DROP COLUMN stock_article_id;

-- 6. Supprimer la table stock_articles
DROP TABLE IF EXISTS stock_articles;
