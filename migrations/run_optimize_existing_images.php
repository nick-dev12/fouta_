<?php
/**
 * Conversion WebP des images existantes (navigateur ou CLI).
 *
 * Navigateur (hébergement mutualisé) :
 *   /migrations/run_optimize_existing_images.php
 *   /migrations/run_optimize_existing_images.php?run=1&cible=produits
 *
 * CLI :
 *   php migrations/run_optimize_existing_images.php produits
 */
$is_cli = (PHP_SAPI === 'cli');

if ($is_cli) {
    require_once __DIR__ . '/../includes/image_optimizer_batch.php';

    $target = isset($argv[1]) ? trim((string) $argv[1], '/\\') : 'all';
    $db = null;
    $conn = __DIR__ . '/../conn/conn.php';
    if (is_file($conn)) {
        require_once $conn;
    }

    $result = image_optimizer_batch_run($db, [
        'cible' => $target,
        'limit' => 0,
        'sync_db' => true,
    ]);

    if (empty($result['success'])) {
        fwrite(STDERR, ($result['message'] ?? 'Erreur') . "\n");
        exit(1);
    }

    foreach ($result['lines'] as $line) {
        echo $line . "\n";
    }
    foreach ($result['errors'] as $err) {
        fwrite(STDERR, $err . "\n");
    }

    echo "\n=== Total ===\n";
    echo (int) $result['processed'] . ' optimisée(s), '
        . (int) $result['skipped'] . ' ignorée(s), '
        . (int) $result['orphans_removed'] . ' doublon(s) supprimé(s), '
        . (int) $result['failed'] . ' échec(s), '
        . ($result['saved_kb'] ?? 0) . " Ko économisés.\n";

    if (!empty($result['sync'])) {
        $sync = $result['sync'];
        echo "\nSynchronisation BDD : " . (int) ($sync['updated'] ?? 0) . " mise(s) à jour.\n";
        foreach ($sync['details'] ?? [] as $label => $count) {
            if ((int) $count > 0) {
                echo "  - {$label} : {$count}\n";
            }
        }
    }

    if ((int) ($result['processed'] ?? 0) > 0) {
        echo "\nJournal : " . ($result['mapping_log'] ?? '') . "\n";
    }

    exit((int) ($result['failed'] ?? 0) > 0 ? 1 : 0);
}

@set_time_limit(120);
@ini_set('memory_limit', '256M');

require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/../includes/migration_web_auth.php';
require_once __DIR__ . '/../includes/image_optimizer_batch.php';

migration_web_require_auth();

$cible = isset($_GET['cible']) ? trim((string) $_GET['cible'], '/\\') : '';
$run = isset($_GET['run']) || $cible !== '';
$limit = (int) ($_GET['limit'] ?? 20);
if ($limit <= 0) {
    $limit = 20;
}
if ($limit > 100) {
    $limit = 100;
}
$auto = !empty($_GET['auto']);

/**
 * @param array<string, mixed> $overview
 */
function migration_optimize_stats_html($overview) {
    if (empty($overview['success'])) {
        return '';
    }
    $db = $overview['db'] ?? [];
    $files = $overview['files'] ?? [];
    $html = '<div class="stats"><ul>';
    $html .= '<li><strong>' . (int) ($db['produits_total'] ?? 0) . '</strong> produits en base</li>';
    $html .= '<li><strong>' . (int) ($db['produits_avec_image'] ?? 0) . '</strong> avec image principale</li>';
    $html .= '<li><strong>' . (int) ($db['produits_image_webp'] ?? 0) . '</strong> chemins BDD déjà en .webp</li>';
    $html .= '<li><strong>' . (int) ($files['pending'] ?? 0) . '</strong> fichier(s) JPG/PNG/GIF à convertir</li>';
    $html .= '<li><strong>' . (int) ($files['webp'] ?? 0) . '</strong> fichier(s) WebP déjà présents sur le disque</li>';
    if ((int) ($files['orphans'] ?? 0) > 0) {
        $html .= '<li><strong>' . (int) $files['orphans'] . '</strong> doublon(s) (original + WebP) à nettoyer</li>';
    }
    $html .= '</ul>';
    $html .= '<p class="warn">Le compteur « à convertir » compte les <em>fichiers</em> sur le serveur, pas le nombre de produits. '
        . 'Un produit peut avoir plusieurs images ; beaucoup peuvent déjà être en WebP.</p></div>';

    return $html;
}

$db_ok = isset($db) && ($db instanceof PDO);
$overview_cible = ($cible !== '' ? $cible : 'produits');
$overview = $db_ok ? image_optimizer_batch_overview($db, $overview_cible) : ['success' => false];

if (!$run) {
    $qs = migration_web_preserve_query();
    $prefix = $qs !== '' ? '?' . $qs . '&' : '?';
    $body = '<h1>Optimisation images → WebP</h1>';
    $body .= '<p class="meta">Traitement par lots (' . (int) $limit . ' opérations max par requête) — les images déjà optimisées sont ignorées.</p>';
    $body .= migration_optimize_stats_html($overview);
    $body .= '<ul class="links">';
    $body .= '<li><a href="' . htmlspecialchars($prefix . 'run=1&cible=produits', ENT_QUOTES, 'UTF-8') . '">Convertir les images produits</a></li>';
    $body .= '<li><a href="' . htmlspecialchars($prefix . 'run=1&cible=slider', ENT_QUOTES, 'UTF-8') . '">Convertir les images slider</a></li>';
    $body .= '<li><a href="' . htmlspecialchars($prefix . 'run=1&cible=all', ENT_QUOTES, 'UTF-8') . '">Tout convertir (produits + slider)</a></li>';
    $body .= '<li><a href="run_sync_image_paths_database.php' . ($qs !== '' ? '?' . htmlspecialchars($qs, ENT_QUOTES, 'UTF-8') : '') . '">Synchroniser uniquement les chemins BDD</a></li>';
    $body .= '</ul>';
    $body .= '<p class="warn">Prérequis : extension PHP GD avec support WebP.</p>';
    migration_web_render_page('Optimisation images WebP', $body);
    exit;
}

