-- Suppression en cascade : si un bon de livraison est supprimé, les lignes de
-- liaison facture_mensuelle_bl sont supprimées automatiquement (sinon erreur #1451).
-- À exécuter une fois sur les bases déjà créées avec migration_admin_b2b_structure.sql

ALTER TABLE `facture_mensuelle_bl` DROP FOREIGN KEY `fk_fmb_bl`;

ALTER TABLE `facture_mensuelle_bl`
  ADD CONSTRAINT `fk_fmb_bl` FOREIGN KEY (`bl_id`) REFERENCES `bons_livraison` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
