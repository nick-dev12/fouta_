<?php
/**
 * Ajoute bulletin_paie_parametres.jours_presence_defaut
 * php migrations/run_alter_bulletin_jours_presence_defaut.php
 */
require_once __DIR__ . '/../conn/conn.php';

if (!$db) {
    fwrite(STDERR, "Connexion BDD impossible.\n");
    exit(1);
}

$sqlFile = __DIR__ . '/alter_bulletin_paie_jours_presence_defaut.sql';
if (!is_readable($sqlFile)) {
    fwrite(STDERR, "Fichier SQL introuvable : $sqlFile\n");
    exit(1);
}

try {
    $db->exec('SET NAMES utf8mb4');
    $db->exec(trim(file_get_contents($sqlFile)));
    echo "+ colonne bulletin_paie_parametres.jours_presence_defaut\n";
} catch (PDOException $e) {
    $m = strtolower($e->getMessage());
    if (strpos($m, 'duplicate') !== false || strpos($m, 'already exists') !== false
        || strpos($m, 'déjà') !== false || strpos($m, 'exists') !== false) {
        echo "— colonne jours_presence_defaut déjà présente\n";
        exit(0);
    }
    fwrite(STDERR, 'Erreur : ' . $e->getMessage() . "\n");
    exit(1);
}
