<?php
/**
 * php migrations/run_create_employe_prets.php
 */
require_once __DIR__ . '/../conn/conn.php';

$sql = file_get_contents(__DIR__ . '/create_employe_prets_table.sql');
if ($sql === false || trim($sql) === '') {
    fwrite(STDERR, "Fichier SQL introuvable.\n");
    exit(1);
}
$sql = preg_replace('/^\s*--.*$/m', '', $sql);
global $db;
if (!$db) {
    fwrite(STDERR, "Pas de connexion BDD.\n");
    exit(1);
}
$error_fatal = false;
foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmtRaw) {
    if ($stmtRaw === '') {
        continue;
    }
    try {
        $db->exec($stmtRaw);
        echo "OK: " . preg_replace('/\s+/', ' ', substr($stmtRaw, 0, 72)) . "…\n";
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        if (stripos($msg, 'already exists') !== false
            || stripos($msg, 'Duplicate key') !== false) {
            echo "IGNORÉ (déjà présent): " . preg_replace('/\s+/', ' ', substr($stmtRaw, 0, 60)) . "…\n";
            continue;
        }
        fwrite(STDERR, $msg . "\n");
        $error_fatal = true;
    }
}
if ($error_fatal) {
    exit(1);
}
echo "Terminé — table employe_prets.\n";
