<?php
$sql = file_get_contents(__DIR__ . '/../jomas_fouta_fixed.sql');
$map = [];

if (preg_match_all('/ALTER TABLE `([^`]+)`\s+((?:ADD CONSTRAINT[^;]+)+);/s', $sql, $blocks, PREG_SET_ORDER)) {
    foreach ($blocks as $block) {
        $table = $block[1];
        $body = $block[2];
        if (!preg_match_all(
            '/FOREIGN KEY \(`([^`]+)`\) REFERENCES `([^`]+)` \(`([^`]+)`\)/',
            $body,
            $fks,
            PREG_SET_ORDER
        )) {
            continue;
        }
        foreach ($fks as $fk) {
            $entry = [
                'COLUMN_NAME' => $fk[1],
                'REFERENCED_TABLE_NAME' => $fk[2],
                'REFERENCED_COLUMN_NAME' => $fk[3],
            ];
            $exists = false;
            foreach ($map[$table] ?? [] as $existing) {
                if ($existing['COLUMN_NAME'] === $entry['COLUMN_NAME']) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $map[$table][] = $entry;
            }
        }
    }
}

$out = "<?php\n/**\n * Registre FK statique (fallback si information_schema vide).\n * Généré depuis jomas_fouta_fixed.sql\n */\n\nif (!function_exists('sync_registry_static_foreign_keys')) {\n";
$out .= "    function sync_registry_static_foreign_keys(\$table) {\n";
$out .= '        static $map = ' . var_export($map, true) . ";\n";
$out .= "        return \$map[\$table] ?? [];\n";
$out .= "    }\n}\n";

file_put_contents(__DIR__ . '/../includes/sync_fk_static.php', $out);
echo count($map) . " tables\n";
foreach ($map['caisse_vente_lignes'] ?? [] as $fk) {
    echo $fk['COLUMN_NAME'] . ' -> ' . $fk['REFERENCED_TABLE_NAME'] . "\n";
}
