<?php
/**
 * Ajoute subject_admin_id sur employe_absences (liste des absences = comptes admin hors rôle admin)
 * php migrations/run_alter_employe_absences_subject_admin.php
 */
require_once __DIR__ . '/../conn/conn.php';

$sqlFile = __DIR__ . '/alter_employe_absences_subject_admin.sql';

try {
    $sql = file_get_contents($sqlFile);
    if ($sql === false || trim($sql) === '') {
        echo "Fichier SQL introuvable ou vide.\n";
        exit(1);
    }
    foreach (array_filter(array_map('trim', preg_split('/;\s*\R/', $sql))) as $stmt) {
        if ($stmt !== '') {
            $db->exec($stmt);
        }
    }
    echo "+ alter employe_absences subject_admin_id OK\n";
} catch (PDOException $e) {
    $msg = $e->getMessage();
    if (stripos($msg, 'Duplicate column') !== false || stripos($msg, 'already exists') !== false) {
        echo "— Colonne / contraintes déjà appliquées (subject_admin_id)\n";
    } elseif (stripos($msg, 'Unknown column') !== false && stripos($msg, 'subject_admin') !== false) {
        echo "Erreur: exécutez d’abord create_employes_absences_tables si la table n’existe pas.\n";
        echo $msg . "\n";
        exit(1);
    } else {
        echo 'Erreur: ' . $msg . "\n";
        exit(1);
    }
}
