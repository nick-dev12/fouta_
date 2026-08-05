<?php
/**
 * Corrige le mojibake dans un export SQL phpMyAdmin avant réimport.
 *
 * Usage :
 *   php migrations/fix_sql_dump_file.php "jomas_fouta (1).sql"
 *   php migrations/fix_sql_dump_file.php input.sql output_fixed.sql
 */
require_once __DIR__ . '/includes/utf8_fix_functions.php';

if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/plain; charset=UTF-8');
}

$input = $argv[1] ?? '';
if ($input === '' || !is_file($input)) {
    fwrite(STDERR, "Usage : php migrations/fix_sql_dump_file.php <fichier.sql> [sortie.sql]\n");
    exit(1);
}

$output = $argv[2] ?? preg_replace('/\.sql$/i', '_fixed.sql', $input);
if ($output === $input) {
    $output = $input . '_fixed.sql';
}

/**
 * Échappe une chaîne pour un dump phpMyAdmin (quotes simples).
 *
 * @param string $value
 * @return string
 */
function sql_dump_escape_string($value)
{
    return str_replace(
        ["\\", "'", "\0", "\n", "\r", "\x1a"],
        ["\\\\", "\\'", "\\0", "\\n", "\\r", "\\Z"],
        $value
    );
}

/**
 * Corrige les littéraux '...' d'une ligne INSERT.
 *
 * @param string $line
 * @return array{line: string, fixes: int}
 */
function sql_fix_line_string_literals($line)
{
    $fixes = 0;
    $len = strlen($line);
    $out = '';
    $i = 0;

    while ($i < $len) {
        $ch = $line[$i];

        if ($ch !== "'") {
            $out .= $ch;
            $i++;
            continue;
        }

        $i++;
        $raw = '';
        while ($i < $len) {
            if ($line[$i] === '\\' && ($i + 1) < $len) {
                $raw .= $line[$i] . $line[$i + 1];
                $i += 2;
                continue;
            }
            if ($line[$i] === "'") {
                break;
            }
            $raw .= $line[$i];
            $i++;
        }

        $decoded = stripcslashes($raw);
        $fixed = utf8_fix_text($decoded);
        if ($fixed !== $decoded) {
            $fixes++;
            $out .= "'" . sql_dump_escape_string($fixed) . "'";
        } else {
            $out .= "'" . $raw . "'";
        }

        if ($i < $len && $line[$i] === "'") {
            $i++;
        }
    }

    return ['line' => $out, 'fixes' => $fixes];
}

function sql_line_is_data_line($line)
{
    $trim = ltrim($line);
    if ($trim === '') {
        return false;
    }
    if (stripos($trim, 'INSERT INTO') === 0) {
        return true;
    }
    return $trim[0] === '(';
}

$in = fopen($input, 'rb');
if (!$in) {
    fwrite(STDERR, "Impossible de lire : $input\n");
    exit(1);
}

$out = fopen($output, 'wb');
if (!$out) {
    fclose($in);
    fwrite(STDERR, "Impossible d'écrire : $output\n");
    exit(1);
}

$total_fixes = 0;
$line_no = 0;

while (($line = fgets($in)) !== false) {
    $line_no++;

    if (sql_line_is_data_line($line) && strpos($line, 'Ã') !== false) {
        $result = sql_fix_line_string_literals(rtrim($line, "\r\n"));
        $total_fixes += $result['fixes'];
        fwrite($out, $result['line'] . "\n");
    } else {
        fwrite($out, $line);
    }
}

fclose($in);
fclose($out);

echo "Fichier corrigé : $output\n";
echo "Chaînes corrigées : $total_fixes\n";
echo "Importez ce fichier dans phpMyAdmin (utf8mb4).\n";
