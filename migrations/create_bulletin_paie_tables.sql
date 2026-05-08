-- Paramètres bulletins de paie + historique (schéma de référence)
-- Préférez : php migrations/run_create_bulletin_paie.php (recrée employe_bulletins_paie si besoin)

CREATE TABLE IF NOT EXISTS `bulletin_paie_parametres` (
  `id` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `employeur_nom` VARCHAR(255) NOT NULL DEFAULT '',
  `employeur_adresse` TEXT NULL,
  `employeur_ninea` VARCHAR(80) NULL DEFAULT '',
  `employeur_rc` VARCHAR(120) NULL DEFAULT '',
  `employeur_cnss_ref` VARCHAR(120) NULL DEFAULT '',
  `rubriques_json` LONGTEXT NULL COMMENT 'Activation des lignes gains/retenues/travail/mentions (JSON)',
  `retenues_taux_json` LONGTEXT NULL COMMENT 'Taux %% sur brut : trimf, ipres_rg, ipres_cadre, css',
  `jours_presence_defaut` SMALLINT UNSIGNED NULL DEFAULT NULL COMMENT 'Jours de presence reference (tous employes)',
  `prime_transport_mensuelle` DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'Montant mensuel de référence de la prime de transport',
  `conges_annuels_global` SMALLINT UNSIGNED NULL DEFAULT NULL COMMENT 'Quota annuel global de jours de congé par employé',
  `forfait_heures_sup_mensuel` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Forfait HS sursalaire (FCFA / mois)',
  `date_modification` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La migration PHP exécute DROP TABLE IF EXISTS puis CREATE complet avec FK :
-- employe_bulletins_paie (employe_id -> employes, admin_id -> admin)
