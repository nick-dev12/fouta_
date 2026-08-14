<?php
/**
 * Production VPS → environnement de DÉVELOPPEMENT (WAMP).
 *
 * Copie intégrale : base de données + dossier upload/.
 *
 * Prérequis :
 *   copier config/pull_prod_dev.example.php → config/pull_prod_dev.php
 *
 * Usage :
 *   scripts\pull_prod_to_dev.bat
 *   php scripts/pull_prod_to_dev.php
 *   php scripts/pull_prod_to_dev.php --db-only
 *   php scripts/pull_prod_to_dev.php --files-only
 *   php scripts/pull_prod_to_dev.php --dry-run
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI uniquement.\n");
    exit(1);
}

$root = dirname(__DIR__);
$config_path = $root . '/config/pull_prod_dev.php';
if (!is_file($config_path)) {
    fwrite(STDERR, "Créez config/pull_prod_dev.php depuis config/pull_prod_dev.example.php\n");
    exit(1);
}

require_once $root . '/includes/deploy_helpers.php';
require_once $root . '/includes/pull_prod_from_production.php';

deploy_init_realtime_output();
$cfg = require $config_path;
pull_prod_from_production_run($root, $cfg, $argv ?? [], 'développement WAMP');
