-- Ajoute le rôle « developpeur » (accès complet, même périmètre que l’informaticien).
-- Exécuter une fois sur la base (phpMyAdmin, CLI ou migrations/run_alter_admin_role_developpeur.php).

ALTER TABLE `admin`
  MODIFY COLUMN `role` ENUM(
    'admin',
    'gestion_stock',
    'commercial',
    'commercial_general',
    'informaticien',
    'developpeur',
    'comptabilite',
    'rh',
    'caissier'
  ) NOT NULL DEFAULT 'admin';
