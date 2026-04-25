-- =============================================================================
-- VIDAGE COMPLET DE LA BASE (MySQL / MariaDB)
-- =============================================================================
-- ATTENTION : Ce script supprime TOUTES les données des tables listées ci-dessous
-- (clients, commandes, produits, caisse, facturation, admins, etc.).
-- La structure des tables est conservée ; les AUTO_INCREMENT sont remis à zéro (TRUNCATE).
--
-- Avant d’exécuter :
--   1. Faire une sauvegarde (mysqldump ou export phpMyAdmin).
--   2. Vérifier que vous êtes sur la bonne base (USE nom_de_votre_base;).
--   3. Recréer manuellement un compte administrateur après coup (inscription SQL ou page admin).
--
-- Exécution : phpMyAdmin (onglet SQL) ou :
--   mysql -u user -p nom_base < migrations/vider_base_donnees.sql
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE `admin_password_reset`;
TRUNCATE TABLE `admin`;
TRUNCATE TABLE `bl_lignes`;
TRUNCATE TABLE `bons_livraison`;
TRUNCATE TABLE `caisse_vente_lignes`;
TRUNCATE TABLE `caisse_ventes`;
TRUNCATE TABLE `categories_depenses`;
TRUNCATE TABLE `categories`;
TRUNCATE TABLE `clients_b2b`;
TRUNCATE TABLE `commande_produits`;
TRUNCATE TABLE `commandes`;
TRUNCATE TABLE `commandes_personnalisees`;
TRUNCATE TABLE `contacts`;
TRUNCATE TABLE `depenses`;
TRUNCATE TABLE `devis_produits`;
TRUNCATE TABLE `devis`;
TRUNCATE TABLE `employes`;
TRUNCATE TABLE `facture_mensuelle_bl`;
TRUNCATE TABLE `factures_devis`;
TRUNCATE TABLE `factures_mensuelles`;
TRUNCATE TABLE `factures_personnalisees`;
TRUNCATE TABLE `factures`;
TRUNCATE TABLE `favoris`;
TRUNCATE TABLE `fcm_tokens`;
TRUNCATE TABLE `logos`;
TRUNCATE TABLE `panier`;
TRUNCATE TABLE `produits_variantes`;
TRUNCATE TABLE `produits_visites`;
TRUNCATE TABLE `produits`;
TRUNCATE TABLE `section4_config`;
TRUNCATE TABLE `slider`;
TRUNCATE TABLE `stock_articles`;
TRUNCATE TABLE `stock_mouvements`;
TRUNCATE TABLE `trending_config`;
TRUNCATE TABLE `user_password_reset`;
TRUNCATE TABLE `users`;
TRUNCATE TABLE `videos`;
TRUNCATE TABLE `zones_livraison`;

SET FOREIGN_KEY_CHECKS = 1;

-- Fin du script
