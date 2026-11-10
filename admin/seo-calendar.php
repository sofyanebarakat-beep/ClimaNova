<?php
declare(strict_types=1);
require __DIR__ . '/_inc/auth.php';
require __DIR__ . '/_inc/seo.php';
require __DIR__ . '/_inc/seo_layout.php';
require_login();

seo_header('calendar', 'Calendar', 'When approved articles go live on the blog (Paris time).');
seo_coming_soon('The publishing calendar for scheduled and published articles.');
admin_footer();
