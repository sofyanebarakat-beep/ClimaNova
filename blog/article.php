<?php
// Published SEO articles at /blog/<slug>/ (static posts in blog/<slug>/ are served directly by Apache first).
declare(strict_types=1);
require_once __DIR__ . '/../admin/_inc/seo_public.php';

$slug = (string) ($_GET['slug'] ?? '');
$a = seo_published_article($slug);
if (!$a) {
    http_response_code(404);
    readfile(__DIR__ . '/../404.html');
    exit;
}
if (!str_ends_with((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/')) {
    header('Location: /blog/' . $a['slug'] . '/', true, 301);
    exit;
}
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: public, max-age=300');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', (int) strtotime((string) max($a['updated_at'], $a['published_at']))) . ' GMT');
echo seo_render_article_page($a);
