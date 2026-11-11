<?php
// Google Search Console (read-only) with a service account, using openssl + curl — no Composer, no SDK.
// The key file is stored outside public_html (never in the public repo), like the Neon connection string.
declare(strict_types=1);

final class GscError extends RuntimeException {}

function gsc_file(): string
{
    $docroot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    return ($docroot !== '' ? dirname($docroot) : dirname(__DIR__, 3)) . '/.gsc.json';
}

/**
 * The ONE Google connection for the whole admin (Classement SEO, Paramètres, Keywords, Analytics):
 * GOOGLE_SERVICE_ACCOUNT_JSON + GSC_SITE_URL from the environment / .env.google, else the older .gsc.json.
 * @return array{sa: array, site: string}|null
 */
function gsc_config(): ?array
{
    require_once __DIR__ . '/google.php';
    $g = google_config();
    return $g['sa'] && $g['gsc_site'] ? ['sa' => $g['sa'], 'site' => $g['gsc_site']] : null;
}

/** The older connection file written by Paramètres before .env.google existed. */
function gsc_legacy_config(): ?array
{
    static $cfg = false;
    if ($cfg !== false) return $cfg;
    $cfg = null;
    foreach ([gsc_file(), dirname(__DIR__, 2) . '/.gsc.json'] as $f) {
        if (is_file($f) && ($d = json_decode((string) file_get_contents($f), true)) && !empty($d['sa']['private_key'])) {
            return $cfg = $d;
        }
    }
    return null;
}

function gsc_save(string $keyJson, string $site): string
{
    $sa = json_decode($keyJson, true);
    if (!is_array($sa) || empty($sa['client_email']) || empty($sa['private_key']) || ($sa['type'] ?? '') !== 'service_account') {
        throw new GscError('Ce n’est pas un fichier de clé JSON de compte de service Google.');
    }
    if (!openssl_pkey_get_private($sa['private_key'])) throw new GscError('La clé privée du fichier est illisible.');
    require_once __DIR__ . '/google.php';
    $site = seo_normalize_gsc_site($site) ?? '';
    if ($site === '') {
        throw new GscError('Propriété invalide : « sc-domain:climanova-energie.fr » ou « https://climanova-energie.fr/ ».');
    }
    // Same shared connection as the Analytics tab (keeps the GA4 property ID already saved).
    try {
        google_save_settings($keyJson, $site, (string) (google_config()['ga4_property'] ?? ''));
    } catch (InvalidArgumentException $e) {
        throw new GscError($e->getMessage());
    }
    $keep = ['client_email' => $sa['client_email'], 'private_key' => $sa['private_key'], 'token_uri' => $sa['token_uri'] ?? 'https://oauth2.googleapis.com/token'];
    $file = gsc_file();
    if (@file_put_contents($file, json_encode(['sa' => $keep, 'site' => $site]), LOCK_EX) === false) {
        throw new GscError("Impossible d'écrire $file");
    }
    @chmod($file, 0600);
    return $file;
}

function gsc_b64url(string $s): string
{
    return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
}

function gsc_token(array $sa): string
{
    $cache = sys_get_temp_dir() . '/cn_gsc_token_' . hash('sha256', $sa['client_email']);
    if (is_file($cache) && ($c = json_decode((string) file_get_contents($cache), true)) && ($c['exp'] ?? 0) > time() + 60) {
        return $c['token'];
    }
    $now = time();
    $unsigned = gsc_b64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])) . '.' . gsc_b64url(json_encode([
        'iss' => $sa['client_email'], 'scope' => 'https://www.googleapis.com/auth/webmasters.readonly',
        'aud' => $sa['token_uri'], 'iat' => $now, 'exp' => $now + 3600,
    ]));
    if (!openssl_sign($unsigned, $sig, $sa['private_key'], OPENSSL_ALGO_SHA256)) throw new GscError('Signature de la clé impossible.');

    $ch = curl_init($sa['token_uri']);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
        CURLOPT_POSTFIELDS => http_build_query(['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $unsigned . '.' . gsc_b64url($sig)])]);
    $res = json_decode((string) curl_exec($ch), true);
    if (empty($res['access_token'])) throw new GscError('Google a refusé la clé : ' . ($res['error_description'] ?? $res['error'] ?? 'erreur inconnue'));
    @file_put_contents($cache, json_encode(['token' => $res['access_token'], 'exp' => $now + (int) ($res['expires_in'] ?? 3600)]), LOCK_EX);
    @chmod($cache, 0600);
    return $res['access_token'];
}

/** Search Analytics rows: [['keys' => [...], 'clicks', 'impressions', 'ctr', 'position'], …] */
function gsc_query(array $dimensions, string $start, string $end, int $rowLimit = 1000): array
{
    $cfg = gsc_config();
    if (!$cfg) throw new GscError('Search Console non connecté');
    $ch = curl_init('https://www.googleapis.com/webmasters/v3/sites/' . rawurlencode($cfg['site']) . '/searchAnalytics/query');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . gsc_token($cfg['sa']), 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode(['startDate' => $start, 'endDate' => $end, 'dimensions' => $dimensions, 'rowLimit' => $rowLimit, 'dataState' => 'all'])]);
    $body = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $data = json_decode($body, true);
    if ($status === 403 || $status === 404) {
        throw new GscError("Le compte {$cfg['sa']['client_email']} n'a pas accès à « {$cfg['site']} ». Ajoutez-le dans Search Console → Paramètres → Utilisateurs, ou vérifiez le type de propriété : « https://climanova-energie.fr/ » (préfixe d’URL) n’est pas la même que « sc-domain:climanova-energie.fr » (domaine).");
    }
    if ($status !== 200) throw new GscError('Erreur Google : ' . ($data['error']['message'] ?? "HTTP $status"));
    return $data['rows'] ?? [];
}

/**
 * Per-page Google figures for the last 28 days (data lags ~2 days), keyed by URL path:
 * position, clicks, impressions, and the query that brings the most impressions.
 */
function gsc_page_stats(): array
{
    $end = date('Y-m-d', strtotime('-2 days'));
    $start = date('Y-m-d', strtotime('-29 days'));
    $path = static fn (string $u) => (parse_url($u, PHP_URL_PATH) ?: '/');
    $out = [];
    foreach (gsc_query(['page'], $start, $end, 1000) as $r) {
        $out[$path($r['keys'][0])] = ['position' => $r['position'], 'clicks' => (int) $r['clicks'], 'impressions' => (int) $r['impressions'], 'top_query' => null, 'top_imp' => -1];
    }
    foreach (gsc_query(['page', 'query'], $start, $end, 5000) as $r) {
        $k = $path($r['keys'][0]);
        if (isset($out[$k]) && $r['impressions'] > $out[$k]['top_imp']) {
            $out[$k]['top_query'] = $r['keys'][1];
            $out[$k]['top_imp'] = $r['impressions'];
        }
    }
    return $out;
}
