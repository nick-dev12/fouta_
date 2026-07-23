<?php
/**
 * Crée le registre des champs structurels entrepôt (colonnes dynamiques).
 * php migrations/run_create_entrepot_structure_champs.php
 */
require_once __DIR__ . '/../conn/conn.php';

if (!$db) {
    fwrite(STDERR, "Connexion BDD impossible.\n");
    exit(1);
}

try {
    $sql = file_get_contents(__DIR__ . '/create_entrepot_structure_champs.sql');
    if ($sql === false || trim($sql) === '') {
        throw new RuntimeException('Fichier SQL introuvable.');
    }

    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
        if ($statement === '') {
            continue;
        }
        $db->exec($statement);
    }

    require_once __DIR__ . '/../models/model_entrepot_structure_champs.php';
    entrepot_structure_champs_sync_colonnes_etage();

    echo "+ entrepot_structure_champ OK (champs système seedés)\n";
    echo "+ colonnes entrepot_emplacement_etage synchronisées\n";
} catch (PDOException $e) {
    fwrite(STDERR, 'Erreur : ' . $e->getMessage() . "\n");
    exit(1);
}

echo "Terminé.\n";
