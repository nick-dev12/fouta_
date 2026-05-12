<?php
/**
 * Supprime le paramétrage global des plafonds BL (remplacé par le plafond par contact).
 * php migrations/run_drop_parametres_types_client_bl.php
 */
require_once __DIR__ . '/../conn/conn.php';

if (!$db) {
    fwrite(STDERR, "Connexion BDD impossible.\n");
    exit(1);
}

try {
    $db->exec('SET NAMES utf8mb4');
    $db->exec('DROP TABLE IF EXISTS parametres_types_client_bl');
    echo "OK : table parametres_types_client_bl supprimée (si elle existait).\n";
} catch (PDOException $e) {
    fwrite(STDERR, 'Erreur : ' . $e->getMessage() . "\n");
    exit(1);
}
echo "Terminé.\n";