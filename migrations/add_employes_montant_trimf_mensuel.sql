-- Montant TRIMF mensuel fixe par employé (FCFA), saisi sur la fiche — plus en % dans les paramètres bulletin.
ALTER TABLE `employes`
  ADD COLUMN `montant_trimf_mensuel` DECIMAL(14,2) NULL DEFAULT NULL COMMENT 'TRIMF mensuel FCFA (fiche employé)' AFTER `montant_irpp_mensuel`;
