<?php
// Analytics tab: syncs Search Console + GA4 into Neon and reads the figures for the page.
declare(strict_types=1);

require_once __DIR__ . '/seo_agent.php';
require_once __DIR__ . '/google.php';

const SEO_SYNC_STALE_HOURS = 20;

/** True when the last sync attempt is older than 20 hours (or never happened). */
function seo_analytics_stale(array $state): bool
{
    $last = $state['last_attempt_at'] ?? null;
    return !$last || strtotime($last) < time() - SEO_SYNC_STALE_HOURS * 3600;
}

/** Saves the Google connection (admin only). */
function seo_save_google_connection(string $keyJson, string $site, string $property): void
{
    seo_require_admin();
    google_save_settings($keyJson, $site, $property);
    seo_log('analytics_connect', 'info', 'Google connection settings saved.');
}

/** Path → [article_id, offer_id] for every SEO article slug and offer page. */
function seo_page_owner_map(): array
{
    [$articles, $offers] = db_batch([
        ["select id, slug from seo_articles where slug is not null"],
        ['select id, path from seo_offers where path is not null'],
    ], true);
    $map = [];
    foreach ($articles as $a) $map['/blog/' . $a['slug'] . '/'] = [(int) $a['id'], null];
    foreach ($offers as $o) {
        $k = seo_page_key((string) $o['path']);
        if ($k !== null) $map[$k] = [null, (int) $o['id']];
    }
    return $map;
}

