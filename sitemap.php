<?php
// /sitemap.xml = the static sitemap.xml plus every published SEO article.
declare(strict_types=1);
require_once __DIR__ . '/admin/_inc/seo_public.php';

$xml = (string) file_get_contents(__DIR__ . '/sitemap.xml');
$add = '';
foreach (seo_published_articles() as $a) {
    $loc = SEO_SITE_URL . '/blog/' . $a['slug'] . '/';
    if (str_contains($xml, "<loc>$loc</loc>")) continue;
    $add .= "  <url>\n    <loc>" . htmlspecialchars($loc, ENT_XML1) . "</loc>\n    <lastmod>" . substr((string) max($a['updated_at'], $a['published_at']), 0, 10)
        . "</lastmod>\n    <changefreq>monthly</changefreq>\n    <priority>0.7</priority>\n  </url>\n";
}
header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=900');
echo $add === '' ? $xml : preg_replace('~</urlset>\s*$~', $add . '</urlset>' . "\n", $xml, 1);
