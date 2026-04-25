<?php
/**
 * Retourne l'URL de base du site pour les liens (emails, notifications, etc.)
 * Priorité : config/site.php > config/email.php > déduction depuis $_SERVER
 * 
 * @return string URL sans slash final (ex: https://sugar-paper.com)
 */
function get_site_base_url() {
    $site_url = '';

    if (file_exists(__DIR__ . '/../config/site.php')) {
        $config = require __DIR__ . '/../config/site.php';
        $site_url = $config['site_url'] ?? '';
    }

    if (empty($site_url) && file_exists(__DIR__ . '/../config/email.php')) {
        $config = require __DIR__ . '/../config/email.php';
        $site_url = $config['site_url'] ?? '';
    }

    if (!empty($site_url)) {
        return rtrim($site_url, '/');
    }

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $protocol . '://' . $host;
}
