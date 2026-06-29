-- Paiement individuel d'une facture BL + prérequis numero_reference_fpl si absent
-- Préférer : php migrations/run_add_bl_facture_paiement.php (gère l'ordre automatiquement)

-- 1) Numéro facture BL (si pas encore en base)
-- ALTER TABLE `bons_livraison`
--   ADD COLUMN `numero_reference_fpl` VARCHAR(20) NULL DEFAULT NULL AFTER `numero_bl`,
--   ADD UNIQUE KEY `idx_bl_numero_reference_fpl` (`numero_reference_fpl`);

-- 2) Paiement facture BL
-- ALTER TABLE `bons_livraison`
--   ADD COLUMN `facture_bl_payee` TINYINT(1) NOT NULL DEFAULT 0 AFTER `numero_reference_fpl`,
--   ADD COLUMN `date_paiement_bl` DATETIME NULL DEFAULT NULL AFTER `facture_bl_payee`,
--   ADD KEY `idx_bl_facture_payee` (`facture_bl_payee`);
