-- Options étiquette / QR sur les niveaux de hiérarchie libre
-- est_etiquette_qr : ce niveau porte les étiquettes + QR
-- etiquette_lie_type : 'etage' (Niveau + code abrégé) ou 'niveau' (autre niveau hiérarchique)
-- etiquette_lie_niveau_id : id du niveau lié si type = niveau

ALTER TABLE `entrepot_hierarchie_niveau`
  ADD COLUMN `est_etiquette_qr` TINYINT(1) NOT NULL DEFAULT 0 AFTER `actif`,
  ADD COLUMN `etiquette_lie_type` VARCHAR(20) NOT NULL DEFAULT 'etage' AFTER `est_etiquette_qr`,
  ADD COLUMN `etiquette_lie_niveau_id` INT UNSIGNED NULL DEFAULT NULL AFTER `etiquette_lie_type`;
