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
 * Analyse upload/{cible}/ en un seul passage disque.
 *
 * @return array{
 *   pending:list<array{abs:string, subdir:string}>,
 *   orphans:list<array{abs:string, subdir:string, rel:string}>,
 *   counts:array{pending:int, orphans:int, webp:int, other:int}
 * }
 */
function image_optimizer_batch_scan($upload_root, $target_subdir) {
    $scan_dir = $upload_root . DIRECTORY_SEPARATOR . $target_subdir;
    $empty = [
        'pending' => [],
        'orphans' => [],
        'counts' => ['pending' => 0, 'orphans' => 0, 'webp' => 0, 'other' => 0],
    ];
    if (!is_dir($scan_dir)) {
        return $empty;
    }

    $skip_suffixes = ['_md', '_sm'];
    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
    $pending = [];
    $orphans = [];
    $counts = ['pending' => 0, 'orphans' => 0, 'webp' => 0, 'other' => 0];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($scan_dir, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file_info) {
        if (!$file_info->isFile()) {
            continue;
        }
        $abs = $file_info->getPathname();
        $ext = strtolower($file_info->getExtension());
        $base = pathinfo($abs, PATHINFO_FILENAME);

        foreach ($skip_suffixes as $suffix) {
            if (substr($base, -strlen($suffix)) === $suffix) {
                continue 2;
            }
        }

        if ($ext === 'webp') {
            $counts['webp']++;
            continue;
        }

        if (!in_array($ext, $allowed_ext, true)) {
            $counts['other']++;
            continue;
        }

        $rel = ltrim(str_replace('\\', '/', substr($abs, strlen($upload_root))), '/');
        $webp_abs = dirname($abs) . DIRECTORY_SEPARATOR . $base . '.webp';
        if (image_optimizer_file_exists($webp_abs)) {
            $orphans[] = ['abs' => $abs, 'subdir' => $target_subdir, 'rel' => $rel];
            $counts['orphans']++;
            continue;
        }

        $pending[] = ['abs' => $abs, 'subdir' => $target_subdir];
        $counts['pending']++;
    }

    usort($pending, function ($a, $b) {
        return strcmp($a['abs'], $b['abs']);
    });
    usort($orphans, function ($a, $b) {
        return strcmp($a['abs'], $b['abs']);
    });

    return [
        'pending' => $pending,
        'orphans' => $orphans,
        'counts' => $counts,
    ];
}

/**
 * @param PDO|null $db
 * @return array<string, int>
 */
function image_optimizer_batch_db_stats($db) {
    $stats = [
        'produits_total' => 0,
        'produits_avec_image' => 0,
        'produits_image_webp' => 0,
    ];
    if (!($db instanceof PDO)) {
        return $stats;
    }
    try {
        $stats['produits_total'] = (int) $db->query('SELECT COUNT(*) FROM produits')->fetchColumn();
        $stats['produits_avec_image'] = (int) $db->query(
            "SELECT COUNT(*) FROM produits WHERE image_principale IS NOT NULL AND TRIM(image_principale) != ''"
        )->fetchColumn();
        $stats['produits_image_webp'] = (int) $db->query(
            "SELECT COUNT(*) FROM produits WHERE image_principale LIKE '%.webp'"
        )->fetchColumn();
    } catch (PDOException $e) {
        // ignore
    }

    return $stats;
}

/**
 * @param PDO|null $db
 * @param string $cible
 * @return array<string, mixed>
 */
function image_optimizer_batch_overview($db, $cible = 'produits') {
    $upload_root = realpath(__DIR__ . '/../upload');
    if ($upload_root === false || !is_dir($upload_root)) {
        return ['success' => false, 'message' => 'Dossier upload/ introuvable.'];
    }

    $targets = [];
    $cible = trim((string) $cible, '/\\');
    if ($cible === '' || $cible === 'all') {
        $targets = image_optimizer_batch_allowed_targets();
    } else {
        $targets = [$cible];
    }

    $totals = ['pending' => 0, 'orphans' => 0, 'webp' => 0, 'other' => 0];
    foreach ($targets as $target_subdir) {
        if (!in_array($target_subdir, image_optimizer_batch_allowed_targets(), true)) {
            continue;
        }
        $scan = image_optimizer_batch_scan($upload_root, $target_subdir);
        foreach ($totals as $key => $val) {
            $totals[$key] += (int) ($scan['counts'][$key] ?? 0);
        }
    }

    return [
        'success' => true,
        'cible' => $cible !== '' ? $cible : 'all',
        'files' => $totals,
        'db' => image_optimizer_batch_db_stats($db),
    ];
}

