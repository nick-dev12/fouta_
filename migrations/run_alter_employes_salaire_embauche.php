<?php
/**
 * Ajoute employes.date_embauche et employes.salaire_base si absents.
 * php migrations/run_alter_employes_salaire_embauche.php
 */
require_once __DIR__ . '/../conn/conn.php';

if (!$db) {
    fwrite(STDERR, "Connexion BDD impossible.\n");
    exit(1);
}

$stmts = [
    "ALTER TABLE `employes` ADD COLUMN `date_embauche` DATE NULL DEFAULT NULL",
    "ALTER TABLE `employes` ADD COLUMN `salaire_base` DECIMAL(14,2) NULL DEFAULT NULL COMMENT 'Salaire brut / base habituel FCFA'",
];

try {
    $db->exec('SET NAMES utf8mb4');
    foreach ($stmts as $sql) {
        try {
            $db->exec($sql);
            echo '+ ' . preg_replace('/\s+/', ' ', substr($sql, 0, 72)) . "…\n";
        } catch (PDOException $e) {
            $m = strtolower($e->getMessage());
            if (strpos($m, 'duplicate') !== false || strpos($m, 'already exists') !== false
                || strpos($m, 'déjà') !== false) {
                echo '— colonne déjà présente (' . substr($sql, 0, 50) . "…)\n";
            } else {
                throw $e;
            }
        }
    }
    echo "\nMigration employes (date_embauche, salaire_base) terminée.\n";
} catch (PDOException $e) {
    fwrite(STDERR, 'Erreur : ' . $e->getMessage() . "\n");
    exit(1);
}
