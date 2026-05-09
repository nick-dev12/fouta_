<?php
/**
 * Vide toutes les tables de la base (données uniquement, schéma conservé).
 *
 * DANGER : irréversible. En HTTP, une simple visite de l’URL exécute le vidage.
 * À utiliser uniquement en développement local. Ne jamais exposer ce fichier sur Internet.
 *
 * CLI : php tools/empty_database.php --confirm
 * Web : ouvrir …/tools/empty_database.php (aucune clé)
 */
declare(strict_types=1);

/**
 * Sortie erreurs : STDERR absent en SAPI web.
 */
function tools_empty_db_err(string $msg): void
{
    if (defined('STDERR') && STDERR) {
        fwrite(STDERR, $msg);
        return;
    }
    $e = @fopen('php://stderr', 'wb');
    if ($e !== false) {
        fwrite($e, $msg);
        fclose($e);
        return;
    }
    echo $msg;
}

/**
 * @return int code de sortie 0 = OK, 1 = erreur
 */
function tools_empty_database_run(PDO $db): int
{
    $sqlList = <<<'SQL'
SELECT TABLE_NAME
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_TYPE = 'BASE TABLE'
ORDER BY TABLE_NAME
SQL;

    $stmt = $db->query($sqlList);
    $tables = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_COLUMN) : false;
    if ($tables === false) {
        tools_empty_db_err("Impossible de lister les tables.\n");
        return 1;
    }

    if ($tables === []) {
        echo "Aucune table dans cette base.\n";
        return 0;
    }

    $db->exec('SET FOREIGN_KEY_CHECKS = 0');

    $truncated = 0;
    foreach ($tables as $table) {
        $name = (string) $table;
        $safe = str_replace('`', '``', $name);
        $db->exec('TRUNCATE TABLE `' . $safe . '`');
        $truncated++;
        echo "OK  TRUNCATE `$name`\n";
    }

    $db->exec('SET FOREIGN_KEY_CHECKS = 1');

    echo "\nVérification des comptes (tous doivent être 0)…\n";
    $errors = 0;
    foreach ($tables as $table) {
        $name = (string) $table;
        $safe = str_replace('`', '``', $name);
        $c = (int) $db->query('SELECT COUNT(*) FROM `' . $safe . '`')->fetchColumn();
        if ($c !== 0) {
            echo "ERREUR  `$name` : $c ligne(s)\n";
            $errors++;
        }
    }

    echo "\nTables vidées : $truncated / " . count($tables) . ".\n";
    if ($errors > 0) {
        tools_empty_db_err("Échec : $errors table(s) non vides.\n");
        return 1;
    }

    echo "Terminé : base vide, tout est OK.\n";
    return 0;
}

$isCli = PHP_SAPI === 'cli';

if ($isCli) {
    $argv = $_SERVER['argv'] ?? [];
    if (!in_array('--confirm', $argv, true)) {
        tools_empty_db_err("Usage : php tools/empty_database.php --confirm\n");
        exit(1);
    }
} else {
    header('Content-Type: text/plain; charset=utf-8');
}

$connPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'conn' . DIRECTORY_SEPARATOR . 'conn.php';
if (!is_readable($connPath)) {
    tools_empty_db_err("Fichier introuvable : $connPath\n");
    exit(1);
}

require_once $connPath;

if (!isset($db) || !($db instanceof PDO)) {
    tools_empty_db_err("Connexion PDO indisponible (vérifiez conn/conn.php).\n");
    exit(1);
}

/** @var PDO $db */
exit(tools_empty_database_run($db));
