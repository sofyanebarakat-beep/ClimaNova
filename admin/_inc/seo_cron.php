<?php
// Automation settings (separate .env.cron outside public_html), the Bearer CRON_SECRET guard and the run lock.
// No dependency on the article code, so Paramètres can use it on its own.
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/seo_logic.php';
require_once __DIR__ . '/seo_agent.php';

/* ---------- Server settings (separate file so saving AI settings never wipes them) ---------- */

function seo_cron_file(): string
{
    $docroot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    return ($docroot !== '' ? dirname($docroot) : dirname(__DIR__, 3)) . '/.env.cron';
}

function seo_cron_env(string $name): ?string
{
    $v = getenv($name);
    if ($v !== false && $v !== '') return $v;
    foreach ([seo_cron_file(), dirname(__DIR__, 2) . '/.env.cron'] as $f) {
        if (!is_file($f) || !is_readable($f)) continue;
        foreach (file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if (preg_match('/^\s*' . preg_quote($name, '/') . '\s*=\s*"?(.*?)"?\s*$/', $line, $m) && $m[1] !== '') return $m[1];
        }
    }
    return null;
}

/** Merges keys into the server .env.cron (outside public_html, chmod 600). */
function seo_cron_save(array $kv): void
{
    seo_require_admin();
    $file = seo_cron_file();
    $vars = [];
    if (is_file($file)) {
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if (preg_match('/^\s*([A-Z0-9_]+)\s*=\s*"?(.*?)"?\s*$/', $line, $m)) $vars[$m[1]] = $m[2];
        }
    }
    foreach ($kv as $k => $v) $vars[$k] = $v;
    $out = "# SEO automation — written by /admin Paramètres. Do not share.\n";
    foreach ($vars as $k => $v) $out .= "$k=\"$v\"\n";
    if (@file_put_contents($file, $out, LOCK_EX) === false) throw new RuntimeException("Could not write $file");
    @chmod($file, 0600);
}

function seo_automation_enabled(): bool
{
    return seo_cron_env('SEO_AUTOMATION_ENABLED') === 'true';
}

function seo_request_authorization(): ?string
{
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;
    if ($h === null && function_exists('getallheaders')) {
        foreach (getallheaders() as $k => $v) if (strcasecmp($k, 'Authorization') === 0) $h = $v;
    }
    return $h;
}

/** Cron endpoints: POST + valid Bearer secret, else 401; when automation is off, answers and does nothing. */
function seo_cron_guard(string $endpoint): void
{
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex, nofollow');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Use POST.']);
        exit;
    }
    if (!seo_cron_token_ok(seo_cron_env('CRON_SECRET'), seo_parse_bearer(seo_request_authorization()))) {
        usleep(300000);
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized.']);
        exit;
    }
    if (!seo_automation_enabled()) {
        echo json_encode(['ok' => true, 'endpoint' => $endpoint, 'status' => 'disabled', 'message' => 'SEO_AUTOMATION_ENABLED is not "true" — nothing was done.']);
        exit;
    }
    define('SEO_CRON_AUTHORIZED', true); // seo_require_admin() accepts this for the rest of the request
    @set_time_limit(0);
    ignore_user_abort(true);
}

/** Simple run lock in seo_settings so two cron calls never overlap (expires after 15 minutes). */
function seo_cron_lock(string $name): bool
{
    seo_require_admin();
    $rows = db_query("insert into seo_settings (key, value) values ($1, jsonb_build_object('until', now() + interval '15 minutes'))
                      on conflict (key) do update set value = excluded.value, updated_at = now()
                      where (seo_settings.value ->> 'until')::timestamptz < now()
                      returning key", ['cron_lock_' . $name]);
    return (bool) $rows;
}

function seo_cron_unlock(string $name): void
{
    seo_require_admin();
    db_query("update seo_settings set value = jsonb_build_object('until', now() - interval '1 second'), updated_at = now() where key = $1", ['cron_lock_' . $name]);
}

