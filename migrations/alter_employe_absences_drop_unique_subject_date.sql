-- Absences pour compte admin ou fiche employé ; pas d’unique partielle (doublons de date évités en PHP).
ALTER TABLE `employe_absences` DROP INDEX `ux_subject_admin_date`;
