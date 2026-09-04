<?php
/**
 * ROUTEUR DE DÉVELOPPEMENT — pour `php -S 127.0.0.1:8080 routeur-dev.php`.
 *
 * Le serveur PHP intégré NE LIT PAS le .htaccess : sans ce routeur, l'URL
 * /p/{code} de la vitrine client (celle du QR des étiquettes de pièce)
 * répondrait 404 en local alors qu'elle vit sous Apache. Ce fichier rejoue
 * la règle du .htaccess — et rien d'autre : tout le reste suit le régime
 * normal du serveur intégré.
 */

$chemin = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$chemin = is_string($chemin) ? $chemin : '/';

/* La vitrine client : /p/{code} → p.php?code=… (la règle du .htaccess). */
if (preg_match('#^/p/(.+)$#', $chemin, $m)) {
    $_GET['code'] = rawurldecode($m[1]);
    require __DIR__ . '/p.php';
    return true;
}

/* robots.txt / sitemap.xml comme sous Apache. */
if ($chemin === '/robots.txt') {
    require __DIR__ . '/robots.php';
    return true;
}
if ($chemin === '/sitemap.xml') {
    require __DIR__ . '/sitemap.php';
    return true;
}

/* Fichier existant (asset) ou script : régime normal du serveur intégré. */
return false;
