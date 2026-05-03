-- Badge QR RH (PNG sous upload/) + cache du contenu encodé
ALTER TABLE `employes`
  ADD COLUMN `qr_chemin` VARCHAR(500) NULL DEFAULT NULL COMMENT 'Chemin relatif PNG sous upload/ (ex employes_qr/employe_1.png)',
  ADD COLUMN `qr_payload` VARCHAR(2048) NULL DEFAULT NULL COMMENT 'Donnees encodees dans le QR (audit)';
