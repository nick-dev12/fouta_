<?php
/**
 * Mise à jour automatique du serveur entreprise (foutasvr).
 *
 * À exécuter SUR le serveur local : /var/www/fouta
 *
 * Usage :
 *   php scripts/update_entreprise_server.php
 *   php scripts/update_entreprise_server.php --dry-run
 *   php scripts/update_entreprise_server.php --all-migrations
 *   php scripts/update_entreprise_server.php --pull-prod
 *   php scripts/update_entreprise_server.php --sync-push
 *
 * Prérequis :
 *   - Git installé et dépôt configuré (scripts/install_git_entreprise.sh)
 *   - config/update_entreprise.php
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI uniquement.\n");
    exit(1);
}

$root = dirname(__DIR__);
$config_path = $root . '/config/update_entreprise.php';

require_once $root . '/includes/deploy_helpers.php';
require_once $root . '/includes/update_entreprise_helpers.php';

deploy_init_realtime_output();

$argv = $argv ?? [];
$dry_run = in_array('--dry-run', $argv, true);
$all_migrations = in_array('--all-migrations', $argv, true);
$pull_prod = in_array('--pull-prod', $argv, true);
$sync_push = in_array('--sync-push', $argv, true);
$skip_git = in_array('--skip-git', $argv, true);

if (!is_file($config_path)) {
    deploy_fail('Créez config/update_entreprise.php depuis config/update_entreprise.example.php');
}

$cfg = require $config_path;
$web_root = rtrim((string) ($cfg['web_root'] ?? $root), '/');

if (!is_dir($web_root)) {
    deploy_fail('web_root introuvable : ' . $web_root);
}

chdir($web_root);

$errors = 0;

deploy_log('=== Mise à jour serveur entreprise Fouta ===');
deploy_log('Dossier : ' . $web_root);
if ($dry_run) {
    deploy_log('Mode : DRY-RUN');
}

// --- 1. Vérifications préalables ---
deploy_log('');
deploy_log('--- 1/7 Vérifications ---');

if (!update_entreprise_check_command('git')) {
    deploy_log('Git absent — lancez : bash scripts/install_git_entreprise.sh');
    $errors++;
} else {
    deploy_log('Git : ' . trim((string) shell_exec('git --version')));
}

foreach (['php', 'composer', 'mysql'] as $cmd) {
    if (!update_entreprise_check_command($cmd)) {
        deploy_log('MANQUE : ' . $cmd);
        $errors++;
    } else {
        deploy_log('OK : ' . $cmd);
    }
}

$conn_file = $web_root . '/conn/conn.php';
if (!is_file($conn_file)) {
    deploy_log('MANQUE : conn/conn.php');
    $errors++;
} else {
    require_once $conn_file;
    global $db;
    if ($db instanceof PDO) {
        deploy_log('OK : connexion BDD locale');
    } else {
        deploy_log('ERREUR : conn/conn.php sans PDO');
        $errors++;
    }
}

if (!is_file($web_root . '/config/sync.php')) {
    deploy_log('WARN : config/sync.php absent (sync non testée)');
}

if ($errors > 0 && !$dry_run) {
    deploy_fail($errors . ' problème(s) bloquant(s) — corrigez avant de continuer.');
}

// --- 2. Git pull ---
deploy_log('');
deploy_log('--- 2/7 Git pull ---');

if ($skip_git) {
    deploy_log('Git ignoré (--skip-git)');
} else {
    $git = $cfg['git'] ?? [];
    $remote = $git['remote'] ?? 'origin';
    $branch = $git['branch'] ?? 'main';

    if (!is_dir($web_root . '/.git')) {
        deploy_log('WARN : pas de dépôt Git — lancez scripts/install_git_entreprise.sh');
    } else {
        update_entreprise_protect_configs($web_root, $cfg['protected_files'] ?? [], $dry_run);
        $pull_ok = update_entreprise_git_pull($web_root, $remote, $branch, $dry_run);
        if (!$pull_ok) {
            deploy_log('WARN : git pull échoué ou rien à mettre à jour');
        }
    }
}

// --- 3. Composer ---
deploy_log('');
deploy_log('--- 3/7 Composer ---');

$composer_cmd = 'composer install --no-dev --optimize-autoloader';
if ($dry_run) {
    deploy_log('(dry-run) ' . $composer_cmd);
} else {
    $res = deploy_run('cd ' . escapeshellarg($web_root) . ' && ' . $composer_cmd, false);
    if ($res['code'] !== 0) {
        deploy_log('ERREUR composer : ' . implode("\n", $res['output']));
        $errors++;
    } else {
        deploy_log('Composer : OK');
    }
}

// --- 4. Migrations ---
deploy_log('');
deploy_log('--- 4/7 Migrations base de données ---');

$migrations = $cfg['migrations_core'] ?? [];
if ($all_migrations) {
    deploy_log('Mode --all-migrations : découverte des scripts run_*.php');
    $migrations = update_entreprise_discover_migrations(
        $web_root,
        $cfg['migrations_exclude_auto'] ?? []
    );
}

foreach ($migrations as $rel) {
    $full = $web_root . '/' . str_replace('\\', '/', $rel);
    if (!is_file($full)) {
        deploy_log('SKIP (fichier absent) : ' . $rel);
        continue;
    }
    deploy_log('Migration : ' . $rel);
    if ($dry_run) {
        continue;
    }
    $php = PHP_BINARY ?: 'php';
    $res = deploy_run($php . ' ' . escapeshellarg($full), false);
    if ($res['code'] !== 0) {
        deploy_log('WARN migration code ' . $res['code'] . ' : ' . $rel);
        if (!empty($res['output'])) {
            deploy_log(implode("\n", array_slice($res['output'], -5)));
        }
    }
}

// --- 5. Permissions ---
deploy_log('');
deploy_log('--- 5/7 Permissions ---');

$perm = $cfg['permissions'] ?? [];
$owner = $perm['owner'] ?? 'fouta';
$group = $perm['group'] ?? 'www-data';
$upload = $web_root . '/upload';

if ($dry_run) {
    deploy_log("(dry-run) chown $owner:$group upload/");
} else {
    if (is_dir($upload)) {
        deploy_run('chown -R ' . escapeshellarg($owner) . ':' . escapeshellarg($group) . ' ' . escapeshellarg($web_root), false);
        deploy_run('chown -R www-data:www-data ' . escapeshellarg($upload), false);
        deploy_run('chmod -R ' . escapeshellarg($perm['upload_mode'] ?? '775') . ' ' . escapeshellarg($upload), false);
        deploy_log('Permissions upload/ : OK');
    }
}

// --- 6. Sync local → VPS ---
deploy_log('');
deploy_log('--- 6/7 Vérification synchronisation ---');

$sync_cfg = $cfg['sync'] ?? [];
$sync_enabled = !empty($sync_cfg['enabled']);

if (!$sync_enabled || !is_file($web_root . '/config/sync.php')) {
    deploy_log('Sync : ignorée (non configurée)');
} else {
    require_once $web_root . '/includes/sync_functions.php';

    if (!empty($sync_cfg['ping']) && !$dry_run) {
        deploy_log('Test ping API production...');
        try {
            $sync_config = sync_load_config();
            $ping = sync_remote_request('ping', [], $sync_config);
            if (!empty($ping['success'])) {
                deploy_log('Sync ping : OK — node ' . ($ping['node_id'] ?? '?')
                    . ', tables ' . ($ping['tables'] ?? '?'));
            } else {
                deploy_log('Sync ping : ÉCHEC — ' . json_encode($ping, JSON_UNESCAPED_UNICODE));
                $errors++;
            }
        } catch (Throwable $e) {
            deploy_log('Sync ping : ERREUR — ' . $e->getMessage());
            $errors++;
        }
    } elseif ($dry_run) {
        deploy_log('(dry-run) sync_test_ping.php');
    }

    if (!empty($sync_cfg['verify_tables']) && !$dry_run && isset($db) && $db instanceof PDO) {
        deploy_log('Vérification comptages sync_verify.php...');
        $verify_script = $web_root . '/scripts/sync_verify.php';
        if (is_file($verify_script)) {
            deploy_run(PHP_BINARY . ' ' . escapeshellarg($verify_script), false);
        }
    }

    if (($sync_push || !empty($sync_cfg['push_after_update'])) && !$dry_run) {
        deploy_log('Push sync local → VPS...');
        $push_script = $web_root . '/scripts/sync_local_to_vps.php';
        if (is_file($push_script)) {
            deploy_run(PHP_BINARY . ' ' . escapeshellarg($push_script), false);
        }
    }
}

// --- 7. Ré-import production (optionnel) ---
deploy_log('');
deploy_log('--- 7/7 Import production (optionnel) ---');

$pull_cfg = $cfg['pull_production'] ?? [];
$do_pull = $pull_prod || !empty($pull_cfg['enabled']);

if ($do_pull) {
    $pull_file = $web_root . '/' . ($pull_cfg['config_file'] ?? 'config/pull_prod_entreprise.php');
    if (!is_file($pull_file)) {
        deploy_log('ERREUR : ' . $pull_cfg['config_file'] . ' introuvable pour --pull-prod');
        $errors++;
    } else {
        deploy_log('Ré-import production (BDD + upload/) — ATTENTION : écrase le local');
        if ($dry_run) {
            deploy_log('(dry-run) pull_prod_to_entreprise.php');
        } else {
            require_once $web_root . '/includes/pull_prod_from_production.php';
            $pull_cfg_data = require $pull_file;
            pull_prod_from_production_run($web_root, $pull_cfg_data, [], 'serveur local entreprise');
        }
    }
} else {
    deploy_log('Import production : ignoré (utilisez --pull-prod pour forcer)');
}

// --- Apache ---
if (!empty($cfg['reload_apache']) && !$dry_run) {
    deploy_log('');
    deploy_log('Rechargement Apache...');
    deploy_run('sudo systemctl reload apache2', false);
}

// --- Résumé ---
deploy_log('');
deploy_log('=== Mise à jour terminée ===');
deploy_log('Site LAN      : http://192.168.1.196/');
deploy_log('Site Tailscale: http://100.78.39.100/');
deploy_log('Logs sync     : tail -f /var/log/fouta-sync.log');

if ($errors > 0) {
    deploy_log('ATTENTION : ' . $errors . ' avertissement(s)/erreur(s) — vérifiez les logs ci-dessus.');
    exit(1);
}

exit(0);
