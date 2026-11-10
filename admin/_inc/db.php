<?php
// Neon Postgres over HTTPS (Neon's SQL-over-HTTP endpoint, as used by @neondatabase/serverless).
// Hostinger's PHP has no pdo_pgsql, but has curl, so this needs nothing installed on the host.
//
// The connection string is never committed (the repo is public). It is read, in order, from:
//   1. the DATABASE_URL environment variable
//   2. <parent of public_html>/.env.local  — on Hostinger, outside the web root (written by the dashboard setup form)
//   3. <repo root>/.env.local               — local development only (git-ignored, never deployed)
declare(strict_types=1);

final class DbError extends RuntimeException {}

function db_env_file_server(): string
{
    $docroot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    return ($docroot !== '' ? dirname($docroot) : dirname(__DIR__, 3)) . '/.env.local';
}

function db_read_env_file(string $file): ?string
{
    if (!is_file($file) || !is_readable($file)) return null;
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        if (preg_match('/^\s*DATABASE_URL\s*=\s*"?([^"\s]+)"?\s*$/', $line, $m)) return $m[1];
    }
    return null;
}

function db_url(): ?string
{
    static $url = false;
    if ($url !== false) return $url;
    $url = getenv('DATABASE_URL') ?: null;
    $url ??= db_read_env_file(db_env_file_server());
    $url ??= db_read_env_file(dirname(__DIR__, 2) . '/.env.local');
    return $url;
}

function db_configured(): bool
{
    return db_url() !== null;
}

/** Only Neon Postgres URLs are accepted, so the setup form cannot point the server elsewhere. */
function db_valid_url(string $url): bool
{
    $p = parse_url($url);
    return is_array($p)
        && in_array($p['scheme'] ?? '', ['postgres', 'postgresql'], true)
        && !empty($p['user']) && !empty($p['pass'])
        && preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)*\.neon\.tech$/', $p['host'] ?? '')
        && !empty(trim($p['path'] ?? '', '/'));
}

/**
 * Runs one parameterised statement and returns rows as associative arrays.
 * Always pass values through $params ($1, $2, …), never by string concatenation.
 */
function db_query(string $sql, array $params = []): array
{
    return db_rows(db_post(['query' => $sql, 'params' => db_params($params)]));
}

/**
 * Runs several statements in ONE request and one transaction; returns one row list per statement.
 * $statements: [[sql, params], …]. Pass $readOnly for pure reads.
 */
function db_batch(array $statements, bool $readOnly = false): array
{
    $queries = array_map(static fn ($s) => ['query' => $s[0], 'params' => db_params($s[1] ?? [])], $statements);
    $headers = ['Neon-Batch-Isolation-Level: ReadCommitted'];
    if ($readOnly) $headers[] = 'Neon-Batch-Read-Only: true';
    $data = db_post(['queries' => $queries], $headers);
    return array_map('db_rows', $data['results'] ?? []);
}

function db_params(array $params): array
{
    return array_map(static fn ($v) => $v === null ? null : (is_bool($v) ? ($v ? 'true' : 'false') : (string) $v), $params);
}

function db_rows(array $result): array
{
    $names = array_map(static fn ($f) => $f['name'], $result['fields'] ?? []);
    return array_map(static fn ($row) => array_combine($names, $row), $result['rows'] ?? []);
}

function db_post(array $payload, array $extraHeaders = []): array
{
    $url = db_url();
    if ($url === null) throw new DbError('Base de données non configurée');
    $host = parse_url($url, PHP_URL_HOST);

    $ch = curl_init("https://{$host}/sql");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => array_merge([
            'Content-Type: application/json',
            'Neon-Connection-String: ' . $url,
            'Neon-Raw-Text-Output: true',
            'Neon-Array-Mode: true',
        ], $extraHeaders),
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $curlErr = curl_error($ch);

    if ($body === false) throw new DbError('Connexion à Neon impossible : ' . $curlErr);
    $data = json_decode((string) $body, true);
    if ($status !== 200 || !is_array($data)) {
        throw new DbError('Erreur Neon : ' . (is_array($data) ? ($data['message'] ?? "HTTP $status") : "HTTP $status"));
    }
    return $data;
}

/**
 * One clear sentence for an admin. Technical details go to the server log only.
 */
function admin_error(Throwable $e, string $doing): string
{
    error_log("[admin] $doing: " . get_class($e) . ': ' . $e->getMessage());
    $msg = $e->getMessage();
    if ($e instanceof DbError) {
        if (str_contains($msg, 'non configurée')) return 'The database is not connected yet — add it in Paramètres.';
        if (str_contains($msg, 'does not exist')) return "Could not $doing because the database is missing a table — run the latest migration.";
        if (str_contains($msg, 'Connexion')) return "Could not $doing because the database did not respond — please try again in a minute.";
        return "Could not $doing because the database returned an error — please try again.";
    }
    return "Could not $doing — please try again.";
}

/** Stores the connection string outside the web root. Returns the file written. */
function db_save_url(string $url): string
{
    $file = db_env_file_server();
    $ok = @file_put_contents($file, "# Neon Postgres — written by /admin setup. Do not share.\nDATABASE_URL=\"{$url}\"\n", LOCK_EX);
    if ($ok === false) throw new DbError("Impossible d'écrire {$file}");
    @chmod($file, 0600);
    return $file;
}

/** Best-effort audit log: a database problem must never block logging in. */
function log_admin_event(string $event, string $email): void
{
    if (!db_configured()) return;
    try {
        db_query(
            'insert into admin_activity (email, event, ip, user_agent) values ($1, $2, $3::inet, $4)',
            [
                mb_substr(strtolower(trim($email)), 0, 254),
                $event,
                filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ?: null,
                mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 300),
            ],
        );
    } catch (Throwable $e) {
        error_log('admin_activity insert failed: ' . $e->getMessage());
    }
}
