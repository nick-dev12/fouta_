<?php
/**
 * Colonne IRPP fiche employé + forfait HS paramètres BP + normalisation JSON rubriques / taux
 * php migrations/run_add_employe_irpp_bp_forfait_trimf.php
 */
require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/../models/model_bulletin_paie.php';

if (!$db) {
    fwrite(STDERR, "Connexion BDD impossible.\n");
    exit(1);
}

function col_exists(PDO $db, string $table, string $col): bool {
    try {
        $st = $db->prepare('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '` LIKE ?');
        $st->execute([$col]);
        return (bool) $st->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return false;
    }
}

try {
    $db->exec('SET NAMES utf8mb4');

    if (!col_exists($db, 'employes', 'montant_irpp_mensuel')) {
        $db->exec("
            ALTER TABLE `employes`
            ADD COLUMN `montant_irpp_mensuel` DECIMAL(14,2) NULL DEFAULT NULL COMMENT 'IRPP mensuel fixe (FCFA), fiche employé' AFTER `salaire_base`
        ");
        echo "+ employes.montant_irpp_mensuel\n";
    } else {
        echo "— employes.montant_irpp_mensuel existe déjà\n";
    }

    if (!col_exists($db, 'bulletin_paie_parametres', 'forfait_heures_sup_mensuel')) {
        $db->exec("
            ALTER TABLE `bulletin_paie_parametres`
            ADD COLUMN `forfait_heures_sup_mensuel` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Forfait HS sursalaire (FCFA / mois)' AFTER `conges_annuels_global`
        ");
        echo "+ bulletin_paie_parametres.forfait_heures_sup_mensuel\n";
    } else {
        echo "— bulletin_paie_parametres.forfait_heures_sup_mensuel existe déjà\n";
    }

    if (bp_tables_parametres_disponibles()) {
        $row = bp_get_parametres_row();
        if ($row) {
            $rj = json_decode((string) ($row['rubriques_json'] ?? ''), true);
            $rub = bp_merge_rubriques(is_array($rj) ? $rj : null);
            $tj = json_decode((string) ($row['retenues_taux_json'] ?? ''), true);
            $taux = bp_merge_retenues_taux(is_array($tj) ? $tj : null);
            $st = $db->prepare('UPDATE bulletin_paie_parametres SET rubriques_json = :rj, retenues_taux_json = :tj WHERE id = 1');
            $st->execute([
                'rj' => json_encode($rub, JSON_UNESCAPED_UNICODE),
                'tj' => json_encode($taux, JSON_UNESCAPED_UNICODE),
            ]);
            echo "~ bulletin_paie_parametres JSON rubriques / taux normalisés (id=1)\n";
        }
    }

    echo "\nMigration IRPP / forfait HS / TRIMF terminée.\n";
} catch (PDOException $e) {
    fwrite(STDERR, 'Erreur : ' . $e->getMessage() . "\n");
    exit(1);
}
