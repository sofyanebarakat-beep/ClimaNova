<?php
// Puts components/tracking-head.html at the top of <head> and components/tracking-body.html right after <body>
// on every public page. Safe to run again: the marked blocks are replaced, never duplicated.
//   php scripts/inject-tracking.php          (writes)
//   php scripts/inject-tracking.php --check  (lists pages that are missing or out of date; exit 1 if any)
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
$head = rtrim((string) file_get_contents("$root/components/tracking-head.html"));
$body = rtrim((string) file_get_contents("$root/components/tracking-body.html"));
$check = in_array('--check', $argv, true);

// Public pages = every page that loads the site's main script, plus the link-in-bio page.
$skip = '~^(admin|node_modules|ClimaNovaEnergie copy|climavovaall|innerpages|template-pages/(changelog|style-guide)|components|Skills|projects/[^/]+/)~';
$files = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if ($f->getExtension() !== 'html') continue;
    $rel = substr($f->getPathname(), strlen($root) + 1);
    if (preg_match($skip, $rel) || basename($rel) === 'googlef9e69acee68e5e5a.html') continue;
    $html = (string) file_get_contents($f->getPathname());
    if (!str_contains($html, 'js/core/main.js') && $rel !== 'link/index.html' && $rel !== 'template-pages/license.html') continue;
    $files[] = $rel;
}
sort($files);
$changed = [];
foreach ($files as $rel) {
    $path = "$root/$rel";
    $html = (string) file_get_contents($path);
    $new = preg_replace('~\s*<!-- CN:TRACKING-HEAD START.*?CN:TRACKING-HEAD END -->~s', '', $html);
    $new = preg_replace('~<!-- CN:TRACKING-BODY START.*?CN:TRACKING-BODY END -->\n?~s', '', (string) $new);
    // Head block: right after <meta charset>, else right after <head>.
    if (preg_match('~<meta\s+charset=[^>]*>~i', (string) $new, $m, PREG_OFFSET_CAPTURE)) {
        $at = $m[0][1] + strlen($m[0][0]);
    } elseif (preg_match('~<head[^>]*>~i', (string) $new, $m, PREG_OFFSET_CAPTURE)) {
        $at = $m[0][1] + strlen($m[0][0]);
    } else { fwrite(STDERR, "no <head>: $rel\n"); continue; }
    $new = substr((string) $new, 0, $at) . "\n" . $head . substr((string) $new, $at);
    if (!preg_match('~<body[^>]*>~i', $new, $m, PREG_OFFSET_CAPTURE)) { fwrite(STDERR, "no <body>: $rel\n"); continue; }
    $at = $m[0][1] + strlen($m[0][0]);
    $new = substr($new, 0, $at) . "\n" . $body . "\n" . ltrim(substr($new, $at), "\n");
    if ($new !== $html) {
        $changed[] = $rel;
        if (!$check) file_put_contents($path, $new);
    }
}
echo ($check ? 'Out of date: ' : 'Updated: ') . count($changed) . ' of ' . count($files) . " public pages\n";
if ($check && $changed) { echo implode("\n", $changed), "\n"; exit(1); }
