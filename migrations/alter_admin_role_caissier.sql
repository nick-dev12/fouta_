-- Ajoute le rôle « caissier » (exécuter une fois sur la base).

ALTER TABLE `admin`
  MODIFY COLUMN `role` ENUM(
    'admin',
    'gestion_stock',
    'commercial',
    'comptabilite',
    'rh',
    'caissier'
  ) NOT NULL DEFAULT 'admin';
