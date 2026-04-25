<?php
/**
 * Migration: Colonne nom_produit sur commande_produits (libellé personnalisable)
 * Exécuter: php migrations/run_add_commande_produits_nom.php
 */
require_once __DIR__ . '/../conn/conn.php';

global $db;

try {
    $stmt = $db->query("SHOW COLUMNS FROM commande_produits LIKE 'nom_produit'");
    if ($stmt->rowCount() === 0) {
        $db->exec("ALTER TABLE commande_produits ADD COLUMN nom_produit VARCHAR(255) NULL DEFAULT NULL AFTER produit_id");
        echo "Colonne nom_produit ajoutée.\n";
    } else {
        echo "Colonne nom_produit existe déjà.\n";
    }
} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
