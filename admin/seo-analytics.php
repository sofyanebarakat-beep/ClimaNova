<?php
declare(strict_types=1);
require __DIR__ . '/_inc/auth.php';
require __DIR__ . '/_inc/seo.php';
require __DIR__ . '/_inc/seo_layout.php';
require_login();

seo_header('analytics', 'Analytics', 'Results from Google Search Console and Google Analytics 4.');
seo_coming_soon('Clicks, positions and conversions per article and per service.');
admin_footer();
