<?php
/**
 * Diagnostic installation sync (à lancer sur le VPS en SSH).
 * CLI : php scripts/sync_check_install.php
 */

$root = dirname(__DIR__);
$checks = [];

$checks[] = ['Fichier sync/api.php', is_file($root . '/sync/api.php')];
$checks[] = ['Fichier config/sync.php', is_file($root . '/config/sync.php')];
$checks[] = ['Fichier conn/conn.php', is_file($root . '/conn/conn.php')];
$checks[] = ['includes/sync_functions.php', is_file($root . '/includes/sync_functions.php')];

echo "=== Diagnostic sync — racine : $root ===\n\n";

foreach ($checks as $check) {
    echo ($check[1] ? '[OK] ' : '[MANQUANT] ') . $check[0] . "\n";
}

if (is_file($root . '/config/sync.php')) {
    $config = require $root . '/config/sync.php';
    echo "\nnode_id : " . ($config['node_id'] ?? '—') . "\n";
    echo "remote_url : " . ($config['remote_url'] ?? '—') . "\n";
}

if (is_file($root . '/conn/conn.php')) {
    require_once $root . '/conn/conn.php';
    global $db;
    if ($db instanceof PDO) {
        try {
            $db->query('SELECT 1 FROM sync_log LIMIT 1');
            echo "\n[OK] Table sync_log accessible\n";
        } catch (Throwable $e) {
            echo "\n[WARN] sync_log : " . $e->getMessage() . "\n";
        }
    }
}

echo "\n=== Test local API (CLI) ===\n";
if (is_file($root . '/sync/api.php')) {
    echo "Le fichier existe sur le disque.\n";
    echo "Si le ping HTTP renvoie 404, ce dossier n'est PAS le DocumentRoot Apache.\n";
    echo "Comparez avec : grep -R DocumentRoot /etc/apache2/ 2>/dev/null\n";
    echo "Copiez sync/ vers la vraie racine web du domaine.\n";
}

echo "\nURL publique attendue :\n";
echo "https://e.foutapoidslourds.com/sync/api.php?action=ping\n";
