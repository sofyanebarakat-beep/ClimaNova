<?php
declare(strict_types=1);
require __DIR__ . '/_inc/auth.php';
require __DIR__ . '/_inc/seo.php';
require __DIR__ . '/_inc/seo_layout.php';
require_login();

seo_header('budget', 'API Budget', 'Monthly AI spending limit, reservations and real costs.');
seo_coming_soon('Edit the monthly allowance, warning threshold and hard stop, and see every AI request with its cost.');
admin_footer();
