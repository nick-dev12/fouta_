<?php
/**
 * Jobs d’export catalogue PDF en arrière-plan (fichiers JSON + worker CLI/HTTP).
 */

if (!defined('EXPORT_CATALOGUE_ASYNC_MIN')) {
    define('EXPORT_CATALOGUE_ASYNC_MIN', 50);
}
if (!defined('EXPORT_CATALOGUE_BATCH_SIZE')) {
    define('EXPORT_CATALOGUE_BATCH_SIZE', 200);
}
if (!defined('EXPORT_CATALOGUE_PDF_MAX')) {
    define('EXPORT_CATALOGUE_PDF_MAX', 5000);
}

require_once __DIR__ . '/../conn/conn.php';

/**
 * Vérifie que la connexion PDO globale est disponible.
 */
function export_catalogue_require_db() {
    global $db;
    if (!isset($db) || !($db instanceof PDO)) {
        throw new RuntimeException('Connexion à la base de données impossible.');
    }
}

/**
 * @return string
 */
function export_catalogue_jobs_dir() {
    $dir = __DIR__ . '/../upload/temp/export_catalogue_jobs';
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return '';
        }
    }
    $files = $dir . '/files';
    if (!is_dir($files)) {
        @mkdir($files, 0755, true);
    }

    return is_dir($dir) && is_writable($dir) ? $dir : '';
}

/**
 * @return string
 */
function export_catalogue_job_last_setup_error() {
    $dir = __DIR__ . '/../upload/temp/export_catalogue_jobs';
    if (!is_dir(dirname($dir))) {
        return 'Le dossier upload/temp/ est inaccessible. Vérifiez les droits d’écriture.';
    }
    if (!is_dir($dir) || !is_writable($dir)) {
        return 'Impossible d’écrire dans upload/temp/export_catalogue_jobs/. Vérifiez les permissions.';
    }

    return 'Impossible d’enregistrer la tâche d’export.';
}

/**
 * @return string
 */
function export_catalogue_job_file_path($job_id) {
    $job_id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $job_id);
    $dir = export_catalogue_jobs_dir();
    if ($dir === '') {
        return '';
    }

    return $dir . '/' . $job_id . '.json';
}

/**
 * @param array<string, mixed> $job
 * @return bool
 */
function export_catalogue_job_save(array $job) {
    if (empty($job['id'])) {
        return false;
    }
    $job['updated_at'] = time();
    $path = export_catalogue_job_file_path($job['id']);
    if ($path === '') {
        return false;
    }
    $json = json_encode($job, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return false;
    }

    $tmp = $path . '.' . getmypid() . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (@file_put_contents($tmp, $json) === false) {
        return false;
    }

    return @rename($tmp, $path);
}

/**
 * @return array<string, mixed>|null
 */
function export_catalogue_job_load($job_id) {
    $path = export_catalogue_job_file_path($job_id);
    if (!is_file($path)) {
        return null;
    }
    $raw = @file_get_contents($path);
    if ($raw === false || $raw === '') {
        return null;
    }
    $data = json_decode($raw, true);

    return is_array($data) ? $data : null;
}

/**
 * @param int $admin_id
 * @param array<string, mixed> $filters
 * @param array<string, mixed> $meta
 * @return array<string, mixed>|null
 */
function export_catalogue_job_create($admin_id, array $filters, array $meta) {
    if (export_catalogue_jobs_dir() === '') {
        return null;
    }

    export_catalogue_job_cleanup_old();

    $job_id = 'exp_' . bin2hex(random_bytes(12));
    $token = bin2hex(random_bytes(16));
    $slug_debut = preg_replace('/[^0-9]/', '', (string) ($filters['date_debut'] ?? ''));
    $slug_fin = preg_replace('/[^0-9]/', '', (string) ($filters['date_fin'] ?? ''));
    $pdf_name = 'catalogue-produits-' . $slug_debut . '-' . $slug_fin . '-' . substr($job_id, -8) . '.pdf';

    $job = [
        'id' => $job_id,
        'token' => $token,
        'admin_id' => (int) $admin_id,
        'status' => 'queued',
        'progress' => 0,
        'message' => 'En attente de démarrage…',
        'filters' => $filters,
        'meta' => $meta,
        'total' => (int) ($meta['total'] ?? 0),
        'pdf_filename' => $pdf_name,
        'pdf_path' => '',
        'error' => '',
        'created_at' => time(),
        'updated_at' => time(),
    ];

    if (!export_catalogue_job_save($job)) {
        return null;
    }

    return $job;
}

