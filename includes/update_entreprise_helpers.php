<?php
/**
 * Helpers pour scripts/update_entreprise_server.php
 */

if (!function_exists('update_entreprise_check_command')) {
    function update_entreprise_check_command($cmd) {
        $which = PHP_OS_FAMILY === 'Windows'
            ? shell_exec('where ' . escapeshellarg($cmd) . ' 2>nul')
            : shell_exec('command -v ' . escapeshellarg($cmd) . ' 2>/dev/null');
        return trim((string) $which) !== '';
    }
}

if (!function_exists('update_entreprise_protect_configs')) {
    function update_entreprise_protect_configs($web_root, array $files, $dry_run) {
        foreach ($files as $rel) {
            $full = $web_root . '/' . str_replace('\\', '/', $rel);
            if (!is_file($full)) {
                continue;
            }
            if ($dry_run) {
                deploy_log('(dry-run) skip-worktree ' . $rel);
                continue;
            }
            $rel_q = escapeshellarg($rel);
            $cmd = 'cd ' . escapeshellarg($web_root) . ' && git update-index --skip-worktree ' . $rel_q . ' 2>/dev/null';
            shell_exec($cmd);
        }
        deploy_log('Fichiers protégés (skip-worktree) : ' . count(array_filter($files, function ($f) use ($web_root) {
            return is_file($web_root . '/' . str_replace('\\', '/', $f));
        })));
    }
}

if (!function_exists('update_entreprise_git_pull')) {
    function update_entreprise_git_pull($web_root, $remote, $branch, $dry_run) {
        $cd = 'cd ' . escapeshellarg($web_root);

        if ($dry_run) {
            deploy_log("(dry-run) git fetch $remote && git pull $remote $branch");
            return true;
        }

        deploy_log("git fetch $remote...");
        $fetch = deploy_run($cd . ' && git fetch ' . escapeshellarg($remote) . ' 2>&1', false);
        if ($fetch['code'] !== 0) {
            deploy_log('git fetch échoué : ' . implode("\n", $fetch['output']));
            return false;
        }

        $status_before = shell_exec($cd . ' && git rev-parse HEAD 2>/dev/null');
        deploy_log("git pull $remote $branch...");
        $pull = deploy_run($cd . ' && git pull ' . escapeshellarg($remote) . ' ' . escapeshellarg($branch) . ' 2>&1', false);
        $status_after = shell_exec($cd . ' && git rev-parse HEAD 2>/dev/null');

        if (!empty($pull['output'])) {
            foreach ($pull['output'] as $line) {
                deploy_log('  ' . $line);
            }
        }

        if ($status_before !== $status_after) {
            deploy_log('Git : mise à jour appliquée (' . trim(substr($status_after, 0, 8)) . ')');
        } else {
            deploy_log('Git : déjà à jour');
        }

        return $pull['code'] === 0 || stripos(implode("\n", $pull['output']), 'Already up to date') !== false;
    }
}

if (!function_exists('update_entreprise_discover_migrations')) {
    function update_entreprise_discover_migrations($web_root, array $exclude_basenames) {
        $dir = $web_root . '/migrations';
        $list = [];
        if (!is_dir($dir)) {
            return $list;
        }
        foreach (glob($dir . '/run_*.php') as $full) {
            $base = basename($full);
            if (in_array($base, $exclude_basenames, true)) {
                continue;
            }
            $list[] = 'migrations/' . $base;
        }
        sort($list);
        $core = [
            'migrations/run_migration_production_ajouts.php',
            'migrations/run_add_sync_columns.php',
            'migrations/run_assign_sync_uuids.php',
        ];
        return array_values(array_unique(array_merge($core, $list)));
    }
}