/** Replaces one source's rows for the date range — only called with non-empty data, so an empty answer never wipes old data. */
function seo_replace_daily(string $source, string $start, string $end, array $rows): void
{
    db_batch([
        ['delete from seo_analytics_daily where source = $1 and day between $2::date and $3::date', [$source, $start, $end]],
        ["insert into seo_analytics_daily (source, day, page_path, clicks, impressions, position, sessions, engaged_sessions, article_id, offer_id)
          select $1, x.day, x.page_path, x.clicks, x.impressions, x.position, x.sessions, x.engaged_sessions, x.article_id, x.offer_id
            from json_to_recordset($2::json) as x(day date, page_path text, clicks int, impressions int, position real, sessions int,
                                                   engaged_sessions int, article_id bigint, offer_id bigint)
          on conflict (source, day, page_path) do nothing", [$source, json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]],
    ]);
}

/** Merges URL variants and attaches article/offer ids. */
function seo_page_rows(array $raw, array $owners): array
{
    $rows = [];
    foreach ($raw as $r) {
        $key = seo_page_key($r['url']);
        if ($key === null) continue;
        $rows[] = ['day' => $r['day'], 'page_path' => $key] + $r;
    }
    $merged = seo_merge_page_rows($rows);
    foreach ($merged as &$m) {
        unset($m['url']);
        [$m['article_id'], $m['offer_id']] = $owners[$m['page_path']] ?? [null, null];
    }
    return $merged;
}

/**
 * Pulls the last 30 complete days from Search Console and GA4. Only one sync runs at a time.
 * Returns ['ok' => bool, 'message' => sentence].
 */
function seo_analytics_sync(string $trigger = 'manual'): array
{
    seo_require_admin();
    @set_time_limit(180);
    $claimed = db_query("update seo_settings
        set value = value || jsonb_build_object('running', true, 'started_at', now(), 'last_attempt_at', now()), updated_at = now()
        where key = 'analytics_sync'
          and (coalesce((value ->> 'running')::boolean, false) = false or (value ->> 'started_at')::timestamptz < now() - interval '10 minutes')
        returning key");
    if (!$claimed) return ['ok' => false, 'message' => 'A sync is already running — wait a minute and reload.'];

    $cfg = google_config();
    $tz = new DateTimeZone('Europe/Paris');
    $end = (new DateTimeImmutable('yesterday', $tz))->format('Y-m-d');
    $start = (new DateTimeImmutable('yesterday', $tz))->modify('-29 days')->format('Y-m-d');
    $parts = [];
    $errors = [];
    try {
        if (!$cfg['sa']) throw new GoogleError('Connect Google first: paste the service account key below.');
        $owners = seo_page_owner_map();

        // Search Console
        if ($cfg['gsc_site']) {
            try {
                $totals = google_gsc_rows($cfg, ['date'], $start, $end);
                $pages = google_gsc_rows($cfg, ['date', 'page'], $start, $end);
                $rows = array_map(static fn ($r) => ['day' => $r['keys'][0], 'page_path' => '', 'clicks' => (int) $r['clicks'], 'impressions' => (int) $r['impressions'],
                    'position' => round((float) $r['position'], 2), 'sessions' => 0, 'engaged_sessions' => 0, 'article_id' => null, 'offer_id' => null], $totals);
                $pageRows = seo_page_rows(array_map(static fn ($r) => ['day' => $r['keys'][0], 'url' => $r['keys'][1], 'clicks' => (int) $r['clicks'],
                    'impressions' => (int) $r['impressions'], 'position' => (float) $r['position'], 'sessions' => 0, 'engaged_sessions' => 0], $pages), $owners);
                if ($rows || $pageRows) {
                    seo_replace_daily('gsc', $start, $end, array_merge($rows, $pageRows));
                    $parts[] = 'Search Console: ' . count($rows) . ' days, ' . count($pageRows) . ' page rows';
                } else {
                    $parts[] = 'Search Console returned no data (old data kept)';
                }
            } catch (GoogleError $e) {
                $errors[] = 'Search Console: ' . $e->getMessage();
            }
        } else {
            $errors[] = 'Search Console: add GSC_SITE_URL.';
        }

        // GA4
        if ($cfg['ga4_property']) {
            try {
                $ymd = static fn (string $d) => substr($d, 0, 4) . '-' . substr($d, 4, 2) . '-' . substr($d, 6, 2);
                $totals = google_ga4_rows($cfg, ['date'], ['sessions', 'engagedSessions'], $start, $end);
                $landing = google_ga4_rows($cfg, ['date', 'landingPage'], ['sessions', 'engagedSessions'], $start, $end);
                $events = google_ga4_rows($cfg, ['date', 'eventName'], ['eventCount', 'keyEvents'], $start, $end);
                $rows = array_map(static fn ($r) => ['day' => $ymd($r['dims'][0]), 'page_path' => '', 'clicks' => 0, 'impressions' => 0, 'position' => null,
                    'sessions' => (int) $r['metrics'][0], 'engaged_sessions' => (int) $r['metrics'][1], 'article_id' => null, 'offer_id' => null], $totals);
                $pageRows = seo_page_rows(array_map(static fn ($r) => ['day' => $ymd($r['dims'][0]), 'url' => $r['dims'][1], 'clicks' => 0, 'impressions' => 0,
                    'position' => null, 'sessions' => (int) $r['metrics'][0], 'engaged_sessions' => (int) $r['metrics'][1]],
                    array_filter($landing, static fn ($r) => str_starts_with($r['dims'][1], '/'))), $owners);
                foreach ($pageRows as &$p) $p['position'] = null;
                unset($p);
                if ($rows || $pageRows) {
                    seo_replace_daily('ga4', $start, $end, array_merge($rows, $pageRows));
                    $parts[] = 'GA4: ' . count($rows) . ' days, ' . count($pageRows) . ' landing-page rows';
                } else {
                    $parts[] = 'GA4 returned no data (old data kept)';
                }
                $ev = array_map(static fn ($r) => ['day' => $ymd($r['dims'][0]), 'event_name' => mb_substr($r['dims'][1], 0, 100),
                    'event_count' => (int) $r['metrics'][0], 'key_events' => (int) $r['metrics'][1]], $events);
                if ($ev) {
                    db_batch([
                        ['delete from seo_analytics_events where day between $1::date and $2::date', [$start, $end]],
                        ['insert into seo_analytics_events (day, event_name, event_count, key_events)
                          select x.day, x.event_name, x.event_count, x.key_events from json_to_recordset($1::json) as x(day date, event_name text, event_count int, key_events int)
                          on conflict do nothing', [json_encode($ev, JSON_UNESCAPED_UNICODE)]],
                    ]);
                }
            } catch (GoogleError $e) {
                $errors[] = 'GA4: ' . $e->getMessage();
            }
        } else {
            $parts[] = 'GA4 not connected yet (add the GA4 property ID)';
        }
    } catch (Throwable $e) {
        $errors[] = $e instanceof GoogleError ? $e->getMessage() : admin_error($e, 'sync Google data');
    }

    $ok = !$errors;
    $message = trim(implode('; ', $parts) . ($parts && $errors ? '. ' : '') . implode(' ', $errors));
    $message = $message === '' ? 'Nothing to sync.' : $message;
    db_query("update seo_settings set value = value || jsonb_build_object('running', false, 'last_finished_at', now(), 'last_ok', $1::boolean,
              'last_message', $2::text, 'range', $3::text) || case when $1::boolean then jsonb_build_object('last_success_at', now()) else '{}'::jsonb end,
              updated_at = now() where key = 'analytics_sync'", [$ok, $message, "$start → $end"]);
    seo_log('analytics_sync', $ok ? 'success' : ($parts ? 'info' : 'failed'), "Google sync ($trigger, $start → $end): $message");
    return ['ok' => $ok, 'message' => $message];
}

/** Everything the Analytics page shows (last 30 days). */
function seo_analytics_page(): array
{
    seo_require_admin();
    [$totals, $top, $events, $manual, $state] = db_batch([
        ["select source, sum(clicks)::int as clicks, sum(impressions)::int as impressions, sum(sessions)::int as sessions,
                 sum(engaged_sessions)::int as engaged, min(day) as first_day, max(day) as last_day
            from seo_analytics_daily where page_path = '' and day >= current_date - 30 group by source"],
        ["select kind, id, name, url, impressions, clicks, position, sessions from (
            select 'Article' as kind, a.id, a.title as name, coalesce(a.published_url, '/blog/' || a.slug || '/') as url,
                   sum(d.impressions) filter (where d.source = 'gsc')::int as impressions, sum(d.clicks) filter (where d.source = 'gsc')::int as clicks,
                   (sum(d.position * d.impressions) filter (where d.source = 'gsc') / nullif(sum(d.impressions) filter (where d.source = 'gsc'), 0))::numeric(6,1) as position,
                   sum(d.sessions) filter (where d.source = 'ga4')::int as sessions
              from seo_analytics_daily d join seo_articles a on a.id = d.article_id where d.day >= current_date - 30 group by a.id
            union all
            select 'Offer', o.id, o.name, o.path,
                   sum(d.impressions) filter (where d.source = 'gsc')::int, sum(d.clicks) filter (where d.source = 'gsc')::int,
                   (sum(d.position * d.impressions) filter (where d.source = 'gsc') / nullif(sum(d.impressions) filter (where d.source = 'gsc'), 0))::numeric(6,1),
                   sum(d.sessions) filter (where d.source = 'ga4')::int
              from seo_analytics_daily d join seo_offers o on o.id = d.offer_id where d.day >= current_date - 30 group by o.id
          ) t order by coalesce(impressions, 0) desc, coalesce(sessions, 0) desc limit 50"],
        ["select day, event_name, event_count, key_events from seo_analytics_events where day >= current_date - 30 order by day desc, event_name"],
        ["select occurred_at, source, event_name, page_path, value from seo_conversions where occurred_at >= now() - interval '30 days' order by occurred_at desc limit 50"],
        ["select value from seo_settings where key = 'analytics_sync'"],
    ], true);
    $bySource = array_column($totals, null, 'source');
    $conv = ['consultation' => 0, 'registration' => 0];
    $recent = [];
    foreach ($events as $e) {
        $kind = seo_conversion_kind($e['event_name']);
        if (!$kind) continue;
        $n = (int) $e['key_events'] > 0 ? (int) $e['key_events'] : (int) $e['event_count'];
        $conv[$kind] += $n;
        if ($n > 0) $recent[] = ['when' => $e['day'], 'what' => $e['event_name'], 'kind' => $kind, 'count' => $n, 'source' => 'GA4', 'page' => ''];
    }
    foreach ($manual as $m) {
        $kind = seo_conversion_kind((string) $m['event_name']) ?? 'consultation';
        $conv[$kind]++;
        $recent[] = ['when' => substr($m['occurred_at'], 0, 16), 'what' => $m['event_name'] ?: 'conversion', 'kind' => $kind, 'count' => 1, 'source' => $m['source'], 'page' => (string) $m['page_path']];
    }
    usort($recent, static fn ($a, $b) => strcmp($b['when'], $a['when']));
    return [
        'gsc' => $bySource['gsc'] ?? null, 'ga4' => $bySource['ga4'] ?? null, 'conversions' => $conv,
        'top' => $top, 'recent' => array_slice($recent, 0, 25),
        'state' => json_decode((string) ($state[0]['value'] ?? '{}'), true) ?: [],
    ];
}
