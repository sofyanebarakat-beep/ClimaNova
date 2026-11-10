<?php
declare(strict_types=1);
require __DIR__ . '/_inc/auth.php';
require __DIR__ . '/_inc/seo.php';
require __DIR__ . '/_inc/seo_layout.php';
require_login();

seo_header('campaigns', 'Campaigns', 'SEO campaigns per service: goal, dates and status.');
seo_coming_soon('Create and manage campaigns for each service.');
admin_footer();
