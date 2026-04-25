<?php
/**
 * Migration production - Ajouts uniquement (aucune suppression de données)
 * Exécuter : php migrations/run_migration_production_ajouts.php
 *
 * Ce script crée les tables manquantes et ajoute les colonnes manquantes.
 * Compatible MySQL 5.7+ (contrairement au fichier .sql qui requiert MySQL 8.0.29+).
 */

require_once __DIR__ . '/../conn/conn.php';

global $db;

if (!$db instanceof PDO) {
    echo "Connexion à la base indisponible.\n";
    exit(1);
}

$ok = 0;
$skip = 0;
$err = 0;

function run_sql($sql, $desc = '') {
    global $db, $ok, $skip, $err;
    $sql = trim($sql);
    if (empty($sql) || preg_match('/^--/', $sql)) return true;
    try {
        $db->exec($sql);
        echo "OK: " . ($desc ?: substr($sql, 0, 60)) . "...\n";
        $ok++;
        return true;
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        if (preg_match('/Duplicate (column|key name|entry)/i', $msg) || strpos($msg, 'already exists') !== false) {
            echo "IGNORÉ (existe déjà): " . ($desc ?: substr($sql, 0, 50)) . "...\n";
            $skip++;
            return true;
        }
        echo "ERREUR: $msg\n";
        $err++;
        return false;
    }
}

echo "=== Migration production - Ajouts uniquement ===\n\n";

// Exécuter les CREATE TABLE du fichier SQL (Partie 1)
$sql_content = file_get_contents(__DIR__ . '/migration_production_ajouts.sql');
$sql_content = preg_replace('/--[^\n]*\n/', "\n", $sql_content);
$statements = preg_split('/;\s*\n/', $sql_content);
foreach ($statements as $stmt) {
    $stmt = trim($stmt);
    if (empty($stmt)) continue;
    if (preg_match('/^SET\s+/', $stmt)) { $db->exec($stmt); continue; }
    if (preg_match('/^CREATE TABLE IF NOT EXISTS/', $stmt)) {
        run_sql($stmt . ';', 'CREATE TABLE');
    }
}

// Colonnes à ajouter (ALTER TABLE ADD COLUMN - sans IF NOT EXISTS pour compatibilité)
$alter_columns = [
    "ALTER TABLE `users` ADD COLUMN `accepte_conditions` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Acceptation des conditions' AFTER `statut`",
    "ALTER TABLE `admin` ADD COLUMN `role` ENUM('admin', 'utilisateur') NOT NULL DEFAULT 'admin' AFTER `statut`",
    "ALTER TABLE `produits` ADD COLUMN `couleurs` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Couleurs disponibles' AFTER `unite`",
    "ALTER TABLE `produits` ADD COLUMN `taille` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Tailles disponibles' AFTER `couleurs`",
    "ALTER TABLE `produits` ADD COLUMN `stock_article_id` INT(11) NULL DEFAULT NULL AFTER `categorie_id`",
    "ALTER TABLE `commandes` ADD COLUMN `zone_livraison_id` INT(11) NULL DEFAULT NULL AFTER `adresse_livraison`",
    "ALTER TABLE `commandes` ADD COLUMN `frais_livraison` DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER `zone_livraison_id`",
    "ALTER TABLE `commandes` ADD COLUMN `client_nom` VARCHAR(255) NULL DEFAULT NULL AFTER `user_id`",
    "ALTER TABLE `commandes` ADD COLUMN `client_prenom` VARCHAR(255) NULL DEFAULT NULL AFTER `client_nom`",
    "ALTER TABLE `commandes` ADD COLUMN `client_email` VARCHAR(255) NULL DEFAULT NULL AFTER `client_prenom`",
    "ALTER TABLE `commandes` ADD COLUMN `client_telephone` VARCHAR(50) NULL DEFAULT NULL AFTER `client_email`",
    "ALTER TABLE `commande_produits` ADD COLUMN `nom_produit` VARCHAR(255) NULL DEFAULT NULL AFTER `produit_id`",
    "ALTER TABLE `commande_produits` ADD COLUMN `couleur` VARCHAR(255) NULL DEFAULT NULL AFTER `prix_total`",
    "ALTER TABLE `commande_produits` ADD COLUMN `poids` VARCHAR(100) NULL DEFAULT NULL AFTER `couleur`",
    "ALTER TABLE `commande_produits` ADD COLUMN `taille` VARCHAR(100) NULL DEFAULT NULL AFTER `poids`",
    "ALTER TABLE `commande_produits` ADD COLUMN `variante_id` INT(11) NULL DEFAULT NULL AFTER `taille`",
    "ALTER TABLE `commande_produits` ADD COLUMN `variante_nom` VARCHAR(255) NULL DEFAULT NULL AFTER `variante_id`",
    "ALTER TABLE `commande_produits` ADD COLUMN `surcout_poids` DECIMAL(10,2) NULL DEFAULT 0 AFTER `variante_nom`",
    "ALTER TABLE `commande_produits` ADD COLUMN `surcout_taille` DECIMAL(10,2) NULL DEFAULT 0 AFTER `surcout_poids`",
    "ALTER TABLE `panier` ADD COLUMN `couleur` VARCHAR(255) NULL DEFAULT NULL AFTER `quantite`",
    "ALTER TABLE `panier` ADD COLUMN `poids` VARCHAR(100) NULL DEFAULT NULL AFTER `couleur`",
    "ALTER TABLE `panier` ADD COLUMN `taille` VARCHAR(100) NULL DEFAULT NULL AFTER `poids`",
    "ALTER TABLE `panier` ADD COLUMN `variante_id` INT(11) NULL DEFAULT NULL AFTER `taille`",
    "ALTER TABLE `panier` ADD COLUMN `variante_nom` VARCHAR(255) NULL DEFAULT NULL AFTER `variante_id`",
    "ALTER TABLE `panier` ADD COLUMN `variante_image` VARCHAR(255) NULL DEFAULT NULL AFTER `variante_nom`",
    "ALTER TABLE `panier` ADD COLUMN `surcout_poids` DECIMAL(10,2) NULL DEFAULT 0 AFTER `variante_image`",
    "ALTER TABLE `panier` ADD COLUMN `surcout_taille` DECIMAL(10,2) NULL DEFAULT 0 AFTER `surcout_poids`",
    "ALTER TABLE `panier` ADD COLUMN `prix_unitaire` DECIMAL(10,2) NULL DEFAULT NULL AFTER `surcout_taille`",
    "ALTER TABLE `factures` ADD COLUMN `token` VARCHAR(64) NULL DEFAULT NULL UNIQUE AFTER `date_creation`",
    "ALTER TABLE `commandes_personnalisees` ADD COLUMN `image_reference` VARCHAR(255) NULL DEFAULT NULL AFTER `description`",
];

foreach ($alter_columns as $sql) {
    run_sql($sql);
}

// Modifier statut commandes
run_sql("ALTER TABLE `commandes` MODIFY COLUMN `statut` ENUM('en_attente', 'confirmee', 'prise_en_charge', 'en_preparation', 'livraison_en_cours', 'expediee', 'livree', 'annulee') NOT NULL DEFAULT 'en_attente'");

echo "\n=== Terminé ===\n";
echo "OK: $ok | Ignorés (existe déjà): $skip | Erreurs: $err\n";
