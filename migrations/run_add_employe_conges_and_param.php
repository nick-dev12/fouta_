<?php
/**
 * Ajout quota congés global + table employe_conges.
 * php migrations/run_add_employe_conges_and_param.php
 */
require_once __DIR__ . '/../conn/conn.php';

if (!$db) {
    fwrite(STDERR, "Connexion BDD impossible.\n");
    exit(1);
}

try {
    $db->exec('SET NAMES utf8mb4');
    try {
        $db->exec("
            ALTER TABLE `bulletin_paie_parametres`
            ADD COLUMN `conges_annuels_global` SMALLINT UNSIGNED NULL DEFAULT NULL
            COMMENT 'Quota annuel global de jours de congé par employé'
        ");
        echo "+ bulletin_paie_parametres.conges_annuels_global\n";
    } catch (PDOException $e) {
        $m = strtolower($e->getMessage());
        if (strpos($m, 'duplicate') !== false || strpos($m, 'already exists') !== false || strpos($m, 'déjà') !== false) {
            echo "— bulletin_paie_parametres.conges_annuels_global existe déjà\n";
        } else {
            throw $e;
        }
    }

    $sql = file_get_contents(__DIR__ . '/add_employe_conges_and_param.sql');
    if ($sql === false || trim($sql) === '') {
        throw new RuntimeException('Fichier SQL introuvable.');
    }
    if (preg_match('/CREATE TABLE IF NOT EXISTS `employe_conges`[\s\S]+$/', $sql, $mCreate)) {
        $db->exec($mCreate[0]);
        echo "+ table employe_conges\n";
    }

    $db->exec("
        UPDATE bulletin_paie_parametres
        SET conges_annuels_global = 30
        WHERE id = 1 AND (conges_annuels_global IS NULL)
    ");
    echo "Migration congés terminée.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Erreur : ' . $e->getMessage() . "\n");
    exit(1);
}
