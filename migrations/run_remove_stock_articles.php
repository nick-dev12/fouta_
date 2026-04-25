<?php
/**
 * Migration: Supprimer stock_articles et utiliser uniquement produits.stock
 * Exécuter: php migrations/run_remove_stock_articles.php
 *
 * Cette migration supprime définitivement la table stock_articles et la colonne
 * stock_article_id. Le stock est géré exclusivement par produits.stock.
 */
require_once __DIR__ . '/../conn/conn.php';

try {
    // Vérifier si stock_articles existe
    $stmt = $db->query("SHOW TABLES LIKE 'stock_articles'");
    if (!$stmt || $stmt->rowCount() === 0) {
        echo "La table stock_articles n'existe pas. Rien à faire.\n";
        exit(0);
    }

    // 1. Synchroniser produits.stock depuis stock_articles (si colonne existe)
    $stmt = $db->query("SHOW COLUMNS FROM produits LIKE 'stock_article_id'");
    if ($stmt && $stmt->rowCount() > 0) {
        $db->exec("
            UPDATE produits p
            INNER JOIN stock_articles s ON p.stock_article_id = s.id
            SET p.stock = s.quantite, p.date_modification = NOW()
            WHERE p.stock_article_id IS NOT NULL
        ");
        echo "Stock synchronisé vers produits.\n";
    }

    // 2. Supprimer FK stock_mouvements -> stock_articles (si table et FK existent)
    $stmt = $db->query("SHOW TABLES LIKE 'stock_mouvements'");
    if ($stmt && $stmt->rowCount() > 0) {
        try {
            $db->exec("ALTER TABLE stock_mouvements DROP FOREIGN KEY fk_mouvements_stock_article");
            echo "FK stock_mouvements supprimée.\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), '1091') !== false) echo "FK stock_mouvements déjà absente.\n";
            else throw $e;
        }

        // 3. Supprimer colonne stock_article_id de stock_mouvements
        $stmt = $db->query("SHOW COLUMNS FROM stock_mouvements LIKE 'stock_article_id'");
        if ($stmt && $stmt->rowCount() > 0) {
            $db->exec("ALTER TABLE stock_mouvements DROP COLUMN stock_article_id");
            echo "Colonne stock_article_id supprimée de stock_mouvements.\n";
        }
    }

    // 4. Supprimer FK produits -> stock_articles (si existe)
    try {
        $db->exec("ALTER TABLE produits DROP FOREIGN KEY fk_produits_stock_article");
        echo "FK produits supprimée.\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), '1091') !== false) echo "FK produits déjà absente.\n";
        else throw $e;
    }

    // 5. Supprimer colonne stock_article_id de produits
    $stmt = $db->query("SHOW COLUMNS FROM produits LIKE 'stock_article_id'");
    if ($stmt && $stmt->rowCount() > 0) {
        $db->exec("ALTER TABLE produits DROP COLUMN stock_article_id");
        echo "Colonne stock_article_id supprimée de produits.\n";
    }

    // 6. Supprimer la table stock_articles
    $db->exec("DROP TABLE IF EXISTS stock_articles");
    echo "Table stock_articles supprimée.\n";

    echo "\nMigration terminée. Le stock est désormais géré uniquement par produits.stock.\n";
} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
    exit(1);
}
