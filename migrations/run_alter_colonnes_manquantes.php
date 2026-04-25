<?php
/**
 * Ajoute les colonnes manquantes à une base de production existante
 * Exécuter: php migrations/run_alter_colonnes_manquantes.php
 * Ignore les colonnes qui existent déjà
 */
require_once __DIR__ . '/../conn/conn.php';

function colonne_existe($table, $colonne) {
    global $db;
    $stmt = $db->prepare("
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
    ");
    $stmt->execute([$table, $colonne]);
    return $stmt->fetch() !== false;
}

function alter_si_manquant($table, $colonne, $def) {
    global $db;
    if (!colonne_existe($table, $colonne)) {
        $db->exec("ALTER TABLE `$table` ADD COLUMN `$colonne` $def");
        echo "  + $table.$colonne\n";
    }
}

function modifier_statut_commandes() {
    global $db;
    try {
        $db->exec("ALTER TABLE `commandes` MODIFY COLUMN `statut` ENUM('en_attente', 'confirmee', 'prise_en_charge', 'en_preparation', 'livraison_en_cours', 'expediee', 'livree', 'paye', 'annulee') NOT NULL DEFAULT 'en_attente'");
        echo "  ~ commandes.statut (paye ajouté)\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'paye') === false) echo "  ! " . $e->getMessage() . "\n";
    }
}

try {
    echo "Ajout des colonnes manquantes...\n\n";

    alter_si_manquant('users', 'accepte_conditions', "TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Acceptation des conditions'");
    alter_si_manquant('admin', 'role', "ENUM('admin', 'utilisateur') NOT NULL DEFAULT 'admin'");

    alter_si_manquant('commandes', 'zone_livraison_id', 'INT(11) NULL DEFAULT NULL');
    alter_si_manquant('commandes', 'frais_livraison', 'DECIMAL(10,2) NOT NULL DEFAULT 0');
    alter_si_manquant('commandes', 'client_nom', 'VARCHAR(255) NULL DEFAULT NULL');
    alter_si_manquant('commandes', 'client_prenom', 'VARCHAR(255) NULL DEFAULT NULL');
    alter_si_manquant('commandes', 'client_email', 'VARCHAR(255) NULL DEFAULT NULL');
    alter_si_manquant('commandes', 'client_telephone', 'VARCHAR(50) NULL DEFAULT NULL');
    modifier_statut_commandes();

    alter_si_manquant('commande_produits', 'nom_produit', 'VARCHAR(255) NULL DEFAULT NULL');
    alter_si_manquant('commande_produits', 'couleur', 'VARCHAR(255) NULL DEFAULT NULL');
    alter_si_manquant('commande_produits', 'poids', 'VARCHAR(100) NULL DEFAULT NULL');
    alter_si_manquant('commande_produits', 'taille', 'VARCHAR(100) NULL DEFAULT NULL');
    alter_si_manquant('commande_produits', 'variante_id', 'INT(11) NULL DEFAULT NULL');
    alter_si_manquant('commande_produits', 'variante_nom', 'VARCHAR(255) NULL DEFAULT NULL');
    alter_si_manquant('commande_produits', 'surcout_poids', 'DECIMAL(10,2) NULL DEFAULT 0');
    alter_si_manquant('commande_produits', 'surcout_taille', 'DECIMAL(10,2) NULL DEFAULT 0');

    alter_si_manquant('panier', 'couleur', 'VARCHAR(255) NULL DEFAULT NULL');
    alter_si_manquant('panier', 'poids', 'VARCHAR(100) NULL DEFAULT NULL');
    alter_si_manquant('panier', 'taille', 'VARCHAR(100) NULL DEFAULT NULL');
    alter_si_manquant('panier', 'variante_id', 'INT(11) NULL DEFAULT NULL');
    alter_si_manquant('panier', 'variante_nom', 'VARCHAR(255) NULL DEFAULT NULL');
    alter_si_manquant('panier', 'variante_image', 'VARCHAR(255) NULL DEFAULT NULL');
    alter_si_manquant('panier', 'surcout_poids', 'DECIMAL(10,2) NULL DEFAULT 0');
    alter_si_manquant('panier', 'surcout_taille', 'DECIMAL(10,2) NULL DEFAULT 0');
    alter_si_manquant('panier', 'prix_unitaire', 'DECIMAL(10,2) NULL DEFAULT NULL');

    alter_si_manquant('produits', 'couleurs', "VARCHAR(255) NULL DEFAULT NULL COMMENT 'Couleurs disponibles'");
    alter_si_manquant('produits', 'taille', "VARCHAR(255) NULL DEFAULT NULL COMMENT 'Tailles disponibles'");

    alter_si_manquant('factures', 'token', 'VARCHAR(64) NULL DEFAULT NULL');

    // Rendre user_id nullable pour commandes manuelles
    try {
        $db->exec("ALTER TABLE `commandes` MODIFY COLUMN `user_id` INT(11) NULL DEFAULT NULL");
        echo "  ~ commandes.user_id (nullable pour commandes manuelles)\n";
    } catch (PDOException $e) {
        echo "  ! user_id: " . $e->getMessage() . "\n";
    }

    echo "\nTerminé.\n";
} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
    exit(1);
}
