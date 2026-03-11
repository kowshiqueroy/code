<?php
/**
 * Helpers — App-level utility functions
 * FILE: config/Helpers.php
 */

declare(strict_types=1);

class Helpers
{
    /**
     * Generate a unique numeric barcode (EAN-13-style, 13 digits).
     * Prefix: 200 (internal use range).
     */
    public static function generateBarcode(int $productId, ?int $variantId = null): string
    {
        // 200 + padded product id (6 digits) + variant (4 digits) + random(2)
        $base = '200'
            . str_pad((string) $productId, 6, '0', STR_PAD_LEFT)
            . str_pad((string) ($variantId ?? 0), 4, '0', STR_PAD_LEFT);
        // Luhn-style check digit (mod-10)
        $sum  = 0;
        foreach (str_split($base) as $i => $d) {
            $n    = (int) $d;
            $sum += ($i % 2 === 0) ? $n : $n * 3;
        }
        $check = (10 - ($sum % 10)) % 10;
        return $base . $check;
    }

    /**
     * Generate a human-readable invoice number.
     * e.g. INV-20240115-0042
     */
    public static function generateInvoiceNumber(string $prefix = 'INV'): string
    {
        $date = date('Ymd');
        $seq  = Database::scalar(
            "SELECT COUNT(*) FROM sales WHERE DATE(created_at) = CURDATE()"
        );
        return $prefix . '-' . $date . '-' . str_pad((string)((int)$seq + 1), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Format a monetary value using shop settings.
     */
    public static function money(float $amount, string $symbol = '৳'): string
    {
        return $symbol . number_format($amount, 2);
    }

    /**
     * Return a JSON response and exit.
     *
     * @param mixed $data
     */
    public static function jsonResponse(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Return a JSON error response and exit.
     */
    public static function jsonError(string $message, int $status = 400): never
    {
        self::jsonResponse(['success' => false, 'error' => $message], $status);
    }

    /**
     * Return a JSON success response and exit.
     */
    public static function jsonSuccess(mixed $data = null, string $message = 'OK'): never
    {
        self::jsonResponse(['success' => true, 'message' => $message, 'data' => $data]);
    }

    /**
     * Validate required POST fields, return null or an error message.
     *
     * @param string[] $fields
     */
    public static function requirePostFields(array $fields): ?string
    {
        foreach ($fields as $f) {
            if (!isset($_POST[$f]) || trim($_POST[$f]) === '') {
                return "Field '{$f}' is required.";
            }
        }
        return null;
    }

    /**
     * Load shop settings from DB (cached in static variable).
     *
     * @return array<string, mixed>
     */
    public static function shopSettings(): array
    {
        static $settings = null;
        if ($settings === null) {
            $settings = Database::queryOne("SELECT * FROM settings WHERE id = 1") ?? [];
        }
        return $settings;
    }

    /**
     * Log application errors to file.
     */
    public static function logError(string $message, array $context = []): void
    {
        $entry = date('[Y-m-d H:i:s]') . ' ' . $message;
        if ($context) {
            $entry .= ' | ' . json_encode($context);
        }
        @file_put_contents(LOG_PATH . '/app.log', $entry . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
