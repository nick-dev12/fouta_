<?php
/**
 * Colonnes emplacement entrepôt détaillé sur produits.
 * php migrations/run_add_produits_emplacement_entrepot.php
 */
require_once __DIR__ . '/../conn/conn.php';

if (!$db) {
    fwrite(STDERR, "Connexion BDD impossible.\n");
    exit(1);
}

$colonnes = [
    'allee' => "TINYINT UNSIGNED NULL DEFAULT NULL COMMENT 'Allée entrepôt (1-10)'",
    'zone_emplacement' => "TINYINT UNSIGNED NULL DEFAULT NULL COMMENT 'Zone entrepôt (1-10)'",
    'position_emplacement' => "TINYINT UNSIGNED NULL DEFAULT NULL COMMENT 'Position dans le rayon (1-10)'",
    'barre_rayon' => "TINYINT UNSIGNED NULL DEFAULT NULL COMMENT 'Barre / étagère du rayon (1-10)'",
];

try {
    $db->exec('SET NAMES utf8mb4');
    $st = $db->query('SHOW COLUMNS FROM produits');
    $existing = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $existing[$row['Field']] = true;
    }

    $after = !empty($existing['numero_rayon']) ? 'numero_rayon' : (!empty($existing['etage']) ? 'etage' : 'unite');

    foreach ($colonnes as $col => $def) {
        if (!empty($existing[$col])) {
            echo "— produits.$col existe déjà\n";
            continue;
        }
        $db->exec("ALTER TABLE produits ADD COLUMN `$col` $def AFTER `$after`");
        echo "+ produits.$col\n";
        $existing[$col] = true;
        $after = $col;
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
