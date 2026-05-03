<?php
/**
 * php migrations/run_alter_employe_absences_drop_unique.php
 */
require_once __DIR__ . '/../conn/conn.php';

try {
    $db->exec(trim(file_get_contents(__DIR__ . '/alter_employe_absences_drop_unique_subject_date.sql')));
    echo "+ ux_subject_admin_date supprime (doublons dates controles applicatif)\n";
} catch (PDOException $e) {
    $m = $e->getMessage();
    if (stripos($m, 'check that column/key exists') !== false || stripos($m, "Can't DROP") !== false) {
        echo "— Index ux_subject_admin_date deja absent\n";
    } else {
        echo 'Erreur: ' . $m . "\n";
        exit(1);
    }
}
