<?php
declare(strict_types=1);
require __DIR__ . '/_inc/auth.php';
require __DIR__ . '/_inc/seo.php';
require __DIR__ . '/_inc/seo_layout.php';
require_login();

seo_header('articles', 'Articles & Approval', 'AI drafts, quality checks and human approval before anything publishes.');
seo_coming_soon('Draft articles, quality scores, featured-image upload and the approve / reject flow.');
admin_footer();
