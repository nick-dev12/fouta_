<?php
/**
 * Applique migrations/alter_admin_role_commercial_general_informaticien.sql
 * Usage (ligne de commande depuis la racine du projet) : php migrations/run_alter_admin_role_commercial_general_informaticien.php
 */
require_once dirname(__DIR__) . '/conn/conn.php';

$sql_file = __DIR__ . '/alter_admin_role_commercial_general_informaticien.sql';
if (!is_file($sql_file)) {
    fwrite(STDERR, "Fichier SQL introuvable.\n");
    exit(1);
}
$sql = file_get_contents($sql_file);
if ($sql === false || trim($sql) === '') {
    fwrite(STDERR, "Fichier SQL vide.\n");
    exit(1);
}

try {
    global $db;
    $db->exec($sql);
    echo "Migration rôles commercial_general / informaticien : OK\n";
} catch (PDOException $e) {
    fwrite(STDERR, 'Erreur : ' . $e->getMessage() . "\n");
    exit(1);
}
