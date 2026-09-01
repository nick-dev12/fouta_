-- Séparation des sections prix / stock / catégorie (formulaire produit)

ALTER TABLE `produit_formulaire_champ`
  MODIFY COLUMN `section` ENUM('info','prix','stock','categorie','ref','variantes','options','media') NOT NULL DEFAULT 'info';

UPDATE `produit_formulaire_champ` SET `section` = 'stock' WHERE `slug` IN ('stock', 'statut');
UPDATE `produit_formulaire_champ` SET `section` = 'categorie' WHERE `slug` IN ('categorie_id', 'sous_categorie_id');
