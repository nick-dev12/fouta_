-- Unifie les bons de livraison : l’ancien statut « paye » est fusionné dans « valide » (comptabilité).
-- À exécuter une fois sur la base (phpMyAdmin ou mysql CLI).

UPDATE `bons_livraison` SET `statut` = 'valide' WHERE `statut` = 'paye';

ALTER TABLE `bons_livraison`
  MODIFY COLUMN `statut` ENUM('brouillon','valide') NOT NULL DEFAULT 'brouillon';
