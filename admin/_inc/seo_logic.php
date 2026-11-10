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

const SEO_CAMPAIGN_STATUSES = ['draft' => 'Draft', 'active' => 'Active', 'paused' => 'Paused', 'completed' => 'Completed'];
const SEO_LANGUAGES = ['fr' => 'French', 'en' => 'English'];
const SEO_WEEKDAYS = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];

/** "fr, BE,fr" → ['FR', 'BE'] plus errors for anything that is not two letters. */
function seo_parse_countries(string $raw): array
{
    $codes = [];
    $errors = [];
    foreach (preg_split('/[\s,;]+/', trim($raw), -1, PREG_SPLIT_NO_EMPTY) as $c) {
        $u = strtoupper($c);
        if (!preg_match('/^[A-Z]{2}$/', $u)) { $errors[] = "“{$c}” is not a 2-letter country code (for example FR)."; continue; }
        $codes[$u] = true;
    }
    return [array_keys($codes), $errors];
}

/** "17:00, 9:00" → ['09:00', '17:00'] (sorted) plus errors. Duplicates are an error, not silently merged. */
function seo_parse_times(string $raw): array
{
    $times = [];
    $errors = [];
    foreach (preg_split('/[\s,;]+/', trim($raw), -1, PREG_SPLIT_NO_EMPTY) as $t) {
        if (!preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $t, $m)) { $errors[] = "“{$t}” is not a time in HH:mm format (for example 09:00)."; continue; }
        $norm = sprintf('%02d:%s', (int) $m[1], $m[2]);
        if (in_array($norm, $times, true)) { $errors[] = "The publishing time {$norm} is listed twice."; continue; }
        $times[] = $norm;
    }
    sort($times);
    return [$times, $errors];
}

function seo_valid_timezone(string $tz): bool
{
    return $tz !== '' && in_array($tz, DateTimeZone::listIdentifiers(), true);
}

function seo_valid_date(string $d): bool
{
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $d);
    return $dt !== false && $dt->format('Y-m-d') === $d;
}

/**
 * Validates the campaign form. $usableOfferIds = ids of offers that are enabled and in the catalog.
 * Returns ['data' => cleaned values, 'errors' => one sentence each].
 */
function seo_validate_campaign(array $in, array $usableOfferIds): array
{
    $e = [];
    $name = trim(preg_replace('/\s+/u', ' ', (string) ($in['name'] ?? '')));
    $len = mb_strlen($name);
    if ($len < 5 || $len > 120) $e[] = 'Campaign name must be between 5 and 120 characters.';

    $goal = trim((string) ($in['goal'] ?? ''));
    if (mb_strlen($goal) > 500) $e[] = 'Campaign goal must be 500 characters or fewer.';

    $language = (string) ($in['language'] ?? '');
    if (!isset(SEO_LANGUAGES[$language])) $e[] = 'Choose a language from the list.';

    [$countries, $ce] = seo_parse_countries((string) ($in['countries'] ?? ''));
    array_push($e, ...$ce);
    if (!$ce && !$countries) $e[] = 'Add at least one target country code (for example FR).';
    if (count($countries) > 20) $e[] = 'Use 20 target countries or fewer.';

    $weekly = filter_var($in['weekly_target'] ?? '', FILTER_VALIDATE_INT);
    if ($weekly === false || $weekly < 1 || $weekly > 12) { $e[] = 'Weekly article target must be a whole number from 1 to 12.'; $weekly = null; }

    $start = trim((string) ($in['start_date'] ?? ''));
    $end = trim((string) ($in['end_date'] ?? ''));
    if (!seo_valid_date($start)) $e[] = 'Choose a valid start date.';
    if ($end !== '' && !seo_valid_date($end)) $e[] = 'Choose a valid end date, or leave it empty.';
    if (seo_valid_date($start) && seo_valid_date($end) && $end < $start) $e[] = 'The end date cannot be before the start date.';

    $rawDays = (array) ($in['days'] ?? []);
    $days = [];
    $badDay = false;
    foreach ($rawDays as $d) {
        $n = filter_var($d, FILTER_VALIDATE_INT);
        if ($n === false || $n < 1 || $n > 7) { $badDay = true; continue; }
        $days[] = $n;
    }
    if ($badDay) $e[] = 'Publishing days must be weekdays from Monday to Sunday.';
    if (count($days) !== count(array_unique($days))) $e[] = 'Each publishing day can only be chosen once.';
    $days = array_values(array_unique($days));
    sort($days);
    if (!$days && !$badDay) $e[] = 'Choose at least one publishing day.';

    [$times, $te] = seo_parse_times((string) ($in['times'] ?? ''));
    array_push($e, ...$te);
    if (!$te && !$times) $e[] = 'Add at least one publishing time, for example 09:00.';
    if (count($times) > 4) $e[] = 'Use 4 publishing times or fewer.';

    if ($weekly !== null && $days && $times && $weekly > count($days) * count($times)) {
        $slots = count($days) * count($times);
        $e[] = "A weekly target of {$weekly} needs at least {$weekly} publishing slots; you have {$slots} (days × times).";
    }

    $tz = trim((string) ($in['time_zone'] ?? ''));
    if (!seo_valid_timezone($tz)) $e[] = "“{$tz}” is not a valid time zone (for example Europe/Paris).";

    $status = (string) ($in['status'] ?? '');
    if (!isset(SEO_CAMPAIGN_STATUSES[$status])) $e[] = 'Choose a status from the list.';

    $offers = array_values(array_unique(array_map('intval', (array) ($in['offers'] ?? []))));
    $usable = array_map('intval', $usableOfferIds);
    if (!$offers) {
        $e[] = 'Choose at least one offer to promote.';
    } elseif (array_diff($offers, $usable)) {
        $e[] = 'One of the chosen offers is not enabled for promotion — enable it in the Offers tab first.';
    }
    sort($offers);

    return [
        'data' => [
            'name' => $name, 'goal' => $goal, 'language' => $language, 'countries' => $countries,
            'weekly_target' => $weekly, 'start_date' => $start, 'end_date' => $end === '' ? null : $end,
            'days' => $days, 'times' => $times, 'time_zone' => $tz, 'status' => $status, 'offers' => $offers,
            'automation_enabled' => !empty($in['automation_enabled']), 'auto_publish' => !empty($in['auto_publish']),
        ],
        'errors' => $e,
    ];
}

function seo_days_label(array $days): string
{
    $days = array_map('intval', $days);
    sort($days);
    if ($days === [1, 2, 3, 4, 5, 6, 7]) return 'Every day';
    if ($days === [1, 2, 3, 4, 5]) return 'Mon–Fri';
    return implode(', ', array_map(static fn ($d) => SEO_WEEKDAYS[$d] ?? '?', $days));
}

function seo_money(float $usd): string
{
    return '$' . number_format($usd, 2, '.', ',');
}
