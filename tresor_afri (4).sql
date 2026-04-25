-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3306
-- Généré le : mar. 31 mars 2026 à 21:21
-- Version du serveur : 9.1.0
-- Version de PHP : 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- Permet les DROP / CREATE dans n'importe quel ordre (évite l'erreur #1451 à l'import)
SET FOREIGN_KEY_CHECKS = 0;

--
-- Base de données : `tresor_afri`
--

-- --------------------------------------------------------

--
-- Structure de la table `admin`
--

DROP TABLE IF EXISTS `admin`;
CREATE TABLE IF NOT EXISTS `admin` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prenom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_creation` datetime NOT NULL,
  `derniere_connexion` datetime DEFAULT NULL,
  `statut` enum('actif','inactif') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'actif',
  `role` enum('admin','gestion_stock','commercial','comptabilite','rh','caissier') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'admin',
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `admin_password_reset`
--

DROP TABLE IF EXISTS `admin_password_reset`;
CREATE TABLE IF NOT EXISTS `admin_password_reset` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT '0',
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_token` (`token`),
  KEY `idx_email` (`email`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `bl_lignes`
--

DROP TABLE IF EXISTS `bl_lignes`;
CREATE TABLE IF NOT EXISTS `bl_lignes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `bl_id` int NOT NULL,
  `produit_id` int DEFAULT NULL,
  `designation` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantite` decimal(12,3) NOT NULL DEFAULT '1.000',
  `prix_unitaire_ht` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_ligne_ht` decimal(12,2) NOT NULL DEFAULT '0.00',
  `ordre` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_bl` (`bl_id`),
  KEY `idx_produit` (`produit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `bons_livraison`
--

DROP TABLE IF EXISTS `bons_livraison`;
CREATE TABLE IF NOT EXISTS `bons_livraison` (
  `id` int NOT NULL AUTO_INCREMENT,
  `numero_bl` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `client_b2b_id` int NOT NULL,
  `devis_id` int DEFAULT NULL COMMENT 'Si issu d une conversion devis',
  `admin_createur_id` int DEFAULT NULL COMMENT 'Commercial / admin ayant créé le BL',
  `statut` enum('brouillon','valide') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'brouillon',
  `date_bl` date NOT NULL,
  `total_ht` decimal(12,2) NOT NULL DEFAULT '0.00',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_numero_bl` (`numero_bl`),
  KEY `idx_client` (`client_b2b_id`),
  KEY `idx_statut_date` (`statut`,`date_bl`),
  KEY `idx_devis` (`devis_id`),
  KEY `fk_bl_admin` (`admin_createur_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `caisse_ventes`
--

DROP TABLE IF EXISTS `caisse_ventes`;
CREATE TABLE IF NOT EXISTS `caisse_ventes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `admin_id` int NOT NULL,
  `caissier_id` int DEFAULT NULL,
  `numero_ticket` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Recherche caisse (ticket non payé uniquement)',
  `montant_total` decimal(12,2) NOT NULL,
  `remise_globale_pct` decimal(5,2) NOT NULL DEFAULT '0.00',
  `mode_paiement` enum('especes','carte','mobile_money','cheque','mixte','autre') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'especes',
  `montant_especes` decimal(12,2) DEFAULT NULL,
  `montant_carte` decimal(12,2) DEFAULT NULL,
  `montant_mobile_money` decimal(12,2) DEFAULT NULL,
  `montant_recu` decimal(12,2) DEFAULT NULL,
  `monnaie_rendue` decimal(12,2) DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `statut` enum('en_attente','paye') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'paye',
  `date_vente` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_encaissement` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_numero_ticket` (`numero_ticket`),
  UNIQUE KEY `uk_caisse_ventes_reference` (`reference`),
  KEY `idx_admin` (`admin_id`),
  KEY `idx_date` (`date_vente`),
  KEY `idx_caissier` (`caissier_id`),
  KEY `idx_statut` (`statut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `caisse_vente_lignes`
--

DROP TABLE IF EXISTS `caisse_vente_lignes`;
CREATE TABLE IF NOT EXISTS `caisse_vente_lignes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `vente_id` int NOT NULL,
  `produit_id` int NOT NULL,
  `designation` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantite` int NOT NULL,
  `prix_unitaire` decimal(12,2) NOT NULL,
  `remise_ligne_pct` decimal(5,2) NOT NULL DEFAULT '0.00',
  `total_ligne` decimal(12,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_vente` (`vente_id`),
  KEY `idx_produit` (`produit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `categories`
--

DROP TABLE IF EXISTS `categories`;
CREATE TABLE IF NOT EXISTS `categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nom` (`nom`),
  UNIQUE KEY `idx_nom` (`nom`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `categories_depenses`
--

DROP TABLE IF EXISTS `categories_depenses`;
CREATE TABLE IF NOT EXISTS `categories_depenses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type_tva` enum('sans_tva','avec_tva','mixte') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'mixte',
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_nom` (`nom`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `clients_b2b`
--

DROP TABLE IF EXISTS `clients_b2b`;
CREATE TABLE IF NOT EXISTS `clients_b2b` (
  `id` int NOT NULL AUTO_INCREMENT,
  `raison_sociale` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom_contact` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `prenom_contact` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telephone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `adresse` text COLLATE utf8mb4_unicode_ci,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `admin_createur_id` int DEFAULT NULL,
  `statut` enum('actif','inactif') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'actif',
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_raison` (`raison_sociale`(100)),
  KEY `idx_statut` (`statut`),
  KEY `idx_cb2b_admin_createur` (`admin_createur_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `commandes`
--

DROP TABLE IF EXISTS `commandes`;
CREATE TABLE IF NOT EXISTS `commandes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `admin_createur_id` int DEFAULT NULL COMMENT 'Saisie manuelle admin',
  `admin_dernier_traitement_id` int DEFAULT NULL COMMENT 'Dernier changement de statut',
  `client_nom` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `client_prenom` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `client_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `client_telephone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `numero_commande` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `montant_total` decimal(10,2) NOT NULL,
  `adresse_livraison` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `zone_livraison_id` int DEFAULT NULL,
  `frais_livraison` decimal(10,2) NOT NULL DEFAULT '0.00',
  `telephone_livraison` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `statut` enum('en_attente','confirmee','prise_en_charge','en_preparation','livraison_en_cours','expediee','livree','paye','annulee') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en_attente',
  `date_commande` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_livraison` datetime DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `numero_commande` (`numero_commande`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_statut` (`statut`),
  KEY `idx_date_commande` (`date_commande`),
  KEY `idx_cmd_admin_createur` (`admin_createur_id`),
  KEY `idx_cmd_admin_traitement` (`admin_dernier_traitement_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `commandes_personnalisees`
--

DROP TABLE IF EXISTS `commandes_personnalisees`;
CREATE TABLE IF NOT EXISTS `commandes_personnalisees` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prenom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telephone` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `image_reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type_produit` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantite` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_souhaitee` date DEFAULT NULL,
  `zone_livraison_id` int DEFAULT NULL,
  `prix` decimal(10,2) DEFAULT NULL,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `statut` enum('en_attente','confirmee','en_preparation','devis_envoye','acceptee','refusee','terminee','annulee') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en_attente',
  `notes_admin` text COLLATE utf8mb4_unicode_ci,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_statut` (`statut`),
  KEY `idx_date_creation` (`date_creation`),
  KEY `idx_zone_livraison` (`zone_livraison_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `commande_produits`
--

DROP TABLE IF EXISTS `commande_produits`;
CREATE TABLE IF NOT EXISTS `commande_produits` (
  `id` int NOT NULL AUTO_INCREMENT,
  `commande_id` int NOT NULL,
  `produit_id` int NOT NULL,
  `nom_produit` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantite` int NOT NULL,
  `prix_unitaire` decimal(10,2) NOT NULL,
  `prix_total` decimal(10,2) NOT NULL,
  `couleur` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `poids` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `taille` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `surcout_poids` decimal(10,2) DEFAULT '0.00',
  `surcout_taille` decimal(10,2) DEFAULT '0.00',
  `variante_nom` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `variante_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_commande_id` (`commande_id`),
  KEY `idx_produit_id` (`produit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `contacts`
--

DROP TABLE IF EXISTS `contacts`;
CREATE TABLE IF NOT EXISTS `contacts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prenom` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `telephone` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_telephone` (`telephone`),
  KEY `idx_nom` (`nom`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `depenses`
--

DROP TABLE IF EXISTS `depenses`;
CREATE TABLE IF NOT EXISTS `depenses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `categorie_id` int DEFAULT NULL,
  `type_depense` enum('sans_tva','avec_tva') COLLATE utf8mb4_unicode_ci NOT NULL,
  `libelle` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `montant_ht` decimal(12,2) NOT NULL DEFAULT '0.00',
  `taux_tva` decimal(5,2) DEFAULT NULL COMMENT 'Ex: 20.00 pour 20%',
  `montant_tva` decimal(12,2) DEFAULT NULL,
  `montant_ttc` decimal(12,2) DEFAULT NULL,
  `date_depense` date NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `admin_createur_id` int DEFAULT NULL,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_date` (`date_depense`),
  KEY `idx_type` (`type_depense`),
  KEY `idx_categorie` (`categorie_id`),
  KEY `fk_dep_admin` (`admin_createur_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `devis`
--

DROP TABLE IF EXISTS `devis`;
CREATE TABLE IF NOT EXISTS `devis` (
  `id` int NOT NULL AUTO_INCREMENT,
  `numero_devis` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `client_nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `client_prenom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `client_telephone` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `client_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `adresse_livraison` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `zone_livraison_id` int DEFAULT NULL,
  `frais_livraison` decimal(10,2) NOT NULL DEFAULT '0.00',
  `user_id` int DEFAULT NULL,
  `admin_createur_id` int DEFAULT NULL COMMENT 'Admin ayant créé le devis',
  `montant_total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `statut` enum('brouillon','envoye','accepte','refuse') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'brouillon',
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_numero_devis` (`numero_devis`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_zone_livraison_id` (`zone_livraison_id`),
  KEY `idx_statut` (`statut`),
  KEY `idx_date_creation` (`date_creation`),
  KEY `idx_devis_admin_createur` (`admin_createur_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `devis_produits`
--

DROP TABLE IF EXISTS `devis_produits`;
CREATE TABLE IF NOT EXISTS `devis_produits` (
  `id` int NOT NULL AUTO_INCREMENT,
  `devis_id` int NOT NULL,
  `produit_id` int NOT NULL,
  `nom_produit` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantite` int NOT NULL DEFAULT '1',
  `prix_unitaire` decimal(10,2) NOT NULL,
  `prix_total` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_devis_id` (`devis_id`),
  KEY `idx_produit_id` (`produit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `employes`
--

DROP TABLE IF EXISTS `employes`;
CREATE TABLE IF NOT EXISTS `employes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prenom` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telephone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `poste` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `service` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_embauche` date DEFAULT NULL,
  `statut` enum('actif','inactif','suspendu') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'actif',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `admin_id` int DEFAULT NULL COMMENT 'Compte d accès interne lié (optionnel)',
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_statut` (`statut`),
  KEY `idx_admin` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `factures`
--

DROP TABLE IF EXISTS `factures`;
CREATE TABLE IF NOT EXISTS `factures` (
  `id` int NOT NULL AUTO_INCREMENT,
  `commande_id` int NOT NULL,
  `numero_facture` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_facture` date NOT NULL,
  `montant_total` decimal(10,2) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_commande` (`commande_id`),
  UNIQUE KEY `token` (`token`),
  KEY `idx_numero` (`numero_facture`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `factures_devis`
--

DROP TABLE IF EXISTS `factures_devis`;
CREATE TABLE IF NOT EXISTS `factures_devis` (
  `id` int NOT NULL AUTO_INCREMENT,
  `devis_id` int NOT NULL,
  `numero_facture` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_facture` date NOT NULL,
  `montant_total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `token` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `admin_createur_id` int DEFAULT NULL COMMENT 'Admin ayant généré la facture',
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_numero_facture` (`numero_facture`),
  UNIQUE KEY `idx_devis_id` (`devis_id`),
  KEY `idx_token` (`token`),
  KEY `idx_factures_devis_admin` (`admin_createur_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `factures_mensuelles`
--

DROP TABLE IF EXISTS `factures_mensuelles`;
CREATE TABLE IF NOT EXISTS `factures_mensuelles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `numero_facture` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `client_b2b_id` int NOT NULL,
  `annee` smallint NOT NULL,
  `mois` tinyint NOT NULL COMMENT '1 à 12',
  `statut` enum('brouillon','validee','payee') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'brouillon',
  `total_ht` decimal(12,2) NOT NULL DEFAULT '0.00',
  `date_emission` date DEFAULT NULL,
  `date_paiement` date DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `admin_createur_id` int DEFAULT NULL,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_numero_facture` (`numero_facture`),
  UNIQUE KEY `uniq_client_mois` (`client_b2b_id`,`annee`,`mois`),
  KEY `idx_statut` (`statut`),
  KEY `fk_fm_admin` (`admin_createur_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `factures_personnalisees`
--

DROP TABLE IF EXISTS `factures_personnalisees`;
CREATE TABLE IF NOT EXISTS `factures_personnalisees` (
  `id` int NOT NULL AUTO_INCREMENT,
  `commande_personnalisee_id` int NOT NULL,
  `numero_facture` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_facture` date NOT NULL,
  `montant_total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `token` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_cp` (`commande_personnalisee_id`),
  UNIQUE KEY `token` (`token`),
  KEY `idx_numero` (`numero_facture`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `facture_mensuelle_bl`
--

DROP TABLE IF EXISTS `facture_mensuelle_bl`;
CREATE TABLE IF NOT EXISTS `facture_mensuelle_bl` (
  `id` int NOT NULL AUTO_INCREMENT,
  `facture_mensuelle_id` int NOT NULL,
  `bl_id` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_bl` (`bl_id`),
  KEY `idx_facture` (`facture_mensuelle_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `favoris`
--

DROP TABLE IF EXISTS `favoris`;
CREATE TABLE IF NOT EXISTS `favoris` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `produit_id` int NOT NULL,
  `date_ajout` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_user_produit` (`user_id`,`produit_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_produit_id` (`produit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `fcm_tokens`
--

DROP TABLE IF EXISTS `fcm_tokens`;
CREATE TABLE IF NOT EXISTS `fcm_tokens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `admin_id` int DEFAULT NULL,
  `token` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('user','admin') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user',
  `user_agent` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_token` (`token`(191)),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_admin_id` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `logos`
--

DROP TABLE IF EXISTS `logos`;
CREATE TABLE IF NOT EXISTS `logos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ordre` int NOT NULL DEFAULT '0',
  `statut` enum('actif','inactif') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'actif',
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_statut` (`statut`),
  KEY `idx_ordre` (`ordre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `panier`
--

DROP TABLE IF EXISTS `panier`;
CREATE TABLE IF NOT EXISTS `panier` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `produit_id` int NOT NULL,
  `quantite` int NOT NULL DEFAULT '1',
  `couleur` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `poids` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `taille` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `surcout_poids` decimal(10,2) DEFAULT '0.00',
  `surcout_taille` decimal(10,2) DEFAULT '0.00',
  `prix_unitaire` decimal(10,2) DEFAULT NULL,
  `date_ajout` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `variante_nom` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `variante_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `variante_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_produit_id` (`produit_id`),
  KEY `idx_date_ajout` (`date_ajout`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `produits`
--

DROP TABLE IF EXISTS `produits`;
CREATE TABLE IF NOT EXISTS `produits` (
  `id` int NOT NULL AUTO_INCREMENT,
  `identifiant_interne` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nom` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `prix` decimal(10,2) NOT NULL,
  `prix_promotion` decimal(10,2) DEFAULT NULL,
  `stock` int NOT NULL DEFAULT '0',
  `categorie_id` int NOT NULL,
  `stock_article_id` int DEFAULT NULL,
  `image_principale` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `images` text COLLATE utf8mb4_unicode_ci,
  `poids` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unite` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unité',
  `etage` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Étage entrepôt / magasin',
  `numero_rayon` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Numéro ou code rayon',
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `statut` enum('actif','inactif','rupture_stock') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'actif',
  `couleurs` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Couleurs disponibles (ex: Rouge, Bleu, Vert)',
  `taille` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Tailles disponibles (ex: S, M, L ou 21cm, 14.8cm)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_identifiant_interne` (`identifiant_interne`),
  KEY `idx_categorie` (`categorie_id`),
  KEY `idx_statut` (`statut`),
  KEY `idx_stock_article` (`stock_article_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `produits_variantes`
--

DROP TABLE IF EXISTS `produits_variantes`;
CREATE TABLE IF NOT EXISTS `produits_variantes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `produit_id` int NOT NULL,
  `nom` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prix` decimal(10,2) NOT NULL,
  `prix_promotion` decimal(10,2) DEFAULT NULL,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ordre` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_produit_id` (`produit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `produits_visites`
--

DROP TABLE IF EXISTS `produits_visites`;
CREATE TABLE IF NOT EXISTS `produits_visites` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `produit_id` int NOT NULL,
  `date_visite` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_user_produit` (`user_id`,`produit_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_produit_id` (`produit_id`),
  KEY `idx_date_visite` (`date_visite`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `section4_config`
--

DROP TABLE IF EXISTS `section4_config`;
CREATE TABLE IF NOT EXISTS `section4_config` (
  `id` int NOT NULL AUTO_INCREMENT,
  `titre` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `texte` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image_fond` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `statut` enum('actif','inactif') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'actif',
  `date_modification` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `slider`
--

DROP TABLE IF EXISTS `slider`;
CREATE TABLE IF NOT EXISTS `slider` (
  `id` int NOT NULL AUTO_INCREMENT,
  `titre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `paragraphe` text COLLATE utf8mb4_unicode_ci,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bouton_texte` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bouton_lien` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ordre` int NOT NULL DEFAULT '0',
  `statut` enum('actif','inactif') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'actif',
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_statut` (`statut`),
  KEY `idx_ordre` (`ordre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `stock_articles`
--

DROP TABLE IF EXISTS `stock_articles`;
CREATE TABLE IF NOT EXISTS `stock_articles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `image_principale` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantite` int NOT NULL DEFAULT '0',
  `categorie_id` int NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_categorie` (`categorie_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `stock_mouvements`
--

DROP TABLE IF EXISTS `stock_mouvements`;
CREATE TABLE IF NOT EXISTS `stock_mouvements` (
  `id` int NOT NULL AUTO_INCREMENT,
  `type` enum('entree','sortie','inventaire') COLLATE utf8mb4_unicode_ci NOT NULL,
  `stock_article_id` int DEFAULT NULL,
  `produit_id` int DEFAULT NULL,
  `quantite` int NOT NULL,
  `quantite_avant` int DEFAULT NULL,
  `quantite_apres` int DEFAULT NULL,
  `reference_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_id` int DEFAULT NULL,
  `reference_numero` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_mouvement` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `notes` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `idx_stock_article` (`stock_article_id`),
  KEY `idx_produit` (`produit_id`),
  KEY `idx_type` (`type`),
  KEY `idx_date` (`date_mouvement`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `trending_config`
--

DROP TABLE IF EXISTS `trending_config`;
CREATE TABLE IF NOT EXISTS `trending_config` (
  `id` int NOT NULL AUTO_INCREMENT,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'categories',
  `titre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Enhance Your Music Experience',
  `bouton_texte` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Buy Now!',
  `bouton_lien` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '#',
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'speaker.png',
  `date_modification` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prenom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telephone` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `statut` enum('actif','inactif') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'actif',
  `accepte_conditions` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Acceptation des conditions d''utilisation (0 = non accepté, 1 = accepté)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `user_password_reset`
--

DROP TABLE IF EXISTS `user_password_reset`;
CREATE TABLE IF NOT EXISTS `user_password_reset` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT '0',
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_token` (`token`),
  KEY `idx_email` (`email`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `videos`
--

DROP TABLE IF EXISTS `videos`;
CREATE TABLE IF NOT EXISTS `videos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `titre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `fichier_video` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nom du fichier vidéo uploadé',
  `image_preview` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Image de prévisualisation de la vidéo',
  `overlay_texte` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Texte overlay (Prix, Détails, Avis, etc.)',
  `ordre` int NOT NULL DEFAULT '0' COMMENT 'Ordre d''affichage dans le carrousel',
  `statut` enum('actif','inactif') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'actif',
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_statut` (`statut`),
  KEY `idx_ordre` (`ordre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `zones_livraison`
--

DROP TABLE IF EXISTS `zones_livraison`;
CREATE TABLE IF NOT EXISTS `zones_livraison` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ville` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quartier` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prix_livraison` decimal(10,2) NOT NULL DEFAULT '0.00',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `statut` enum('actif','inactif') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'actif',
  PRIMARY KEY (`id`),
  KEY `idx_ville` (`ville`),
  KEY `idx_statut` (`statut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `bl_lignes`
--
ALTER TABLE `bl_lignes`
  ADD CONSTRAINT `fk_bl_lignes_bl` FOREIGN KEY (`bl_id`) REFERENCES `bons_livraison` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_bl_lignes_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour la table `bons_livraison`
--
ALTER TABLE `bons_livraison`
  ADD CONSTRAINT `fk_bl_admin` FOREIGN KEY (`admin_createur_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_bl_client` FOREIGN KEY (`client_b2b_id`) REFERENCES `clients_b2b` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Contraintes pour la table `caisse_ventes`
--
ALTER TABLE `caisse_ventes`
  ADD CONSTRAINT `fk_caisse_ventes_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_caisse_ventes_caissier` FOREIGN KEY (`caissier_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour la table `caisse_vente_lignes`
--
ALTER TABLE `caisse_vente_lignes`
  ADD CONSTRAINT `fk_cvl_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cvl_vente` FOREIGN KEY (`vente_id`) REFERENCES `caisse_ventes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `clients_b2b`
--
ALTER TABLE `clients_b2b`
  ADD CONSTRAINT `fk_cb2b_admin_createur` FOREIGN KEY (`admin_createur_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour la table `commandes`
--
ALTER TABLE `commandes`
  ADD CONSTRAINT `fk_cmd_admin_createur` FOREIGN KEY (`admin_createur_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cmd_admin_traitement` FOREIGN KEY (`admin_dernier_traitement_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_commandes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour la table `commandes_personnalisees`
--
ALTER TABLE `commandes_personnalisees`
  ADD CONSTRAINT `fk_cp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cp_zone_livraison` FOREIGN KEY (`zone_livraison_id`) REFERENCES `zones_livraison` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour la table `commande_produits`
--
ALTER TABLE `commande_produits`
  ADD CONSTRAINT `fk_commande_produits_commande` FOREIGN KEY (`commande_id`) REFERENCES `commandes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_commande_produits_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Contraintes pour la table `depenses`
--
ALTER TABLE `depenses`
  ADD CONSTRAINT `fk_dep_admin` FOREIGN KEY (`admin_createur_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_dep_cat` FOREIGN KEY (`categorie_id`) REFERENCES `categories_depenses` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour la table `devis`
--
ALTER TABLE `devis`
  ADD CONSTRAINT `fk_devis_admin_createur` FOREIGN KEY (`admin_createur_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_devis_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_devis_zone` FOREIGN KEY (`zone_livraison_id`) REFERENCES `zones_livraison` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour la table `devis_produits`
--
ALTER TABLE `devis_produits`
  ADD CONSTRAINT `fk_devis_produits_devis` FOREIGN KEY (`devis_id`) REFERENCES `devis` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_devis_produits_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Contraintes pour la table `employes`
--
ALTER TABLE `employes`
  ADD CONSTRAINT `fk_employes_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour la table `factures`
--
ALTER TABLE `factures`
  ADD CONSTRAINT `fk_factures_commande` FOREIGN KEY (`commande_id`) REFERENCES `commandes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `factures_devis`
--
ALTER TABLE `factures_devis`
  ADD CONSTRAINT `fk_factures_devis_admin` FOREIGN KEY (`admin_createur_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_factures_devis_devis` FOREIGN KEY (`devis_id`) REFERENCES `devis` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `factures_mensuelles`
--
ALTER TABLE `factures_mensuelles`
  ADD CONSTRAINT `fk_fm_admin` FOREIGN KEY (`admin_createur_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_fm_client` FOREIGN KEY (`client_b2b_id`) REFERENCES `clients_b2b` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Contraintes pour la table `factures_personnalisees`
--
ALTER TABLE `factures_personnalisees`
  ADD CONSTRAINT `fk_fp_commande_perso` FOREIGN KEY (`commande_personnalisee_id`) REFERENCES `commandes_personnalisees` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `facture_mensuelle_bl`
--
ALTER TABLE `facture_mensuelle_bl`
  ADD CONSTRAINT `fk_fmb_bl` FOREIGN KEY (`bl_id`) REFERENCES `bons_livraison` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_fmb_facture` FOREIGN KEY (`facture_mensuelle_id`) REFERENCES `factures_mensuelles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `favoris`
--
ALTER TABLE `favoris`
  ADD CONSTRAINT `fk_favoris_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_favoris_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `panier`
--
ALTER TABLE `panier`
  ADD CONSTRAINT `fk_panier_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_panier_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `produits`
--
ALTER TABLE `produits`
  ADD CONSTRAINT `fk_produits_categorie` FOREIGN KEY (`categorie_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_produits_stock_article` FOREIGN KEY (`stock_article_id`) REFERENCES `stock_articles` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour la table `produits_variantes`
--
ALTER TABLE `produits_variantes`
  ADD CONSTRAINT `fk_variantes_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `produits_visites`
--
ALTER TABLE `produits_visites`
  ADD CONSTRAINT `fk_visites_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_visites_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `stock_articles`
--
ALTER TABLE `stock_articles`
  ADD CONSTRAINT `fk_stock_articles_categorie` FOREIGN KEY (`categorie_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Contraintes pour la table `stock_mouvements`
--
ALTER TABLE `stock_mouvements`
  ADD CONSTRAINT `fk_mouvements_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_mouvements_stock_article` FOREIGN KEY (`stock_article_id`) REFERENCES `stock_articles` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

SET FOREIGN_KEY_CHECKS = 1;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
