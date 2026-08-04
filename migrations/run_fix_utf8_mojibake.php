<?php
/**
 * Corrige les encodages corrompus après import BDD (mojibake + latin1).
 * Usage CLI : php migrations/run_fix_utf8_mojibake.php
 */
require_once __DIR__ . '/../conn/conn.php';

if (!$db) {
    fwrite(STDERR, "Connexion BDD impossible.\n");
    exit(1);
}

if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/plain; charset=UTF-8');
}

/**
 * Score de corruption (0 = texte propre).
 *
 * @param string|null $text
 * @return int
 */
function utf8_brokenness_score($text)
{
    if ($text === null || $text === '') {
        return 0;
    }
    $score = 0;
    if (!mb_check_encoding($text, 'UTF-8')) {
        $score += 1000;
    }
    $score += substr_count($text, 'Ã') * 10;
    $score += substr_count($text, 'â€') * 10;
    $score += substr_count($text, 'Ãƒ') * 5;
    return $score;
}

/**
 * Corrige mojibake simple ou multiple (ex. ÃƒÂ© → é, ExtÃÂ…©rieur → Extérieur).
 *
 * @param string|null $text
 * @return string|null
 */
function utf8_fix_text($text)
{
    if ($text === null || $text === '') {
        return $text;
    }

    $current = $text;
    if (!mb_check_encoding($current, 'UTF-8')) {
        $try = mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1');
        if ($try !== false && mb_check_encoding($try, 'UTF-8')) {
            $current = $try;
        }
    }

    $best = $current;
    $best_score = utf8_brokenness_score($best);

    for ($pass = 0; $pass < 12; $pass++) {
        if ($best_score === 0) {
            break;
        }

        $try = mb_convert_encoding($current, 'UTF-8', 'ISO-8859-1');
        if ($try === false || !mb_check_encoding($try, 'UTF-8') || $try === $current) {
            break;
        }

        $try_score = utf8_brokenness_score($try);
        if ($try_score < $best_score) {
            $best = $try;
            $best_score = $try_score;
        }

        $current = $try;
    }

    return $best;
}

/**
 * @param string $bytes
 * @return string
 */
function utf8_fix_from_binary($bytes)
{
    if ($bytes === '') {
        return '';
    }

    return utf8_fix_text($bytes);
}

/**
 * @param PDO $db
 * @param string $table
 * @param string $column
 * @param string $pk
 * @return array{fixed: int, skipped: int}
 */
function utf8_fix_table_column(PDO $db, $table, $column, $pk = 'id')
{
    $fixed = 0;
    $skipped = 0;

    $sql = "SELECT `$pk` AS _pk, CAST(`$column` AS BINARY) AS _raw
            FROM `$table`
            WHERE `$column` IS NOT NULL AND `$column` != ''";
    $stmt = $db->query($sql);
    if (!$stmt) {
        return ['fixed' => 0, 'skipped' => 0];
    }

    $update = $db->prepare("UPDATE `$table` SET `$column` = :val WHERE `$pk` = :pk");

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $id = $row['_pk'];
        $raw = (string) ($row['_raw'] ?? '');
        $new = utf8_fix_from_binary($raw);
        $old_score = utf8_brokenness_score($raw);
        $new_score = utf8_brokenness_score($new);

        if ($new === $raw || $new_score >= $old_score) {
            continue;
        }

        try {
            $update->execute(['val' => $new, 'pk' => $id]);
            if ($update->rowCount() > 0) {
                $fixed++;
            }
        } catch (PDOException $e) {
            echo "Ignoré $table.$column #$id : " . $e->getMessage() . "\n";
            $skipped++;
        }
    }

    return ['fixed' => $fixed, 'skipped' => $skipped];
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

$total_fixed = 0;
$total_skipped = 0;

foreach ($columns as [$table, $column]) {
    $check = $db->query("SHOW TABLES LIKE " . $db->quote($table));
    if (!$check || !$check->fetchColumn()) {
        continue;
    }
    $col_check = $db->query("SHOW COLUMNS FROM `$table` LIKE " . $db->quote($column));
    if (!$col_check || !$col_check->fetchColumn()) {
        continue;
    }

    $result = utf8_fix_table_column($db, $table, $column);
    $total_fixed += $result['fixed'];
    $total_skipped += $result['skipped'];

    if ($result['fixed'] > 0) {
        echo "$table.$column : {$result['fixed']} ligne(s) corrigée(s)\n";
    }
    if ($result['skipped'] > 0) {
        echo "$table.$column : {$result['skipped']} ligne(s) ignorée(s)\n";
    }
}

echo $total_fixed > 0
    ? "Terminé — $total_fixed correction(s)"
    : "Aucune correction nécessaire";

if ($total_skipped > 0) {
    echo " ($total_skipped ignorée(s))";
}
echo ".\n";
