-- Statut familial, type de contrat, PDF du contrat (optionnel)
ALTER TABLE `employes`
  ADD COLUMN `statut_familial` VARCHAR(40) NULL DEFAULT NULL COMMENT 'RH — célibataire, marié(e), etc.'
    AFTER `notes`;
ALTER TABLE `employes`
  ADD COLUMN `type_contrat` VARCHAR(40) NULL DEFAULT NULL COMMENT 'RH — CDI, CDD, etc.'
    AFTER `statut_familial`;
ALTER TABLE `employes`
  ADD COLUMN `contrat_pdf_chemin` VARCHAR(500) NULL DEFAULT NULL COMMENT 'Chemin relatif PDF sous upload/ (ex employes_contrats/...)'
    AFTER `type_contrat`;
