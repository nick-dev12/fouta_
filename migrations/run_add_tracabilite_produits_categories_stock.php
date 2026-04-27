<?php
/**
 * Traçabilité : créateur / dernier modificateur (produits, catégories) et admin sur mouvements de stock.
 * Exécuter: php migrations/run_add_tracabilite_produits_categories_stock.php
 */
require_once __DIR__ . '/../conn/conn.php';

function colonne_existe_ts($table, $colonne) {
    global $db;
    $stmt = $db->prepare("
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
    ");
    $stmt->execute([$table, $colonne]);
    return $stmt->fetch() !== false;
}

function alter_si_manquant_ts($table, $colonne, $def) {
    global $db;
    if (!colonne_existe_ts($table, $colonne)) {
        $db->exec("ALTER TABLE `$table` ADD COLUMN `$colonne` $def");
        echo "  + $table.$colonne\n";
    }
}

function table_existe_ts($table) {
    global $db;
    $stmt = $db->prepare("
        SELECT 1 FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
    ");
    $stmt->execute([$table]);
    return $stmt->fetch() !== false;
}

try {
    echo "Traçabilité produits / catégories / stock_mouvements...\n\n";

    alter_si_manquant_ts('produits', 'admin_createur_id', 'INT(11) NULL DEFAULT NULL');
    alter_si_manquant_ts('produits', 'admin_dernier_modificateur_id', 'INT(11) NULL DEFAULT NULL');

    alter_si_manquant_ts('categories', 'admin_createur_id', 'INT(11) NULL DEFAULT NULL');
    alter_si_manquant_ts('categories', 'admin_dernier_modificateur_id', 'INT(11) NULL DEFAULT NULL');

    if (table_existe_ts('stock_mouvements')) {
        alter_si_manquant_ts('stock_mouvements', 'admin_id', 'INT(11) NULL DEFAULT NULL');
    } else {
        echo "  ! Table stock_mouvements absente — exécutez d'abord run_add_stock_mouvements.php\n";
    }

    // Index pour les listes d'activité
    try {
        $db->exec("CREATE INDEX idx_produits_admin_createur ON produits (admin_createur_id)");
        echo "  + index idx_produits_admin_createur\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate') === false) {
            echo "  ! idx produits createur: " . $e->getMessage() . "\n";
        }
    }
    try {
        $db->exec("CREATE INDEX idx_produits_admin_modif ON produits (admin_dernier_modificateur_id)");
        echo "  + index idx_produits_admin_modif\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate') === false) {
            echo "  ! idx produits modif: " . $e->getMessage() . "\n";
        }
    }
    try {
        $db->exec("CREATE INDEX idx_categories_admin_createur ON categories (admin_createur_id)");
        echo "  + index idx_categories_admin_createur\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate') === false) {
            echo "  ! idx categories: " . $e->getMessage() . "\n";
        }
    }
    if (table_existe_ts('stock_mouvements') && colonne_existe_ts('stock_mouvements', 'admin_id')) {
        try {
            $db->exec("CREATE INDEX idx_stock_mouvements_admin ON stock_mouvements (admin_id)");
            echo "  + index idx_stock_mouvements_admin\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate') === false) {
                echo "  ! idx stock_mouvements admin: " . $e->getMessage() . "\n";
            }
        }
    }

    // Clés étrangères optionnelles (échouent si déjà présentes ou contraintes nommées différemment)
    $fks = [
        "ALTER TABLE produits ADD CONSTRAINT fk_produits_admin_createur FOREIGN KEY (admin_createur_id) REFERENCES admin(id) ON DELETE SET NULL ON UPDATE CASCADE",
        "ALTER TABLE produits ADD CONSTRAINT fk_produits_admin_modif FOREIGN KEY (admin_dernier_modificateur_id) REFERENCES admin(id) ON DELETE SET NULL ON UPDATE CASCADE",
        "ALTER TABLE categories ADD CONSTRAINT fk_categories_admin_createur FOREIGN KEY (admin_createur_id) REFERENCES admin(id) ON DELETE SET NULL ON UPDATE CASCADE",
        "ALTER TABLE categories ADD CONSTRAINT fk_categories_admin_modif FOREIGN KEY (admin_dernier_modificateur_id) REFERENCES admin(id) ON DELETE SET NULL ON UPDATE CASCADE",
    ];
    if (table_existe_ts('stock_mouvements') && colonne_existe_ts('stock_mouvements', 'admin_id')) {
        $fks[] = "ALTER TABLE stock_mouvements ADD CONSTRAINT fk_stock_mouvements_admin FOREIGN KEY (admin_id) REFERENCES admin(id) ON DELETE SET NULL ON UPDATE CASCADE";
    }
    foreach ($fks as $sql) {
        try {
            $db->exec($sql);
            echo "  + FK appliquée\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate') === false && strpos($e->getMessage(), '1022') === false) {
                echo "  ! FK: " . $e->getMessage() . "\n";
            }
        }
    }

    echo "\nTerminé.\n";
} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
    exit(1);
}
