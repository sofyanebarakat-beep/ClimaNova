<?php
// POST /api/seo-publish — Authorization: Bearer <CRON_SECRET>. Auto-schedule approved articles, publish due ones,
// recover publishing claims stuck for more than 10 minutes, and log the result.
declare(strict_types=1);
require __DIR__ . '/../admin/_inc/seo.php';
require __DIR__ . '/../admin/_inc/seo_automation.php';
seo_cron_guard('seo-publish');
echo json_encode(['ok' => true, 'endpoint' => 'seo-publish'] + seo_run_publish(), JSON_UNESCAPED_UNICODE);
