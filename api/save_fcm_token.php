<?php
/**
 * API pour enregistrer le token FCM (notifications push)
 * POST: token, type (user|admin)
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Méthode non autorisée';
    echo json_encode($response);
    exit;
}

$token = isset($_POST['token']) ? trim($_POST['token']) : '';
$type = isset($_POST['type']) ? $_POST['type'] : '';

if (empty($token) || !in_array($type, ['user', 'admin'])) {
    $response['message'] = 'Paramètres invalides';
    echo json_encode($response);
    exit;
}

if ($type === 'user') {
    if (!isset($_SESSION['user_id'])) {
        $response['message'] = 'Non connecté';
        echo json_encode($response);
        exit;
    }
    $user_id = (int) $_SESSION['user_id'];
    $admin_id = null;
} else {
    if (!isset($_SESSION['admin_id'])) {
        $response['message'] = 'Non connecté';
        echo json_encode($response);
        exit;
    }
    $admin_id = (int) $_SESSION['admin_id'];
    $user_id = null;
}

require_once __DIR__ . '/../models/model_fcm.php';

if (save_fcm_token($token, $type, $user_id, $admin_id)) {
    $response['success'] = true;
    $response['message'] = 'Notifications activées';
} else {
    $response['message'] = 'Erreur lors de l\'enregistrement';
}

echo json_encode($response);
