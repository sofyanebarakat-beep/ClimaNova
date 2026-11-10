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
    if ($summary['committed'] >= $summary['allowance'] - 1e-9) return false; // used up once spend + reservations REACH the limit
    return $summary['committed'] + max(0.0, $estimate) <= $summary['allowance'] + 1e-9;
}

/**
 * "0.5, 0.8, 95%" → [0.5, 0.8, 0.95]. Fractions (0–1, exclusive) or percentages (1–99 with or without %).
 * Returns [thresholds sorted ascending, errors].
 */
function seo_parse_thresholds(string $raw): array
{
    $out = [];
    $errors = [];
    foreach (preg_split('/[\s,;]+/', trim($raw), -1, PREG_SPLIT_NO_EMPTY) as $t) {
        $pct = str_ends_with($t, '%');
        $num = rtrim($t, '%');
        if (!is_numeric($num)) { $errors[] = "“{$t}” is not a number — use values like 0.5, 0.8, 0.95."; continue; }
        $v = (float) $num;
        if ($pct || $v >= 1) $v /= 100;
        if ($v <= 0 || $v >= 1) { $errors[] = "“{$t}” must be between 0 and 1 (or 1% and 99%)."; continue; }
        $out[(string) round($v, 4)] = round($v, 4);
    }
    $out = array_values($out);
    sort($out);
    if (!$out && !$errors) $errors[] = 'Add at least one warning threshold, for example 0.8.';
    if (count($out) > 5) $errors[] = 'Use 5 warning thresholds or fewer.';
    return [$out, $errors];
}

/** Highest threshold reached by the share of the allowance already committed (null when none). */
function seo_threshold_passed(float $committed, float $allowance, array $thresholds): ?float
{
    if ($allowance <= 0) return null;
    $passed = null;
    foreach ($thresholds as $t) if ($committed / $allowance >= $t - 1e-9) $passed = (float) $t;
    return $passed;
}

/** Allowance in USD: 0–10000 with at most 2 decimals, or null. */
function seo_parse_allowance(string $raw): ?float
{
    $s = str_replace(',', '.', trim($raw, " \t\$"));
    if (!preg_match('/^\d{1,5}(\.\d{1,2})?$/', $s)) return null;
    $v = (float) $s;
    return $v <= 10000 ? $v : null;
}

/* ---------- Automation logs ---------- */

const SEO_LOG_OPERATIONS = [
    'article_generation' => 'Article generation', 'keyword_research' => 'Keyword research', 'weekly_planning' => 'Weekly planning',
    'scheduled_generation' => 'Scheduled generation', 'publishing' => 'Publishing', 'weekly_autopilot' => 'Weekly autopilot',
    'google_sync' => 'Google data sync', 'other' => 'Settings & other',
];
const SEO_LOG_STATUSES = ['running' => 'Running', 'success' => 'Success', 'error' => 'Error', 'skipped' => 'Skipped'];

/** Operation for a job name (same rules as migration 009's backfill). */
function seo_log_operation(string $job): string
{
    return match (true) {
        in_array($job, ['keyword_ideas', 'gsc_ideas', 'add_keyword'], true) => 'keyword_research',
        in_array($job, ['weekly_plan', 'regenerate_topic', 'add_topic'], true) => 'weekly_planning',
        str_contains($job, 'article') || str_starts_with($job, 'rewrite') => 'article_generation',
        str_starts_with($job, 'batch') || str_starts_with($job, 'scheduled_generation') => 'scheduled_generation',
        str_starts_with($job, 'publish') || str_starts_with($job, 'schedule') || str_starts_with($job, 'unpublish') => 'publishing',
        str_starts_with($job, 'autopilot') => 'weekly_autopilot',
        str_starts_with($job, 'gsc') || str_starts_with($job, 'ga4') || str_contains($job, 'analytics') || str_starts_with($job, 'google') => 'google_sync',
        default => 'other',
    };
}

/** Stored status → displayed status (older rows used 'failed' and 'info'). */
function seo_log_status(string $status): string
{
    return match ($status) { 'failed' => 'error', 'info' => 'success', default => $status };
}

