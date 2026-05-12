<?php
/**
 * Colonnes adresse et plafond BL (cumul HT max) sur contacts.
 * php migrations/run_add_contacts_adresse_plafond_bl.php
 */
require_once __DIR__ . '/../conn/conn.php';

if (!$db) {
    fwrite(STDERR, "Connexion BDD impossible.\n");
    exit(1);
}

$cols = [
    'adresse' => "ALTER TABLE contacts ADD COLUMN adresse TEXT NULL AFTER email",
    'plafond_bl_cumul_ht' => "ALTER TABLE contacts ADD COLUMN plafond_bl_cumul_ht DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER adresse",
];

try {
    $db->exec('SET NAMES utf8mb4');
    $st = $db->query("SHOW COLUMNS FROM contacts");
    $existing = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $existing[$row['Field']] = true;
    }
    foreach ($cols as $name => $sql) {
        if (!empty($existing[$name])) {
            echo "— contacts.$name existe déjà\n";
            continue;
        }
        $db->exec($sql);
        echo "+ contacts.$name\n";
    }
} catch (PDOException $e) {
    $m = $e->getMessage();
    if (stripos($m, 'Duplicate column') !== false) {
        echo "— Colonnes déjà présentes.\n";
    } else {
        fwrite(STDERR, "Erreur : $m\n");
        exit(1);
    }
}
echo "Terminé.\n";
