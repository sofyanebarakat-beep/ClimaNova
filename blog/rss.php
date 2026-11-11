<?php
// RSS feed of the blog: static posts + published SEO articles, newest first. Served at /blog/rss.xml.
declare(strict_types=1);
require_once __DIR__ . '/../admin/_inc/seo_public.php';

$items = [];
foreach (seo_static_blog_cards() as $c) {
    if ($c['path'] === '') continue;
    $items[] = ['title' => $c['title'], 'link' => SEO_SITE_URL . seo_norm_internal($c['path']), 'description' => $c['excerpt'],
        'date' => $c['date'] ? strtotime($c['date'] . ' 09:00 Europe/Paris') : 0, 'image' => null];
}
foreach (seo_published_articles() as $a) {
    $items[] = ['title' => $a['title'], 'link' => SEO_SITE_URL . '/blog/' . $a['slug'] . '/', 'description' => $a['meta_description'],
        'date' => strtotime((string) $a['published_at']), 'image' => $a['content']['featuredImage']['url'] ?? null];
}
usort($items, static fn ($x, $y) => $y['date'] <=> $x['date']);
$x = static fn ($s) => htmlspecialchars((string) $s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
header('Content-Type: application/rss+xml; charset=utf-8');
header('Cache-Control: public, max-age=900');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:media="http://search.yahoo.com/mrss/">
<channel>
<title>Blog ClimaNova Énergie</title>
<link><?= SEO_SITE_URL ?>/blog/</link>
<description>Conseils climatisation, chauffage, pompe à chaleur et rénovation énergétique à Nice et dans les Alpes-Maritimes.</description>
<language>fr-FR</language>
<atom:link href="<?= SEO_SITE_URL ?>/blog/rss.xml" rel="self" type="application/rss+xml"/>
<?php if ($items): ?><lastBuildDate><?= date(DATE_RSS, max(array_column($items, 'date'))) ?></lastBuildDate><?php endif; ?>
<?php foreach (array_slice($items, 0, 50) as $i): ?>
<item>
  <title><?= $x($i['title']) ?></title>
  <link><?= $x($i['link']) ?></link>
  <guid isPermaLink="true"><?= $x($i['link']) ?></guid>
  <description><?= $x($i['description']) ?></description>
<?php if ($i['date']): ?>  <pubDate><?= date(DATE_RSS, $i['date']) ?></pubDate>
<?php endif; if ($i['image']): ?>  <media:content url="<?= $x($i['image']) ?>" medium="image"/>
<?php endif; ?>
</item>
<?php endforeach; ?>
</channel>
</rss>
