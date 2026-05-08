<?php
/**
 * Ajoute produits.image_etiquette_fpl
 * php migrations/run_add_produit_image_etiquette_fpl.php
 */
require_once __DIR__ . '/../conn/conn.php';

if (!$db) {
    fwrite(STDERR, "Connexion BDD impossible.\n");
    exit(1);
}

$sqlFile = __DIR__ . '/add_produit_image_etiquette_fpl.sql';
if (!is_readable($sqlFile)) {
    fwrite(STDERR, "Fichier SQL introuvable : $sqlFile\n");
    exit(1);
}

try {
    $db->exec('SET NAMES utf8mb4');
    $db->exec(trim(file_get_contents($sqlFile)));
    echo "+ colonne produits.image_etiquette_fpl\n";
} catch (PDOException $e) {
    $m = strtolower($e->getMessage());
    if (strpos($m, 'duplicate') !== false || strpos($m, 'already exists') !== false
        || strpos($m, 'déjà') !== false || strpos($m, 'exist') !== false) {
        echo "— colonne image_etiquette_fpl déjà présente\n";
        exit(0);
    }
    fwrite(STDERR, 'Erreur : ' . $e->getMessage() . "\n");
    exit(1);
}
