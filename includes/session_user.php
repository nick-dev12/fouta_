<?php
/**
 * Configuration de session persistante pour les utilisateurs connectés
 * Durée : 30 jours (cookie + données serveur)
 * À inclure AVANT session_start() sur les pages utilisateur
 */

if (session_status() === PHP_SESSION_NONE) {
    $session_lifetime = 30 * 24 * 3600; // 30 jours en secondes

    session_set_cookie_params([
        'lifetime' => $session_lifetime,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    ini_set('session.gc_maxlifetime', (string) $session_lifetime);
}
