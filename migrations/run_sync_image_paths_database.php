<?php
/**
 * Synchronise les chemins d'images en BDD (.jpg/.png → .webp si le fichier existe).
 *
 * Navigateur :
 *   /migrations/run_sync_image_paths_database.php
 *
 * CLI :
 *   php migrations/run_sync_image_paths_database.php
 */
$is_cli = (PHP_SAPI === 'cli');

require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/../includes/image_optimizer_batch.php';

if ($is_cli) {
    if (!isset($db) || !($db instanceof PDO)) {
        fwrite(STDERR, "Connexion PDO indisponible.\n");
        exit(1);
    }

    $result = image_optimizer_batch_sync_database($db);
    if ($result['database'] !== '') {
        echo 'Base connectée : ' . $result['database'] . "\n";
    }
    echo 'Synchronisation terminée : ' . (int) $result['updated'] . " enregistrement(s) mis à jour.\n";
    foreach ($result['details'] as $label => $count) {
        if ((int) $count > 0) {
            echo "  - {$label} : {$count}\n";
        }
    }
    exit(0);
}

require_once __DIR__ . '/../includes/migration_web_auth.php';
migration_web_require_auth();

if (!isset($db) || !($db instanceof PDO)) {
    migration_web_render_page('Erreur', '<h1>Erreur</h1><p class="err">Connexion PDO indisponible.</p>');
    exit;
}

$result = image_optimizer_batch_sync_database($db);

$lines = [];
if ($result['database'] !== '') {
    $lines[] = 'Base : ' . $result['database'];
}
$lines[] = 'Mises à jour : ' . (int) $result['updated'];
foreach ($result['details'] as $label => $count) {
    if ((int) $count > 0) {
        $lines[] = "  - {$label} : {$count}";
    }
}
if ((int) $result['updated'] === 0) {
    $lines[] = '(Aucun chemin à corriger.)';
}

$body = '<h1>Synchronisation chemins images (BDD)</h1>';
$body .= '<p class="ok">' . (int) $result['updated'] . ' enregistrement(s) mis à jour.</p>';
$body .= '<pre>' . htmlspecialchars(implode("\n", $lines), ENT_QUOTES, 'UTF-8') . '</pre>';
$body .= '<div class="actions">';
$menu_qs = migration_web_preserve_query();
$body .= '<a href="run_optimize_existing_images.php' . ($menu_qs !== '' ? '?' . htmlspecialchars($menu_qs, ENT_QUOTES, 'UTF-8') : '') . '">Retour optimisation WebP</a>';
$body .= '</div>';

migration_web_render_page('Sync chemins images BDD', $body);
