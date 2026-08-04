<?php
/**
 * Corrige le mojibake (ex. Ã‰tagÃ¨res → Étagères) après import BDD en mauvais encodage.
 * Usage : php migrations/run_fix_utf8_mojibake.php
 */
require_once __DIR__ . '/../conn/conn.php';

if (!$db) {
    fwrite(STDERR, "Connexion BDD impossible.\n");
    exit(1);
}

$db_name = $db->query('SELECT DATABASE()')->fetchColumn();
$db->exec("ALTER DATABASE `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

$columns = [
    ['entrepot_structure_champ', 'label'],
    ['entrepot_champ_element', 'nom'],
    ['entrepot_etage', 'nom'],
    ['entrepot_zone', 'nom'],
    ['entrepot_rayon', 'nom'],
    ['entrepot_etagere', 'nom'],
    ['entrepot_barre', 'nom'],
    ['entrepot_position', 'nom'],
    ['entrepot_hierarchie_noeud', 'nom'],
    ['entrepot_hierarchie_niveau', 'nom'],
    ['categories', 'nom'],
    ['categories', 'description'],
    ['produits', 'nom'],
    ['produits', 'description'],
    ['users', 'nom'],
    ['users', 'prenom'],
    ['admin', 'nom'],
    ['admin', 'prenom'],
];

$fix_sql = 'CONVERT(CAST(CONVERT(`%s` USING latin1) AS BINARY) USING utf8mb4)';
$total = 0;

foreach ($columns as [$table, $column]) {
    $check = $db->query("SHOW TABLES LIKE " . $db->quote($table));
    if (!$check || !$check->fetchColumn()) {
        continue;
    }
    $col_check = $db->query("SHOW COLUMNS FROM `$table` LIKE " . $db->quote($column));
    if (!$col_check || !$col_check->fetchColumn()) {
        continue;
    }

    $expr = sprintf($fix_sql, $column);
    $stmt = $db->prepare(
        "UPDATE `$table` SET `$column` = $expr WHERE `$column` LIKE '%Ã%' OR `$column` LIKE '%â€%'"
    );
    $stmt->execute();
    $n = $stmt->rowCount();
    if ($n > 0) {
        echo "$table.$column : $n ligne(s) corrigée(s)\n";
        $total += $n;
    }
}

echo $total > 0
    ? "Terminé — $total correction(s).\n"
    : "Aucune ligne mojibake détectée (ou déjà corrigée).\n";
