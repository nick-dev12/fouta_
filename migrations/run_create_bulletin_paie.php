<?php
/**
 * Bulletins de paie : tables parametres + bulletins employés + colonnes fiche employé
 * php migrations/run_create_bulletin_paie.php
 */
require_once __DIR__ . '/../conn/conn.php';

if (!$db) {
    fwrite(STDERR, "Connexion BDD impossible.\n");
    exit(1);
}

$default_rubriques = [
    'gains' => [
        'salaire_base' => true,
        'heures_sup' => true,
        'prime_performance' => true,
        'prime_transport' => true,
        'assurance_maladie' => false,
        'sursalaire' => false,
        'indemnite_transport' => true,
        'indemnite_logement' => true,
        'indemnite_fonction' => true,
    ],
    'retenues' => [
        'irpp' => true,
        'trimf' => true,
        'ipres_rg' => true,
        'ipres_cadre' => false,
        'css' => true,
        'accident_travail' => true,
        'pret_salaire' => true,
        'autres_retenues' => true,
    ],
    'travail' => [
        'heures_travaillees' => true,
        'heures_sup' => true,
        'jours_presence' => true,
        'conges' => false,
    ],
    'mentions' => [
        'date_paiement' => true,
        'mode_paiement' => true,
        'signature' => true,
    ],
];

try {
    $db->exec('SET NAMES utf8mb4');

    $db->exec("
CREATE TABLE IF NOT EXISTS `bulletin_paie_parametres` (
  `id` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `employeur_nom` VARCHAR(255) NOT NULL DEFAULT '',
  `employeur_adresse` TEXT NULL,
  `employeur_ninea` VARCHAR(80) NULL DEFAULT '',
  `employeur_rc` VARCHAR(120) NULL DEFAULT '',
  `employeur_cnss_ref` VARCHAR(120) NULL DEFAULT '',
  `rubriques_json` LONGTEXT NULL,
  `retenues_taux_json` LONGTEXT NULL COMMENT 'Taux % sur brut : trimf, ipres_rg, ipres_cadre, css',
  `jours_presence_defaut` SMALLINT UNSIGNED NULL DEFAULT NULL COMMENT 'Jours de presence reference (tous employes)',
  `prime_transport_mensuelle` DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'Montant mensuel de référence de la prime de transport',
  `conges_annuels_global` SMALLINT UNSIGNED NULL DEFAULT NULL COMMENT 'Quota annuel global de jours de congé par employé',
  `forfait_heures_sup_mensuel` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Forfait HS sursalaire (FCFA / mois)',
  `date_modification` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "+ table bulletin_paie_parametres\n";

    $db->exec('DROP TABLE IF EXISTS `employe_bulletins_paie`');
    echo "~ table employe_bulletins_paie recréée (perte des anciennes lignes si présentes)\n";

    $db->exec("
CREATE TABLE `employe_bulletins_paie` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `employe_id` INT(11) NOT NULL,
  `mois_paie` CHAR(7) NOT NULL,
  `date_paiement` DATE NOT NULL,
  `salaire_base` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `montant_brut` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `total_retenues` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `net_imposable` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `net_a_payer` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `montant_irpp` DECIMAL(14,2) NULL DEFAULT NULL,
  `montant_ipres` DECIMAL(14,2) NULL DEFAULT NULL,
  `montant_css` DECIMAL(14,2) NULL DEFAULT NULL,
  `montant_penalites_absence` DECIMAL(14,2) NULL DEFAULT NULL,
  `snapshot_json` LONGTEXT NOT NULL,
  `admin_id` INT(11) NULL DEFAULT NULL,
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_emp_mois` (`employe_id`, `mois_paie`),
  KEY `idx_date_crea` (`date_creation`),
  CONSTRAINT `fk_bulletin_employe` FOREIGN KEY (`employe_id`) REFERENCES `employes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_bulletin_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "+ table employe_bulletins_paie (avec FK)\n";

    foreach (['salaire_base' => "DECIMAL(14,2) NULL DEFAULT NULL COMMENT 'Salaire de base habituel (FCFA)'",
        'montant_irpp_mensuel' => "DECIMAL(14,2) NULL DEFAULT NULL COMMENT 'IRPP mensuel fixe bulletin (FCFA)'",
        'categorie_paie' => "VARCHAR(120) NULL DEFAULT NULL COMMENT 'Catégorie / classification (bulletin)'",
    ] as $col => $def) {
        try {
            $db->exec("ALTER TABLE `employes` ADD COLUMN `$col` $def");
            echo "+ employes.$col\n";
        } catch (PDOException $e) {
            $m = strtolower($e->getMessage());
            if (strpos($m, 'duplicate') !== false || strpos($m, 'already exists') !== false
                || strpos($m, 'déjà') !== false) {
                echo "— employes.$col existe déjà\n";
            } else {
                throw $e;
            }
        }
    }

    $stmt = $db->query('SELECT COUNT(*) FROM bulletin_paie_parametres WHERE id = 1');
    if ((int) $stmt->fetchColumn() === 0) {
        $default_taux = ['trimf' => 0.0, 'ipres_rg' => 0.0, 'ipres_cadre' => 0.0, 'css' => 0.0];
        $ins = $db->prepare('
            INSERT INTO bulletin_paie_parametres (id, employeur_nom, employeur_adresse, employeur_ninea, employeur_rc, employeur_cnss_ref, rubriques_json, retenues_taux_json, jours_presence_defaut, prime_transport_mensuelle, conges_annuels_global, forfait_heures_sup_mensuel)
            VALUES (1, :nom, NULL, \'\', \'\', \'\', :rj, :tj, 22, 0, 30, 0)
        ');
        $ins->execute([
            'nom' => 'À renseigner — Paramètres > Bulletin de paie',
            'rj' => json_encode($default_rubriques, JSON_UNESCAPED_UNICODE),
            'tj' => json_encode($default_taux, JSON_UNESCAPED_UNICODE),
        ]);
        echo "+ ligne par défaut bulletin_paie_parametres\n";
    } else {
        echo "— bulletin_paie_parametres id=1 déjà présent\n";
    }

    echo "\nMigration bulletin de paie terminée.\n";
} catch (PDOException $e) {
    fwrite(STDERR, 'Erreur : ' . $e->getMessage() . "\n");
    exit(1);
}
