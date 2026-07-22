-- Barres rattachées par rayon (numero unique par rayon, plus par étage)

ALTER TABLE `entrepot_barre`
  DROP INDEX `uniq_entrepot_barre_etage_num`;

ALTER TABLE `entrepot_barre`
  ADD UNIQUE KEY `uniq_entrepot_barre_rayon_num` (`rayon_id`, `numero`);
