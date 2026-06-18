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
    $json = json_encode($job, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) {
        return false;
    }

    return @file_put_contents($path, $json, LOCK_EX) !== false;
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
 * Envoie la réponse JSON au navigateur puis exécute l’export (sans worker externe).
 *
 * @param array<string, mixed> $job
 */
function export_catalogue_job_send_json_and_run(array $job) {
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

    ignore_user_abort(true);
    if (function_exists('set_time_limit')) {
        @set_time_limit(0);
    }
    if (function_exists('ini_set')) {
        @ini_set('memory_limit', '768M');
        @ini_set('display_errors', '0');
    }

    export_catalogue_job_run((string) $job['id'], (string) $job['token']);
    exit;
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
function export_catalogue_spawn_worker($job_id, $token) {
    $worker = realpath(__DIR__ . '/../admin/produits/export-catalogue-pdf-worker.php');
    if ($worker === false) {
        return export_catalogue_spawn_worker_http($job_id, $token);
    }

    $php = export_catalogue_php_binary();
    if ($php !== null) {
        $cmd = escapeshellarg($php) . ' ' . escapeshellarg($worker) . ' '
            . escapeshellarg($job_id) . ' ' . escapeshellarg($token);
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            @pclose(@popen('cmd /c start "" /B ' . $cmd, 'r'));

            return true;
        }
        @exec($cmd . ' > /dev/null 2>&1 &');

        return true;
    }

    return export_catalogue_spawn_worker_http($job_id, $token);
}

/**
 * Secours : lance le worker via HTTP (réponse coupée immédiatement).
 *
 * @return bool
 */
function export_catalogue_spawn_worker_http($job_id, $token) {
    require_once __DIR__ . '/site_url.php';
    $base = rtrim(get_site_base_url(), '/');
    if ($base === '') {
        return false;
    }

    $url = $base . '/admin/produits/export-catalogue-pdf-worker.php?job='
        . rawurlencode($job_id) . '&token=' . rawurlencode($token);

    $parts = parse_url($url);
    if ($parts === false || empty($parts['host'])) {
        return false;
    }

    $scheme = ($parts['scheme'] ?? 'http') === 'https' ? 'ssl://' : '';
    $host = $parts['host'];
    $port = isset($parts['port']) ? (int) $parts['port'] : (($parts['scheme'] ?? 'http') === 'https' ? 443 : 80);
    $path = ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');

    $fp = @fsockopen($scheme . $host, $port, $errno, $errstr, 3);
    if ($fp === false) {
        return false;
    }

    $req = "GET {$path} HTTP/1.1\r\nHost: {$host}\r\nConnection: Close\r\n\r\n";
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
    require_once __DIR__ . '/../conn/conn.php';
    require_once __DIR__ . '/export_produits_catalogue_pdf.php';

    $date_debut = (string) ($filters['date_debut'] ?? date('Y-m-d'));
    $date_fin = (string) ($filters['date_fin'] ?? date('Y-m-d'));
    $mode = (string) ($filters['mode'] ?? 'tous');
    $recherche = (string) ($filters['recherche'] ?? '');
    $categorie_id = (int) ($filters['categorie_id'] ?? 0);
    $marque_id = (int) ($filters['marque_id'] ?? 0);
    $fournisseur_id = (int) ($filters['fournisseur_id'] ?? 0);

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

    $has_fournisseur_filtre = produits_has_column('fournisseur_id');
    $fournisseur_nom = 'Tous les fournisseurs';
    if ($has_fournisseur_filtre && $fournisseur_id > 0) {
        require_once __DIR__ . '/../models/model_fournisseurs.php';
        $four = get_fournisseur_by_id($fournisseur_id);
        if ($four && !empty($four['nom'])) {
            $fournisseur_nom = (string) $four['nom'];
        }
    }

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
        'show_fournisseur_filtre' => $has_fournisseur_filtre,
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
    if (($job['status'] ?? '') === 'running') {
        return true;
    }
    if (($job['status'] ?? '') === 'done') {
        return true;
    }

    if (function_exists('set_time_limit')) {
        @set_time_limit(0);
    }
    if (function_exists('ini_set')) {
        @ini_set('memory_limit', '768M');
    }

    require_once __DIR__ . '/../conn/conn.php';
    require_once __DIR__ . '/export_produits_catalogue_pdf.php';
    require_once __DIR__ . '/../models/model_produits.php';

    export_catalogue_job_update_progress($job, 2, 'Démarrage de l’export…', 'running');

    $filters = is_array($job['filters'] ?? null) ? $job['filters'] : [];
    $meta = is_array($job['meta'] ?? null) ? $job['meta'] : export_catalogue_build_meta_from_filters($filters);
    $total = (int) ($meta['total'] ?? 0);

    if ($total <= 0) {
        export_catalogue_job_fail($job, 'Aucun produit à exporter.');
        return false;
    }
    if ($total > EXPORT_CATALOGUE_PDF_MAX) {
        export_catalogue_job_fail($job, 'Maximum ' . EXPORT_CATALOGUE_PDF_MAX . ' produits par export.');
        return false;
    }

    $progress_load = function ($loaded, $total_count) use (&$job) {
        $pct = 5 + (int) floor(35 * $loaded / max(1, $total_count));
        export_catalogue_job_update_progress(
            $job,
            $pct,
            'Chargement des produits (' . (int) $loaded . ' / ' . (int) $total_count . ')…',
            'running'
        );
    };

    $produits = get_admin_produits_export_catalogue_all(
        (string) ($filters['date_debut'] ?? ''),
        (string) ($filters['date_fin'] ?? ''),
        (string) ($filters['mode'] ?? 'tous'),
        (string) ($filters['recherche'] ?? ''),
        (int) ($filters['categorie_id'] ?? 0),
        (int) ($filters['marque_id'] ?? 0),
        (int) ($filters['fournisseur_id'] ?? 0),
        EXPORT_CATALOGUE_BATCH_SIZE,
        $progress_load
    );

    export_catalogue_job_update_progress($job, 45, 'Construction du document PDF…', 'running');

    $output_path = export_catalogue_job_pdf_output_path($job_id);
    $ok = export_catalogue_write_pdf_file($produits, $meta, $output_path, function ($pct, $msg) use (&$job) {
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