/**
 * @param array<string, mixed> $job
 * @param int $progress
 * @param string $message
 * @param string|null $status
 */
function export_catalogue_job_update_progress(array &$job, $progress, $message, $status = null) {
    $job['progress'] = max(0, min(100, (int) $progress));
    $job['message'] = (string) $message;
    if ($status !== null) {
        $job['status'] = (string) $status;
    }
    export_catalogue_job_save($job);
}

/**
 * @param array<string, mixed> $job
 * @param string $error
 */
function export_catalogue_job_fail(array &$job, $error) {
    $job['status'] = 'failed';
    $job['progress'] = 0;
    $job['message'] = 'Échec de l’export';
    $job['error'] = (string) $error;
    export_catalogue_job_save($job);
}

/**
 * @param array<string, mixed> $job
 */
function export_catalogue_job_complete(array &$job) {
    $job['status'] = 'done';
    $job['progress'] = 100;
    $job['message'] = 'PDF prêt au téléchargement';
    export_catalogue_job_save($job);
}

/**
 * @return string
 */
function export_catalogue_job_pdf_output_path($job_id) {
    $dir = export_catalogue_jobs_dir();
    if ($dir === '') {
        return '';
    }
    $files = $dir . '/files';
    if (!is_dir($files)) {
        @mkdir($files, 0755, true);
    }

    return $files . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $job_id) . '.pdf';
}

/**
 * @return bool
 */
function export_catalogue_job_is_cancelled($job_id) {
    $job = export_catalogue_job_load($job_id);

    return is_array($job) && (($job['status'] ?? '') === 'cancelled');
}

/**
 * @return bool
 */
function export_catalogue_job_cancel($job_id, $token, $admin_id) {
    $job = export_catalogue_job_load($job_id);
    if ($job === null || !export_catalogue_job_belongs_to_admin($job, $admin_id)) {
        return false;
    }
    if (!export_catalogue_job_token_valid($job, $token)) {
        return false;
    }
    if (($job['status'] ?? '') === 'done') {
        return true;
    }
    $job['status'] = 'cancelled';
    $job['progress'] = 0;
    $job['message'] = 'Export annulé';
    $job['error'] = '';

    return export_catalogue_job_save($job);
}

/**
 * Envoie la réponse JSON de démarrage (sans bloquer sur l’export).
 *
 * @param array<string, mixed> $job
 */
function export_catalogue_job_send_json_only(array $job) {
    $payload = json_encode([
        'ok' => true,
        'job_id' => $job['id'],
        'token' => $job['token'],
        'total' => (int) ($job['total'] ?? 0),
        'async' => true,
    ], JSON_UNESCAPED_UNICODE);

    if ($payload === false) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Erreur encodage JSON.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    if (function_exists('session_write_close')) {
        session_write_close();
    }

    header('Content-Type: application/json; charset=utf-8');
    header('Content-Length: ' . strlen($payload));
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Connection: close');
    echo $payload;

    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        @flush();
    }
}

/**
 * @deprecated Utiliser spawn + send_json_only + wait_or_run
 * @param array<string, mixed> $job
 */
function export_catalogue_job_send_json_and_run(array $job) {
    export_catalogue_spawn_worker((string) $job['id'], (string) $job['token']);
    export_catalogue_job_send_json_only($job);

    ignore_user_abort(true);
    if (function_exists('set_time_limit')) {
        @set_time_limit(0);
    }
    if (function_exists('ini_set')) {
        @ini_set('memory_limit', '768M');
        @ini_set('display_errors', '0');
    }

    export_catalogue_job_wait_or_run((string) $job['id'], (string) $job['token']);
    exit;
}

/**
 * Attend que le worker démarre ; sinon exécute l’export dans ce processus (secours production).
 */
