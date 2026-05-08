<?php
/**
 * Vide toutes les tables de la base (données uniquement, schéma conservé).
 * DANGER : irréversible.
 *
 * CLI  : php tools/empty_database.php --confirm
 * Web  : ouvrir …/tools/empty_database.php (formulaire) ou en GET :
 *        ?key=VOTRE_CLE&confirm=EMPTY_ALL
 */
declare(strict_types=1);

/**
 * Sortie erreurs : STDIN/STDERR ne sont pas des constantes en SAPI web (navigateur).
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
 * Lit un paramètre web (POST prioritaire sur GET).
 */
function tools_empty_db_web_input(string $name): string
{
    if (isset($_POST[$name])) {
        return trim((string) $_POST[$name]);
    }
    if (isset($_GET[$name])) {
        return trim((string) $_GET[$name]);
    }
    return '';
}

/**
 * @param string|null $erreur message à afficher (XSS échappé)
 */
function tools_empty_db_web_show_form(?string $erreur): void
{
    header('Content-Type: text/html; charset=utf-8');
    http_response_code($erreur !== null ? 403 : 200);
    $errHtml = $erreur !== null
        ? '<p style="color:#c00;background:#fee;padding:12px;border-radius:8px">' . htmlspecialchars($erreur, ENT_QUOTES, 'UTF-8') . '</p>'
        : '';
    $self = htmlspecialchars($_SERVER['SCRIPT_NAME'] ?? '', ENT_QUOTES, 'UTF-8');
    echo <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Vider la base (outils)</title>
<style>
body{font-family:system-ui,sans-serif;max-width:480px;margin:40px auto;padding:0 16px;color:#0D0D0D}
h1{font-size:1.15rem;color:#3564a6}
.warn{background:rgba(255,107,53,.12);border:1px solid #FF6B35;padding:12px;border-radius:8px;margin:16px 0;font-size:.9rem}
label{display:block;margin:16px 0 6px;font-weight:600}
input[type=password]{width:100%;max-width:100%;box-sizing:border-box;padding:10px;border:1px solid rgba(53,100,166,.2);border-radius:8px}
button{margin-top:20px;background:#3564a6;color:#fff;border:none;padding:12px 20px;border-radius:8px;font-weight:600;cursor:pointer}
button:hover{background:#2d5690}
</style>
</head>
<body>
<h1>Vider toutes les tables</h1>
<p class="warn"><strong>Attention :</strong> opération irréversible. Toutes les données seront supprimées (schéma conservé).</p>
{$errHtml}
<form method="post" action="{$self}">
  <label for="key">Clé (<code>tools/empty_database_web_key.php</code>)</label>
  <input id="key" name="key" type="password" autocomplete="off" required>
  <input type="hidden" name="confirm" value="EMPTY_ALL">
  <button type="submit">Confirmer et vider la base</button>
</form>
<p style="margin-top:24px;font-size:.85rem;color:#737373">Alternative : <code>?key=…&amp;confirm=EMPTY_ALL</code> en GET.</p>
</body>
</html>
HTML;
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
        tools_empty_db_err("  Supprime toutes les lignes de toutes les tables de la BDD (conn/conn.php).\n");
        exit(1);
    }
} else {
    $keyFile = __DIR__ . DIRECTORY_SEPARATOR . 'empty_database_web_key.php';
    if (!is_readable($keyFile)) {
        header('Content-Type: text/plain; charset=utf-8');
        http_response_code(503);
        echo "Clé web absente.\n";
        echo "Copiez tools/empty_database_web_key.example.php vers tools/empty_database_web_key.php\n";
        exit(1);
    }
    $loaded = require $keyFile;
    $secret = is_string($loaded) ? trim($loaded) : '';
    if ($secret === '') {
        header('Content-Type: text/plain; charset=utf-8');
        http_response_code(503);
        echo "empty_database_web_key.php doit retourner une chaîne non vide.\n";
        exit(1);
    }

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $k = tools_empty_db_web_input('key');
    $c = tools_empty_db_web_input('confirm');

    if ($method === 'GET' && $k === '' && $c === '') {
        tools_empty_db_web_show_form(null);
        exit(0);
    }

    if ($c !== 'EMPTY_ALL') {
        tools_empty_db_web_show_form('Confirmation invalide. Utilisez le formulaire ou ajoutez confirm=EMPTY_ALL dans l’URL.');
        exit(1);
    }
    if ($k === '') {
        tools_empty_db_web_show_form('Saisissez la clé secrète.');
        exit(1);
    }
    if (!hash_equals($secret, $k)) {
        tools_empty_db_web_show_form('Clé incorrecte.');
        exit(1);
    }

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
