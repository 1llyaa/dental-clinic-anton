<?php
declare(strict_types=1);

const LOGIN_MAX_FAILS = 5;
const LOGIN_LOCK_SECONDS = 15 * 60;

function start_admin_session(array $config): void
{
    $dir = $config['data_dir'] . '/private/sessions';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    if (is_dir($dir) && is_writable($dir)) {
        // Own folder: shared hosting may clean the default one with a shorter lifetime.
        session_save_path($dir);
    }
    ini_set('session.gc_maxlifetime', (string) $config['session_ttl']);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('zl_admin');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/admin/',
        'secure' => (bool) $config['cookie_secure'],
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

function is_logged_in(array $config): bool
{
    $at = $_SESSION['login_at'] ?? null;
    if (!is_int($at)) {
        return false;
    }
    if (time() - $at > (int) $config['session_ttl']) {
        logout();
        return false;
    }
    return true;
}

function login(): void
{
    session_regenerate_id(true);
    $_SESSION = ['login_at' => time(), 'csrf' => bin2hex(random_bytes(32))];
}

function logout(): void
{
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_valid($token): bool
{
    return is_string($token) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

// ---- Rate limiting: failed logins per IP, stored in data/private/ ----

function attempts_path(array $config): string
{
    return $config['data_dir'] . '/private/login-attempts.json';
}

/** Seconds until $ip may try again (0 = not locked). */
function login_locked_for(array $config, string $ip, ?int $now = null): int
{
    $now = $now ?? time();
    $all = read_json(attempts_path($config));
    $until = (int) ($all[$ip]['locked_until'] ?? 0);
    return max(0, $until - $now);
}

function record_login_failure(array $config, string $ip, ?int $now = null): void
{
    $now = $now ?? time();
    with_lock($config['data_dir'], function () use ($config, $ip, $now) {
        $all = read_json(attempts_path($config));
        // forget stale entries so the file stays small
        foreach ($all as $k => $v) {
            if (($v['last'] ?? 0) < $now - 86400) {
                unset($all[$k]);
            }
        }
        $entry = $all[$ip] ?? ['count' => 0, 'last' => 0, 'locked_until' => 0];
        if (($entry['locked_until'] ?? 0) && $entry['locked_until'] <= $now) {
            $entry['count'] = 0; // previous lock expired, start over
            $entry['locked_until'] = 0;
        }
        $entry['count']++;
        $entry['last'] = $now;
        if ($entry['count'] >= LOGIN_MAX_FAILS) {
            $entry['locked_until'] = $now + LOGIN_LOCK_SECONDS;
        }
        $all[$ip] = $entry;
        write_json_atomic(attempts_path($config), $all);
    });
}

function clear_login_failures(array $config, string $ip): void
{
    with_lock($config['data_dir'], function () use ($config, $ip) {
        $all = read_json(attempts_path($config));
        if (isset($all[$ip])) {
            unset($all[$ip]);
            write_json_atomic(attempts_path($config), $all);
        }
    });
}

function password_configured(array $config): bool
{
    $h = (string) ($config['password_hash'] ?? '');
    return $h !== '' && password_get_info($h)['algo'] !== null && strpos($h, '...') === false;
}
