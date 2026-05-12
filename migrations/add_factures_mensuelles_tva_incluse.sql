-- Option « TVA en sus » sur facture mensuelle B2B (sinon montant total = TTC avec TVA décomposée)
ALTER TABLE `factures_mensuelles`
  ADD COLUMN `tva_incluse` TINYINT(1) NOT NULL DEFAULT 0
  COMMENT '1 = TVA en sus sur total HT BL (TTC = HT + TVA); 0 = total facturé TTC avec TVA incluse'
  AFTER `total_ht`;
