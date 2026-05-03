<?php
/** php migrations/run_add_employes_photo_chemin.php */
require_once __DIR__ . '/../conn/conn.php';

try {
    $sql = trim((string) file_get_contents(__DIR__ . '/add_employes_photo_chemin.sql'));
    if ($sql !== '') {
        $db->exec($sql);
    }
    echo "+ employes.photo_chemin OK\n";
} catch (PDOException $e) {
    $m = $e->getMessage();
    if (stripos($m, 'Duplicate column') !== false) {
        echo "— colonne photo_chemin deja presente\n";
    } else {
        echo 'Erreur: ' . $m . "\n";
        exit(1);
    }
}
