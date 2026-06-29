-- Paiement individuel d'une facture BL (hors facture mensuelle groupée)
ALTER TABLE `bons_livraison`
  ADD COLUMN `facture_bl_payee` TINYINT(1) NOT NULL DEFAULT 0 AFTER `numero_reference_fpl`,
  ADD COLUMN `date_paiement_bl` DATETIME NULL DEFAULT NULL AFTER `facture_bl_payee`,
  ADD KEY `idx_bl_facture_payee` (`facture_bl_payee`);
