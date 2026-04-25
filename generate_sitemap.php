<?php
/**
 * Génère le fichier sitemap.xml
 * Exécuter via CLI : php generate_sitemap.php
 * Ou via navigateur : /generate_sitemap.php (à protéger en production)
 */
require_once __DIR__ . '/includes/site_url.php';
require_once __DIR__ . '/models/model_produits.php';
require_once __DIR__ . '/models/model_categories.php';

$base = get_site_base_url();

$static_pages = [
    ['loc' => '/', 'priority' => '1.0', 'changefreq' => 'daily'],
    ['loc' => '/contact.php', 'priority' => '0.8', 'changefreq' => 'monthly'],
    ['loc' => '/produits.php', 'priority' => '0.9', 'changefreq' => 'daily'],
    ['loc' => '/nouveautes.php', 'priority' => '0.9', 'changefreq' => 'daily'],
    ['loc' => '/promo.php', 'priority' => '0.9', 'changefreq' => 'daily'],
    ['loc' => '/commande-personnalisee.php', 'priority' => '0.7', 'changefreq' => 'monthly'],
    ['loc' => '/politique-confidentialite.php', 'priority' => '0.4', 'changefreq' => 'yearly'],
    ['loc' => '/conditions-utilisation.php', 'priority' => '0.4', 'changefreq' => 'yearly'],
];

$category_pages = [];
foreach (get_all_categories() as $cat) {
    if (!empty($cat['nom'])) {
        $category_pages[] = [
            'loc' => '/categorie.php?id=' . (int)$cat['id'],
            'priority' => '0.85',
            'changefreq' => 'weekly',
            'lastmod' => $cat['date_modification'] ?? null
        ];
    }
}

$product_pages = [];
foreach (get_all_produits('actif') as $p) {
    $product_pages[] = [
        'loc' => '/produit.php?id=' . (int)$p['id'],
        'priority' => '0.8',
        'changefreq' => 'weekly',
        'lastmod' => $p['date_modification'] ?? $p['date_creation'] ?? null
    ];
}

$urls = array_merge($static_pages, $category_pages, $product_pages);

$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
$xml .= '        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"' . "\n";
$xml .= '        xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9' . "\n";
$xml .= '        http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">' . "\n";

foreach ($urls as $u) {
    $loc = $base . $u['loc'];
    $lastmod = !empty($u['lastmod']) ? date('Y-m-d', strtotime($u['lastmod'])) : date('Y-m-d');
    $changefreq = $u['changefreq'] ?? 'weekly';
    $priority = $u['priority'] ?? '0.5';
    $xml .= "    <url>\n";
    $xml .= "        <loc>" . htmlspecialchars($loc) . "</loc>\n";
    $xml .= "        <lastmod>{$lastmod}</lastmod>\n";
    $xml .= "        <changefreq>{$changefreq}</changefreq>\n";
    $xml .= "        <priority>{$priority}</priority>\n";
    $xml .= "    </url>\n";
}

$xml .= '</urlset>';

$file = __DIR__ . '/sitemap.xml';
if (file_put_contents($file, $xml) !== false) {
    echo "sitemap.xml créé avec succès (" . count($urls) . " URLs)\n";
} else {
    echo "Erreur : impossible d'écrire sitemap.xml\n";
}
