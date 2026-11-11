<?php
// Local dev server router: mimics the .htaccess rewrites. php -S 127.0.0.1:8099 tests/router.php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$root = dirname(__DIR__);
if (preg_match('~^/admin/(seo(-[a-z-]+)?)/?$~', $path, $m)) {
    $_SERVER['SCRIPT_NAME'] = "/admin/{$m[1]}.php";
    chdir($root . '/admin');
    require $root . "/admin/{$m[1]}.php";
    return true;
}
if ($path === '/sitemap.xml') { require $root . '/sitemap.php'; return true; }
if ($path === '/llms.txt') { require $root . '/llms.php'; return true; }
if ($path === '/blog/rss.xml') { require $root . '/blog/rss.php'; return true; }
if ($path === '/blog/' || $path === '/blog') { require $root . '/blog/index.php'; return true; }
if (preg_match('~^/seo-media/([a-z0-9-]+\.(jpg|png|webp))$~', $path, $m)) { $_GET['f'] = $m[1]; require $root . '/seo-media.php'; return true; }
if (preg_match('~^/blog/([a-z0-9]+(-[a-z0-9]+)*)/?$~', $path, $m) && !is_dir($root . "/blog/{$m[1]}")) {
    $_GET['slug'] = $m[1];
    require $root . '/blog/article.php';
    return true;
}
return false;
