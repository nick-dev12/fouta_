<?php
/**
 * Applique migrations/alter_admin_role_gestion_stock_general.sql
 * Usage : php migrations/run_alter_admin_role_gestion_stock_general.php
 */
require_once dirname(__DIR__) . '/conn/conn.php';

$sql_file = __DIR__ . '/alter_admin_role_gestion_stock_general.sql';
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
    echo "Migration rôle gestion_stock_general : OK\n";
} catch (PDOException $e) {
    fwrite(STDERR, 'Erreur : ' . $e->getMessage() . "\n");
    exit(1);
}
