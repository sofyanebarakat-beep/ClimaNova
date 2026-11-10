<?php
declare(strict_types=1);
require __DIR__ . '/_inc/auth.php';
require __DIR__ . '/_inc/seo.php';
require __DIR__ . '/_inc/seo_layout.php';
require_login();

seo_header('offers', 'Offers', 'The ClimaNova services the articles promote, each with its own page.');
seo_coming_soon('The list of synced services with their pages, target keywords and on/off switch. Use “Sync offers” on the Dashboard to import them.');
admin_footer();
