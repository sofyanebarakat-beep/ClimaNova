<?php
// Google Search Console + Google Analytics 4 Data API with a service account (signed RS256 JWT, openssl + curl, no SDK).
// Server secrets only: GOOGLE_SERVICE_ACCOUNT_JSON, GSC_SITE_URL, GA4_PROPERTY_ID — from the environment, or from
// .env.google outside public_html (written by the Analytics tab). The older .gsc.json (Paramètres) is used as a fallback.
declare(strict_types=1);

require_once __DIR__ . '/seo_article_logic.php';
require_once __DIR__ . '/gsc.php';

/** Thrown with a plain one-sentence message that is safe to show an admin. */
final class GoogleError extends RuntimeException {}

const GOOGLE_SCOPES = 'https://www.googleapis.com/auth/webmasters.readonly https://www.googleapis.com/auth/analytics.readonly';

function google_env_file_server(): string
{
    $docroot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    return ($docroot !== '' ? dirname($docroot) : dirname(__DIR__, 3)) . '/.env.google';
}

/** KEY=value lines (value may be wrapped in ' or "). Later files win. */
function google_env_values(): array
{
    $vars = [];
    foreach ([dirname(__DIR__, 2) . '/.env.local', dirname(__DIR__, 2) . '/.env.google', google_env_file_server()] as $f) {
        if (!is_file($f) || !is_readable($f)) continue;
        foreach (file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if (!preg_match('/^\s*(GOOGLE_SERVICE_ACCOUNT_JSON|GSC_SITE_URL|GA4_PROPERTY_ID)\s*=\s*(.*)$/', $line, $m)) continue;
            $v = trim($m[2]);
            if (strlen($v) >= 2 && ($v[0] === "'" || $v[0] === '"') && substr($v, -1) === $v[0]) $v = substr($v, 1, -1);
            $vars[$m[1]] = $v;
        }
    }
    return $vars;
}

function google_env(string $name): ?string
{
    $v = getenv($name);
    if ($v !== false && $v !== '') return $v;
    $f = google_env_values()[$name] ?? null;
    return $f !== null && $f !== '' ? $f : null;
}

/** ['sa' => ?array, 'email' => ?string, 'gsc_site' => ?string, 'ga4_property' => ?string] */
function google_config(): array
{
    $sa = null;
    $json = google_env('GOOGLE_SERVICE_ACCOUNT_JSON');
    if ($json !== null) {
        $d = json_decode($json, true);
        if (is_array($d) && !empty($d['client_email']) && !empty($d['private_key'])) $sa = $d;
    }
    $legacy = gsc_config();
    if (!$sa && $legacy) $sa = $legacy['sa'];
    $site = google_env('GSC_SITE_URL') ?? ($legacy['site'] ?? null);
    $prop = google_env('GA4_PROPERTY_ID');
    return [
        'sa' => $sa,
        'email' => $sa['client_email'] ?? null,
        'gsc_site' => $site !== null ? seo_normalize_gsc_site($site) : null,
        'ga4_property' => $prop !== null && preg_match('/^\d{4,15}$/', $prop) ? $prop : null,
    ];
}

/** Saves the Google secrets to .env.google outside public_html (chmod 600). An empty key keeps the saved one. */
function google_save_settings(string $keyJson, string $site, string $property): void
{
    $keyJson = trim($keyJson);
    if ($keyJson === '') {
        $sa = google_config()['sa'];
        if (!$sa) throw new InvalidArgumentException('Paste the whole service account key file (JSON).');
    } else {
        $sa = json_decode($keyJson, true);
        if (!is_array($sa) || ($sa['type'] ?? '') !== 'service_account' || empty($sa['client_email']) || empty($sa['private_key'])) {
            throw new InvalidArgumentException('This is not a Google service account key file — download the JSON key again.');
        }
        if (!openssl_pkey_get_private($sa['private_key'])) throw new InvalidArgumentException('The private key in this file cannot be read — download the JSON key again.');
    }
    $siteNorm = seo_normalize_gsc_site($site);
    if ($siteNorm === null) throw new InvalidArgumentException('Search Console site must be "https://example.com/" (URL-prefix property) or "sc-domain:example.com" (domain property).');
    $property = trim($property);
    if ($property !== '' && !preg_match('/^\d{4,15}$/', $property)) throw new InvalidArgumentException('The GA4 property ID is numbers only (Admin → Property details), not the G- measurement ID — or leave it empty for now.');
    $keep = ['type' => 'service_account', 'client_email' => $sa['client_email'], 'private_key' => $sa['private_key'],
        'token_uri' => $sa['token_uri'] ?? 'https://oauth2.googleapis.com/token'];
    $lines = ['# Google Search Console + GA4 — written by /admin/seo-analytics. Do not share.',
        "GOOGLE_SERVICE_ACCOUNT_JSON='" . json_encode($keep, JSON_UNESCAPED_SLASHES) . "'",
        "GSC_SITE_URL=\"$siteNorm\"", "GA4_PROPERTY_ID=\"$property\""];
    $file = google_env_file_server();
    if (@file_put_contents($file, implode("\n", $lines) . "\n", LOCK_EX) === false) throw new RuntimeException("Could not write $file");
    @chmod($file, 0600);
}

