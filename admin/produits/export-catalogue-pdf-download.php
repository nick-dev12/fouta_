<?php
/**
 * Téléchargement du PDF catalogue généré en arrière-plan.
 */

require_once __DIR__ . '/../../includes/admin_pdf_response.php';
admin_pdf_request_begin();

session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../includes/export_catalogue_job.php';

$job_id = trim($_GET['job'] ?? '');
$token = trim($_GET['token'] ?? '');

if ($job_id === '' || $token === '') {
    http_response_code(400);
    echo 'Paramètres invalides.';
    exit;
}

$job = export_catalogue_job_load($job_id);
if ($job === null) {
    http_response_code(404);
    echo 'Export introuvable.';
    exit;
}

if (!export_catalogue_job_belongs_to_admin($job, (int) $_SESSION['admin_id'])) {
    http_response_code(403);
    echo 'Accès refusé.';
    exit;
}

if (!export_catalogue_job_token_valid($job, $token)) {
    http_response_code(403);
    echo 'Jeton invalide.';
    exit;
}

if (($job['status'] ?? '') !== 'done') {
    http_response_code(409);
    echo 'Export non terminé.';
    exit;
}

$path = (string) ($job['pdf_path'] ?? '');
if ($path === '' || !is_file($path)) {
    http_response_code(404);
    echo 'Fichier PDF introuvable.';
    exit;
}

$binary = @file_get_contents($path);
if ($binary === false || $binary === '') {
    http_response_code(500);
    echo 'Lecture du PDF impossible.';
    exit;
}

$filename = (string) ($job['pdf_filename'] ?? 'catalogue-produits.pdf');
if (!admin_pdf_send_binary($binary, $filename)) {
    http_response_code(500);
    echo 'Envoi du PDF impossible.';
    exit;
}
