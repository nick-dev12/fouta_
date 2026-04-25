-- Migration: ajout de la colonne prix sur commandes_personnalisees
-- Exécuter via php migrations/run_add_prix_commandes_personnalisees.php
-- Ou manuellement: ALTER TABLE commandes_personnalisees ADD COLUMN prix DECIMAL(10,2) NULL DEFAULT NULL AFTER date_souhaitee;

ALTER TABLE commandes_personnalisees
ADD COLUMN prix DECIMAL(10,2) NULL DEFAULT NULL AFTER date_souhaitee;
