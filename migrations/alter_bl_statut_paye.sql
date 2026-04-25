-- Ajoute le statut "paye" aux bons de livraison (comptabilité)
-- À exécuter une fois sur la base concernée.

ALTER TABLE `bons_livraison`
  MODIFY `statut` ENUM('brouillon','valide','paye') NOT NULL DEFAULT 'brouillon';
