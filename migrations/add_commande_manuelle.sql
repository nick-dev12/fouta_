-- Migration: Support commandes manuelles (user_id NULL + infos client)
-- Exécuter via: php migrations/run_add_commande_manuelle.php

-- Ajouter colonnes client pour commandes manuelles
ALTER TABLE `commandes` ADD COLUMN IF NOT EXISTS `client_nom` VARCHAR(255) NULL DEFAULT NULL AFTER `user_id`;
ALTER TABLE `commandes` ADD COLUMN IF NOT EXISTS `client_prenom` VARCHAR(255) NULL DEFAULT NULL AFTER `client_nom`;
ALTER TABLE `commandes` ADD COLUMN IF NOT EXISTS `client_email` VARCHAR(255) NULL DEFAULT NULL AFTER `client_prenom`;
ALTER TABLE `commandes` ADD COLUMN IF NOT EXISTS `client_telephone` VARCHAR(50) NULL DEFAULT NULL AFTER `client_email`;

-- Rendre user_id nullable (nécessite de supprimer la FK d'abord)
-- ALTER TABLE commandes DROP FOREIGN KEY fk_commandes_user;
-- ALTER TABLE commandes MODIFY user_id INT(11) NULL DEFAULT NULL;
