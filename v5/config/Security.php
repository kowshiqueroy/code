<?php
/**
 * Security — Input sanitisation, CSRF, output escaping
 * FILE: config/Security.php
 */

declare(strict_types=1);

class Security
{
    /**
     * Sanitise a string input: strip tags, trim, limit length.
     */
    public static function sanitizeString(mixed $value, int $maxLen = 500): string
    {
        return mb_substr(trim(strip_tags((string) $value)), 0, $maxLen);
    }

    /**
     * Sanitise an integer.
     */
    public static function sanitizeInt(mixed $value): int
    {
        return (int) filter_var($value, FILTER_SANITIZE_NUMBER_INT);
    }

    /**
     * Sanitise a float.
     */
    public static function sanitizeFloat(mixed $value): float
    {
        return (float) filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    }

    /**
     * Sanitise an email address.
     */
    public static function sanitizeEmail(mixed $value): string
    {
        return (string) filter_var(trim((string) $value), FILTER_SANITIZE_EMAIL);
    }

    /**
     * HTML-escape a value for safe output inside HTML.
     */
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Verify CSRF token from POST or header, abort if invalid.
     */
    public static function verifyCsrfOrAbort(): void
    {
        $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!Session::verifyCsrf($token)) {
            http_response_code(403);
            die(json_encode(['error' => 'CSRF token mismatch.']));
        }
    }

    /**
     * Hash a password.
     */
    public static function hashPassword(string $plain): string
    {
        return password_hash($plain, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
    }

    /**
     * Verify a plain password against a stored hash.
     */
    public static function verifyPassword(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }

    /**
     * Generate a random token (hex).
     */
    public static function token(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }

    /**
     * Generate a UUID v4.
     */
    public static function uuid(): string
    {
        $data    = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Enforce HTTPS (redirect if not).
     */
    public static function requireHttps(): void
    {
        if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
            $url = 'https://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '/');
            header('Location: ' . $url, true, 301);
            exit;
        }
    }
}
