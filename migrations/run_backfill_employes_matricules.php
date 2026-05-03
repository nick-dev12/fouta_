<?php
/**
 * Attribue un matricule FPLxxxxxx aux employés sans ligne dans employes_matricules.
 */
require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/../models/model_employe_matricules.php';

try {
    $stmt = $db->query('
        SELECT e.id FROM employes e
        LEFT JOIN employes_matricules m ON m.employe_id = e.id
        WHERE m.id IS NULL
    ');
    $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
} catch (PDOException $e) {
    echo 'Erreur liste: ' . $e->getMessage() . "\n";
    exit(1);
}

$n = 0;
foreach ($ids as $id) {
    if ($id > 0 && employe_matricule_assigner_si_absent((int) $id)) {
        ++$n;
    }
}

echo '+ backfill matricules: ' . count($ids) . ' sans matricule, ' . $n . ' assignes avec succes.' . "\n";
