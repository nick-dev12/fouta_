-- Lien unique champ custom ↔ barres

ALTER TABLE `entrepot_structure_champ`
  ADD COLUMN `lie_barre` TINYINT(1) NOT NULL DEFAULT 0 AFTER `est_systeme`;

ALTER TABLE `entrepot_barre`
  ADD COLUMN `champ_element_id` INT UNSIGNED NULL DEFAULT NULL AFTER `zone_id`;
