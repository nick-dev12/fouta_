<?php
/**
 * Exécute la migration add_variantes_et_surcoûts.sql
 * À lancer une seule fois : php migrations/run_add_variantes_surcouts.php
 */
require_once __DIR__ . '/../conn/conn.php';

$sql_file = __DIR__ . '/add_variantes_et_surcoûts.sql';
if (!file_exists($sql_file)) {
    die("Fichier SQL introuvable.\n");
}

$sql = file_get_contents($sql_file);
$statements = array_filter(array_map('trim', preg_split('/;\s*$/m', $sql)));
$statements = array_filter($statements, function ($s) {
    return !empty($s) && strpos($s, '--') !== 0;
});

global $db;
$ok = 0;
$err = 0;
foreach ($statements as $stmt) {
    if (empty(trim($stmt))) continue;
    try {
        $db->exec($stmt);
        echo "OK: " . substr($stmt, 0, 60) . "...\n";
        $ok++;
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false || strpos($e->getMessage(), 'already exists') !== false) {
            echo "IGNORÉ (existe déjà): " . substr($stmt, 0, 50) . "...\n";
        } else {
            echo "ERREUR: " . $e->getMessage() . "\n";
            $err++;
        }
    }
}
echo "\nTerminé. OK: $ok, Erreurs: $err\n";
