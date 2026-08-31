<?php
/**
 * Exécute l’export catalogue PDF (appel « fire-and-forget » du navigateur).
 *
 * Cette requête libère immédiatement le verrou de session afin que les
 * requêtes de statut puissent lire la progression en parallèle, puis
 * génère le PDF en arrière-plan (ignore_user_abort) même si l’utilisateur
 * change de page ou actualise.
 */

ob_start();

session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Non authentifié'], JSON_UNESCAPED_UNICODE);
    exit;
}

/* LE GARDE-BARRIÈRE (31/08/2026) : cette page ne demandait jamais si le
 * compte connecté avait le droit d'être là. La règle existe depuis
 * toujours dans includes/admin_route_access.php ; il manquait l'appel. */
require_once __DIR__ . '/../../includes/admin_route_access.php';
admin_route_enforce_json_empty();
require_once __DIR__ . '/../includes/require_access_json.php';
require_once __DIR__ . '/../../includes/export_catalogue_job.php';

$admin_id = (int) $_SESSION['admin_id'];

$source = array_merge($_GET, $_POST);
$job_id = trim($source['job'] ?? '');
$token = trim($source['token'] ?? '');

$job = ($job_id !== '' && $token !== '') ? export_catalogue_job_load($job_id) : null;
$valid = ($job !== null
    && export_catalogue_job_belongs_to_admin($job, $admin_id)
    && export_catalogue_job_token_valid($job, $token));

if (function_exists('session_write_close')) {
    session_write_close();
}

while (ob_get_level() > 0) {
    ob_end_clean();
}

$payload = json_encode(['ok' => $valid], JSON_UNESCAPED_UNICODE);
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

if (!$valid) {
    exit;
}

ignore_user_abort(true);
if (function_exists('set_time_limit')) {
    @set_time_limit(0);
}
if (function_exists('ini_set')) {
    @ini_set('memory_limit', '768M');
    @ini_set('display_errors', '0');
}

export_catalogue_job_run($job_id, $token);
exit;
