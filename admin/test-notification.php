<?php
/**
 * Page de test des notifications push (Admin)
 * Envoie une notification de test à l'admin connecté puis redirige vers le dashboard
 */

session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: login.php');
    exit;
}
require_once __DIR__ . '/../includes/require_access.php';


require_once __DIR__ . '/../models/model_fcm.php';
require_once __DIR__ . '/../services/firebase_push.php';

$tokens = get_fcm_tokens_by_admin($_SESSION['admin_id']);

if (empty($tokens)) {
    $_SESSION['notification_test_message'] = 'Aucun token enregistré. Activez d\'abord les notifications depuis le tableau de bord.';
    $_SESSION['notification_test_type'] = 'error';
} else {
    $result = firebase_send_notification(
        $tokens,
        'Test FOUTA POIDS LOURDS',
        'Ceci est une notification de test. Les notifications fonctionnent correctement !',
        ['link' => '/admin/dashboard.php', 'tag' => 'test']
    );
    if ($result['success'] > 0) {
        $_SESSION['notification_test_message'] = "Notification envoyée avec succès ({$result['success']} appareil(s)). Vérifiez votre ordinateur.";
        $_SESSION['notification_test_type'] = 'success';
    } else {
        $_SESSION['notification_test_message'] = "Échec de l'envoi. " . implode(' ', $result['errors']);
        $_SESSION['notification_test_type'] = 'error';
    }
}

header('Location: dashboard.php');
exit;
