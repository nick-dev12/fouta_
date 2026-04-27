<?php
/**
 * Cible sûre après connexion (loader plein écran) — chemins internes uniquement
 */

if (!function_exists('post_login_sanitize_next_url')) {
    /**
     * @param string $url
     * @return string chemin relatif commençant par /
     */
    function post_login_sanitize_next_url($url) {
        $url = trim((string) $url);
        if ($url === '') {
            return '/index.php';
        }
        if (preg_match('!\s!', $url) || strpos($url, '//') !== false) {
            return '/index.php';
        }
        if ($url[0] !== '/') {
            $url = '/' . $url;
        }
        if (strlen($url) > 500) {
            return '/index.php';
        }
        return $url;
    }
}
