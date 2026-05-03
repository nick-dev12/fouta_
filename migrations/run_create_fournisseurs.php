<?php
/**
 * Crée la table fournisseurs, ajoute produits.fournisseur_id (+ FK si possible),
 * rattache les produits ayant déjà nom_fournisseur aux entrées créées.
 *
 * php migrations/run_create_fournisseurs.php
 */
require_once __DIR__ . '/../conn/conn.php';

function table_existe_ff($nom) {
    global $db;
    $stmt = $db->prepare(
        'SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
    );
    $stmt->execute([$nom]);
    return $stmt->fetch() !== false;
}

function colonne_existe_ff($table, $colonne) {
    global $db;
    $stmt = $db->prepare(
        'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
    );
    $stmt->execute([$table, $colonne]);
    return $stmt->fetch() !== false;
}

function fk_exists($fk_name) {
    global $db;
    $stmt = $db->prepare(
        "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'produits'
         AND CONSTRAINT_TYPE = 'FOREIGN KEY' AND CONSTRAINT_NAME = ? LIMIT 1"
    );
    $stmt->execute([$fk_name]);
    return $stmt->fetch() !== false;
}

try {
    $sqlFile = __DIR__ . '/create_fournisseurs_and_produits_fk.sql';
    if (file_exists($sqlFile)) {
        $db->exec(file_get_contents($sqlFile));
    } elseif (!table_existe_ff('fournisseurs')) {
        $db->exec("CREATE TABLE IF NOT EXISTS `fournisseurs` (
          `id` int NOT NULL AUTO_INCREMENT,
          `nom` varchar(255) NOT NULL,
          `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_fournisseurs_nom` (`nom`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    echo table_existe_ff('fournisseurs') ? "+ table fournisseurs OK\n" : "! table fournisseurs absente\n";

    if (!colonne_existe_ff('produits', 'fournisseur_id')) {
        $after = colonne_existe_ff('produits', 'nom_fournisseur') ? ' AFTER `nom_fournisseur`' : '';
        $db->exec(
            'ALTER TABLE `produits` ADD COLUMN `fournisseur_id` INT NULL DEFAULT NULL' . $after
        );
        echo "+ produits.fournisseur_id\n";
    } else {
        echo "— produits.fournisseur_id existe déjà\n";
    }

    if (colonne_existe_ff('produits', 'nom_fournisseur')) {
        $ins = $db->prepare(
            'INSERT IGNORE INTO fournisseurs (nom, date_creation) SELECT DISTINCT TRIM(nom_fournisseur), NOW()
             FROM produits WHERE nom_fournisseur IS NOT NULL AND CHAR_LENGTH(TRIM(nom_fournisseur)) > 0'
        );
        $ins->execute();
        $db->exec(
            'UPDATE produits p INNER JOIN fournisseurs f ON TRIM(p.nom_fournisseur) = f.nom
             SET p.fournisseur_id = f.id WHERE p.fournisseur_id IS NULL'
        );
        echo "+ rattachement produits depuis nom_fournisseur (distinct)\n";
    }

    $fk_name = 'fk_produits_fournisseur';
    if (!fk_exists($fk_name)) {
        try {
            $db->exec(
                "ALTER TABLE `produits` ADD CONSTRAINT `$fk_name` FOREIGN KEY (`fournisseur_id`)
                 REFERENCES `fournisseurs` (`id`) ON DELETE SET NULL ON UPDATE CASCADE"
            );
            echo "+ contrainte FK $fk_name\n";
        } catch (PDOException $e) {
            echo "! FK non créée (moteur / droits): " . $e->getMessage() . "\n";
        }
    } else {
        echo "— FK existe déjà\n";
    }
} catch (PDOException $e) {
    echo 'Erreur: ' . $e->getMessage() . "\n";
    exit(1);
}
