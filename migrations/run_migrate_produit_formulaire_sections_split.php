<?php
/**
 * Migration CLI : sections prix / stock / catégorie séparées.
 * Usage : php migrations/run_migrate_produit_formulaire_sections_split.php
 */
require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/../models/model_produit_formulaire_champs.php';

if (!$db) {
    fwrite(STDERR, "Connexion BDD indisponible.\n");
    exit(1);
}

produit_formulaire_champs_ensure_schema();

$sql_file = __DIR__ . '/migrate_produit_formulaire_sections_split.sql';
if (!is_file($sql_file)) {
    fwrite(STDERR, "Fichier SQL introuvable.\n");
    exit(1);
}

$sql = file_get_contents($sql_file);
if ($sql === false || trim($sql) === '') {
    fwrite(STDERR, "Fichier SQL vide.\n");
    exit(1);
}

$statements = array_filter(array_map('trim', preg_split('/;\s*\n/', $sql)));
foreach ($statements as $stmt) {
    if ($stmt === '') {
        continue;
    }
    try {
        $db->exec($stmt);
        echo "+ OK: " . substr(str_replace(["\r", "\n"], ' ', $stmt), 0, 80) . "…\n";
    } catch (PDOException $e) {
        fwrite(STDERR, "Erreur : " . $e->getMessage() . "\n");
        exit(1);
    }
}

produit_formulaire_champs_sync_sections_systeme();
echo "Sections système synchronisées.\n";
