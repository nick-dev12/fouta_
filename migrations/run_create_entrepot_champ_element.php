<?php
/**
 * Crée entrepot_champ_element (noms des champs personnalisés par étage).
 * php migrations/run_create_entrepot_champ_element.php
 */
require_once __DIR__ . '/../conn/conn.php';

if (!$db) {
    fwrite(STDERR, "Connexion BDD impossible.\n");
    exit(1);
}

try {
    $sql = file_get_contents(__DIR__ . '/create_entrepot_champ_element.sql');
    if ($sql === false || trim($sql) === '') {
        throw new RuntimeException('Fichier SQL introuvable.');
    }
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
        if ($statement === '') {
            continue;
        }
        $db->exec($statement);
    }
    echo "+ entrepot_champ_element OK\n";
} catch (PDOException $e) {
    fwrite(STDERR, 'Erreur : ' . $e->getMessage() . "\n");
    exit(1);
}

echo "Terminé.\n";
