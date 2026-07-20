<?php
/**
 * Crée entrepot_emplacement_config + entrepot_emplacement_etage (seed 3 étages).
 * php migrations/run_create_entrepot_emplacement_config.php
 */
require_once __DIR__ . '/../conn/conn.php';

if (!$db) {
    fwrite(STDERR, "Connexion BDD impossible.\n");
    exit(1);
}

try {
    $sql = file_get_contents(__DIR__ . '/create_entrepot_emplacement_config.sql');
    if ($sql === false || trim($sql) === '') {
        throw new RuntimeException('Fichier SQL introuvable.');
    }

    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
        if ($statement === '') {
            continue;
        }
        $db->exec($statement);
    }

    echo "+ entrepot_emplacement_config OK\n";
    echo "+ entrepot_emplacement_etage OK (seed 3 étages)\n";
} catch (PDOException $e) {
    if (stripos($e->getMessage(), 'already exists') !== false) {
        echo "— Tables déjà présentes.\n";
    } else {
        fwrite(STDERR, 'Erreur : ' . $e->getMessage() . "\n");
        exit(1);
    }
}

echo "Terminé.\n";
