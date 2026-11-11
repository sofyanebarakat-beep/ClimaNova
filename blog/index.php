<?php
// The blog list: the static blog/index.html with published SEO articles mixed in by date (newest first).
// If the database is unreachable, the static page is served unchanged.
declare(strict_types=1);
require_once __DIR__ . '/../admin/_inc/seo_public.php';

$html = (string) file_get_contents(__DIR__ . '/index.html');
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache'); // always fresh: a newly published article shows at once
$articles = seo_published_articles();
$cards = seo_static_blog_cards($html);
if ($articles && $cards) {
    $items = [];
    foreach ($cards as $i => $c) $items[] = [$c['date'] ?? '0000-00-00', 1, $i, $c['html']];
    foreach ($articles as $i => $a) $items[] = [substr((string) $a['published_at'], 0, 10), 2, $i, seo_article_card_html($a)];
    usort($items, static fn ($x, $y) => [$y[0], $y[1], $x[2]] <=> [$x[0], $x[1], $y[2]]);
    $first = strpos($html, $cards[0]['html']);
    $last = $cards[count($cards) - 1]['html'];
    $end = strpos($html, $last) + strlen($last);
    $html = substr($html, 0, $first) . implode('', array_column($items, 3)) . substr($html, $end);
}
$rss = '<link rel="alternate" type="application/rss+xml" title="Blog ClimaNova Énergie" href="' . SEO_SITE_URL . '/blog/rss.xml">';
echo str_contains($html, 'application/rss+xml') ? $html : preg_replace('~</head>~', $rss . "\n</head>", $html, 1);