/**
 * @param PDO|null $db
 * @param array<string, mixed> $options cible, limit, sync_db, mapping_log, cleanup_orphans
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
    $limit = (int) ($options['limit'] ?? 0);
    if ($limit < 0) {
        $limit = 0;
    }
    $sync_db = !empty($options['sync_db']);
    $cleanup_orphans = !array_key_exists('cleanup_orphans', $options) || !empty($options['cleanup_orphans']);
    $mapping_log = isset($options['mapping_log'])
        ? (string) $options['mapping_log']
        : (__DIR__ . '/../scripts/optimize_image_mapping.jsonl');

    $targets = [];
    if ($target === '' || $target === 'all') {
        $targets = image_optimizer_batch_allowed_targets();
    } else {
        $targets = [$target];
    }

    $all_pending = [];
    $all_orphans = [];
    $file_counts = ['pending' => 0, 'orphans' => 0, 'webp' => 0, 'other' => 0];
    foreach ($targets as $target_subdir) {
        if (!in_array($target_subdir, image_optimizer_batch_allowed_targets(), true)) {
            continue;
        }
        $scan = image_optimizer_batch_scan($upload_root, $target_subdir);
        foreach ($scan['pending'] as $item) {
            $all_pending[] = $item;
        }
        foreach ($scan['orphans'] as $item) {
            $all_orphans[] = $item;
        }
        foreach ($file_counts as $key => $val) {
            $file_counts[$key] += (int) ($scan['counts'][$key] ?? 0);
        }
    }

    $total_pending = count($all_pending);
    $total_orphans = count($all_orphans);
    $batch = ($limit > 0) ? array_slice($all_pending, 0, $limit) : $all_pending;

    $processed = 0;
    $skipped = 0;
    $orphans_removed = 0;
    $failed = 0;
    $saved_bytes = 0;
    $lines = [];
    $errors = [];
    $ops = 0;

    foreach ($batch as $item) {
        if ($limit > 0 && $ops >= $limit) {
            break;
        }
        $abs = $item['abs'];
        $target_subdir = $item['subdir'];
        $rel = ltrim(str_replace('\\', '/', substr($abs, strlen($upload_root))), '/');
        $dir_abs = dirname($abs);
        $rel_dir = dirname($rel);
        $rel_subdir = ($rel_dir === '.' ? '' : $rel_dir);
        $base = pathinfo($abs, PATHINFO_FILENAME);

        $expected_rel = function_exists('image_db_webp_equivalent_path')
            ? image_db_webp_equivalent_path($rel)
            : preg_replace('/\.[^.]+$/', '.webp', $rel);
        if ($expected_rel !== '' && image_optimizer_is_fully_optimized($expected_rel)) {
            @unlink($abs);
            $skipped++;
            $lines[] = "Ignoré (déjà optimisé) {$rel}";
            $ops++;
            continue;
        }

        $bytes_before = (int) filesize($abs);
        $result = image_optimizer_process_tmp($abs, $dir_abs, $rel_subdir, 'img_', $base);
        if (!empty($result['skipped_existing'])) {
            @unlink($abs);
            $skipped++;
            $lines[] = "Ignoré (déjà optimisé) {$rel}";
            $ops++;
            continue;
        }
        if (empty($result['success'])) {
            $failed++;
            $msg = (string) ($result['message'] ?? 'erreur');
            $errors[] = "{$rel} : {$msg}";
            $lines[] = "Échec {$rel} : {$msg}";
            $ops++;
            continue;
        }

        $new_rel = (string) ($result['relative_path'] ?? '');
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
        $ops++;
    }

    if ($cleanup_orphans && $total_orphans > 0) {
        $room = ($limit > 0) ? max(0, $limit - $ops) : $total_orphans;
        $orphan_batch = ($room > 0) ? array_slice($all_orphans, 0, $room) : [];
        foreach ($orphan_batch as $item) {
            if (@unlink($item['abs'])) {
                $orphans_removed++;
                $lines[] = 'Doublon supprimé ' . ($item['rel'] ?? '');
                $ops++;
            }
        }
    }

    $remaining_pending = max(0, $total_pending - $processed - $skipped);
    $remaining_orphans = max(0, $total_orphans - $orphans_removed);
    $has_more = ($limit > 0 && ($remaining_pending > 0 || ($cleanup_orphans && $remaining_orphans > 0)));
    $blocked_failures = ($processed === 0 && $skipped === 0 && $failed > 0 && $remaining_pending > 0);

    $sync = null;
    if ($sync_db && $db_ready && function_exists('image_db_sync_all_image_paths') && !$has_more) {
        $sync = image_db_sync_all_image_paths($db);
    }

    return [
        'success' => true,
        'cible' => $target,
        'processed' => $processed,
        'skipped' => $skipped,
        'orphans_removed' => $orphans_removed,
        'failed' => $failed,
        'saved_bytes' => $saved_bytes,
        'saved_kb' => round($saved_bytes / 1024, 1),
        'lines' => $lines,
        'errors' => $errors,
        'total_pending' => $total_pending,
        'remaining_pending' => $remaining_pending,
        'remaining_orphans' => $remaining_orphans,
        'total_orphans' => $total_orphans,
        'limit' => $limit,
        'has_more' => $has_more && !$blocked_failures,
        'blocked_failures' => $blocked_failures,
        'file_counts' => $file_counts,
        'db_stats' => image_optimizer_batch_db_stats($db),
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
