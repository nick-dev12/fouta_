<?php
/**
 * Conversion batch des images existantes (CLI et navigateur).
 */

require_once __DIR__ . '/image_optimizer.php';

/**
 * @return list<string>
 */
function image_optimizer_batch_allowed_targets() {
    return ['produits', 'slider'];
}

/**
 * @param string $upload_root
 * @param string $target_subdir
 * @return list<string> chemins absolus
 */
function image_optimizer_batch_list_source_files($upload_root, $target_subdir) {
    $scan_dir = $upload_root . DIRECTORY_SEPARATOR . $target_subdir;
    if (!is_dir($scan_dir)) {
        return [];
    }

    $skip_suffixes = ['_md', '_sm'];
    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
    $files = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($scan_dir, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file_info) {
        if (!$file_info->isFile()) {
            continue;
        }
        $abs = $file_info->getPathname();
        $ext = strtolower($file_info->getExtension());
        if (!in_array($ext, $allowed_ext, true)) {
            continue;
        }

        $base = pathinfo($abs, PATHINFO_FILENAME);
        foreach ($skip_suffixes as $suffix) {
            if (substr($base, -strlen($suffix)) === $suffix) {
                continue 2;
            }
        }

        $rel = ltrim(str_replace('\\', '/', substr($abs, strlen($upload_root))), '/');
        $webp_abs = dirname($abs) . DIRECTORY_SEPARATOR . $base . '.webp';
        if (is_file($webp_abs)) {
            continue;
        }

        $files[] = $abs;
    }

    sort($files, SORT_STRING);

    return $files;
}

/**
 * @param PDO|null $db
 * @param array<string, mixed> $options cible, offset, limit, sync_db, mapping_log
 * @return array<string, mixed>
 */
function image_optimizer_batch_run($db, $options = []) {
    $upload_root = realpath(__DIR__ . '/../upload');
    if ($upload_root === false || !is_dir($upload_root)) {
        return [
            'success' => false,
            'message' => 'Dossier upload/ introuvable.',
        ];
    }

    if (!image_optimizer_webp_available()) {
        return [
            'success' => false,
            'message' => 'WebP indisponible : activez GD avec imagewebp (php.ini).',
        ];
    }

    $db_ready = ($db instanceof PDO);
    if ($db_ready) {
        require_once __DIR__ . '/image_optimizer_db.php';
    }

    $target = isset($options['cible']) ? trim((string) $options['cible'], '/\\') : 'all';
    $offset = max(0, (int) ($options['offset'] ?? 0));
    $limit = (int) ($options['limit'] ?? 0);
    if ($limit < 0) {
        $limit = 0;
    }
    $sync_db = !empty($options['sync_db']);
    $mapping_log = isset($options['mapping_log'])
        ? (string) $options['mapping_log']
        : (__DIR__ . '/../scripts/optimize_image_mapping.jsonl');

    $targets = [];
    if ($target === '' || $target === 'all') {
        $targets = image_optimizer_batch_allowed_targets();
    } else {
        $targets = [$target];
    }

    $all_files = [];
    foreach ($targets as $target_subdir) {
        if (!in_array($target_subdir, image_optimizer_batch_allowed_targets(), true)) {
            continue;
        }
        foreach (image_optimizer_batch_list_source_files($upload_root, $target_subdir) as $abs) {
            $all_files[] = ['abs' => $abs, 'subdir' => $target_subdir];
        }
    }

    $total_pending = count($all_files);
    $batch = ($limit > 0) ? array_slice($all_files, $offset, $limit) : array_slice($all_files, $offset);

    $processed = 0;
    $skipped = 0;
    $failed = 0;
    $saved_bytes = 0;
    $lines = [];
    $errors = [];

    foreach ($batch as $item) {
        $abs = $item['abs'];
        $target_subdir = $item['subdir'];
        $rel = ltrim(str_replace('\\', '/', substr($abs, strlen($upload_root))), '/');
        $dir_abs = dirname($abs);
        $rel_dir = dirname($rel);
        $rel_subdir = ($rel_dir === '.' ? '' : $rel_dir);
        $base = pathinfo($abs, PATHINFO_FILENAME);

        $bytes_before = (int) filesize($abs);
        $result = image_optimizer_process_tmp($abs, $dir_abs, $rel_subdir, 'img_', $base);
        if (empty($result['success'])) {
            $failed++;
            $msg = (string) ($result['message'] ?? 'erreur');
            $errors[] = "{$rel} : {$msg}";
            $lines[] = "Échec {$rel} : {$msg}";
            continue;
        }

        $new_rel = (string) ($result['relative_path'] ?? '');
        $expected_rel = function_exists('image_db_webp_equivalent_path')
            ? image_db_webp_equivalent_path($rel)
            : preg_replace('/\.[^.]+$/', '.webp', $rel);
        $db_rel = ($expected_rel !== '') ? $expected_rel : $new_rel;

        if ($db_ready) {
            if ($target_subdir === 'slider') {
                $old_db = basename($rel);
                $new_db = basename($db_rel);
                if ($old_db !== '' && $new_db !== '' && $old_db !== $new_db) {
                    $log_line = json_encode(['old' => $old_db, 'new' => $new_db, 'scope' => 'slider'], JSON_UNESCAPED_UNICODE) . "\n";
                    @file_put_contents($mapping_log, $log_line, FILE_APPEND | LOCK_EX);
                    if (function_exists('image_db_replace_column_exact')) {
                        image_db_replace_column_exact($db, 'slider', 'image', $old_db, $new_db);
                    }
                }
            } elseif ($rel !== '' && $db_rel !== '' && $rel !== $db_rel) {
                $log_line = json_encode(['old' => $rel, 'new' => $db_rel], JSON_UNESCAPED_UNICODE) . "\n";
                @file_put_contents($mapping_log, $log_line, FILE_APPEND | LOCK_EX);
                if (function_exists('image_db_apply_path_mapping')) {
                    image_db_apply_path_mapping($db, $rel, $db_rel);
                }
            }
        }

        @unlink($abs);

        $processed++;
        $saved_bytes += max(0, $bytes_before - (int) ($result['bytes_after'] ?? $bytes_before));
        $lines[] = "OK {$rel} → {$db_rel}";
    }

    $next_offset = $offset + count($batch);
    $has_more = ($limit > 0 && $next_offset < $total_pending);

    $sync = null;
    if ($sync_db && $db_ready && function_exists('image_db_sync_all_image_paths') && !$has_more) {
        $sync = image_db_sync_all_image_paths($db);
    }

    return [
        'success' => true,
        'cible' => $target,
        'processed' => $processed,
        'skipped' => $skipped,
        'failed' => $failed,
        'saved_bytes' => $saved_bytes,
        'saved_kb' => round($saved_bytes / 1024, 1),
        'lines' => $lines,
        'errors' => $errors,
        'total_pending' => $total_pending,
        'offset' => $offset,
        'limit' => $limit,
        'next_offset' => $next_offset,
        'has_more' => $has_more,
        'sync' => $sync,
        'mapping_log' => $mapping_log,
    ];
}

/**
 * @param PDO $db
 * @return array<string, mixed>
 */
function image_optimizer_batch_sync_database($db) {
    require_once __DIR__ . '/image_optimizer_db.php';

    $db_name = function_exists('image_db_current_database')
        ? image_db_current_database($db)
        : '';

    $result = image_db_sync_all_image_paths($db);

    return [
        'success' => true,
        'database' => $db_name,
        'updated' => (int) ($result['updated'] ?? 0),
        'details' => $result['details'] ?? [],
    ];
}
