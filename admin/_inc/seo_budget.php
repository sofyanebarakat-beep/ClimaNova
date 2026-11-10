<?php
// API Budget + Automation Logs server functions (admin only).
declare(strict_types=1);

require_once __DIR__ . '/seo_agent.php';

function seo_budget_page_data(): array
{
    seo_require_admin();
    [$settings, $usage, $byPurpose, $recent] = db_batch([
        ["select value from seo_settings where key = 'budget'"],
        ["select recorded_usd, reserved_usd, requests, failed_requests, invalid_outputs, input_tokens, output_tokens
            from seo_monthly_usage where month = date_trunc('month', now() at time zone 'Europe/Paris')::date"],
        ["select purpose, count(*) filter (where status in ('succeeded', 'recorded')) as answered,
                 count(*) filter (where status = 'failed') as failed,
                 count(*) filter (where metadata ->> 'outcome' = 'invalid_output') as invalid,
                 coalesce(sum(cost_usd) filter (where status in ('succeeded', 'recorded')), 0) as cost
            from seo_api_usage
           where created_at >= date_trunc('month', now() at time zone 'Europe/Paris') at time zone 'Europe/Paris'
           group by purpose order by cost desc, answered desc"],
        ["select created_at, purpose, provider, model, status, metadata ->> 'outcome' as outcome, reserved_usd, cost_usd,
                 input_tokens, output_tokens
            from seo_api_usage order by id desc limit 25"],
    ], true);
    $value = json_decode($settings[0]['value'] ?? '{}', true) ?: [];
    return ['settings' => seo_budget_settings($value), 'budget' => seo_budget_from($value, $usage[0] ?? null),
        'usage' => $usage[0] ?? [], 'by_purpose' => $byPurpose, 'recent' => $recent];
}

/** Validates and saves the budget. Returns errors (one sentence each); empty = saved. */
function seo_save_budget(string $allowanceRaw, string $thresholdsRaw, bool $hardStop): array
{
    seo_require_admin();
    $errors = [];
    $allowance = seo_parse_allowance($allowanceRaw);
    if ($allowance === null) $errors[] = 'The monthly allowance must be an amount in USD from 0 to 10000, for example 1 or 5.50.';
    [$thresholds, $te] = seo_parse_thresholds($thresholdsRaw);
    array_push($errors, ...$te);
    if ($errors) return $errors;

    $old = seo_budget_settings(json_decode(db_query("select value from seo_settings where key = 'budget'")[0]['value'] ?? '{}', true) ?: []);
    $value = ['monthly_allowance_usd' => $allowance, 'warning_thresholds' => $thresholds,
        'warning_threshold_pct' => round($thresholds[0] * 100), 'hard_stop' => $hardStop];
    db_query("insert into seo_settings (key, value) values ('budget', $1::jsonb)
              on conflict (key) do update set value = seo_settings.value || excluded.value, updated_at = now()", [json_encode($value)]);
    $changes = [];
    if (abs($old['allowance'] - $allowance) > 1e-9) $changes[] = sprintf('allowance $%s → $%s', number_format($old['allowance'], 2), number_format($allowance, 2));
    if ($old['thresholds'] !== $thresholds) $changes[] = 'warnings at ' . implode(', ', array_map(static fn ($t) => round($t * 100) . '%', $thresholds));
    if ($old['hard_stop'] !== $hardStop) $changes[] = 'hard stop ' . ($hardStop ? 'on' : 'off');
    seo_log('budget_settings', 'success', $changes ? 'API budget updated: ' . implode('; ', $changes) . '.' : 'API budget saved without changes.', $value);
    return [];
}

/** Automation logs with optional filters, newest first, 50 per page (keyset pagination on id). */
function seo_list_logs(string $operation, string $status, ?int $beforeId): array
{
    seo_require_admin();
    $where = ['true'];
    $params = [];
    if (isset(SEO_LOG_OPERATIONS[$operation])) { $params[] = $operation; $where[] = 'coalesce(operation, \'other\') = $' . count($params); }
    if (isset(SEO_LOG_STATUSES[$status])) {
        $stored = ['error' => ['error', 'failed'], 'success' => ['success', 'info']][$status] ?? [$status];
        $params[] = '{' . implode(',', $stored) . '}';
        $where[] = 'status = any($' . count($params) . '::text[])';
    }
    if ($beforeId) { $params[] = $beforeId; $where[] = 'id < $' . count($params); }
    $rows = db_query('select id, job, operation, status, message, created_at, started_at, finished_at
                        from seo_automation_logs where ' . implode(' and ', $where) . ' order by id desc limit 51', $params);
    $more = count($rows) > 50;
    return ['rows' => array_slice($rows, 0, 50), 'more' => $more];
}
