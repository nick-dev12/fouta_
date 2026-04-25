<?php
/**
 * Page d'accueil du dossier admin
 * Redirige vers login.php ou dashboard.php selon la session
 * Programmation procédurale uniquement
 */

session_start();

// Si l'admin est connecté, rediriger vers le dashboard
if (isset($_SESSION['admin_id']) && isset($_SESSION['admin_email'])) {
    header('Location: dashboard.php');
    exit;
}

// Sinon, rediriger vers la page de connexion
header('Location: login.php');
exit;

?>