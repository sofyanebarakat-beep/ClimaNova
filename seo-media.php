<?php
// Public images of SEO articles: /seo-media/<file> → the image folder outside public_html.
declare(strict_types=1);
require_once __DIR__ . '/admin/_inc/seo_public.php';

$f = (string) ($_GET['f'] ?? '');
$types = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
$ext = pathinfo($f, PATHINFO_EXTENSION);
$path = preg_match('/^[a-z0-9-]{8,120}\.(jpg|png|webp)$/', $f) ? seo_media_dir() . '/' . $f : '';
if ($path === '' || !is_file($path)) {
    http_response_code(404);
    header('Content-Type: text/plain');
    echo 'Not found';
    exit;
}
header('Content-Type: ' . $types[$ext]);
header('Content-Length: ' . filesize($path));
header('Cache-Control: public, max-age=31536000, immutable');
header('X-Content-Type-Options: nosniff');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', (int) filemtime($path)) . ' GMT');
readfile($path);
