<?php
// POST /api/seo-generate — Authorization: Bearer <CRON_SECRET>. Collect batches → weekly autopilot → send due topics.
declare(strict_types=1);
require __DIR__ . '/../admin/_inc/seo.php';
require __DIR__ . '/../admin/_inc/seo_automation.php';
seo_cron_guard('seo-generate');
echo json_encode(['ok' => true, 'endpoint' => 'seo-generate'] + seo_run_generate(dirname(__DIR__)), JSON_UNESCAPED_UNICODE);
