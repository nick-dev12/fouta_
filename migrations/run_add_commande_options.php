<?php
/**
 * Migration: Ajouter couleur, poids, taille à commande_produits
 * Exécuter: php migrations/run_add_commande_options.php
 */
require_once __DIR__ . '/../conn/conn.php';

global $db;

$columns = ['couleur' => 'VARCHAR(255) NULL DEFAULT NULL', 'poids' => 'VARCHAR(100) NULL DEFAULT NULL', 'taille' => 'VARCHAR(100) NULL DEFAULT NULL'];
$after = 'prix_total';

foreach ($columns as $name => $def) {
    try {
        $db->exec("ALTER TABLE commande_produits ADD COLUMN `$name` $def AFTER $after");
        echo "Colonne $name ajoutée.\n";
        $after = $name;
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "Colonne $name existe déjà.\n";
        } else {
            echo "Erreur $name: " . $e->getMessage() . "\n";
        }
    }
}
echo "Migration terminée.\n";
