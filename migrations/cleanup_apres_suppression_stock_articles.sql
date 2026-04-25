-- =============================================================================
-- Nettoyage APRÈS suppression manuelle de la table stock_articles
-- Exécuter dans phpMyAdmin (onglet SQL)
-- Si une requête échoue avec "Duplicate column" ou "Can't DROP", ignorer (déjà fait)
-- =============================================================================

-- 1. Supprimer la FK stock_mouvements -> stock_articles (si elle existe encore)
ALTER TABLE `stock_mouvements` DROP FOREIGN KEY `fk_mouvements_stock_article`;

-- 2. Supprimer la colonne stock_article_id de stock_mouvements
ALTER TABLE `stock_mouvements` DROP COLUMN `stock_article_id`;

-- 3. Supprimer la FK produits -> stock_articles (si elle existe encore)
ALTER TABLE `produits` DROP FOREIGN KEY `fk_produits_stock_article`;

-- 4. Supprimer la colonne stock_article_id de produits
ALTER TABLE `produits` DROP COLUMN `stock_article_id`;
