<?php
/**
 * Auth.php — Session & Authentication Helper
 */

declare(strict_types=1);

class Auth
{
    /** Start or resume the secure session. */
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /** Check inactivity and auto-logout. */
    public static function checkInactivity(): void
    {
        self::start();
        if (isset($_SESSION['last_activity'])) {
            if (time() - $_SESSION['last_activity'] > SESSION_LIFETIME) {
                self::logout();
                header('Location: ' . BASE_URL . '/login.php?reason=timeout');
                exit;
            }
        }
        $_SESSION['last_activity'] = time();
    }

    /** Return true if a user is logged in. */
    public static function check(): bool
    {
        self::start();
        return !empty($_SESSION['user_id']);
    }

    /** Require authentication; redirect to login if not. */
    public static function required(?string $requiredRole = null): void
    {
        self::checkInactivity();
        if (!self::check()) {
            header('Location: ' . BASE_URL . '/login.php');
            exit;
        }
        if ($requiredRole && self::role() !== $requiredRole) {
            http_response_code(403);
            die('Access denied.');
        }
    }

    /** Log in the user by credentials; return user array or null. */
    public static function attempt(string $username, string $password): ?array
    {
        $user = DB::one(
            'SELECT id, full_name, username, password_hash, role, is_active
             FROM users WHERE username = ? LIMIT 1',
            [trim($username)]
        );
        if (!$user || !$user['is_active']) return null;
        if (!password_verify($password, $user['password_hash'])) return null;

        // Rehash if needed
        if (password_needs_rehash($user['password_hash'], PASSWORD_ALGO, ['cost' => PASSWORD_COST])) {
            $newHash = password_hash($password, PASSWORD_ALGO, ['cost' => PASSWORD_COST]);
            DB::run('UPDATE users SET password_hash=? WHERE id=?', [$newHash, $user['id']]);
        }

        self::start();
        session_regenerate_id(true); // Prevent session fixation

        $_SESSION['user_id']    = $user['id'];
        $_SESSION['username']   = $user['username'];
        $_SESSION['full_name']  = $user['full_name'];
        $_SESSION['role']       = $user['role'];
        $_SESSION['last_activity'] = time();

        DB::run('UPDATE users SET last_login_at=NOW() WHERE id=?', [$user['id']]);
        AuditLog::write((int)$user['id'], 'LOGIN', 'users', (int)$user['id']);

        return $user;
    }

    /** Destroy session and log the event. */
    public static function logout(): void
    {
        self::start();
        $uid = (int)($_SESSION['user_id'] ?? 0);
        if ($uid) AuditLog::write($uid, 'LOGOUT', 'users', $uid);
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function id(): int   { return (int)($_SESSION['user_id'] ?? 0); }
    public static function role(): string { return $_SESSION['role'] ?? ''; }
    public static function name(): string { return $_SESSION['full_name'] ?? ''; }
    public static function isAdmin(): bool { return self::role() === 'admin'; }
}
