-- Ajouter la colonne accepte_conditions à la table users
ALTER TABLE `users` 
ADD COLUMN `accepte_conditions` TINYINT(1) NOT NULL DEFAULT 0 
AFTER `statut`;

-- Commentaire pour clarifier la colonne
ALTER TABLE `users` 
MODIFY COLUMN `accepte_conditions` TINYINT(1) NOT NULL DEFAULT 0 
COMMENT 'Acceptation des conditions d''utilisation (0 = non accepté, 1 = accepté)';

