-- Rôles : commercial général (Devis & BL + périmètre commercial), informaticien (accès complet type admin).
-- Exécuter une fois sur la base (phpMyAdmin ou CLI).

ALTER TABLE `admin`
  MODIFY COLUMN `role` ENUM(
    'admin',
    'gestion_stock',
    'commercial',
    'commercial_general',
    'informaticien',
    'comptabilite',
    'rh',
    'caissier'
  ) NOT NULL DEFAULT 'admin';