function google_b64url(string $s): string
{
    return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
}

/** Access token for the service account (cached until 1 minute before it expires). */
function google_token(array $sa): string
{
    $cache = sys_get_temp_dir() . '/cn_google_token_' . hash('sha256', $sa['client_email'] . GOOGLE_SCOPES);
    if (is_file($cache) && ($c = json_decode((string) file_get_contents($cache), true)) && ($c['exp'] ?? 0) > time() + 60) return $c['token'];
    $now = time();
    $aud = $sa['token_uri'] ?? 'https://oauth2.googleapis.com/token';
    $unsigned = google_b64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])) . '.' . google_b64url(json_encode([
        'iss' => $sa['client_email'], 'scope' => GOOGLE_SCOPES, 'aud' => $aud, 'iat' => $now, 'exp' => $now + 3600,
    ]));
    if (!openssl_sign($unsigned, $sig, $sa['private_key'], OPENSSL_ALGO_SHA256)) throw new GoogleError('The service account key could not sign in — paste the key file again.');
    $ch = curl_init($aud);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
        CURLOPT_POSTFIELDS => http_build_query(['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $unsigned . '.' . google_b64url($sig)])]);
    $body = curl_exec($ch);
    $res = json_decode((string) $body, true);
    if (empty($res['access_token'])) {
        error_log('[google token] ' . substr((string) $body, 0, 300));
        throw new GoogleError($body === false ? 'Could not reach Google — please try again in a minute.' : 'Google refused the service account key — paste the key file again (it may have been deleted).');
    }
    @file_put_contents($cache, json_encode(['token' => $res['access_token'], 'exp' => $now + (int) ($res['expires_in'] ?? 3600)]), LOCK_EX);
    @chmod($cache, 0600);
    return $res['access_token'];
}

/** POST JSON to a Google API. Throws GoogleError with a plain message. */
function google_post(array $cfg, string $url, array $body, string $service): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 45, CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . google_token($cfg['sa']), 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_SLASHES)]);
    $resp = curl_exec($ch);
    $status = $resp === false ? 0 : (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $data = json_decode((string) $resp, true);
    if ($status !== 200 || !is_array($data)) {
        $gm = (string) ($data['error']['message'] ?? '');
        error_log("[google $service] HTTP $status: " . substr($gm ?: (string) $resp, 0, 300));
        throw new GoogleError(seo_google_error($status, $service, (string) $cfg['email'], $gm));
    }
    return $data;
}

/** Search Console rows for one request, following pages of 25 000 rows. */
function google_gsc_rows(array $cfg, array $dimensions, string $start, string $end): array
{
    $url = 'https://searchconsole.googleapis.com/webmasters/v3/sites/' . rawurlencode((string) $cfg['gsc_site']) . '/searchAnalytics/query';
    $rows = [];
    for ($startRow = 0; $startRow < 250000; $startRow += 25000) {
        $data = google_post($cfg, $url, ['startDate' => $start, 'endDate' => $end, 'dimensions' => $dimensions, 'rowLimit' => 25000, 'startRow' => $startRow, 'type' => 'web'], 'gsc');
        $batch = $data['rows'] ?? [];
        array_push($rows, ...$batch);
        if (count($batch) < 25000) break;
    }
    return $rows;
}

/** GA4 runReport rows as [['dims' => [...], 'metrics' => [...]]]. */
function google_ga4_rows(array $cfg, array $dimensions, array $metrics, string $start, string $end): array
{
    $url = 'https://analyticsdata.googleapis.com/v1beta/properties/' . $cfg['ga4_property'] . ':runReport';
    $rows = [];
    for ($offset = 0; $offset < 500000; $offset += 100000) {
        $data = google_post($cfg, $url, [
            'dateRanges' => [['startDate' => $start, 'endDate' => $end]],
            'dimensions' => array_map(static fn ($d) => ['name' => $d], $dimensions),
            'metrics' => array_map(static fn ($m) => ['name' => $m], $metrics),
            'limit' => 100000, 'offset' => $offset, 'keepEmptyRows' => false,
        ], 'ga4');
        foreach ($data['rows'] ?? [] as $r) {
            $rows[] = ['dims' => array_column($r['dimensionValues'] ?? [], 'value'), 'metrics' => array_map('floatval', array_column($r['metricValues'] ?? [], 'value'))];
        }
        if (count($data['rows'] ?? []) < 100000) break;
    }
    return $rows;
}
