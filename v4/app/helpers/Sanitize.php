<?php
/**
 * Sanitize.php — Input Sanitization Utilities
 */
declare(strict_types=1);

class Sanitize
{
    /** Strip tags and trim a string. */
    public static function str(mixed $val, int $maxLen = 500): string
    {
        return mb_substr(strip_tags(trim((string)$val)), 0, $maxLen);
    }

    /** Return a safe integer or null. */
    public static function int(mixed $val): ?int
    {
        $v = filter_var($val, FILTER_VALIDATE_INT);
        return $v === false ? null : (int)$v;
    }

    /** Return a safe float or null. */
    public static function float(mixed $val): ?float
    {
        $v = filter_var($val, FILTER_VALIDATE_FLOAT);
        return $v === false ? null : (float)$v;
    }

    /** Return a sanitized email or empty string. */
    public static function email(mixed $val): string
    {
        return (string)filter_var(trim((string)$val), FILTER_SANITIZE_EMAIL);
    }

    /** Escape for HTML output. */
    public static function html(mixed $val): string
    {
        return htmlspecialchars((string)$val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Safely decode JSON; return array or null. */
    public static function json(mixed $val): ?array
    {
        if (!is_string($val)) return null;
        $data = json_decode($val, true);
        return is_array($data) ? $data : null;
    }

    /** Validate and format a date string (Y-m-d). */
    public static function date(mixed $val): ?string
    {
        $d = DateTime::createFromFormat('Y-m-d', (string)$val);
        return ($d && $d->format('Y-m-d') === (string)$val) ? (string)$val : null;
    }
}
