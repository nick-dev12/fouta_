-- =============================================================================
-- AJOUT DES COLONNES MANQUANTES (base existante)
-- Préférer: php migrations/run_alter_colonnes_manquantes.php (vérifie avant d'ajouter)
-- Ce fichier SQL brut peut générer des erreurs "Duplicate column" si colonne existe
-- =============================================================================

SET NAMES utf8mb4;

-- USERS: accepte_conditions
ALTER TABLE `users` ADD COLUMN `accepte_conditions` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Acceptation des conditions (0=non, 1=oui)' AFTER `statut`;

-- ADMIN: role
ALTER TABLE `admin` ADD COLUMN `role` ENUM('admin', 'utilisateur') NOT NULL DEFAULT 'admin' AFTER `statut`;

-- COMMANDES: zone_livraison_id, frais_livraison, client_*
ALTER TABLE `commandes` ADD COLUMN `zone_livraison_id` INT(11) NULL DEFAULT NULL AFTER `adresse_livraison`;
ALTER TABLE `commandes` ADD COLUMN `frais_livraison` DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER `zone_livraison_id`;
ALTER TABLE `commandes` ADD COLUMN `client_nom` VARCHAR(255) NULL DEFAULT NULL AFTER `user_id`;
ALTER TABLE `commandes` ADD COLUMN `client_prenom` VARCHAR(255) NULL DEFAULT NULL AFTER `client_nom`;
ALTER TABLE `commandes` ADD COLUMN `client_email` VARCHAR(255) NULL DEFAULT NULL AFTER `client_prenom`;
ALTER TABLE `commandes` ADD COLUMN `client_telephone` VARCHAR(50) NULL DEFAULT NULL AFTER `client_email`;

-- COMMANDES: statut avec 'paye'
ALTER TABLE `commandes` MODIFY COLUMN `statut` ENUM('en_attente', 'confirmee', 'prise_en_charge', 'en_preparation', 'livraison_en_cours', 'expediee', 'livree', 'paye', 'annulee') NOT NULL DEFAULT 'en_attente';

-- COMMANDE_PRODUITS: nom_produit, couleur, poids, taille, variante_*, surcout_*
ALTER TABLE `commande_produits` ADD COLUMN `nom_produit` VARCHAR(255) NULL DEFAULT NULL AFTER `produit_id`;
ALTER TABLE `commande_produits` ADD COLUMN `couleur` VARCHAR(255) NULL DEFAULT NULL AFTER `prix_total`;
ALTER TABLE `commande_produits` ADD COLUMN `poids` VARCHAR(100) NULL DEFAULT NULL AFTER `couleur`;
ALTER TABLE `commande_produits` ADD COLUMN `taille` VARCHAR(100) NULL DEFAULT NULL AFTER `poids`;
ALTER TABLE `commande_produits` ADD COLUMN `variante_id` INT(11) NULL DEFAULT NULL AFTER `taille`;
ALTER TABLE `commande_produits` ADD COLUMN `variante_nom` VARCHAR(255) NULL DEFAULT NULL AFTER `variante_id`;
ALTER TABLE `commande_produits` ADD COLUMN `surcout_poids` DECIMAL(10,2) NULL DEFAULT 0 AFTER `variante_nom`;
ALTER TABLE `commande_produits` ADD COLUMN `surcout_taille` DECIMAL(10,2) NULL DEFAULT 0 AFTER `surcout_poids`;

-- PANIER: couleur, poids, taille, variante_*, surcout_*, prix_unitaire
ALTER TABLE `panier` ADD COLUMN `couleur` VARCHAR(255) NULL DEFAULT NULL AFTER `quantite`;
ALTER TABLE `panier` ADD COLUMN `poids` VARCHAR(100) NULL DEFAULT NULL AFTER `couleur`;
ALTER TABLE `panier` ADD COLUMN `taille` VARCHAR(100) NULL DEFAULT NULL AFTER `poids`;
ALTER TABLE `panier` ADD COLUMN `variante_id` INT(11) NULL DEFAULT NULL AFTER `taille`;
ALTER TABLE `panier` ADD COLUMN `variante_nom` VARCHAR(255) NULL DEFAULT NULL AFTER `variante_id`;
ALTER TABLE `panier` ADD COLUMN `variante_image` VARCHAR(255) NULL DEFAULT NULL AFTER `variante_nom`;
ALTER TABLE `panier` ADD COLUMN `surcout_poids` DECIMAL(10,2) NULL DEFAULT 0 AFTER `variante_image`;
ALTER TABLE `panier` ADD COLUMN `surcout_taille` DECIMAL(10,2) NULL DEFAULT 0 AFTER `surcout_poids`;
ALTER TABLE `panier` ADD COLUMN `prix_unitaire` DECIMAL(10,2) NULL DEFAULT NULL AFTER `surcout_taille`;

-- PRODUITS: couleurs, taille
ALTER TABLE `produits` ADD COLUMN `couleurs` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Couleurs disponibles' AFTER `unite`;
ALTER TABLE `produits` ADD COLUMN `taille` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Tailles disponibles' AFTER `couleurs`;

-- FACTURES: token
ALTER TABLE `factures` ADD COLUMN `token` VARCHAR(64) NULL DEFAULT NULL AFTER `date_creation`;

-- COMMANDES: user_id nullable (pour commandes manuelles sans client connecté)
ALTER TABLE `commandes` MODIFY COLUMN `user_id` INT(11) NULL DEFAULT NULL;
