<?php
/**
 * Worker export catalogue PDF (CLI ou HTTP fire-and-forget).
 * Usage CLI : php export-catalogue-pdf-worker.php JOB_ID TOKEN
 */
require_once __DIR__ . '/../../includes/export_catalogue_job.php';

$job_id = '';
$token = '';

if (PHP_SAPI === 'cli') {
    $job_id = isset($argv[1]) ? trim((string) $argv[1]) : '';
    $token = isset($argv[2]) ? trim((string) $argv[2]) : '';
} else {
    $job_id = trim($_GET['job'] ?? '');
    $token = trim($_GET['token'] ?? '');

    ignore_user_abort(true);
    if (function_exists('set_time_limit')) {
        @set_time_limit(0);
    }

    header('Content-Type: text/plain; charset=utf-8');
    echo 'OK';
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        if (ob_get_level() > 0) {
            @ob_end_flush();
        }
        @flush();
    }
}

if ($job_id === '' || $token === '') {
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "Usage: php export-catalogue-pdf-worker.php JOB_ID TOKEN\n");
        exit(1);
    }
    exit;
}

export_catalogue_job_run($job_id, $token);

if (PHP_SAPI === 'cli') {
    exit(0);
}