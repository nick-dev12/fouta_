<?php
/**
 * Migration: Table stock_mouvements
 * Exécuter: php migrations/run_add_stock_mouvements.php
 */
require_once __DIR__ . '/../conn/conn.php';

global $db;

try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS `stock_mouvements` (
          `id` INT(11) NOT NULL AUTO_INCREMENT,
          `type` ENUM('entree', 'sortie', 'inventaire') NOT NULL,
          `stock_article_id` INT(11) NULL DEFAULT NULL,
          `produit_id` INT(11) NULL DEFAULT NULL,
          `quantite` INT(11) NOT NULL,
          `quantite_avant` INT(11) NULL DEFAULT NULL,
          `quantite_apres` INT(11) NULL DEFAULT NULL,
          `reference_type` VARCHAR(50) NULL DEFAULT NULL,
          `reference_id` INT(11) NULL DEFAULT NULL,
          `reference_numero` VARCHAR(100) NULL DEFAULT NULL,
          `date_mouvement` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `notes` TEXT NULL,
          PRIMARY KEY (`id`),
          KEY `idx_stock_article` (`stock_article_id`),
          KEY `idx_produit` (`produit_id`),
          KEY `idx_type` (`type`),
          KEY `idx_date` (`date_mouvement`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "Table stock_mouvements créée ou existe déjà.\n";

    // Ajouter les FK si les tables existent
    try {
        $db->exec("ALTER TABLE stock_mouvements ADD CONSTRAINT fk_mouvements_stock_article FOREIGN KEY (stock_article_id) REFERENCES stock_articles(id) ON DELETE SET NULL ON UPDATE CASCADE");
        echo "FK stock_article ajoutée.\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate') === false) {
            echo "Note FK stock_article: " . $e->getMessage() . "\n";
        }
    }
    try {
        $db->exec("ALTER TABLE stock_mouvements ADD CONSTRAINT fk_mouvements_produit FOREIGN KEY (produit_id) REFERENCES produits(id) ON DELETE SET NULL ON UPDATE CASCADE");
        echo "FK produit ajoutée.\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate') === false) {
            echo "Note FK produit: " . $e->getMessage() . "\n";
        }
    }

    echo "Migration stock_mouvements terminée.\n";
} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
    exit(1);
}
