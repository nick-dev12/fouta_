-- IRPP mensuel sur la fiche employé ; forfait HS (sursalaire) dans les paramètres bulletin
-- Exécuter : php migrations/run_add_employe_irpp_bp_forfait_trimf.php

ALTER TABLE `employes`
  ADD COLUMN `montant_irpp_mensuel` DECIMAL(14,2) NULL DEFAULT NULL COMMENT 'IRPP mensuel fixe (FCFA), fiche employé' AFTER `salaire_base`;

ALTER TABLE `bulletin_paie_parametres`
  ADD COLUMN `forfait_heures_sup_mensuel` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Forfait HS sursalaire (FCFA / mois)' AFTER `conges_annuels_global`;