function seo_duration(?string $start, ?string $end): string
{
    if (!$start || !$end) return '';
    $s = max(0, strtotime($end) - strtotime($start));
    return $s < 60 ? "{$s} s" : (intdiv($s, 60) . ' min ' . ($s % 60) . ' s');
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

/* ---------- Keywords & topics: text matching ---------- */

const SEO_INTENTS = ['informational' => 'Informational', 'commercial' => 'Commercial', 'transactional' => 'Transactional', 'local' => 'Local', 'navigational' => 'Navigational'];
const SEO_STOPWORDS = ['le', 'la', 'les', 'l', 'de', 'des', 'du', 'd', 'un', 'une', 'et', 'en', 'a', 'au', 'aux', 'pour', 'sur', 'avec',
    'par', 'ou', 'dans', 'votre', 'vos', 'notre', 'nos', 'son', 'sa', 'ses', 'ce', 'cette', 'ces', 'est', 'que', 'qui', 'quoi',
    'comment', 'quel', 'quelle', 'quels', 'quelles', 'pourquoi', 'faut', 'il', 'elle', 'on', 'se', 's', 'y', 'ne', 'pas', 'plus',
    'tout', 'tous', 'guide', 'complet', 'climanova', 'energie', 'the', 'of', 'and', 'to', 'in', 'for'];

/** "Climatisation à Nice : prix 2026 !" → "climatisation a nice prix 2026" */
function seo_normalize(string $text): string
{
    $t = mb_strtolower(trim($text));
    $t = strtr($t, ['à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ç' => 'c', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'î' => 'i', 'ï' => 'i', 'í' => 'i', 'ô' => 'o', 'ö' => 'o', 'ó' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ú' => 'u',
        'ÿ' => 'y', 'ñ' => 'n', 'œ' => 'oe', 'æ' => 'ae', '’' => ' ', "'" => ' ']);
    $t = preg_replace('/[^a-z0-9]+/', ' ', $t);
    return trim(preg_replace('/\s+/', ' ', $t));
}

/** Significant words, singular-ish (trailing s/x dropped), without stopwords. */
function seo_tokens(string $text): array
{
    $out = [];
    foreach (explode(' ', seo_normalize($text)) as $w) {
        if ($w === '' || in_array($w, SEO_STOPWORDS, true)) continue;
        if (strlen($w) > 3) $w = preg_replace('/[sx]$/', '', $w);
        $out[$w] = true;
    }
    return array_keys($out);
}

/**
 * Do two keywords/titles say the same thing? Same normalized text, one containing the other (for short phrases),
 * or ≥ $threshold of shared significant words (Jaccard).
 */
function seo_overlaps(string $a, string $b, float $threshold = 0.6): bool
{
    $na = seo_normalize($a);
    $nb = seo_normalize($b);
    if ($na === '' || $nb === '') return false;
    if ($na === $nb) return true;
    $ta = seo_tokens($a);
    $tb = seo_tokens($b);
    if (!$ta || !$tb) return false;
    $inter = count(array_intersect($ta, $tb));
    if ($inter === min(count($ta), count($tb)) && min(count($ta), count($tb)) >= 2 && max(count($ta), count($tb)) <= 4) return true;
    return $inter / count(array_unique(array_merge($ta, $tb))) >= $threshold;
}

function seo_overlaps_any(string $text, array $others, float $threshold = 0.6): bool
{
    foreach ($others as $o) if (seo_overlaps($text, (string) $o, $threshold)) return true;
    return false;
}

function seo_is_brand_query(string $query): bool
{
    return (bool) preg_match('/clima\s*nova|climanova/', seo_normalize($query));
}

/** Up to $limit titles that share the most significant words with $terms (offer names + target keywords). */
function seo_related_titles(array $titles, array $terms, int $limit = 60): array
{
    $vocab = [];
    foreach ($terms as $t) foreach (seo_tokens((string) $t) as $w) $vocab[$w] = true;
    $scored = [];
    foreach (array_unique($titles) as $i => $title) {
        $score = count(array_intersect(seo_tokens((string) $title), array_keys($vocab)));
        if ($score > 0) $scored[] = [$score, $i, $title];
    }
    usort($scored, static fn ($a, $b) => [$b[0], $a[1]] <=> [$a[0], $b[1]]);
    return array_column(array_slice($scored, 0, $limit), 2);
}

/**
 * Search Console query rows → idea rows: brand searches and anything overlapping existing keywords removed,
 * top $limit by impressions.
 */
function seo_gsc_ideas(array $rows, array $existing, int $limit = 25): array
{
    $ideas = [];
    foreach ($rows as $r) {
        $q = trim((string) ($r['keys'][0] ?? $r['query'] ?? ''));
        if ($q === '' || seo_is_brand_query($q) || seo_overlaps_any($q, $existing)) continue;
        $ideas[] = ['query' => $q, 'impressions' => (int) $r['impressions'], 'clicks' => (int) $r['clicks'], 'position' => round((float) $r['position'], 1)];
    }
    usort($ideas, static fn ($a, $b) => [$b['impressions'], $b['clicks']] <=> [$a['impressions'], $a['clicks']]);
    return array_slice($ideas, 0, $limit);
}

/* ---------- Weekly plan ---------- */

function seo_monday(string $date): string
{
    return (new DateTimeImmutable($date))->modify('monday this week')->format('Y-m-d');
}

/** Weeks since the campaign's first Monday (0 for the first week); drives the offer rotation. */
function seo_week_index(string $campaignStart, string $monday): int
{
    $d = (new DateTimeImmutable(seo_monday($campaignStart)))->diff(new DateTimeImmutable($monday));
    return intdiv((int) $d->days, 7) * ($d->invert ? -1 : 1);
}

/**
 * Plans the open slots of one week.
 *   $campaign: weekly_target, publish_days (1-7), publish_times, start_date, end_date (nullable), week_index
 *   $offers:   offer ids in priority order (enabled offers of the campaign)
 *   $keywords: unused keywords [['id', 'offer_id' (null = campaign-wide)]] in priority order
 *   $taken:    topics already planned that week (not rejected): [['date' => Y-m-d, 'offer_id' => id], …]
 * Returns [['date', 'time', 'offer_id', 'keyword_id'], …] for at most (target − taken) FREE slots.
 * Balancing: each slot goes to the offer with the fewest topics this week; ties follow the rotation order,
 * which starts at (week index mod offer count) so each week begins with the next offer.
 * Each keyword is used once; an offer with no keyword left falls back to a campaign-wide keyword, then to none.
 */
function seo_plan_week(array $campaign, string $monday, array $offers, array $keywords, array $taken = []): array
{
    if (!$offers) return [];
    $need = (int) $campaign['weekly_target'] - count($taken);
    if ($need <= 0) return [];
    $start = new DateTimeImmutable($monday);
    $days = array_map('intval', $campaign['publish_days']);
    sort($days);
    $times = $campaign['publish_times'];
    sort($times);
    $usedPerDate = array_count_values(array_column($taken, 'date'));
    $slots = [];
    foreach ($days as $d) {
        $date = $start->modify('+' . ($d - 1) . ' days')->format('Y-m-d');
        if ($date < $campaign['start_date'] || (!empty($campaign['end_date']) && $date > $campaign['end_date'])) continue;
        foreach (array_slice($times, $usedPerDate[$date] ?? 0) as $t) $slots[] = ['date' => $date, 'time' => $t];
    }
    $slots = array_slice($slots, 0, $need);

    $byOffer = [];
    $wide = [];
    foreach ($keywords as $k) {
        if ($k['offer_id'] === null || $k['offer_id'] === '') $wide[] = (int) $k['id'];
        else $byOffer[(int) $k['offer_id']][] = (int) $k['id'];
    }
    $n = count($offers);
    $offset = (($campaign['week_index'] ?? 0) % $n + $n) % $n;
    $order = array_map('intval', array_merge(array_slice($offers, $offset), array_slice($offers, 0, $offset)));
    $count = array_fill_keys($order, 0);
    foreach ($taken as $t) if (isset($count[(int) $t['offer_id']])) $count[(int) $t['offer_id']]++;
    $cursor = 0;
    foreach ($slots as &$s) {
        $best = null;
        for ($j = 0; $j < $n; $j++) {               // fewest topics wins; ties → next offer in rotation order
            $o = $order[($cursor + $j) % $n];
            if ($best === null || $count[$o] < $count[$best]) $best = $o;
        }
        $count[$best]++;
        $cursor = (array_search($best, $order, true) + 1) % $n;
        $s['offer_id'] = $best;
        $s['keyword_id'] = !empty($byOffer[$best]) ? array_shift($byOffer[$best]) : (array_shift($wide) ?? null);
    }
    return $slots;
}

/** Manual/edited topic fields → [clean, errors]. */
function seo_validate_topic(array $in): array
{
    $e = [];
    $title = trim(preg_replace('/\s+/u', ' ', (string) ($in['title'] ?? '')));
    $angle = trim((string) ($in['angle'] ?? ''));
    $date = trim((string) ($in['planned_for'] ?? ''));
    if (mb_strlen($title) < 10 || mb_strlen($title) > 160) $e[] = 'Topic title must be between 10 and 160 characters.';
    if (mb_strlen($angle) > 600) $e[] = 'The angle must be 600 characters or fewer.';
    if (!seo_valid_date($date)) $e[] = 'Choose a valid date for the topic.';
    return [['title' => $title, 'angle' => $angle, 'planned_for' => $date], $e];
}
