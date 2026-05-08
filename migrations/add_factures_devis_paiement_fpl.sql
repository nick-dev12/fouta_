-- Paiement facture devis + référence comptable FPLnnnnn
ALTER TABLE `factures_devis`
  ADD COLUMN `payee` TINYINT(1) NOT NULL DEFAULT 0 AFTER `montant_total`,
  ADD COLUMN `date_paiement` DATETIME NULL DEFAULT NULL AFTER `payee`,
  ADD COLUMN `numero_reference_fpl` VARCHAR(20) NULL DEFAULT NULL AFTER `date_paiement`,
  ADD KEY `idx_factures_devis_payee` (`payee`);

-- Unicité MySQL : plusieurs NULL autorisés avant assignation
ALTER TABLE `factures_devis`
  ADD UNIQUE KEY `idx_factures_devis_numero_fpl` (`numero_reference_fpl`);
