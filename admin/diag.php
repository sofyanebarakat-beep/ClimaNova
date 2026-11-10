<?php
// TEMPORARY: checks what the host supports for Neon. Remove after use.
declare(strict_types=1);
require __DIR__ . '/_inc/auth.php';
require_login();
header('Content-Type: text/plain; charset=utf-8');
$host = 'ep-silent-mouse-bez0oinc-pooler.c-9.us-east-2.aws.neon.tech';
echo 'php ', PHP_VERSION, "\n";
foreach (['pdo_pgsql', 'pgsql', 'curl', 'openssl'] as $e) echo $e, ' ', extension_loaded($e) ? 'yes' : 'no', "\n";
if (function_exists('pg_version') ) { echo 'libpq ', pg_version()['client'] ?? '?', "\n"; }
if (defined('PDO::PGSQL_ATTR_DISABLE_PREPARES')) echo "pdo pgsql consts yes\n";
$t = microtime(true); $s = @fsockopen($host, 5432, $no, $err, 6);
echo 'tcp 5432 ', $s ? 'open' : "blocked ($no $err)", ' ', round((microtime(true) - $t) * 1000), "ms\n";
$t = microtime(true); $s = @fsockopen('ssl://' . $host, 443, $no, $err, 6);
echo 'tcp 443 ', $s ? 'open' : "blocked ($no $err)", ' ', round((microtime(true) - $t) * 1000), "ms\n";
echo 'docroot ', $_SERVER['DOCUMENT_ROOT'] ?? '?', "\n";
echo 'parent writable ', is_writable(dirname($_SERVER['DOCUMENT_ROOT'] ?? '/')) ? 'yes' : 'no', "\n";
