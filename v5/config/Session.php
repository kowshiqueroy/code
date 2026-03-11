<?php
/**
 * Session — Secure PHP session management
 * FILE: config/Session.php
 */

declare(strict_types=1);

class Session
{
    private static bool $started = false;

    /**
     * Start the session with hardened settings.
     * Call once per request before any output.
     */
    public static function start(): void
    {
        if (self::$started) {
            return;
        }

        session_name(SESSION_NAME);

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => SESSION_SECURE,
            'httponly' => SESSION_HTTP_ONLY,
            'samesite' => SESSION_SAME_SITE,
        ]);

        session_start();
        self::$started = true;

        self::checkInactivity();
        self::regenerateIfNeeded();
    }

    /**
     * Check inactivity timeout and destroy session if expired.
     */
    private static function checkInactivity(): void
    {
        if (isset($_SESSION['_last_activity'])) {
            if ((time() - $_SESSION['_last_activity']) > SESSION_LIFETIME) {
                self::destroy();
                return;
            }
        }
        $_SESSION['_last_activity'] = time();
    }

    /**
     * Regenerate session ID periodically to prevent fixation.
     */
    private static function regenerateIfNeeded(): void
    {
        if (!isset($_SESSION['_regenerated'])) {
            $_SESSION['_regenerated'] = time();
            return;
        }
        if ((time() - $_SESSION['_regenerated']) > 300) {
            session_regenerate_id(true);
            $_SESSION['_regenerated'] = time();
        }
    }

    // ── Getters ──────────────────────────────────────────────

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    // ── Auth helpers ─────────────────────────────────────────

    public static function login(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id']         = (int) $user['id'];
        $_SESSION['user_name']        = $user['name'];
        $_SESSION['user_role']        = $user['role'];
        $_SESSION['_last_activity']   = time();
        $_SESSION['_regenerated']     = time();
    }

    public static function logout(): void
    {
        self::destroy();
    }

    public static function isLoggedIn(): bool
    {
        return isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0;
    }

    public static function userId(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    public static function userRole(): string
    {
        return (string) ($_SESSION['user_role'] ?? '');
    }

    public static function isAdmin(): bool
    {
        return self::userRole() === 'admin';
    }

    public static function requireLogin(): void
    {
        if (!self::isLoggedIn()) {
            header('Location: /login.php');
            exit;
        }
    }

    public static function requireAdmin(): void
    {
        self::requireLogin();
        if (!self::isAdmin()) {
            http_response_code(403);
            die('Access denied.');
        }
    }

    // ── Flash messages ───────────────────────────────────────

    public static function flash(string $key, string $message): void
    {
        $_SESSION['_flash'][$key] = $message;
    }

    public static function getFlash(string $key): ?string
    {
        $msg = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $msg;
    }

    // ── CSRF Token ───────────────────────────────────────────

    public static function csrfToken(): string
    {
        if (!isset($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
        }
        return $_SESSION['_csrf_token'];
    }

    public static function verifyCsrf(string $token): bool
    {
        $stored = $_SESSION['_csrf_token'] ?? '';
        return hash_equals($stored, $token);
    }

    // ── Destroy ──────────────────────────────────────────────

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(
                session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']
            );
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        self::$started = false;
    }
}
