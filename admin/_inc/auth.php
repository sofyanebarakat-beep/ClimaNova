<?php
// Session login for the /admin area. No database: the account lives in config.php.
declare(strict_types=1);

require __DIR__ . '/config.php';

const SESSION_IDLE_SECONDS = 7200;   // signed out after 2 h without activity
const MAX_FAILED_ATTEMPTS  = 5;      // per IP address…
const LOCKOUT_SECONDS      = 900;    // …within 15 minutes

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');

session_name('cn_admin');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/admin/',
    'secure'   => !empty($_SERVER['HTTPS']) || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https',
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_valid(?string $token): bool
{
    return is_string($token) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

function is_logged_in(): bool
{
    if (empty($_SESSION['admin_email'])) {
        return false;
    }
    if (time() - ($_SESSION['last_seen'] ?? 0) > SESSION_IDLE_SECONDS) {
        logout();
        return false;
    }
    $_SESSION['last_seen'] = time();
    return true;
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: /admin/');
        exit;
    }
}

// Failed attempts are counted per IP in the server's temp dir, so a new session cannot reset them.
function attempts_file(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    return sys_get_temp_dir() . '/cn_admin_' . hash('sha256', $ip . ADMIN_EMAIL);
}

function recent_failures(): array
{
    $file = attempts_file();
    $list = is_file($file) ? (json_decode((string) file_get_contents($file), true) ?: []) : [];
    $since = time() - LOCKOUT_SECONDS;
    return array_values(array_filter($list, static fn ($t) => is_int($t) && $t > $since));
}

function is_locked_out(): bool
{
    return count(recent_failures()) >= MAX_FAILED_ATTEMPTS;
}

function record_failure(): void
{
    $list = recent_failures();
    $list[] = time();
    @file_put_contents(attempts_file(), json_encode($list), LOCK_EX);
}

function clear_failures(): void
{
    @unlink(attempts_file());
}

function attempt_login(string $email, string $password): bool
{
    $ok = hash_equals(strtolower(ADMIN_EMAIL), strtolower(trim($email)))
        & password_verify($password, ADMIN_PASSWORD_HASH); // both always evaluated
    if (!$ok) {
        record_failure();
        return false;
    }
    clear_failures();
    session_regenerate_id(true);
    $_SESSION['admin_email'] = ADMIN_EMAIL;
    $_SESSION['last_seen'] = time();
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return true;
}

function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
