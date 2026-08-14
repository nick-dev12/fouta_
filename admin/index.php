<?php
/**
 * Page d'accueil du dossier admin
 * Redirige vers login.php ou dashboard.php selon la session
 * Programmation procédurale uniquement
 */

session_start();

// Si l'admin est connecté, rediriger vers l’accueil de son rôle
if (isset($_SESSION['admin_id']) && isset($_SESSION['admin_email'])) {
    require_once __DIR__ . '/../includes/admin_route_access.php';
    $role_redir = admin_normalize_role_for_route($_SESSION['admin_role'] ?? 'admin');
    header('Location: ' . admin_route_build_url(admin_role_default_redirect_path($role_redir)));
    exit;
}

// Sinon, rediriger vers la page de connexion
header('Location: login.php');
exit;

?>