function export_catalogue_job_wait_or_run($job_id, $token, $max_wait_ms = 4000) {
    $step_us = 200000;
    $attempts = max(1, (int) ceil($max_wait_ms / ($step_us / 1000)));

    for ($i = 0; $i < $attempts; $i++) {
        usleep($step_us);
        $job = export_catalogue_job_load($job_id);
        if ($job === null) {
            return;
        }
        $status = (string) ($job['status'] ?? '');
        if (in_array($status, ['running', 'done', 'failed', 'cancelled'], true)) {
            return;
        }
    }

    $job = export_catalogue_job_load($job_id);
    if ($job === null) {
        return;
    }
    if (($job['status'] ?? '') === 'queued') {
        export_catalogue_job_run($job_id, $token);
    }
}

/**
 * @return bool
 */
function export_catalogue_job_belongs_to_admin(array $job, $admin_id) {
    return (int) ($job['admin_id'] ?? 0) === (int) $admin_id;
}

/**
 * @return bool
 */
function export_catalogue_job_token_valid(array $job, $token) {
    return hash_equals((string) ($job['token'] ?? ''), (string) $token);
}

/**
 * @return string|null
 */
function export_catalogue_php_binary() {
    if (defined('PHP_BINARY') && PHP_BINARY !== '' && is_file(PHP_BINARY)) {
        return PHP_BINARY;
    }
    if (defined('PHP_BINDIR')) {
        $win = PHP_BINDIR . DIRECTORY_SEPARATOR . 'php.exe';
        if (is_file($win)) {
            return $win;
        }
        $nix = PHP_BINDIR . DIRECTORY_SEPARATOR . 'php';
        if (is_file($nix)) {
            return $nix;
        }
    }

    return null;
}

/**
 * @return bool
 */
function export_catalogue_spawn_worker_cli($job_id, $token) {
    $worker = realpath(__DIR__ . '/../admin/produits/export-catalogue-pdf-worker.php');
    if ($worker === false) {
        return false;
    }

    $php = export_catalogue_php_binary();
    if ($php === null) {
        return false;
    }

    $cmd = escapeshellarg($php) . ' ' . escapeshellarg($worker) . ' '
        . escapeshellarg($job_id) . ' ' . escapeshellarg($token);
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        @pclose(@popen('cmd /c start "" /B ' . $cmd, 'r'));

        return true;
    }
    @exec($cmd . ' > /dev/null 2>&1 &');

    return true;
}

/**
 * @return bool
 */
function export_catalogue_spawn_worker($job_id, $token) {
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        if (export_catalogue_spawn_worker_cli($job_id, $token)) {
            return true;
        }

        return export_catalogue_spawn_worker_http($job_id, $token);
    }

    if (export_catalogue_spawn_worker_http($job_id, $token)) {
        return true;
    }

    return export_catalogue_spawn_worker_cli($job_id, $token);
}

/**
 * Secours : lance le worker via HTTP (réponse coupée immédiatement).
 *
 * @return bool
 */
