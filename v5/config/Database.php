<?php
/**
 * Database — PDO Singleton with prepared-statement helpers
 * FILE: config/Database.php
 */

declare(strict_types=1);

class Database
{
    private static ?PDO $instance = null;

    // Prevent direct instantiation
    private function __construct() {}
    private function __clone() {}

    /**
     * Return the shared PDO connection.
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_PERSISTENT         => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ];

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                // Log the real message but never expose to client
                error_log('[DB-ERROR] ' . $e->getMessage());
                http_response_code(503);
                die(json_encode(['error' => 'Database unavailable. Please try again later.']));
            }
        }

        return self::$instance;
    }

    /**
     * Execute a SELECT query and return all rows.
     *
     * @param string $sql    Parameterised SQL
     * @param array  $params Bound values
     * @return array<int, array<string, mixed>>
     */
    public static function query(string $sql, array $params = []): array
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Execute a SELECT query and return a single row.
     *
     * @return array<string, mixed>|null
     */
    public static function queryOne(string $sql, array $params = []): ?array
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Execute a scalar SELECT (e.g. COUNT, SUM).
     *
     * @return mixed
     */
    public static function scalar(string $sql, array $params = []): mixed
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    /**
     * Execute an INSERT / UPDATE / DELETE.
     * Returns the last insert ID for INSERTs, otherwise row count.
     */
    public static function execute(string $sql, array $params = []): int
    {
        $pdo  = self::getInstance();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        // If this was an INSERT, return last insert id
        $insertId = (int) $pdo->lastInsertId();
        return $insertId > 0 ? $insertId : $stmt->rowCount();
    }

    /**
     * Convenience INSERT that accepts a table name and data array.
     *
     * @param string               $table
     * @param array<string, mixed> $data
     * @return int  Inserted row ID
     */
    public static function insert(string $table, array $data): int
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO `{$table}` ({$columns}) VALUES ({$placeholders})";
        return self::execute($sql, array_values($data));
    }

    /**
     * Convenience UPDATE.
     *
     * @param string               $table
     * @param array<string, mixed> $data
     * @param array<string, mixed> $where   Column => value conditions (ANDed)
     * @return int  Rows affected
     */
    public static function update(string $table, array $data, array $where): int
    {
        $set   = implode(', ', array_map(fn($col) => "`{$col}` = ?", array_keys($data)));
        $cond  = implode(' AND ', array_map(fn($col) => "`{$col}` = ?", array_keys($where)));
        $sql   = "UPDATE `{$table}` SET {$set} WHERE {$cond}";
        $params = array_merge(array_values($data), array_values($where));
        return self::execute($sql, $params);
    }

    /**
     * Begin a transaction, execute a callable, then commit or rollback.
     *
     * @param callable $callback  Receives the PDO instance
     * @return mixed  Whatever the callback returns
     * @throws Throwable
     */
    public static function transaction(callable $callback): mixed
    {
        $pdo = self::getInstance();
        $pdo->beginTransaction();
        try {
            $result = $callback($pdo);
            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Write an entry to the audit_logs table.
     *
     * @param string               $action
     * @param string               $tableName
     * @param int|null             $recordId
     * @param array<string,mixed>|null $before
     * @param array<string,mixed>|null $after
     */
    public static function audit(
        string $action,
        string $tableName,
        ?int   $recordId  = null,
        ?array $before    = null,
        ?array $after     = null,
        ?string $notes    = null
    ): void {
        $userId = Session::userId();
        self::insert('audit_logs', [
            'user_id'     => $userId,
            'action'      => $action,
            'table_name'  => $tableName,
            'record_id'   => $recordId,
            'before_data' => $before ? json_encode($before) : null,
            'after_data'  => $after  ? json_encode($after)  : null,
            'ip_address'  => $_SERVER['REMOTE_ADDR']        ?? null,
            'user_agent'  => $_SERVER['HTTP_USER_AGENT']    ?? null,
            'notes'       => $notes,
        ]);
    }
}