if (!$db_ok) {
    migration_web_render_page('Erreur', '<h1>Erreur</h1><p class="err">Connexion PDO indisponible.</p>');
    exit;
}

$result = image_optimizer_batch_run($db, [
    'cible' => ($cible !== '' ? $cible : 'all'),
    'limit' => $limit,
    'sync_db' => true,
    'cleanup_orphans' => true,
]);

if (empty($result['success'])) {
    migration_web_render_page('Erreur', '<h1>Erreur</h1><p class="err">' . htmlspecialchars($result['message'] ?? 'Erreur', ENT_QUOTES, 'UTF-8') . '</p>');
    exit;
}

$lines_text = implode("\n", $result['lines']);
if ($lines_text === '' && empty($result['has_more'])) {
    $lines_text = '(Aucune image JPG/PNG/GIF en attente — tout est déjà optimisé.)';
}

$remaining = (int) ($result['remaining_pending'] ?? 0) + (int) ($result['remaining_orphans'] ?? 0);

$body = '<h1>Optimisation images → WebP</h1>';
$body .= '<p class="meta">Cible : <strong>' . htmlspecialchars((string) ($result['cible'] ?? 'all'), ENT_QUOTES, 'UTF-8') . '</strong>';
$body .= ' — <strong>' . $remaining . '</strong> opération(s) restante(s)</p>';

$db_stats = $result['db_stats'] ?? [];
$file_counts = $result['file_counts'] ?? [];
$body .= '<p class="meta">' . (int) ($db_stats['produits_total'] ?? 0) . ' produits · '
    . (int) ($file_counts['webp'] ?? 0) . ' WebP sur disque · '
    . (int) ($result['remaining_pending'] ?? 0) . ' à convertir · '
    . (int) ($result['remaining_orphans'] ?? 0) . ' doublon(s)</p>';

$body .= '<p class="ok">' . (int) $result['processed'] . ' optimisée(s), '
    . (int) $result['skipped'] . ' ignorée(s), '
    . (int) ($result['orphans_removed'] ?? 0) . ' doublon(s) supprimé(s), '
    . (int) $result['failed'] . ' échec(s), '
    . ($result['saved_kb'] ?? 0) . ' Ko économisés sur ce lot.</p>';

if (!empty($result['sync'])) {
    $sync = $result['sync'];
    $body .= '<p class="ok">Synchronisation BDD terminée : ' . (int) ($sync['updated'] ?? 0) . ' enregistrement(s) mis à jour.</p>';
}

if (!empty($result['blocked_failures'])) {
    $body .= '<p class="err">Des fichiers posent problème (format non supporté ou corrompu). Corrigez-les ou supprimez-les manuellement avant de continuer.</p>';
}

$body .= '<pre>' . htmlspecialchars($lines_text, ENT_QUOTES, 'UTF-8') . '</pre>';

if (!empty($result['errors'])) {
    $body .= '<pre class="err">' . htmlspecialchars(implode("\n", $result['errors']), ENT_QUOTES, 'UTF-8') . '</pre>';
}

$head_extra = '';
$body .= '<div class="actions">';
if (!empty($result['has_more'])) {
    $next_qs = migration_web_preserve_query([
        'run' => '1',
        'cible' => ($cible !== '' ? $cible : 'all'),
        'limit' => $limit,
        'offset' => null,
        'auto' => $auto ? '1' : null,
    ]);
    $next_url = 'run_optimize_existing_images.php?' . $next_qs;
    $body .= '<a href="' . htmlspecialchars($next_url, ENT_QUOTES, 'UTF-8') . '">Continuer le lot suivant</a>';
    if ($auto) {
        $head_extra = '<meta http-equiv="refresh" content="2;url=' . htmlspecialchars($next_url, ENT_QUOTES, 'UTF-8') . '">';
        $body .= '<span class="warn"> Reprise automatique dans 2 s…</span>';
    } else {
        $auto_qs = migration_web_preserve_query([
            'run' => '1',
            'cible' => ($cible !== '' ? $cible : 'all'),
            'limit' => $limit,
            'offset' => null,
            'auto' => '1',
        ]);
        $body .= '<a class="secondary" href="run_optimize_existing_images.php?' . htmlspecialchars($auto_qs, ENT_QUOTES, 'UTF-8') . '">Continuer automatiquement</a>';
    }
} else {
    $body .= '<a href="run_optimize_existing_images.php' . (migration_web_preserve_query() !== '' ? '?' . htmlspecialchars(migration_web_preserve_query(), ENT_QUOTES, 'UTF-8') : '') . '">Menu</a>';
    $sync_qs = migration_web_preserve_query();
    $body .= '<a class="secondary" href="run_sync_image_paths_database.php' . ($sync_qs !== '' ? '?' . htmlspecialchars($sync_qs, ENT_QUOTES, 'UTF-8') : '') . '">Resynchroniser la BDD</a>';
}
$body .= '</div>';

migration_web_render_page('Optimisation images WebP', $body, $head_extra);
