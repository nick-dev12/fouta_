-- Copie fonctionnelle du matricule (table employes_matricules source de vérité).
ALTER TABLE `employes`
  ADD COLUMN `matricule` VARCHAR(12) NULL DEFAULT NULL
    COMMENT 'Dénormalisé depuis employes_matricules (FPLxxxxxx)'
  AFTER `photo_chemin`;

UPDATE `employes` `e`
INNER JOIN `employes_matricules` `m` ON `m`.`employe_id` = `e`.`id`
SET `e`.`matricule` = `m`.`matricule`
WHERE (`e`.`matricule` IS NULL OR `e`.`matricule` <> `m`.`matricule`);

ALTER TABLE `employes`
  ADD UNIQUE KEY `uq_employes_matricule` (`matricule`);
