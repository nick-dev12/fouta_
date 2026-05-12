<?php
/**
 * Colonnes telephone et email sur fournisseurs
 * php migrations/run_add_fournisseurs_telephone_email.php
 */
require_once __DIR__ . '/../conn/conn.php';

$sql = file_get_contents(__DIR__ . '/add_fournisseurs_telephone_email.sql');
try {
    $db->exec($sql);
    echo "OK: colonnes telephone et email sur fournisseurs.\n";
} catch (PDOException $e) {
    $m = $e->getMessage();
    if (stripos($m, 'Duplicate column') !== false) {
        echo "— Colonnes déjà présentes.\n";
    } else {
        echo "Erreur: $m\n";
        exit(1);
    }
}
echo "Terminé.\n";
