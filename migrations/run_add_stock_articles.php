<?php
/**
 * Migration: Table stock_articles et colonne stock_article_id sur produits
 * Exécuter: php migrations/run_add_stock_articles.php
 */
require_once __DIR__ . '/../conn/conn.php';

global $db;

try {
    // 1. Créer la table stock_articles
    $db->exec("
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "Table stock_articles créée ou existe déjà.\n";

    // 2. Ajouter stock_article_id à produits si absent
    $stmt = $db->query("SHOW COLUMNS FROM produits LIKE 'stock_article_id'");
    if ($stmt->rowCount() === 0) {
        $db->exec("ALTER TABLE produits ADD COLUMN stock_article_id INT(11) NULL DEFAULT NULL AFTER categorie_id");
        $db->exec("ALTER TABLE produits ADD KEY idx_stock_article (stock_article_id)");
        try {
            $db->exec("ALTER TABLE produits ADD CONSTRAINT fk_produits_stock_article FOREIGN KEY (stock_article_id) REFERENCES stock_articles(id) ON DELETE SET NULL ON UPDATE CASCADE");
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate foreign key') === false) {
                throw $e;
            }
        }
        echo "Colonne stock_article_id ajoutée à produits.\n";
    } else {
        echo "Colonne stock_article_id existe déjà.\n";
    }

    echo "Migration stock_articles terminée.\n";
} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
    exit(1);
}
