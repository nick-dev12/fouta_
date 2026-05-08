-- Canaux Orange Money / Wave — exécuter via run_alter_caisse_ventes_canaux_paiement.php

ALTER TABLE `caisse_ventes`
  ADD COLUMN `montant_orange_money` DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'Part Orange Money (dont mixte)' AFTER `montant_carte`,
  ADD COLUMN `montant_wave` DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'Part Wave (dont mixte)' AFTER `montant_orange_money`;

ALTER TABLE `caisse_ventes`
  MODIFY COLUMN `mode_paiement` ENUM(
    'especes','carte','mobile_money','orange_money','wave','cheque','mixte','autre'
  ) NOT NULL DEFAULT 'especes';

UPDATE `caisse_ventes`
SET `mode_paiement` = 'orange_money'
WHERE `mode_paiement` = 'mobile_money';

UPDATE `caisse_ventes`
SET `montant_orange_money` = COALESCE(`montant_mobile_money`, `montant_total`)
WHERE `mode_paiement` = 'orange_money'
  AND (`montant_orange_money` IS NULL OR `montant_orange_money` = 0);

UPDATE `caisse_ventes`
SET
  `montant_orange_money` = ROUND(COALESCE(`montant_orange_money`, 0) + COALESCE(`montant_mobile_money`, 0), 2),
  `montant_mobile_money` = NULL
WHERE `mode_paiement` = 'mixte'
  AND `montant_mobile_money` IS NOT NULL
  AND (`montant_mobile_money` <> 0);

UPDATE `caisse_ventes`
SET `montant_mobile_money` = NULL
WHERE `mode_paiement` IN ('orange_money', 'wave', 'especes', 'carte', 'cheque', 'autre');

ALTER TABLE `caisse_ventes`
  MODIFY COLUMN `mode_paiement` ENUM(
    'especes','carte','orange_money','wave','cheque','mixte','autre'
  ) NOT NULL DEFAULT 'especes';
