<?php
/**
 * CSRF.php — CSRF Token Protection
 */
declare(strict_types=1);

class CSRF
{
    private const FIELD = '_csrf_token';

    /** Generate and store a new token; return it. */
    public static function generate(): string
    {
        Auth::start();
        $token = bin2hex(random_bytes(32));
        $_SESSION[self::FIELD] = $token;
        $_SESSION[self::FIELD . '_exp'] = time() + CSRF_TOKEN_TTL;
        return $token;
    }

    /** Return the current token or generate one. */
    public static function token(): string
    {
        Auth::start();
        if (empty($_SESSION[self::FIELD]) || time() > ($_SESSION[self::FIELD . '_exp'] ?? 0)) {
            return self::generate();
        }
        return $_SESSION[self::FIELD];
    }

    /** Validate a submitted token; return true if valid. */
    public static function verify(string $submitted): bool
    {
        Auth::start();
        $stored = $_SESSION[self::FIELD] ?? '';
        $exp    = $_SESSION[self::FIELD . '_exp'] ?? 0;
        if (!$stored || time() > $exp) return false;
        return hash_equals($stored, $submitted);
    }

    /** Verify or abort with 403. */
    public static function requireValid(): void
    {
        $submitted = $_POST[self::FIELD]
            ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!self::verify($submitted)) {
            http_response_code(403);
            die(json_encode(['success' => false, 'message' => 'Invalid CSRF token.']));
        }
        // Rotate token after use
        self::generate();
    }

    /** Return an HTML hidden input for forms. */
    public static function field(): string
    {
        return '<input type="hidden" name="' . self::FIELD . '" value="' . htmlspecialchars(self::token()) . '">';
    }
}
