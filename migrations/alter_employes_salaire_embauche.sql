-- Colonnes optionnelles fiche employé (bulletin / RH)
-- Exécution : php migrations/run_alter_employes_salaire_embauche.php

ALTER TABLE `employes` ADD COLUMN `date_embauche` DATE NULL DEFAULT NULL;
ALTER TABLE `employes` ADD COLUMN `salaire_base` DECIMAL(14,2) NULL DEFAULT NULL COMMENT 'Salaire brut / base habituel FCFA';
