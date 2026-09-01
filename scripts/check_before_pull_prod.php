<?php
/**
 * Vérifications AVANT import production → serveur local entreprise.
 * À lancer SUR le serveur local (foutasvr) après avoir configuré pull_prod_entreprise.php
 *
 * CLI : php scripts/check_before_pull_prod.php
 *       php scripts/check_before_pull_prod.php --with-sync
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
$with_sync = in_array('--with-sync', $argv ?? [], true);
$errors = 0;
$warnings = 0;

function check_line($ok, $label, $detail = '') {
    global $errors, $warnings;
    if ($ok === true) {
        echo "[OK]      $label" . ($detail !== '' ? " — $detail" : '') . "\n";
        return;
    }
    if ($ok === 'warn') {
        $warnings++;
        echo "[WARN]    $label" . ($detail !== '' ? " — $detail" : '') . "\n";
        return;
    }
    $errors++;
    echo "[MANQUE]  $label" . ($detail !== '' ? " — $detail" : '') . "\n";
}

echo "\n=== Fouta — vérifications avant import production ===\n\n";

// --- Outils locaux ---
echo "--- Serveur local (foutasvr) ---\n";
foreach (['php', 'mysql', 'mysqldump', 'rsync', 'ssh', 'composer'] as $cmd) {
    $path = trim((string) shell_exec(PHP_OS_FAMILY === 'Windows' ? "where $cmd 2>nul" : "command -v $cmd 2>/dev/null"));
    check_line($path !== '', $cmd, $path !== '' ? basename($path) : 'non installé');
}

$exts = ['pdo_mysql', 'curl', 'json', 'mbstring', 'gd', 'zip'];
foreach ($exts as $ext) {
    check_line(extension_loaded($ext), "extension PHP $ext");
}

// --- Fichiers locaux ---
echo "\n--- Fichiers de configuration locaux ---\n";
check_line(is_file($root . '/conn/conn.php'), 'conn/conn.php');
check_line(is_dir($root . '/vendor'), 'vendor/ (composer install)');
check_line(is_file($root . '/includes/pull_prod_from_production.php'), 'includes/pull_prod_from_production.php');
check_line(is_file($root . '/scripts/pull_prod_to_entreprise.php'), 'scripts/pull_prod_to_entreprise.php');

$config_path = $root . '/config/pull_prod_entreprise.php';
check_line(is_file($config_path), 'config/pull_prod_entreprise.php', 'copier depuis pull_prod_entreprise.example.php');

if (!is_file($config_path)) {
    echo "\n=== Arrêt : créez config/pull_prod_entreprise.php d'abord ===\n\n";
    exit(1);
}

$cfg = require $config_path;
$prod_db = $cfg['production_db'] ?? [];
$prod_ssh = $cfg['production_ssh'] ?? [];
$prod_files = $cfg['production_files'] ?? [];
$target = $cfg['target'] ?? [];

// --- Config locale BDD ---
echo "\n--- Cible locale (target) ---\n";
$local_db = [
    'host' => $target['db_host'] ?? 'localhost',
    'name' => $target['db_name'] ?? '',
    'user' => $target['db_user'] ?? '',
    'pass' => $target['db_pass'] ?? '',
];
check_line($local_db['name'] !== '', 'db_name', $local_db['name'] ?: 'vide');
check_line($local_db['user'] !== '', 'db_user', $local_db['user'] ?: 'vide');
check_line($local_db['pass'] !== '' && !preg_match('/CHANGEZ|TODO/i', $local_db['pass']), 'db_pass', 'mot de passe renseigné');

try {
    $pdo = new PDO(
        'mysql:host=' . $local_db['host'] . ';dbname=' . $local_db['name'] . ';charset=utf8mb4',
        $local_db['user'],
        $local_db['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    check_line(true, 'Connexion BDD locale', $local_db['name'] . '@' . $local_db['host']);
} catch (Throwable $e) {
    check_line(false, 'Connexion BDD locale', $e->getMessage());
}

$web_root = rtrim((string) ($target['web_root'] ?? '/var/www/fouta'), '/');
check_line(is_dir($web_root), 'web_root', $web_root);
check_line(($target['site_url'] ?? '') !== '', 'site_url', $target['site_url'] ?? 'http://192.168.1.196');

// --- Production BDD ---
echo "\n--- Accès production (import BDD) ---\n";
$prod_host = $prod_db['host'] ?? '';
$prod_name = $prod_db['name'] ?? '';
$prod_user = $prod_db['user'] ?? '';
$prod_pass = $prod_db['pass'] ?? '';

check_line($prod_host !== '', 'production_db.host', $prod_host);
check_line($prod_name !== '', 'production_db.name', $prod_name);
check_line($prod_user !== '' && !preg_match('/CHANGEZ|TODO/i', $prod_user), 'production_db.user');
check_line($prod_pass !== '' && !preg_match('/CHANGEZ|TODO/i', $prod_pass), 'production_db.pass');

$db_ok = false;
if ($prod_host && $prod_name && $prod_user && $prod_pass && !preg_match('/CHANGEZ|TODO/i', $prod_pass)) {
    $cnf = sys_get_temp_dir() . '/fouta_check_prod_' . getmypid() . '.cnf';
    file_put_contents($cnf, "[client]\nhost=$prod_host\nport=" . (int) ($prod_db['port'] ?? 3306) . "\nuser=$prod_user\npassword=$prod_pass\n");
    chmod($cnf, 0600);
    $out = [];
    $code = 0;
    exec('mysql --defaults-extra-file=' . escapeshellarg($cnf) . ' -e ' . escapeshellarg('SELECT COUNT(*) AS n FROM information_schema.tables WHERE table_schema=' . escapeshellarg($prod_name)) . ' 2>&1', $out, $code);
    @unlink($cnf);
    if ($code === 0) {
        $db_ok = true;
        check_line(true, 'MySQL production distant (port 3306)', implode(' ', $out));
    } else {
        check_line('warn', 'MySQL production distant', 'inaccessible — repli SSH mysqldump sera utilisé');
        check_line(!empty($prod_ssh['host']) && !empty($prod_ssh['user']), 'production_ssh (repli dump)', ($prod_ssh['user'] ?? '') . '@' . ($prod_ssh['host'] ?? ''));
    }
}

// --- Production fichiers ---
echo "\n--- Accès production (dossier upload/) ---\n";
$upload_path = $prod_ssh['upload_path'] ?? '/home/jomas/foutapoidslourds.com/upload';
check_line($upload_path !== '', 'production_ssh.upload_path', $upload_path);

$ssh_host = $prod_ssh['host'] ?? ($prod_files['host'] ?? '');
$ssh_user = $prod_ssh['user'] ?? ($prod_files['user'] ?? '');
check_line($ssh_host !== '' && $ssh_user !== '', 'production_ssh', "$ssh_user@$ssh_host");

if ($ssh_host && $ssh_user) {
    $identity = $prod_ssh['identity_file'] ?? '';
    $port = (int) ($prod_ssh['port'] ?? 22);
    $ssh_cmd = 'ssh -p ' . $port . ' -o BatchMode=yes -o ConnectTimeout=10 -o StrictHostKeyChecking=accept-new ';
    if ($identity !== '' && is_readable($identity)) {
        $ssh_cmd .= '-i ' . escapeshellarg($identity) . ' ';
    }
    $ssh_cmd .= escapeshellarg("$ssh_user@$ssh_host") . ' ' . escapeshellarg('test -d ' . escapeshellarg($upload_path) . ' && echo UPLOAD_OK || echo UPLOAD_MISSING');
    $ssh_out = trim((string) shell_exec($ssh_cmd . ' 2>&1'));
    if (strpos($ssh_out, 'UPLOAD_OK') !== false) {
        check_line(true, 'Dossier upload/ sur VPS', $upload_path);
    } elseif (strpos($ssh_out, 'Permission denied') !== false || strpos($ssh_out, 'Host key verification failed') !== false) {
        check_line(false, 'SSH production', $ssh_out);
        check_line('warn', 'Astuce SSH', 'ssh-copy-id ' . $ssh_user . '@' . $ssh_host . ' depuis foutasvr');
    } else {
        check_line(false, 'Dossier upload/ sur VPS', $ssh_out ?: 'chemin introuvable — vérifiez upload_path');
    }
}

// --- Sync (optionnel, après import) ---
echo "\n--- Synchronisation local → VPS (après import) ---\n";
if (!$with_sync) {
    check_line('warn', 'Sync non testée', 'relancer avec --with-sync une fois config/sync.php créé');
} else {
    check_line(is_file($root . '/config/sync.php'), 'config/sync.php');
    check_line(is_file($root . '/sync/api.php'), 'sync/api.php (module présent dans le code)');
    if (is_file($root . '/config/sync.php')) {
        require_once $root . '/includes/sync_functions.php';
        try {
            $sync_cfg = sync_load_config();
            $token = $sync_cfg['remote_api_token'] ?? '';
            check_line(strlen($token) >= 32 && !preg_match('/CHANGEZ|TODO/i', $token), 'remote_api_token', strlen($token) . ' caractères');
            $result = sync_remote_request('ping', [], $sync_cfg);
            check_line(!empty($result['success']), 'API sync VPS (ping)', $sync_cfg['remote_url'] ?? '');
        } catch (Throwable $e) {
            check_line(false, 'API sync VPS', $e->getMessage());
            check_line('warn', 'VPS sync', 'php scripts/sync_check_install.php sur le VPS + migrations sync');
        }
    }
}

// --- Scripts import ---
echo "\n--- Scripts d'import (intégralité) ---\n";
$import_scripts = [
    'scripts/pull_prod_to_entreprise.sh' => 'Import complet BDD + upload/',
    'scripts/pull_prod_to_entreprise.php' => 'Moteur PHP import',
    'includes/pull_prod_from_production.php' => 'Dump prod + import local + rsync',
    'includes/deploy_helpers.php' => 'mysqldump, rsync, FTP, repli HTTP',
];
foreach ($import_scripts as $file => $desc) {
    check_line(is_file($root . '/' . $file), $file, $desc);
}

echo "\n--- Ce que l'import couvre ---\n";
echo "  • Base de données : dump COMPLET (toutes tables, triggers, routines)\n";
echo "  • Fichiers        : tout upload/ via FTP ou rsync SSH\n";
echo "  • Repli HTTP      : si FTP/rsync échouent (fichiers référencés en BDD)\n";
echo "  • site.php + .htaccess adaptés pour http://192.168.1.196\n";
echo "\n--- Après import (sync push local → VPS) ---\n";
echo "  php migrations/run_add_sync_columns.php\n";
echo "  php migrations/run_assign_sync_uuids.php\n";
echo "  php scripts/sync_test_ping.php\n";
echo "  php scripts/sync_local_to_vps.php\n";

echo "\n=== Résumé ===\n";
if ($errors === 0) {
    echo "Prêt pour l'import. Lancez :\n";
    echo "  php scripts/pull_prod_to_entreprise.php --dry-run\n";
    echo "  ./scripts/pull_prod_to_entreprise.sh\n\n";
    exit(0);
}
echo "$errors problème(s) bloquant(s), $warnings avertissement(s).\n";
echo "Corrigez config/pull_prod_entreprise.php et les accès VPS avant d'importer.\n\n";
exit(1);
