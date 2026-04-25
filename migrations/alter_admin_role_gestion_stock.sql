-- Remplace le rôle « utilisateur » par « gestion_stock » (liste blanche applicative alignée).
-- Exécuter une fois sur la base de données (phpMyAdmin ou mysql).

ALTER TABLE `admin`
  MODIFY COLUMN `role` ENUM(
    'admin',
    'utilisateur',
    'gestion_stock',
    'commercial',
    'comptabilite',
    'rh'
  ) NOT NULL DEFAULT 'admin';

UPDATE `admin` SET `role` = 'gestion_stock' WHERE `role` = 'utilisateur';

ALTER TABLE `admin`
  MODIFY COLUMN `role` ENUM(
    'admin',
    'gestion_stock',
    'commercial',
    'comptabilite',
    'rh'
  ) NOT NULL DEFAULT 'admin';
