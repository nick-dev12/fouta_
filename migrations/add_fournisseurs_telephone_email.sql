ALTER TABLE `fournisseurs`
  ADD COLUMN `telephone` VARCHAR(40) NULL DEFAULT NULL COMMENT 'Téléphone fournisseur' AFTER `nom`,
  ADD COLUMN `email` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Adresse e-mail fournisseur' AFTER `telephone`;
