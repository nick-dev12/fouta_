<?php
/**
 * Production VPS → serveur LOCAL ENTREPRISE.
 *
 * Copie intégrale : base de données + dossier upload/.
 * À exécuter SUR le serveur de l’entreprise (Ubuntu / fplserver).
 *
 * Prérequis :
 *   copier config/pull_prod_entreprise.example.php → config/pull_prod_entreprise.php
 *
 * Usage :
 *   ./scripts/pull_prod_to_entreprise.sh
 *   php scripts/pull_prod_to_entreprise.php
 *   php scripts/pull_prod_to_entreprise.php --db-only
 *   php scripts/pull_prod_to_entreprise.php --files-only
 *   php scripts/pull_prod_to_entreprise.php --dry-run
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI uniquement.\n");
    exit(1);
}

$root = dirname(__DIR__);
$config_path = $root . '/config/pull_prod_entreprise.php';
if (!is_file($config_path)) {
    fwrite(STDERR, "Créez config/pull_prod_entreprise.php depuis config/pull_prod_entreprise.example.php\n");
    exit(1);
}

require_once $root . '/includes/deploy_helpers.php';
require_once $root . '/includes/pull_prod_from_production.php';

deploy_init_realtime_output();
$cfg = require $config_path;
pull_prod_from_production_run($root, $cfg, $argv ?? [], 'serveur local entreprise');
