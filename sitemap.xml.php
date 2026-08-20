<?php
/**
 * Indra Hotel - Dynamic XML Sitemap Generator for Google / Bing (Clean URLs)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

header('Content-Type: application/xml; charset=utf-8');

$pdo = getDB();
$rooms = $pdo->query("SELECT slug, updated_at FROM rooms WHERE status != 'maintenance'")->fetchAll();
$sOffers = $pdo->query("SELECT id, updated_at FROM special_offers WHERE is_active = 1")->fetchAll();
$spots = $pdo->query("SELECT slug, created_at FROM location_spots")->fetchAll();

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">
    
    <!-- Core Pages -->
    <url>
        <loc><?= url('/home') ?></loc>
        <lastmod><?= date('Y-m-d') ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>
    <url>
        <loc><?= url('/about') ?></loc>
        <lastmod><?= date('Y-m-d') ?></lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.8</priority>
    </url>
    <url>
        <loc><?= url('/rooms') ?></loc>
        <lastmod><?= date('Y-m-d') ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>0.9</priority>
    </url>
    <url>
        <loc><?= url('/eat-drink') ?></loc>
        <lastmod><?= date('Y-m-d') ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
    </url>
    <url>
        <loc><?= url('/wellness') ?></loc>
        <lastmod><?= date('Y-m-d') ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
    </url>
    <url>
        <loc><?= url('/offers') ?></loc>
        <lastmod><?= date('Y-m-d') ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
    </url>
    <url>
        <loc><?= url('/gallery') ?></loc>
        <lastmod><?= date('Y-m-d') ?></lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.7</priority>
    </url>
    <url>
        <loc><?= url('/location') ?></loc>
        <lastmod><?= date('Y-m-d') ?></lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.7</priority>
    </url>
    <url>
        <loc><?= url('/contact') ?></loc>
        <lastmod><?= date('Y-m-d') ?></lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.6</priority>
    </url>
    <url>
        <loc><?= url('/book') ?></loc>
        <lastmod><?= date('Y-m-d') ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>0.9</priority>
    </url>

    <!-- Dynamic Room Detail Pages -->
    <?php foreach ($rooms as $r): ?>
    <url>
        <loc><?= url('/room/' . urlencode($r['slug'])) ?></loc>
        <lastmod><?= date('Y-m-d', strtotime($r['updated_at'] ?? 'now')) ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.85</priority>
    </url>
    <?php endforeach; ?>

    <!-- Dynamic Special Offer Detail Pages -->
    <?php foreach ($sOffers as $so): ?>
    <url>
        <loc><?= url('/offer/' . $so['id']) ?></loc>
        <lastmod><?= date('Y-m-d', strtotime($so['updated_at'] ?? 'now')) ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
    </url>
    <?php endforeach; ?>

    <!-- Dynamic Location Spot Detail Pages -->
    <?php foreach ($spots as $sp): ?>
    <url>
        <loc><?= url('/location/' . urlencode($sp['slug'])) ?></loc>
        <lastmod><?= date('Y-m-d', strtotime($sp['updated_at'] ?? 'now')) ?></lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.75</priority>
    </url>
    <?php endforeach; ?>

</urlset>
