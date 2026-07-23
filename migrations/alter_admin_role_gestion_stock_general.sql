-- Rôle « gestion_stock_general » : périmètre étendu gestion des stocks (catégories, paramètres stock, entrepôt…)
-- Exécuter une fois sur la base (phpMyAdmin ou CLI).

ALTER TABLE `admin`
  MODIFY COLUMN `role` ENUM(
    'admin',
    'gestion_stock',
    'gestion_stock_general',
    'commercial',
    'commercial_general',
    'informaticien',
    'developpeur',
    'comptabilite',
    'rh',
    'caissier'
  ) NOT NULL DEFAULT 'admin';
