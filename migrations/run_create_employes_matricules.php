<?php
/** php migrations/run_create_employes_matricules.php */
require_once __DIR__ . '/../conn/conn.php';

try {
    $sql = trim((string) file_get_contents(__DIR__ . '/create_employes_matricules.sql'));
    if ($sql !== '') {
        $db->exec($sql);
    }
    echo "+ table employes_matricules OK\n";
} catch (PDOException $e) {
    $m = $e->getMessage();
    if (stripos($m, 'already exists') !== false || stripos($m, 'Duplicate') !== false) {
        echo "— table deja creee ou doublon\n";
    } else {
        echo 'Erreur: ' . $m . "\n";
        exit(1);
    }
}
