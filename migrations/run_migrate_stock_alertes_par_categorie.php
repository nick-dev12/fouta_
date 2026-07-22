<?php
/**
 * Périmètre catégorie / sous-catégorie pour les alertes stock.
 * php migrations/run_migrate_stock_alertes_par_categorie.php
 */
require_once __DIR__ . '/../conn/conn.php';

function stock_alertes_mig_table_exists($table)
{
    global $db;
    $stmt = $db->prepare('
        SELECT 1 FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
    ');
    $stmt->execute([$table]);
    return $stmt->fetch() !== false;
}

function stock_alertes_mig_index_exists($table, $index)
{
    global $db;
    $stmt = $db->prepare('
        SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?
    ');
    $stmt->execute([$table, $index]);
    return $stmt->fetch() !== false;
}

try {
    if (!stock_alertes_mig_table_exists('stock_alertes_regles')) {
        echo "Table stock_alertes_regles absente — exécutez d'abord run_create_stock_alertes.php\n";
        exit(1);
    }

    if (stock_alertes_mig_index_exists('stock_alertes_regles', 'uq_stock_alertes_niveau')) {
        $db->exec('ALTER TABLE `stock_alertes_regles` DROP INDEX `uq_stock_alertes_niveau`');
        echo "+ Index uq_stock_alertes_niveau supprimé\n";
    } else {
        echo "— Index uq_stock_alertes_niveau déjà absent\n";
    }

    $statements = [
        "CREATE TABLE IF NOT EXISTS `stock_alertes_regles_categories` (
  `regle_id` int NOT NULL,
  `categorie_id` int NOT NULL,
  PRIMARY KEY (`regle_id`, `categorie_id`),
  KEY `idx_sar_cat_categorie` (`categorie_id`),
  CONSTRAINT `fk_sar_cat_regle` FOREIGN KEY (`regle_id`) REFERENCES `stock_alertes_regles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_sar_cat_categorie` FOREIGN KEY (`categorie_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS `stock_alertes_regles_sous_categories` (
  `regle_id` int NOT NULL,
  `sous_categorie_id` int NOT NULL,
  PRIMARY KEY (`regle_id`, `sous_categorie_id`),
  KEY `idx_sar_sc_sous_cat` (`sous_categorie_id`),
  CONSTRAINT `fk_sar_sc_regle` FOREIGN KEY (`regle_id`) REFERENCES `stock_alertes_regles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_sar_sc_sous_categorie` FOREIGN KEY (`sous_categorie_id`) REFERENCES `sous_categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];

    foreach ($statements as $sql) {
        try {
            $db->exec($sql);
        } catch (PDOException $e) {
            if (stripos($e->getMessage(), 'already exists') === false) {
                throw $e;
            }
        }
    }

    echo "+ Tables stock_alertes_regles_categories / stock_alertes_regles_sous_categories OK\n";
    echo "Migration terminée.\n";
} catch (PDOException $e) {
    echo 'Erreur: ' . $e->getMessage() . "\n";
    exit(1);
}
