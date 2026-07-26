<?php
/**
 * Recompression batch — redirige vers le runner web ou délègue au runner migrations (CLI).
 *
 * Navigateur : /migrations/run_optimize_existing_images.php
 * CLI : php scripts/optimize_existing_images.php [produits|slider|all]
 */
if (PHP_SAPI !== 'cli') {
    header('Location: ../migrations/run_optimize_existing_images.php');
    exit;
}

$target = isset($argv[1]) ? trim((string) $argv[1], '/\\') : 'all';
$_SERVER['argv'] = ['run_optimize_existing_images.php', $target];
require __DIR__ . '/../migrations/run_optimize_existing_images.php';
