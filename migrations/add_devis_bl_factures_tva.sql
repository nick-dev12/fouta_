-- TVA optionnelle sur devis, BL et facture devis (méthode caisse : HT + TVA en sus)
ALTER TABLE `devis`
  ADD COLUMN `tva_incluse` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = total à payer inclut la TVA (TTC)',
  ADD COLUMN `taux_tva_pourcent` DECIMAL(5,2) NOT NULL DEFAULT 18.00;

ALTER TABLE `bons_livraison`
  ADD COLUMN `tva_incluse` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = affichage / règlement TTC sur base du total HT',
  ADD COLUMN `taux_tva_pourcent` DECIMAL(5,2) NOT NULL DEFAULT 18.00;

ALTER TABLE `factures_devis`
  ADD COLUMN `tva_incluse` TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN `montant_ht` DECIMAL(10,2) NULL DEFAULT NULL,
  ADD COLUMN `montant_tva` DECIMAL(10,2) NULL DEFAULT NULL,
  ADD COLUMN `taux_tva_pourcent` DECIMAL(5,2) NULL DEFAULT NULL;
