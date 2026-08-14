<?php
/**
 * Cœur commun : production VPS → machine locale (BDD + upload/).
 * Utilisé par pull_prod_to_dev.php et pull_prod_to_entreprise.php.
 */

function pull_prod_adapt_htaccess_for_http($htaccess_path, $dry_run) {
    if (!is_file($htaccess_path)) {
        return;
    }
    $content = file_get_contents($htaccess_path);
    $updated = preg_replace(
        '/^(RewriteCond %{HTTP_HOST} \^www\\\.e\\\.foutapoidslourds\\\.com\$ \[NC\]\s*$)/m',
        '#$1',
        $content
    );
    $updated = preg_replace(
        '/^(RewriteRule \^ https:\/\/e\.foutapoidslourds\.com)/m',
        '#$1',
        $updated
    );
    if ($updated === $content) {
        return;
    }
    deploy_log('Adaptation .htaccess (pas de redirection HTTPS production).');
    if (!$dry_run) {
        file_put_contents($htaccess_path, $updated);
    }
}

function pull_prod_write_site_php($site_php, $site_url, $dry_run) {
    $php = "<?php\nreturn [\n    'site_url' => " . var_export($site_url, true) . ",\n];\n";
    deploy_log('config/site.php → ' . $site_url);
    if (!$dry_run) {
        file_put_contents($site_php, $php);
    }
}

function pull_prod_target_db_cfg(array $target, $project_root) {
    return deploy_wamp_db_cfg([
        'mysql_bin' => $target['mysql_bin'] ?? '',
        'db_host' => $target['db_host'] ?? 'localhost',
        'db_name' => $target['db_name'] ?? '',
        'db_user' => $target['db_user'] ?? '',
        'db_pass' => $target['db_pass'] ?? null,
    ], $project_root);
}

/**
 * @param string $root Racine du projet
 * @param array $cfg Configuration pull_prod_*
 * @param array $argv Arguments CLI
 * @param string $label Libellé (Développement / Entreprise)
 */
function pull_prod_from_production_run($root, array $cfg, array $argv, $label) {
    $dry_run = in_array('--dry-run', $argv, true);
    $db_only = in_array('--db-only', $argv, true);
    $files_only = in_array('--files-only', $argv, true);

    $prod_db = $cfg['production_db'] ?? [];
    $prod_files = $cfg['production_files'] ?? [];
    $prod_ssh = $cfg['production_ssh'] ?? [];
    $target = $cfg['target'] ?? [];
    $opts = $cfg['options'] ?? [];
    $site_url_prod = $cfg['production_site_url'] ?? 'https://e.foutapoidslourds.com';

    $web_root = rtrim(str_replace('\\', '/', $target['web_root'] ?? $root), '/');
    $web_root_os = str_replace('/', DIRECTORY_SEPARATOR, $web_root);
    $local_upload = $web_root_os . DIRECTORY_SEPARATOR . 'upload';
    $local_site_url = rtrim((string) ($target['site_url'] ?? ''), '/');

    $tools = deploy_mysql_tools($target);
    $local_db = pull_prod_target_db_cfg($target, $root);

    deploy_log('=== Production → ' . $label . ' ===');
    deploy_log('Production : ' . $site_url_prod);
    deploy_log('Cible      : ' . ($local_site_url !== '' ? $local_site_url : $web_root_os));
    deploy_log('Dossier    : ' . $web_root_os);
    deploy_log('Base       : ' . ($local_db['name'] ?? '') . '@' . ($local_db['host'] ?? 'localhost'));
    if ($dry_run) {
        deploy_log('Mode       : DRY-RUN (aucune écriture)');
    }

    $dump_file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fouta_pull_' . date('Ymd_His') . '.sql';
    $local_pdo = null;

    if (!$files_only) {
        deploy_log('');
        deploy_log('--- Base de données ---');
        deploy_log('Export production (' . ($prod_db['name'] ?? '') . '@' . ($prod_db['host'] ?? '') . ')...');
        if (!$dry_run) {
            if (!deploy_dump_production($prod_db, $tools, [], $dump_file, false, $prod_ssh)) {
                deploy_fail('Export BDD production impossible (MySQL distant, SSH production).');
            }
            deploy_log_sql_dump_stats($dump_file, 'Dump production');
        }

        deploy_log('Import dans ' . ($local_db['name'] ?? '') . (empty($opts['recreate_database']) ? '' : ' (recréation complète)') . '...');
        $res = deploy_import_database(
            $local_db,
            $tools['mysql'],
            $dump_file,
            !empty($opts['recreate_database']),
            $dry_run
        );
        if (!$dry_run && $res['code'] !== 0) {
            deploy_fail('Import BDD : ' . implode("\n", $res['output']));
        }
        deploy_log('Base locale mise à jour.');

        if (!$dry_run) {
            try {
                $local_pdo = deploy_connect_wamp_db($local_db);
                $count = (int) $local_pdo->query('SELECT COUNT(*) FROM produits')->fetchColumn();
                deploy_log('Produits en base : ' . $count);
            } catch (Throwable $e) {
                deploy_log('Note : impossible de compter les produits.');
            }
        }
    }

    if (!$db_only && !empty($opts['sync_upload'])) {
        deploy_log('');
        deploy_log('--- Images upload/ ---');
        if (!$local_pdo instanceof PDO && !$dry_run) {
            try {
                $local_pdo = deploy_connect_wamp_db($local_db);
            } catch (Throwable $e) {
                $local_pdo = null;
            }
        }
        deploy_sync_upload_from_production(
            $prod_files,
            $local_upload,
            $opts,
            $dry_run,
            $local_pdo,
            $site_url_prod,
            $prod_ssh
        );
        if (!$dry_run) {
            deploy_log('Fichiers upload/ locaux : ' . deploy_count_files($local_upload));
        }
    }

    if (!empty($opts['write_site_php']) && $local_site_url !== '') {
        pull_prod_write_site_php($web_root_os . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'site.php', $local_site_url, $dry_run);
    }
    if (!empty($opts['adapt_htaccess_for_http'])) {
        pull_prod_adapt_htaccess_for_http($web_root_os . DIRECTORY_SEPARATOR . '.htaccess', $dry_run);
    }

    if (!$dry_run && is_file($dump_file)) {
        @unlink($dump_file);
    }

    deploy_log('');
    deploy_log('=== Terminé (' . $label . ') ===');
    if ($local_site_url !== '') {
        deploy_log('Site  : ' . $local_site_url . '/');
        deploy_log('Admin : ' . $local_site_url . '/admin/');
    }
}
