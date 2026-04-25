-- Tickets caisse : statut en attente / payé, caissier, date d'encaissement (compta).
-- Exécuter une fois après create_caisse_tables.sql.

ALTER TABLE `caisse_ventes`
  ADD COLUMN `caissier_id` INT(11) NULL DEFAULT NULL AFTER `admin_id`,
  ADD COLUMN `statut` ENUM('en_attente', 'paye') NOT NULL DEFAULT 'paye' AFTER `notes`,
  ADD COLUMN `date_encaissement` DATETIME NULL DEFAULT NULL AFTER `date_vente`,
  ADD KEY `idx_caissier` (`caissier_id`),
  ADD KEY `idx_statut` (`statut`);

UPDATE `caisse_ventes` SET `date_encaissement` = `date_vente` WHERE `date_encaissement` IS NULL;

ALTER TABLE `caisse_ventes`
  ADD CONSTRAINT `fk_caisse_ventes_caissier` FOREIGN KEY (`caissier_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
