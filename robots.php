<?php
/**
 * Robots.txt dynamique avec URL du sitemap
 * Configurer .htaccess pour rediriger /robots.txt vers robots.php
 * Ou renommer en robots.txt et utiliser un script de déploiement
 */
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/includes/site_url.php';
$base = get_site_base_url();

echo "# Robots.txt pour FOUTA POIDS LOURDS - Pièces poids lourds\n";
echo "# https://www.robotstxt.org/\n\n";
echo "User-agent: *\n";
echo "Allow: /\n\n";
echo "# Pages à ne pas indexer\n";
echo "Disallow: /user/\n";
echo "Disallow: /admin/\n";
echo "Disallow: /panier.php\n";
echo "Disallow: /commande.php\n";
echo "Disallow: /add-to-panier.php\n\n";
echo "# Sitemap\n";
echo "Sitemap: " . $base . "/sitemap.xml\n";
