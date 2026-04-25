<?php
/**
 * Page de déconnexion utilisateur
 * Programmation procédurale uniquement
 */

session_start();

// Supprimer les tokens FCM du client avant déconnexion
if (isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../models/model_fcm.php';
    delete_fcm_tokens_by_user((int) $_SESSION['user_id']);
}

// Détruire toutes les variables de session
$_SESSION = array();

// Si vous voulez détruire complètement la session, effacez également
// le cookie de session.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finalement, détruire la session
session_destroy();

// Rediriger vers la page d'accueil du site
header('Location: /index.php');
exit;

?>

