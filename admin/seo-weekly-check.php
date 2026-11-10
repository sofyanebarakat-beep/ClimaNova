<?php
declare(strict_types=1);
require __DIR__ . '/_inc/auth.php';
require __DIR__ . '/_inc/seo.php';
require __DIR__ . '/_inc/seo_layout.php';
require_login();

seo_header('weekly', 'Weekly Check', 'One human review per week: approve drafts, check images, confirm the schedule.');
seo_coming_soon('The weekly checklist: articles waiting for approval, missing featured images, failed jobs and next week’s schedule.');
admin_footer();
