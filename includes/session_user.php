<?php
/**
 * Session « client » (Site + espace user/) : cookie persistant jusqu’à déconnexion explicite.
 * À inclure AVANT session_start() sur les pages concernées.
 *
 * Cookie longue durée + gc_maxlifetime alignés : la session ne expire pas (« Remember me » implicite)
 * sauf clic sur Déconnexion (user/deconnexion.php) ou destruction serveur des fichiers de session.
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    // ~10 ans : déconnexion uniquement via le bouton utilisateur
    $session_lifetime = 10 * 365 * 24 * 3600;

    ini_set('session.gc_maxlifetime', (string) $session_lifetime);
    ini_set('session.cookie_lifetime', (string) $session_lifetime);

    session_set_cookie_params([
        'lifetime' => $session_lifetime,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}
