<?php
declare(strict_types=1);
require __DIR__ . '/_inc/auth.php';
require __DIR__ . '/_inc/seo.php';
require __DIR__ . '/_inc/seo_layout.php';
require_login();

seo_header('logs', 'Automation Logs', 'Every automated job: what ran, when, and whether it worked.');
seo_coming_soon('The full, filterable automation log.');
admin_footer();
