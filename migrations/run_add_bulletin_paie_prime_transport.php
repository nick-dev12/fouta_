<?php
/**
 * Ajout prime transport paramètres + table des retraits transport par employé.
 * php migrations/run_add_bulletin_paie_prime_transport.php
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
            ADD COLUMN `prime_transport_mensuelle` DECIMAL(12,2) NULL DEFAULT NULL
            COMMENT 'Montant mensuel de référence de la prime de transport'
        ");
        echo "+ bulletin_paie_parametres.prime_transport_mensuelle\n";
    } catch (PDOException $e) {
        $m = strtolower($e->getMessage());
        if (strpos($m, 'duplicate') !== false || strpos($m, 'already exists') !== false || strpos($m, 'déjà') !== false) {
            echo "— bulletin_paie_parametres.prime_transport_mensuelle existe déjà\n";
        } else {
            throw $e;
        }
    }

    $sql = file_get_contents(__DIR__ . '/add_bulletin_paie_prime_transport.sql');
    if ($sql === false || trim($sql) === '') {
        throw new RuntimeException('Fichier SQL introuvable.');
    }
    if (preg_match('/CREATE TABLE IF NOT EXISTS `employe_prime_transport_retraits`[\s\S]+$/', $sql, $mCreate)) {
        $db->exec($mCreate[0]);
        echo "+ table employe_prime_transport_retraits\n";
    }

    $db->exec("
        UPDATE bulletin_paie_parametres
        SET prime_transport_mensuelle = 0
        WHERE id = 1 AND (prime_transport_mensuelle IS NULL)
    ");

    echo "Migration prime transport bulletin terminée.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Erreur : ' . $e->getMessage() . "\n");
    exit(1);
}
