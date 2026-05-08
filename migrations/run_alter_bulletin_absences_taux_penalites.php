<?php
/**
 * Absences : pénalité, retenue salaire, lien bulletin déduit.
 * Paramètres BP : taux % IRPP / IPRES / CSS (JSON).
 * Bulletins : montants IRPP, IPRES, CSS, pénalités (dénormalisés pour suivi).
 *
 * php migrations/run_alter_bulletin_absences_taux_penalites.php
 */
require_once __DIR__ . '/../conn/conn.php';

if (!$db) {
    fwrite(STDERR, "Connexion BDD impossible.\n");
    exit(1);
}

function migration_try_exec(PDO $db, $sql, $label) {
    try {
        $db->exec($sql);
        echo "+ $label\n";
    } catch (PDOException $e) {
        $m = strtolower($e->getMessage());
        if (strpos($m, 'duplicate') !== false || strpos($m, 'already exists') !== false
            || strpos($m, 'déjà') !== false || strpos($m, 'exists') !== false) {
            echo "— $label (déjà présent)\n";
            return;
        }
        throw $e;
    }
}

try {
    $db->exec('SET NAMES utf8mb4');

    migration_try_exec($db, "
        ALTER TABLE `employe_absences`
        ADD COLUMN `penalite_montant` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT 'Pénalité financière (FCFA)' AFTER `motif`
    ", 'employe_absences.penalite_montant');

    migration_try_exec($db, "
        ALTER TABLE `employe_absences`
        ADD COLUMN `penalite_retenir_salaire` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = à déduire au prochain bulletin' AFTER `penalite_montant`
    ", 'employe_absences.penalite_retenir_salaire');

    migration_try_exec($db, "
        ALTER TABLE `employe_absences`
        ADD COLUMN `penalite_deduite_bulletin_id` INT(11) NULL DEFAULT NULL COMMENT 'Bulletin ayant appliqué la déduction' AFTER `penalite_retenir_salaire`
    ", 'employe_absences.penalite_deduite_bulletin_id');

    migration_try_exec($db, "
        ALTER TABLE `employe_absences`
        ADD KEY `idx_penalite_deduite_bulletin` (`penalite_deduite_bulletin_id`)
    ", 'employe_absences.idx_penalite_deduite_bulletin');

    migration_try_exec($db, "
        ALTER TABLE `employe_absences`
        ADD CONSTRAINT `fk_employe_absences_penalite_bulletin`
        FOREIGN KEY (`penalite_deduite_bulletin_id`) REFERENCES `employe_bulletins_paie` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
    ", 'employe_absences.fk_penalite_bulletin');

    migration_try_exec($db, "
        ALTER TABLE `bulletin_paie_parametres`
        ADD COLUMN `retenues_taux_json` LONGTEXT NULL COMMENT 'Taux %% sur brut : irpp, ipres, css' AFTER `rubriques_json`
    ", 'bulletin_paie_parametres.retenues_taux_json');

    migration_try_exec($db, "
        ALTER TABLE `employe_bulletins_paie`
        ADD COLUMN `montant_irpp` DECIMAL(14,2) NULL DEFAULT NULL AFTER `net_a_payer`
    ", 'employe_bulletins_paie.montant_irpp');

    migration_try_exec($db, "
        ALTER TABLE `employe_bulletins_paie`
        ADD COLUMN `montant_ipres` DECIMAL(14,2) NULL DEFAULT NULL AFTER `montant_irpp`
    ", 'employe_bulletins_paie.montant_ipres');

    migration_try_exec($db, "
        ALTER TABLE `employe_bulletins_paie`
        ADD COLUMN `montant_css` DECIMAL(14,2) NULL DEFAULT NULL AFTER `montant_ipres`
    ", 'employe_bulletins_paie.montant_css');

    migration_try_exec($db, "
        ALTER TABLE `employe_bulletins_paie`
        ADD COLUMN `montant_penalites_absence` DECIMAL(14,2) NULL DEFAULT NULL AFTER `montant_css`
    ", 'employe_bulletins_paie.montant_penalites_absence');

    echo "\nMigration absences / taux retenues / colonnes bulletins terminée.\n";
} catch (PDOException $e) {
    fwrite(STDERR, 'Erreur : ' . $e->getMessage() . "\n");
    exit(1);
}
