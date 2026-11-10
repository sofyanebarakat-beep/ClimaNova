<?php
declare(strict_types=1);
require __DIR__ . '/_inc/auth.php';
require __DIR__ . '/_inc/seo.php';
require __DIR__ . '/_inc/seo_layout.php';
require_login();

seo_header('keywords', 'Keywords & Topics', 'Keyword research and weekly article topics for each campaign.');
seo_coming_soon('Keyword ideas, search intent and the weekly topic plan.');
admin_footer();
