<?php
/**
 * AuditLog.php — Transparent Audit Trail
 */
declare(strict_types=1);

class AuditLog
{
    public static function write(
        int     $userId,
        string  $action,
        string  $table,
        ?int    $recordId   = null,
        ?array  $before     = null,
        ?array  $after      = null
    ): void {
        try {
            DB::run(
                'INSERT INTO audit_logs
                 (user_id, action, table_name, record_id, before_data, after_data, ip_address, user_agent)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $userId,
                    strtoupper(substr($action, 0, 80)),
                    substr($table, 0, 80),
                    $recordId,
                    $before  ? json_encode($before,  JSON_UNESCAPED_UNICODE) : null,
                    $after   ? json_encode($after,   JSON_UNESCAPED_UNICODE) : null,
                    $_SERVER['REMOTE_ADDR']      ?? null,
                    $_SERVER['HTTP_USER_AGENT']  ?? null,
                ]
            );
        } catch (Throwable) {
            // Audit failures must never crash the main flow
        }
    }
}


// ─────────────────────────────────────────────────────────────────────────────

/**
 * Response.php — JSON API Response Helper
 */
class Response
{
    public static function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function success(mixed $data = null, string $message = 'OK'): never
    {
        self::json(['success' => true, 'message' => $message, 'data' => $data]);
    }

    public static function error(string $message, int $status = 400, mixed $errors = null): never
    {
        self::json(['success' => false, 'message' => $message, 'errors' => $errors], $status);
    }
}


// ─────────────────────────────────────────────────────────────────────────────

/**
 * Barcode.php — Auto-generate numeric barcodes
 */
class Barcode
{
    /**
     * Generate a unique 13-digit numeric barcode (EAN-13 style).
     * Uses barcode_sequence table to guarantee uniqueness.
     */
    public static function generate(): string
    {
        DB::run('INSERT INTO barcode_sequence () VALUES ()');
        $seq = (int)DB::lastId();
        // Prefix 200 (private-use range) + zero-padded sequence + check digit
        $partial = '200' . str_pad((string)$seq, 9, '0', STR_PAD_LEFT);
        return $partial . self::ean13Check($partial);
    }

    /** Calculate EAN-13 check digit. */
    private static function ean13Check(string $partial): int
    {
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int)$partial[$i] * ($i % 2 === 0 ? 1 : 3);
        }
        return (10 - ($sum % 10)) % 10;
    }
}


// ─────────────────────────────────────────────────────────────────────────────

/**
 * Finance.php — Ledger & Balance Operations
 */
class Finance
{
    /**
     * Add an entry to finance_ledger and return running balance.
     */
    public static function addEntry(
        string  $entryType,
        float   $amount,
        string  $description,
        string  $referenceType = 'manual',
        ?int    $referenceId   = null,
        ?int    $srId          = null,
        int     $createdBy     = 0
    ): float {
        // Get last running balance
        $last = DB::scalar('SELECT running_balance FROM finance_ledger ORDER BY id DESC LIMIT 1') ?? 0.0;
        $running = (float)$last + $amount;

        DB::run(
            'INSERT INTO finance_ledger
             (entry_type, reference_type, reference_id, amount, description, sr_id, running_balance, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$entryType, $referenceType, $referenceId, $amount, $description, $srId, $running, $createdBy]
        );
        return $running;
    }

    /**
     * Add an SR ledger entry.
     */
    public static function addSrEntry(
        int    $srId,
        string $entryType,
        float  $amount,
        string $description,
        string $refType  = 'manual',
        ?int   $refId    = null,
        int    $createdBy = 0
    ): void {
        DB::run(
            'INSERT INTO sr_ledger (sr_id, entry_type, reference_type, reference_id, amount, description, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$srId, $entryType, $refType, $refId, $amount, $description, $createdBy]
        );
    }

    /**
     * Get the current showroom balance.
     */
    public static function getBalance(): float
    {
        return (float)(DB::scalar('SELECT running_balance FROM finance_ledger ORDER BY id DESC LIMIT 1') ?? 0.0);
    }

    /**
     * Compute ledger summary for a date range.
     * Returns: opening_balance, total_sales, total_cash_in, total_expenses, total_withdrawals, closing_balance
     */
    public static function summary(string $from, string $to): array
    {
        // Opening balance = last entry before $from
        $opening = (float)(DB::scalar(
            "SELECT running_balance FROM finance_ledger
             WHERE entry_date < ? ORDER BY id DESC LIMIT 1",
            [$from . ' 00:00:00']
        ) ?? 0.0);

        $rows = DB::all(
            "SELECT entry_type, SUM(amount) AS total
             FROM finance_ledger
             WHERE entry_date BETWEEN ? AND ?
             GROUP BY entry_type",
            [$from . ' 00:00:00', $to . ' 23:59:59']
        );

        $map = [];
        foreach ($rows as $r) {
            $map[$r['entry_type']] = (float)$r['total'];
        }

        $sales       = ($map['sale_cash'] ?? 0) + ($map['sale_card'] ?? 0);
        $cashIn      = $map['cash_in'] ?? 0;
        $expenses    = abs($map['expense'] ?? 0);
        $withdrawals = abs($map['owner_withdrawal'] ?? 0);
        $closing     = $opening + $sales + $cashIn - $expenses - $withdrawals;

        return compact('opening', 'sales', 'cashIn', 'expenses', 'withdrawals', 'closing');
    }
}
