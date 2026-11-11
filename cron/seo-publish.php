<?php
// Automatic publishing job. Hostinger hPanel → Cron Jobs, every 15 minutes:
//   php /home/<user>/domains/climanova-energie.fr/public_html/cron/seo-publish.php
// Command line only; never reachable over the web (.htaccess also blocks /cron).
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
date_default_timezone_set('Europe/Paris');
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__); // so secrets and images resolve to the same folders as the website
require __DIR__ . '/../admin/_inc/seo_articles.php';
$r = seo_publish_job();
echo date("c") . " scheduled {$r["scheduled"]}, published {$r["published"]}, failed {$r["failed"]}\n";
