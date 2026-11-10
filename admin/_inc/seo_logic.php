<?php
// Pure SEO-agent logic: no database, no network. Covered by tests/seo_logic_test.php.
declare(strict_types=1);

/**
 * Budget state for the month.
 * - committed = recorded spend + open reservations (what new requests must fit under)
 * - state: 'exhausted' when committed >= allowance, 'warning' when past the threshold, else 'ok'
 * - blocked: true when hard stop is on and the allowance is used up
 */
function seo_budget_summary(float $allowance, float $recorded, float $reserved, float $warningPct, bool $hardStop): array
{
    $allowance = max(0.0, $allowance);
    $committed = round($recorded + $reserved, 6);
    $remaining = round(max(0.0, $allowance - $committed), 6);
    $usedPct = $allowance > 0 ? min(100.0, $committed / $allowance * 100) : ($committed > 0 ? 100.0 : 0.0);
    $state = 'ok';
    if ($allowance <= 0 || $committed >= $allowance) {
        $state = 'exhausted';
    } elseif ($usedPct >= $warningPct) {
        $state = 'warning';
    }
    return [
        'allowance' => $allowance,
        'recorded' => round($recorded, 6),
        'reserved' => round($reserved, 6),
        'committed' => $committed,
        'remaining' => $remaining,
        'used_pct' => round($usedPct, 1),
        'warning_pct' => $warningPct,
        'hard_stop' => $hardStop,
        'state' => $state,
        'blocked' => $hardStop && $state === 'exhausted',
    ];
}

/** Can a request with this estimated max cost start? Hard stop forbids going over the allowance. */
function seo_budget_allows(array $summary, float $estimate): bool
{
    if (!$summary['hard_stop']) return true;
    return $summary['committed'] + max(0.0, $estimate) <= $summary['allowance'] + 1e-9;
}

/** Monday 00:00 → next Monday 00:00 of the week containing $now, in the given time zone. */
function seo_week_bounds(DateTimeImmutable $now, string $tz = 'Europe/Paris'): array
{
    $local = $now->setTimezone(new DateTimeZone($tz));
    $start = $local->modify('monday this week')->setTime(0, 0);
    return [$start, $start->modify('+7 days')];
}

/** Offer slug and display name from a service page path like /services/climatisation/. */
function seo_offer_from_path(string $path, string $title): ?array
{
    if (!preg_match('~^/services/([a-z0-9-]+)/?$~', $path, $m)) return null;
    $name = trim(preg_split('~\s+[—|–-]\s+~u', $title)[0] ?? '');
    return ['slug' => $m[1], 'name' => $name !== '' ? $name : ucfirst(str_replace('-', ' ', $m[1]))];
}

/** Public page path of an offer, built from OFFER_PAGE_PATH (https://climanova-energie.fr/services/<slug>/). */
function seo_offer_path(string $slug): string
{
    return '/services/' . trim($slug, '/') . '/';
}

/**
 * "clim nice, Clim Nice ,, pompe à chaleur" → ['clim nice', 'pompe à chaleur']
 * Trims, collapses spaces, drops empties and case-insensitive duplicates, max 30 keywords of 80 chars.
 */
function seo_parse_keywords(string $raw): array
{
    $seen = [];
    $out = [];
    foreach (preg_split('/[,\n;]+/u', $raw) as $k) {
        $k = trim(preg_replace('/\s+/u', ' ', $k));
        if ($k === '') continue;
        $k = mb_substr($k, 0, 80);
        $key = mb_strtolower($k);
        if (isset($seen[$key])) continue;
        $seen[$key] = true;
        $out[] = $k;
        if (count($out) === 30) break;
    }
    return $out;
}

/** Whole number 1–100, or null when invalid. */
function seo_parse_priority(mixed $raw): ?int
{
    $s = trim((string) $raw);
    if (!preg_match('/^\d{1,3}$/', $s)) return null;
    $n = (int) $s;
    return $n >= 1 && $n <= 100 ? $n : null;
}

/** Postgres text[] literal ↔ PHP array (keywords never contain quotes-escaping surprises after this). */
function seo_pg_array(array $items): string
{
    return '{' . implode(',', array_map(static fn ($s) => '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $s) . '"', $items)) . '}';
}

function seo_pg_array_parse(?string $lit): array
{
    if ($lit === null || $lit === '{}' || $lit === '') return [];
    $inner = substr($lit, 1, -1);
    preg_match_all('/"((?:[^"\\\\]|\\\\.)*)"|([^,]+)/', $inner, $m, PREG_SET_ORDER);
    return array_map(static fn ($x) => isset($x[2]) && $x[2] !== '' ? $x[2] : stripcslashes($x[1]), $m);
}

function seo_money(float $usd): string
{
    return '$' . number_format($usd, 2, '.', ',');
}
