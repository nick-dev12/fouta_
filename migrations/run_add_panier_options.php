<?php
/**
 * Migration: Ajouter couleur, poids, taille à la table panier
 * Exécuter: php migrations/run_add_panier_options.php
 */
require_once __DIR__ . '/../conn/conn.php';

global $db;

$columns = [
    'couleur' => ['def' => 'VARCHAR(255) NULL DEFAULT NULL', 'after' => 'quantite'],
    'poids' => ['def' => 'VARCHAR(100) NULL DEFAULT NULL', 'after' => 'couleur'],
    'taille' => ['def' => 'VARCHAR(100) NULL DEFAULT NULL', 'after' => 'poids']
];

foreach ($columns as $col => $opts) {
    try {
        $stmt = $db->query("SHOW COLUMNS FROM panier LIKE '$col'");
        if ($stmt->rowCount() === 0) {
            $db->exec("ALTER TABLE panier ADD COLUMN `$col` {$opts['def']} AFTER {$opts['after']}");
            echo "Colonne '$col' ajoutée.\n";
        } else {
            echo "Colonne '$col' existe déjà.\n";
        }
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "Colonne '$col' existe déjà.\n";
        } else {
            echo "Erreur $col: " . $e->getMessage() . "\n";
        }
    }
}
echo "Migration panier terminée.\n";
