-- Migration : ajout de la colonne role à la table admin
-- Rôles : 'admin' (accès complet) | 'utilisateur' (tout sauf gestion des comptes clients)
-- Les comptes existants reçoivent le rôle 'admin' par défaut
--
-- À exécuter une seule fois : phpMyAdmin ou mysql -u user -p database < admin/migrate_admin_role.sql
-- En cas d'erreur "Duplicate column", la colonne existe déjà.

ALTER TABLE `admin` 
ADD COLUMN `role` ENUM('admin', 'utilisateur') NOT NULL DEFAULT 'admin' 
AFTER `statut`;
