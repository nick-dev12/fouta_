<?php
/**
 * Test connexion FTP production (CLI).
 * Usage : php scripts/test_ftp_production.php
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
$config_path = $root . '/config/deploy_wamp.php';
if (!is_file($config_path)) {
    fwrite(STDERR, "config/deploy_wamp.php introuvable.\n");
    exit(1);
}

$cfg = require $config_path;
$ftp = $cfg['production_files'] ?? [];

require_once $root . '/includes/deploy_helpers.php';

echo "=== Test FTP production ===\n";
echo 'Host : ' . ($ftp['host'] ?? '') . "\n";
echo 'User : ' . ($ftp['user'] ?? '') . "\n";
echo 'Path : ' . ($ftp['remote_path'] ?? '') . "\n";

if (!function_exists('ftp_connect')) {
    fwrite(STDERR, "Extension PHP ftp absente. Activez extension=ftp dans php.ini puis redémarrez WAMP.\n");
    exit(1);
}

$conn = deploy_ftp_connect($ftp);
if (!$conn) {
    fwrite(STDERR, "Connexion FTP échouée.\n");
    exit(1);
}

echo "Connexion : OK\n";
$pwd = @ftp_pwd($conn);
echo 'Répertoire courant : ' . ($pwd !== false ? $pwd : '(inconnu)') . "\n";

$remote = rtrim($ftp['remote_path'] ?? '', '/');
$list = @ftp_nlist($conn, $remote);
if (!is_array($list)) {
    fwrite(STDERR, "Impossible de lister $remote\n");
    ftp_close($conn);
    exit(1);
}

$dirs = [];
foreach ($list as $item) {
    $base = basename(str_replace('\\', '/', $item));
    if ($base === '.' || $base === '..') {
        continue;
    }
    $dirs[] = $base;
}
sort($dirs);
echo 'Dossiers dans upload/ : ' . implode(', ', array_slice($dirs, 0, 12));
if (count($dirs) > 12) {
    echo ' ... (' . count($dirs) . ' entrées)';
}
echo "\n";

$test_remote = $remote . '/logos';
$local = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fouta_ftp_test_' . getmypid() . '.png';
$files = @ftp_nlist($conn, $test_remote);
$sample = '';
if (is_array($files)) {
    foreach ($files as $f) {
        $b = basename(str_replace('\\', '/', $f));
        if ($b !== '.' && $b !== '..' && preg_match('/\.png$/i', $b)) {
            $sample = $test_remote . '/' . $b;
            break;
        }
    }
}

if ($sample !== '' && @ftp_get($conn, $local, $sample, FTP_BINARY) && is_file($local)) {
    echo 'Téléchargement test : OK (' . basename($sample) . ', ' . filesize($local) . " octets)\n";
    @unlink($local);
} else {
    echo "Téléchargement test : ignoré (aucun PNG dans logos/)\n";
}

ftp_close($conn);
echo "=== FTP prêt pour sync_full_refresh.bat ===\n";
