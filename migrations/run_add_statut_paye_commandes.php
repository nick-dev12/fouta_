<?php
/**
 * Exécute la migration: ajout du statut 'paye' à la table commandes
 */
require_once __DIR__ . '/../conn/conn.php';

try {
    $sql = file_get_contents(__DIR__ . '/add_statut_paye_commandes.sql');
    $db->exec($sql);
    echo "Migration réussie: statut 'paye' ajouté à la table commandes.\n";
} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
    exit(1);
}
