<?php
/**
 * DÉMARRE le « tout détourer » en arrière-plan (03/09/2026).
 * Répond en JSON, puis lance le worker CLI qui fait le travail long.
 */

ob_start();
session_start();

require_once __DIR__ . '/../includes/require_access_json.php';
require_once __DIR__ . '/../../includes/admin_permissions.php';

$repondre = function (array $data, int $code = 200) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
};

// Réservé aux comptes non restreints (le rayonniste terrain ne lance pas de lot).
if (admin_is_restricted_admin_account()) {
    $repondre(['ok' => false, 'error' => 'Action réservée à un compte complet.'], 403);
}

// CSRF
$jeton = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
if (empty($_SESSION['admin_csrf']) || $jeton === '' || !hash_equals((string) $_SESSION['admin_csrf'], $jeton)) {
    $repondre(['ok' => false, 'error' => 'Jeton de sécurité invalide. Rechargez la page.'], 403);
}

$root = dirname(__DIR__, 2);
require $root . '/conn/conn.php'; // $db

$dir = $root . '/upload/detour_cache';
if (!is_dir($dir)) {
    @mkdir($dir, 0775, true);
}
$prog = $dir . '/_lot.json';

// Déjà en cours ? (battement récent) → on ne relance pas.
if (is_file($prog)) {
    $j = json_decode((string) @file_get_contents($prog), true);
    if (is_array($j) && empty($j['termine']) && isset($j['battement']) && (time() - (int) $j['battement']) < 30) {
        $repondre(['ok' => true, 'deja' => true, 'total' => (int) ($j['total'] ?? 0)]);
    }
}

$refaire = (isset($_POST['refaire']) && $_POST['refaire'] === '1') ? '1' : '0';

// Total attendu (dénominateur immédiat pour la barre).
try {
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $total = (int) $db->query(
        "SELECT COUNT(*) FROM produits
         WHERE sync_deleted_at IS NULL AND image_principale <> '' AND image_principale IS NOT NULL"
    )->fetchColumn();
} catch (Throwable $e) {
    $repondre(['ok' => false, 'error' => 'Base : ' . $e->getMessage()], 500);
}
if ($total <= 0) {
    $repondre(['ok' => false, 'error' => 'Aucune pièce avec photo à détourer.'], 400);
}

$token = bin2hex(random_bytes(8));

// Progression initiale (le worker la réécrira).
@file_put_contents($prog, json_encode([
    'token' => $token, 'total' => $total, 'fait' => 0, 'uni' => 0, 'charge' => 0,
    'absente' => 0, 'termine' => false, 'erreur' => null,
    'demarre' => time(), 'battement' => time(), 'ids_uni' => [],
], JSON_UNESCAPED_UNICODE));

// Lancer le worker CLI en tâche de fond.
if (!detour_lot_spawn_worker($token, $refaire)) {
    $repondre(['ok' => false, 'error' => "Impossible de lancer le traitement en arrière-plan (php CLI introuvable)."], 500);
}

$repondre(['ok' => true, 'token' => $token, 'total' => $total]);


/**
 * Trouve le binaire php en ligne de commande.
 * @return string|null
 */
function detour_lot_php_cli()
{
    foreach (['/usr/bin/php', '/usr/local/bin/php'] as $c) {
        if (@is_file($c)) {
            return $c;
        }
    }
    if (defined('PHP_BINDIR')) {
        foreach (['php', 'php.exe'] as $n) {
            $c = PHP_BINDIR . DIRECTORY_SEPARATOR . $n;
            if (@is_file($c)) {
                return $c;
            }
        }
    }
    $cmd = (strncasecmp(PHP_OS, 'WIN', 3) === 0 ? 'where php' : 'command -v php') . ' 2>/dev/null';
    $out = @trim((string) @shell_exec($cmd));
    if ($out !== '') {
        $out = preg_split('/\r?\n/', $out)[0];
        if (@is_file($out)) {
            return $out;
        }
    }
    return null;
}

/**
 * Lance le worker sans bloquer la réponse.
 * @return bool
 */
function detour_lot_spawn_worker($token, $refaire)
{
    $php = detour_lot_php_cli();
    if ($php === null) {
        return false;
    }
    $worker = realpath(__DIR__ . '/detourage-lot-worker.php');
    if ($worker === false) {
        return false;
    }
    $cmd = escapeshellarg($php) . ' ' . escapeshellarg($worker) . ' '
        . escapeshellarg((string) $token) . ' ' . escapeshellarg((string) $refaire);
    if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
        @pclose(@popen('cmd /c start "" /B ' . $cmd, 'r'));
        return true;
    }
    @exec($cmd . ' > /dev/null 2>&1 &');
    return true;
}
