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

/**
 * Segment d’URL entre l’hôte et /admin/ (ex. '' en racine, '/Fouta' en sous-dossier WAMP).
 * Sert à construire des chemins /upload/… valides sur l’environnement courant (sans forcer config/site_url).
 */
function get_public_root_uri_path() {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($script !== '' && preg_match('#^(.+?)/admin/#', $script, $m)) {
        $cached = $m[1];
        return $cached;
    }
    $cached = '';
    return $cached;
}

/**
 * URL de base pour la requête HTTP en cours : schéma + hôte + racine publique (sans slash final).
 * À utiliser pour les balises img générées côté admin en local alors que config/site.php pointe la prod.
 */
function get_request_origin_base_url() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $root = get_public_root_uri_path();
    return rtrim($protocol . '://' . $host . $root, '/');
}

/**
 * Chemin fichier du logo vitrine présent sous /image/ (priorité logo-fpl.png).
 *
 * @return string Nom de fichier présent sur disque, ou chaîne vide
 */
function get_site_logo_relative_filename(): string {
    $image_dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'image' . DIRECTORY_SEPARATOR;
    $candidates = ['logo-fpl.png', 'logo fpl_stock.png'];
    foreach ($candidates as $name) {
        if (is_file($image_dir . $name)) {
            return $name;
        }
    }
    return '';
}

/**
 * URL absolue du logo pour img (requête HTTP en cours — admin local / sous-dossier WAMP OK).
 *
 * @return string URL vide si aucun fichier logo trouvé
 */
function get_site_logo_url_for_current_request(): string {
    $name = get_site_logo_relative_filename();
    if ($name === '') {
        return '';
    }
    $encoded = implode('/', array_map('rawurlencode', explode('/', str_replace('\\', '/', $name))));
    return rtrim(get_request_origin_base_url(), '/') . '/image/' . $encoded;
}
