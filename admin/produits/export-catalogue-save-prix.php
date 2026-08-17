<?php
/**
 * Enregistrement des prix modifiés depuis le suivi catalogue (page courante).
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../models/model_produits.php';
require_once __DIR__ . '/../../includes/export_produits_catalogue_pdf.php';
require_once __DIR__ . '/../../includes/export_catalogue_suivi.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: export-catalogue.php');
    exit;
}

$redirect_query = isset($_POST['redirect_query']) ? (string) $_POST['redirect_query'] : '';
$target = 'export-catalogue.php';
if ($redirect_query !== '') {
    $target .= '?' . ltrim($redirect_query, '?');
}

$rows_in = isset($_POST['prix']) && is_array($_POST['prix']) ? $_POST['prix'] : [];
$rows = [];
foreach ($rows_in as $pid => $data) {
    if (!is_array($data)) {
        continue;
    }
    $row = [];
    if (array_key_exists('prix', $data)) {
        $row['prix'] = (string) $data['prix'];
    }
    if (array_key_exists('prix_achat', $data)) {
        $row['prix_achat'] = (string) $data['prix_achat'];
    }
    if ($row !== []) {
        $rows[(int) $pid] = $row;
    }
}

export_catalogue_prix_draft_store($rows);

$csrf = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
$session_csrf = isset($_SESSION['admin_csrf']) ? (string) $_SESSION['admin_csrf'] : '';
if ($csrf === '' || $session_csrf === '' || !hash_equals($session_csrf, $csrf)) {
    $_SESSION['export_catalogue_flash'] = ['type' => 'err', 'message' => 'Session expirée. Rechargez la page puis enregistrez à nouveau — votre saisie a été conservée.'];
    header('Location: ' . $target);
    exit;
}

$res = export_catalogue_maj_prix_produits($rows, (int) $_SESSION['admin_id']);
$_SESSION['export_catalogue_flash'] = [
    'type' => $res['success'] ? 'ok' : 'err',
    'message' => $res['message'],
];
if (!empty($res['success'])) {
    export_catalogue_prix_draft_clear();
}

header('Location: ' . $target);
exit;
