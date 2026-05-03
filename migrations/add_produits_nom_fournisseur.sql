-- Nom du fournisseur (informations catalogue / stock admin)
-- Exécution manuelle ou via migrations/run_add_produits_nom_fournisseur.php

ALTER TABLE `produits`
ADD COLUMN `nom_fournisseur` VARCHAR(255) NULL DEFAULT NULL
COMMENT 'Nom ou raison sociale du fournisseur (optionnel)'
AFTER `description`;
