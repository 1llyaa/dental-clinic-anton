<?php
declare(strict_types=1);

const LOGIN_MAX_FAILS = 5;
const LOGIN_LOCK_SECONDS = 15 * 60;
// All addresses together: stops guessing spread over many IPs. 30 failures per hour.
const LOGIN_GLOBAL_MAX_FAILS = 30;
const LOGIN_GLOBAL_WINDOW = 3600;
const GLOBAL_KEY = '*';

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

/** Rate-limit key: IPv4 as is, IPv6 grouped by /64 (one customer usually owns a whole /64). */
function ip_key(string $ip): string
{
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $bin = inet_pton($ip);
        return inet_ntop(substr($bin, 0, 8) . str_repeat("\0", 8)) . '/64';
    }
    return $ip;
}

function lock_remaining(array $all, string $key, int $now): int
{
    return max(0, (int) ($all[$key]['locked_until'] ?? 0) - $now);
}

/** Seconds until $ip may try again (0 = not locked). */
function login_locked_for(array $config, string $ip, ?int $now = null): int
{
    $now = $now ?? time();
    $all = read_json(attempts_path($config));
    return max(lock_remaining($all, ip_key($ip), $now), lock_remaining($all, GLOBAL_KEY, $now));
}

/** Caller must hold with_lock(). */
function add_failure(array $all, string $key, int $max, int $window, int $now): array
{
    $e = $all[$key] ?? ['count' => 0, 'first' => $now, 'last' => 0, 'locked_until' => 0];
    $expiredLock = ($e['locked_until'] ?? 0) && $e['locked_until'] <= $now;
    if ($expiredLock || $now - (int) ($e['first'] ?? $now) > $window) {
        $e = ['count' => 0, 'first' => $now, 'last' => 0, 'locked_until' => 0];
    }
    $e['count']++;
    $e['last'] = $now;
    if ($e['count'] >= $max) {
        $e['locked_until'] = $now + LOGIN_LOCK_SECONDS;
    }
    $all[$key] = $e;
    return $all;
}

function record_login_failure(array $config, string $ip, ?int $now = null): void
{
    $now = $now ?? time();
    with_lock($config['data_dir'], fn() => record_failure_locked($config, $ip, $now));
}

/** Caller must hold with_lock(). */
function record_failure_locked(array $config, string $ip, int $now): void
{
    $all = read_json(attempts_path($config));
    foreach ($all as $k => $v) { // forget stale entries so the file stays small
        if ($k !== GLOBAL_KEY && ($v['last'] ?? 0) < $now - 86400) {
            unset($all[$k]);
        }
    }
    $all = add_failure($all, ip_key($ip), LOGIN_MAX_FAILS, 86400, $now);
    $all = add_failure($all, GLOBAL_KEY, LOGIN_GLOBAL_MAX_FAILS, LOGIN_GLOBAL_WINDOW, $now);
    write_json_atomic(attempts_path($config), $all);
}

function clear_login_failures(array $config, string $ip): void
{
    with_lock($config['data_dir'], function () use ($config, $ip) {
        $all = read_json(attempts_path($config));
        if (isset($all[ip_key($ip)])) {
            unset($all[ip_key($ip)]);
            write_json_atomic(attempts_path($config), $all);
        }
    });
}

/**
 * Check the lock, verify the password and record a failure as one step, so
 * parallel requests cannot all slip past the check. Returns ok|wrong|locked.
 */
function attempt_login(array $config, string $ip, string $password, ?int $now = null): string
{
    $now = $now ?? time();
    return with_lock($config['data_dir'], function () use ($config, $ip, $password, $now) {
        if (login_locked_for($config, $ip, $now) > 0) {
            return 'locked';
        }
        if ($password !== '' && password_verify($password, (string) $config['password_hash'])) {
            $all = read_json(attempts_path($config));
            if (isset($all[ip_key($ip)])) {
                unset($all[ip_key($ip)]);
                write_json_atomic(attempts_path($config), $all);
            }
            return 'ok';
        }
        record_failure_locked($config, $ip, $now);
        return 'wrong';
    });
}

function password_configured(array $config): bool
{
    $h = (string) ($config['password_hash'] ?? '');
    return $h !== '' && password_get_info($h)['algo'] !== null && strpos($h, '...') === false;
}
