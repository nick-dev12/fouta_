-- Rendre user_id nullable pour permettre les commandes manuelles (admin sans client connecté)
-- Exécuter dans phpMyAdmin

ALTER TABLE `commandes` MODIFY COLUMN `user_id` INT(11) NULL DEFAULT NULL;
