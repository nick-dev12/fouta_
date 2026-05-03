-- Absences : rattachement au compte admin (personne absente), hors rôle « admin »
-- À exécuter si employe_absences existe déjà avec l’ancien schéma (employe_id NOT NULL + unique employé/date).

ALTER TABLE `employe_absences` DROP FOREIGN KEY `fk_employe_absences_employe`;

ALTER TABLE `employe_absences` DROP INDEX `ux_employe_absence_date`;

ALTER TABLE `employe_absences`
  MODIFY `employe_id` INT(11) NULL DEFAULT NULL COMMENT 'Legacy — fiche RH optionnelle',
  ADD COLUMN `subject_admin_id` INT(11) NULL DEFAULT NULL COMMENT 'Compte admin concerné par l absence (hors gestionnaires admin)' AFTER `employe_id`,
  ADD KEY `idx_subject_admin` (`subject_admin_id`),
  ADD UNIQUE KEY `ux_subject_admin_date` (`subject_admin_id`, `date_absence`);

UPDATE `employe_absences` a
INNER JOIN `employes` e ON e.id = a.employe_id
SET a.`subject_admin_id` = e.`admin_id`
WHERE e.`admin_id` IS NOT NULL;

ALTER TABLE `employe_absences`
  ADD CONSTRAINT `fk_employe_absences_employe` FOREIGN KEY (`employe_id`) REFERENCES `employes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_employe_absences_subject_admin` FOREIGN KEY (`subject_admin_id`) REFERENCES `admin` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
