<?php
/**
 * Ajoute produits.nom_fournisseur (nom du fournisseur, optionnel).
 * php migrations/run_add_produits_nom_fournisseur.php
 */
require_once __DIR__ . '/../conn/conn.php';

function colonne_existe_nf($table, $colonne) {
    global $db;
    $stmt = $db->prepare("
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
    ");
    $stmt->execute([$table, $colonne]);
    return $stmt->fetch() !== false;
}

try {
    if (!colonne_existe_nf('produits', 'nom_fournisseur')) {
        $db->exec("
            ALTER TABLE `produits`
            ADD COLUMN `nom_fournisseur` VARCHAR(255) NULL DEFAULT NULL
            COMMENT 'Nom ou raison sociale du fournisseur (optionnel)'
            AFTER `description`
        ");
        echo "+ produits.nom_fournisseur\n";
    } else {
        echo "— produits.nom_fournisseur existe déjà\n";
    }
} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
    exit(1);
}
