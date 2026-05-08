-- TVA optionnelle sur les tickets caisse (prix lignes en HT, TVA ajoutée si demandé)
-- Exécuter après create_caisse_tables.sql et les autres alter caisse.

ALTER TABLE `caisse_ventes`
  ADD COLUMN `montant_ht` DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'Net HT après remise ticket' AFTER `montant_total`,
  ADD COLUMN `montant_tva` DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'TVA (0 si hors TVA)' AFTER `montant_ht`,
  ADD COLUMN `tva_incluse` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = TVA ajoutée sur le net HT' AFTER `montant_tva`;