function export_catalogue_spawn_worker_http($job_id, $token) {
    require_once __DIR__ . '/site_url.php';

    $base_url = rtrim(get_site_base_url(), '/');
    $root_path = rtrim(get_public_root_uri_path(), '/');
    $worker_path = $root_path . '/admin/produits/export-catalogue-pdf-worker.php?job='
        . rawurlencode($job_id) . '&token=' . rawurlencode($token);

    if ($base_url !== '') {
        $url = $base_url . $worker_path;
        if (export_catalogue_spawn_worker_http_request($url)) {
            return true;
        }
    }

    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($host === '') {
        return false;
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $url = $scheme . '://' . $host . $worker_path;

    return export_catalogue_spawn_worker_http_request($url);
}

/**
 * @return bool
 */
function export_catalogue_spawn_worker_http_request($url) {
    $parts = parse_url($url);
    if ($parts === false || empty($parts['host'])) {
        return false;
    }

    $hosts = [];
    $hosts[] = $parts['host'];
    if ($parts['host'] !== '127.0.0.1' && $parts['host'] !== 'localhost') {
        $hosts[] = '127.0.0.1';
    }

    foreach ($hosts as $host) {
        $try = $parts;
        $try['host'] = $host;
        if (export_catalogue_spawn_worker_http_request_once($try)) {
            return true;
        }
    }

    return false;
}

/**
 * @param array<string, mixed> $parts
 * @return bool
 */
function export_catalogue_spawn_worker_http_request_once(array $parts) {
    $scheme = ($parts['scheme'] ?? 'http') === 'https' ? 'ssl://' : '';
    $host = (string) $parts['host'];
    $port = isset($parts['port']) ? (int) $parts['port'] : (($parts['scheme'] ?? 'http') === 'https' ? 443 : 80);
    $path = ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');

    $errno = 0;
    $errstr = '';
    $fp = @stream_socket_client($scheme . $host . ':' . $port, $errno, $errstr, 8);
    if ($fp === false) {
        return false;
    }

    stream_set_timeout($fp, 3);
    $host_header = $_SERVER['HTTP_HOST'] ?? $host;
    $req = "GET {$path} HTTP/1.1\r\nHost: {$host_header}\r\nConnection: Close\r\n\r\n";
    @fwrite($fp, $req);
    @fclose($fp);

    return true;
}

/**
 * Supprime les jobs de plus de 24 h.
 */
function export_catalogue_job_cleanup_old() {
    $dir = export_catalogue_jobs_dir();
    if (!is_dir($dir)) {
        return;
    }
    $cutoff = time() - 86400;
    foreach (glob($dir . '/*.json') ?: [] as $file) {
        if (@filemtime($file) !== false && filemtime($file) < $cutoff) {
            $raw = @file_get_contents($file);
            @unlink($file);
            if ($raw !== false) {
                $data = json_decode($raw, true);
                if (is_array($data) && !empty($data['pdf_path']) && is_file($data['pdf_path'])) {
                    @unlink($data['pdf_path']);
                }
            }
        }
    }
    $files_dir = $dir . '/files';
    if (is_dir($files_dir)) {
        foreach (glob($files_dir . '/*.pdf') ?: [] as $pdf) {
            if (@filemtime($pdf) !== false && filemtime($pdf) < $cutoff) {
                @unlink($pdf);
            }
        }
    }
}

/**
 * Construit les métadonnées PDF à partir des filtres GET.
 *
 * @param array<string, mixed> $filters
 * @return array<string, mixed>
 */
function export_catalogue_build_meta_from_filters(array $filters) {
    export_catalogue_require_db();
    require_once __DIR__ . '/export_produits_catalogue_pdf.php';

    $parsed = export_catalogue_filters_from_request($filters);
    $date_debut = $parsed['date_debut'];
    $date_fin = $parsed['date_fin'];
    $mode = $parsed['mode'];
    $recherche = $parsed['recherche'];
    $categorie_id = $parsed['categorie_id'];
    $marque_id = $parsed['marque_id'];
    $fournisseur_id = 0;
    $has_prix_achat = export_catalogue_has_prix_achat_column();
    $pdf_cols = export_catalogue_pdf_parse_selected_columns($filters, $has_prix_achat);

    require_once __DIR__ . '/../models/model_categories.php';

    $categorie_nom = 'Toutes les catégories';
    if ($categorie_id > 0) {
        $cat = get_categorie_by_id($categorie_id);
        if ($cat && !empty($cat['nom'])) {
            $categorie_nom = (string) $cat['nom'];
        }
    }

    require_once __DIR__ . '/../models/model_produits.php';
    $has_marque_filtre = produits_has_column('marque_id');
    $marque_nom = 'Toutes les marques';
    if ($has_marque_filtre && $marque_id > 0) {
        require_once __DIR__ . '/../models/model_marques.php';
        if (marques_table_ok()) {
            $marque = get_marque_by_id($marque_id);
            if ($marque && !empty($marque['nom'])) {
                $marque_nom = (string) $marque['nom'];
            }
        }
    }

    $has_fournisseur_filtre = false;
    $fournisseur_nom = 'Tous les fournisseurs';

    $total = count_admin_produits_export_catalogue(
        $date_debut,
        $date_fin,
        $mode,
        $recherche,
        $categorie_id,
        $marque_id,
        $fournisseur_id
    );

    return [
        'date_debut' => $date_debut,
        'date_fin' => $date_fin,
        'mode' => $mode,
        'mode_label' => export_catalogue_pdf_mode_label($mode),
        'recherche' => $recherche,
        'total' => $total,
        'categorie_nom' => $categorie_nom,
        'marque_nom' => $marque_nom,
        'fournisseur_nom' => $fournisseur_nom,
        'show_categorie_filtre' => true,
        'show_marque_filtre' => $has_marque_filtre,
        'show_fournisseur_filtre' => false,
        'pdf_cols' => $pdf_cols,
    ];
}

/**
 * Exécute un job d’export (worker).
 *
 * @return bool
 */
function export_catalogue_job_run($job_id, $token) {
    $job = export_catalogue_job_load($job_id);
    if ($job === null || !export_catalogue_job_token_valid($job, $token)) {
        return false;
    }
    $current_status = (string) ($job['status'] ?? '');
    if ($current_status === 'running' || $current_status === 'done') {
        return true;
    }
    if ($current_status === 'cancelled') {
        return false;
    }

    // Revendique la tâche immédiatement pour éviter une double exécution
    // (un worker serveur et l’appel navigateur peuvent arriver en parallèle).
    export_catalogue_job_update_progress($job, max(1, (int) ($job['progress'] ?? 0)), 'Démarrage de l’export…', 'running');

    if (function_exists('set_time_limit')) {
        @set_time_limit(0);
    }
    if (function_exists('ini_set')) {
        @ini_set('memory_limit', '768M');
    }

    export_catalogue_require_db();
    require_once __DIR__ . '/export_produits_catalogue_pdf.php';
    require_once __DIR__ . '/export_catalogue_suivi.php';
    require_once __DIR__ . '/../models/model_produits.php';

    export_catalogue_job_update_progress($job, 2, 'Préparation des données…', 'running');

    $filters = is_array($job['filters'] ?? null) ? $job['filters'] : [];
    $meta = is_array($job['meta'] ?? null) ? $job['meta'] : export_catalogue_build_meta_from_filters($filters);
    $prix_draft = [];
    if (isset($meta['prix_draft']) && is_array($meta['prix_draft'])) {
        $prix_draft = export_catalogue_prix_draft_normalize($meta['prix_draft']);
    }
    $total = (int) ($meta['total'] ?? 0);

    if ($total <= 0) {
        export_catalogue_job_fail($job, 'Aucun produit à exporter.');
        return false;
    }
    if ($total > EXPORT_CATALOGUE_PDF_MAX) {
        export_catalogue_job_fail($job, 'Maximum ' . EXPORT_CATALOGUE_PDF_MAX . ' produits par export.');
        return false;
    }

    $progress_load = function ($loaded, $total_count) use (&$job, $job_id) {
        if (export_catalogue_job_is_cancelled($job_id)) {
            export_catalogue_job_fail($job, 'Export annulé.');
            throw new RuntimeException('cancelled');
        }
        $pct = 5 + (int) floor(35 * $loaded / max(1, $total_count));
        export_catalogue_job_update_progress(
            $job,
            $pct,
            'Chargement des produits (' . (int) $loaded . ' / ' . (int) $total_count . ')…',
            'running'
        );
    };

    try {
        $produits = export_catalogue_load_produits_for_pdf($filters, $prix_draft, $progress_load);
    } catch (RuntimeException $e) {
        if ($e->getMessage() === 'cancelled') {
            return false;
        }
        throw $e;
    }

    if (export_catalogue_job_is_cancelled($job_id)) {
        export_catalogue_job_fail($job, 'Export annulé.');
        return false;
    }

    $loaded = count($produits);
    if ($loaded <= 0) {
        export_catalogue_job_fail($job, 'Aucun produit à exporter.');
        return false;
    }
    if ($total > 0 && $loaded < $total) {
        export_catalogue_job_fail(
            $job,
            'Chargement incomplet des produits (' . $loaded . ' / ' . $total . '). Réessayez l’export.'
        );
        return false;
    }

    $meta['total'] = $loaded;

    export_catalogue_job_update_progress($job, 42, 'Construction du document PDF…', 'running');

    $output_path = export_catalogue_job_pdf_output_path($job_id);
    $ok = export_catalogue_write_pdf_file($produits, $meta, $output_path, function ($pct, $msg) use (&$job, $job_id) {
        if (export_catalogue_job_is_cancelled($job_id)) {
            return;
        }
        export_catalogue_job_update_progress($job, $pct, $msg, 'running');
    });

    if (!$ok) {
        export_catalogue_job_fail($job, export_catalogue_pdf_get_last_error() ?: 'Génération PDF impossible.');
        if (is_file($output_path)) {
            @unlink($output_path);
        }
        return false;
    }

    $job['pdf_path'] = $output_path;
    export_catalogue_job_complete($job);

    return true;
}
