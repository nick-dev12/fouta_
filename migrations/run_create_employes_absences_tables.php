<?php
/**
 * Crée employe_absences et employe_absence_justificatifs
 * php migrations/run_create_employes_absences_tables.php
 */
require_once __DIR__ . '/../conn/conn.php';

try {
    $sql = file_get_contents(__DIR__ . '/create_employes_absences_tables.sql');
    if ($sql !== false && trim($sql) !== '') {
        $db->exec($sql);
    }
    echo "+ employe_absences + employe_absence_justificatifs OK\n";
} catch (PDOException $e) {
    if (stripos($e->getMessage(), 'already exists') !== false) {
        echo "— Tables absences déjà présentes\n";
    } else {
        echo 'Erreur: ' . $e->getMessage() . "\n";
        exit(1);
    }
}
