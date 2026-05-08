<?php
/**
 * Migration : montant_orange_money, montant_wave, ENUM sans mobile_money.
 * php migrations/run_alter_caisse_ventes_canaux_paiement.php
 */
require_once __DIR__ . '/../conn/conn.php';

if (!$db) {
    fwrite(STDERR, "Connexion BDD impossible.\n");
    exit(1);
}

try {
    $db->exec('SET NAMES utf8mb4');

    $st = $db->query("SHOW COLUMNS FROM `caisse_ventes` LIKE 'montant_wave'");
    if ($st && $st->rowCount() > 0) {
        echo "OK — colonnes canaux Wave / Orange déjà présentes.\n";
        exit(0);
    }

    $sqlPath = __DIR__ . '/alter_caisse_ventes_canaux_paiement.sql';
    if (!is_readable($sqlPath)) {
        fwrite(STDERR, "Fichier introuvable : $sqlPath\n");
        exit(1);
    }
    $raw = file_get_contents($sqlPath);
    $parts = array_filter(array_map('trim', preg_split('/;\s*\n/', $raw)));
    foreach ($parts as $chunk) {
        if ($chunk === '') {
            continue;
        }
        // Retirer les lignes de commentaire SQL (-- …) pour ne pas ignorer un bloc dont la 1re ligne est un commentaire.
        $lines = preg_split('/\r\n|\n|\r/', $chunk);
        $buf = [];
        foreach ($lines as $line) {
            if (preg_match('/^\s*--/', $line)) {
                continue;
            }
            $buf[] = $line;
        }
        $chunk = trim(implode("\n", $buf));
        if ($chunk === '') {
            continue;
        }
        $db->exec($chunk);
    }
    echo "+ caisse_ventes : montant_orange_money, montant_wave, mode_paiement (orange_money, wave)\n";
    exit(0);
} catch (PDOException $e) {
    fwrite(STDERR, 'Erreur : ' . $e->getMessage() . "\n");
    exit(1);
}
