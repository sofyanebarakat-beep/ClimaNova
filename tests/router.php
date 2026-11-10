<?php
// Local dev server router: mimics admin/.htaccess clean URLs. php -S 127.0.0.1:8099 tests/router.php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (preg_match('~^/admin/(seo(-[a-z-]+)?)/?$~', $path, $m)) {
    $_SERVER['SCRIPT_NAME'] = "/admin/{$m[1]}.php";
    chdir(__DIR__ . '/../admin');
    require __DIR__ . "/../admin/{$m[1]}.php";
    return true;
}
return false;
