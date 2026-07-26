<?php
/**
 * Sync chemins BDD — redirige vers le runner web ou délègue au runner migrations (CLI).
 *
 * Navigateur : /migrations/run_sync_image_paths_database.php
 * CLI : php scripts/sync_image_paths_database.php
 */
if (PHP_SAPI !== 'cli') {
    header('Location: ../migrations/run_sync_image_paths_database.php');
    exit;
}

require __DIR__ . '/../migrations/run_sync_image_paths_database.php';
