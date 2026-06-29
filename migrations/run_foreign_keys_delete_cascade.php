<?php
/**
 * Passe les clés étrangères RESTRICT / NO ACTION en ON DELETE CASCADE.
 * Permet de supprimer des lignes dans une table parent sans erreur MySQL #1451.
 *
 * Usage : php migrations/run_foreign_keys_delete_cascade.php
 *
 * TRUNCATE (vider une table) : MySQL refuse encore le TRUNCATE si une autre table
 * référence la table cible. Dans phpMyAdmin, décochez « Activer la vérification
 * des clés étrangères » avant un TRUNCATE, ou utilisez migrations/vider_base_donnees.sql
 */
require_once __DIR__ . '/../conn/conn.php';

if (!$db) {
    fwrite(STDERR, "Connexion BDD impossible.\n");
    exit(1);
}

/**
 * @return array<int, array{table:string,name:string,ref_table:string,update:string,cols:array,ref_cols:array}>
 */
function fk_list_restrict_no_action(PDO $db)
{
    $stmt = $db->query("
        SELECT rc.CONSTRAINT_NAME, rc.TABLE_NAME, rc.REFERENCED_TABLE_NAME,
               rc.DELETE_RULE, rc.UPDATE_RULE,
               kcu.COLUMN_NAME, kcu.REFERENCED_COLUMN_NAME, kcu.ORDINAL_POSITION
        FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS rc
        INNER JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
            ON rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA
            AND rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
            AND rc.TABLE_NAME = kcu.TABLE_NAME
        WHERE rc.CONSTRAINT_SCHEMA = DATABASE()
          AND rc.DELETE_RULE IN ('RESTRICT', 'NO ACTION')
        ORDER BY rc.TABLE_NAME, rc.CONSTRAINT_NAME, kcu.ORDINAL_POSITION
    ");

    $grouped = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $key = $row['TABLE_NAME'] . '|' . $row['CONSTRAINT_NAME'];
        if (!isset($grouped[$key])) {
            $grouped[$key] = [
                'table' => $row['TABLE_NAME'],
                'name' => $row['CONSTRAINT_NAME'],
                'ref_table' => $row['REFERENCED_TABLE_NAME'],
                'update' => $row['UPDATE_RULE'],
                'cols' => [],
                'ref_cols' => [],
            ];
        }
        $grouped[$key]['cols'][] = $row['COLUMN_NAME'];
        $grouped[$key]['ref_cols'][] = $row['REFERENCED_COLUMN_NAME'];
    }

    return array_values($grouped);
}

function fk_quote_ident($name)
{
    return '`' . str_replace('`', '``', $name) . '`';
}

function fk_alter_to_cascade(PDO $db, array $fk)
{
    $table = fk_quote_ident($fk['table']);
    $name = fk_quote_ident($fk['name']);
    $refTable = fk_quote_ident($fk['ref_table']);
    $cols = implode(', ', array_map('fk_quote_ident', $fk['cols']));
    $refCols = implode(', ', array_map('fk_quote_ident', $fk['ref_cols']));
    $updateRule = ($fk['update'] === 'NO ACTION') ? 'NO ACTION' : $fk['update'];

    $db->exec("ALTER TABLE {$table} DROP FOREIGN KEY {$name}");
    $db->exec("
        ALTER TABLE {$table}
        ADD CONSTRAINT {$name}
        FOREIGN KEY ({$cols}) REFERENCES {$refTable} ({$refCols})
        ON DELETE CASCADE ON UPDATE {$updateRule}
    ");
}

try {
    $db->exec('SET FOREIGN_KEY_CHECKS = 0');
    $fks = fk_list_restrict_no_action($db);

    if (empty($fks)) {
        echo "Aucune clé étrangère RESTRICT / NO ACTION à modifier.\n";
    } else {
        echo count($fks) . " contrainte(s) à passer en ON DELETE CASCADE :\n";
        foreach ($fks as $fk) {
            echo '  - ' . $fk['table'] . '.' . $fk['name'] . ' -> ' . $fk['ref_table'] . "\n";
            fk_alter_to_cascade($db, $fk);
            echo "    OK\n";
        }
    }

    $db->exec('SET FOREIGN_KEY_CHECKS = 1');
    echo "\nMigration terminée. Les DELETE sur une table parent suppriment désormais les lignes liées.\n";
} catch (PDOException $e) {
    try {
        $db->exec('SET FOREIGN_KEY_CHECKS = 1');
    } catch (PDOException $ignored) {
    }
    fwrite(STDERR, 'Erreur : ' . $e->getMessage() . "\n");
    exit(1);
}
