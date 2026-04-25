<?php
/**
 * Sitemap XML pour le référencement
 * Accessible via /sitemap.php ou /sitemap.xml (avec rewrite)
 * Catégories exclues : les IDs peuvent changer. Produits et pages statiques uniquement.
 */
header('Content-Type: application/xml; charset=utf-8');

require_once __DIR__ . '/includes/site_url.php';
require_once __DIR__ . '/models/model_produits.php';

$base = get_site_base_url();
$logo_url = $base . '/image/logo-fpl.png';

// Pages statiques (logo inclus pour la page d'accueil)
$static_pages = [
    ['loc' => '/', 'priority' => '1.0', 'changefreq' => 'daily', 'image' => $logo_url, 'image_title' => 'FOUTA POIDS LOURDS - Pièces de véhicules poids lourds'],
    ['loc' => '/contact.php', 'priority' => '0.8', 'changefreq' => 'monthly'],
    ['loc' => '/produits.php', 'priority' => '0.9', 'changefreq' => 'daily'],
    ['loc' => '/nouveautes.php', 'priority' => '0.9', 'changefreq' => 'daily'],
    ['loc' => '/promo.php', 'priority' => '0.9', 'changefreq' => 'daily'],
    ['loc' => '/commande-personnalisee.php', 'priority' => '0.7', 'changefreq' => 'monthly'],
    ['loc' => '/politique-confidentialite.php', 'priority' => '0.4', 'changefreq' => 'yearly'],
    ['loc' => '/conditions-utilisation.php', 'priority' => '0.4', 'changefreq' => 'yearly'],
];

// Produits actifs (catégories retirées : IDs peuvent changer)
$produits = get_all_produits('actif');
$product_pages = [];
foreach ($produits as $p) {
    $product_pages[] = [
        'loc' => '/produit.php?id=' . (int)$p['id'],
        'priority' => '0.8',
        'changefreq' => 'weekly',
        'lastmod' => isset($p['date_modification']) ? $p['date_modification'] : ($p['date_creation'] ?? null)
    ];
}

$urls = array_merge($static_pages, $product_pages);

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
<?php foreach ($urls as $u):
    $loc = $base . $u['loc'];
    $lastmod = '';
    if (!empty($u['lastmod'])) {
        $lastmod = date('Y-m-d', strtotime($u['lastmod']));
    } else {
        $lastmod = date('Y-m-d');
    }
?>
    <url>
        <loc><?php echo htmlspecialchars($loc); ?></loc>
        <lastmod><?php echo $lastmod; ?></lastmod>
        <changefreq><?php echo htmlspecialchars($u['changefreq'] ?? 'weekly'); ?></changefreq>
        <priority><?php echo htmlspecialchars($u['priority'] ?? '0.5'); ?></priority>
        <?php if (!empty($u['image'])): ?>
        <image:image>
            <image:loc><?php echo htmlspecialchars($u['image']); ?></image:loc>
            <?php if (!empty($u['image_title'])): ?>
            <image:title><?php echo htmlspecialchars($u['image_title']); ?></image:title>
            <?php endif; ?>
        </image:image>
        <?php endif; ?>
    </url>
<?php endforeach; ?>
</urlset>
