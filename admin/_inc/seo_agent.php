<?php
// SEO agent server functions. Every entry point re-checks the admin session (rule 1).
declare(strict_types=1);

require_once __DIR__ . '/seo_logic.php';

function seo_require_admin(): void
{
    if (!is_logged_in()) {
        throw new RuntimeException('Not signed in as an admin.');
    }
}

function seo_log(string $job, string $status, string $message, ?array $details = null): void
{
    try {
        db_query('insert into seo_automation_logs (job, status, message, details) values ($1, $2, $3, $4::jsonb)',
            [$job, $status, $message, $details === null ? null : json_encode($details, JSON_UNESCAPED_UNICODE)]);
    } catch (Throwable $e) {
        error_log('seo_log failed: ' . $e->getMessage());
    }
}

/**
 * Everything the SEO dashboard shows, in ONE database round trip (one read-only transaction).
 * Monthly usage is read from the seo_monthly_usage view.
 */
function seo_dashboard_data(): array
{
    seo_require_admin();
    [$weekStart, $weekEnd] = seo_week_bounds(new DateTimeImmutable('now'));
    $ws = $weekStart->format(DATE_ATOM);
    $we = $weekEnd->format(DATE_ATOM);

    [$counts, $usage, $settings, $logs] = db_batch([
        ["select
            (select count(*) from seo_campaigns where status = 'active')                         as active_campaigns,
            (select count(*) from seo_campaigns)                                                  as total_campaigns,
            (select count(*) from seo_articles)                                                   as total_articles,
            (select count(*) from seo_articles where status = 'draft')                            as drafts,
            (select count(*) from seo_articles where status = 'generated')                        as generated,
            (select count(*) from seo_articles where status = 'scheduled')                        as scheduled,
            (select count(*) from seo_articles where status = 'published')                        as published,
            (select count(*) from seo_automation_logs where status = 'failed')                    as failed_jobs,
            (select count(*) from seo_offers where is_active)                                     as offers,
            (select count(*) from seo_articles where status = 'scheduled'
               and scheduled_for >= $1::timestamptz and scheduled_for < $2::timestamptz)          as scheduled_week,
            (select count(*) from seo_conversions
               where occurred_at >= $1::timestamptz and occurred_at < $2::timestamptz)            as conversions_week", [$ws, $we]],
        ["select recorded_usd, reserved_usd, requests from seo_monthly_usage
          where month = date_trunc('month', now() at time zone 'Europe/Paris')::date"],
        ["select value from seo_settings where key = 'budget'"],
        ["select created_at, job, status, message from seo_automation_logs order by created_at desc limit 10"],
    ], true);

    $c = array_map('intval', $counts[0] ?? []);
    $b = json_decode($settings[0]['value'] ?? '{}', true) ?: [];
    $budget = seo_budget_summary(
        (float) ($b['monthly_allowance_usd'] ?? 0),
        (float) ($usage[0]['recorded_usd'] ?? 0),
        (float) ($usage[0]['reserved_usd'] ?? 0),
        (float) ($b['warning_threshold_pct'] ?? 80),
        (bool) ($b['hard_stop'] ?? true),
    );
    $budget['requests'] = (int) ($usage[0]['requests'] ?? 0);

    return ['counts' => $c, 'budget' => $budget, 'logs' => $logs, 'week' => [$weekStart, $weekEnd]];
}

/** All offers for the Offers tab: enabled first, then priority, then title. */
function seo_list_offers(): array
{
    seo_require_admin();
    $rows = db_query('select id, slug, name, path, url, description, is_active, promotion_enabled, target_keywords,
                             seo_priority, synced_at, updated_at
                        from seo_offers
                       order by is_active desc, promotion_enabled desc, seo_priority desc, name');
    foreach ($rows as &$r) {
        $r['is_active'] = $r['is_active'] === 't' || $r['is_active'] === 'true';
        $r['promotion_enabled'] = $r['promotion_enabled'] === 't' || $r['promotion_enabled'] === 'true';
        $r['target_keywords'] = seo_pg_array_parse($r['target_keywords']);
        $r['seo_priority'] = (int) $r['seo_priority'];
    }
    return $rows;
}

/** Saves the admin settings of one offer. Returns the cleaned values. */
function seo_save_offer(int $id, bool $enabled, string $keywordsRaw, mixed $priorityRaw): array
{
    seo_require_admin();
    $priority = seo_parse_priority($priorityRaw);
    if ($priority === null) throw new InvalidArgumentException('SEO priority must be a whole number from 1 to 100.');
    $keywords = seo_parse_keywords($keywordsRaw);
    $rows = db_query('update seo_offers set promotion_enabled = $2, target_keywords = $3::text[], seo_priority = $4, updated_at = now()
                      where id = $1 returning slug', [$id, $enabled, seo_pg_array($keywords), $priority]);
    if (!$rows) throw new InvalidArgumentException('This offer no longer exists — sync the catalog and try again.');
    seo_log('save_offer', 'info', "Offer {$rows[0]['slug']} saved: promotion " . ($enabled ? 'on' : 'off') . ", priority $priority, " . count($keywords) . ' keyword(s).');
    return ['slug' => $rows[0]['slug'], 'keywords' => $keywords, 'priority' => $priority];
}

/** Offers that campaigns and articles may use: still in the catalog AND promotion enabled. */
function seo_usable_offers(): array
{
    seo_require_admin();
    return db_query('select id, slug, name, path, seo_priority from seo_offers
                     where is_active and promotion_enabled order by seo_priority desc, name');
}

function seo_assert_offer_usable(int $offerId): void
{
    if (!in_array((string) $offerId, array_column(seo_usable_offers(), 'id'), true)) {
        throw new InvalidArgumentException('This offer is not enabled for promotion — turn it on in the Offers tab first.');
    }
}

/**
 * Imports the service catalog (every /services/<slug>/ page in the sitemap) into seo_offers.
 * Upsert only: never deletes rows and never touches the admin's settings
 * (promotion_enabled, target_keywords, seo_priority). Services that left the catalog are marked inactive.
 */
function seo_sync_offers(string $root): array
{
    seo_require_admin();
    $found = [];
    foreach (sitemap_urls($root) as $url) {
        $page = analyse_page($root, $url);
        $offer = seo_offer_from_path($page['path'], $page['title']);
        if ($offer) $found[] = $offer + ['url' => $url, 'description' => $page['description'] ?? null];
    }
    if (!$found) {
        seo_log('sync_offers', 'failed', 'No service pages were found in the sitemap.');
        throw new RuntimeException('No service pages found in sitemap.xml.');
    }
    $values = [];
    $params = [];
    foreach ($found as $i => $o) {
        $b = $i * 5;
        $values[] = sprintf('($%d, $%d, $%d, $%d, $%d, true, now())', $b + 1, $b + 2, $b + 3, $b + 4, $b + 5);
        $path = seo_offer_path($o['slug']);
        array_push($params, $o['slug'], $o['name'], $path, SITE . $path, $o['description']);
    }
    $slugs = array_column($found, 'slug');
    $marks = implode(',', array_map(static fn ($i) => '$' . ($i + 1), array_keys($slugs)));
    [$upserted, $deactivated] = db_batch([
        // Only catalog fields are updated on conflict; admin settings keep their values.
        ['insert into seo_offers (slug, name, path, url, description, is_active, synced_at) values ' . implode(',', $values) . '
          on conflict (slug) do update set name = excluded.name, path = excluded.path, url = excluded.url,
            description = excluded.description, is_active = true, synced_at = now(), updated_at = now()
          returning slug', $params],
        ["update seo_offers set is_active = false, updated_at = now() where is_active and slug not in ($marks) returning slug", $slugs],
    ]);
    $result = ['synced' => count($upserted), 'deactivated' => count($deactivated)];
    seo_log('sync_offers', 'success', "Synced {$result['synced']} service(s) from the sitemap" .
        ($result['deactivated'] ? ", {$result['deactivated']} marked inactive." : '.'), $result + ['slugs' => $slugs]);
    return $result;
}
