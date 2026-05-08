<?php
/**
 * Migration : colonnes fiscales sur caisse_ventes (TVA optionnelle).
 * php migrations/run_alter_caisse_ventes_tva.php
 */
require_once __DIR__ . '/../conn/conn.php';

if (!$db) {
    fwrite(STDERR, "Connexion BDD impossible.\n");
    exit(1);
}

try {
    $db->exec('SET NAMES utf8mb4');

    $st = $db->query("SHOW COLUMNS FROM `caisse_ventes` LIKE 'tva_incluse'");
    if ($st && $st->rowCount() > 0) {
        echo "OK — colonnes TVA déjà présentes sur caisse_ventes.\n";
        exit(0);
    }

    $sqlPath = __DIR__ . '/alter_caisse_ventes_tva.sql';
    if (!is_readable($sqlPath)) {
        fwrite(STDERR, "Fichier introuvable : $sqlPath\n");
        exit(1);
    }
    $db->exec(file_get_contents($sqlPath));
    echo "+ caisse_ventes : montant_ht, montant_tva, tva_incluse\n";
    exit(0);
} catch (PDOException $e) {
    fwrite(STDERR, 'Erreur : ' . $e->getMessage() . "\n");
    exit(1);
}
