<?php
/**
 * php migrations/run_create_sous_categories_prix_achat.php
 */
require_once __DIR__ . '/../conn/conn.php';

$sql = file_get_contents(__DIR__ . '/create_sous_categories_prix_achat.sql');
if ($sql === false || trim($sql) === '') {
    fwrite(STDERR, "Fichier SQL introuvable.\n");
    exit(1);
}
// Retirer les lignes de commentaires SQL pour que le premier segment ne soit pas ignoré en bloc
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
        if (stripos($msg, 'Duplicate column') !== false
            || stripos($msg, 'already exists') !== false
            || stripos($msg, 'Duplicate key') !== false
            || (stripos($msg, 'Duplicate foreign key') !== false)
            || strpos($msg, '1061') !== false) {
            echo "IGNORÉ (déjà présent): " . preg_replace('/\s+/', ' ', substr($stmtRaw, 0, 60)) . "…\n";
            continue;
        }
        fwrite(STDERR, $msg . "\nStatement: " . substr($stmtRaw, 0, 200) . "\n");
        $error_fatal = true;
    }
}

if ($error_fatal) {
    exit(1);
}
echo "Terminé — sous_categories, prix_achat, sous_categorie_id (+ FK si applicable).\n";
