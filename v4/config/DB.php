<?php
/**
 * DB.php — PDO Singleton Database Wrapper
 */

declare(strict_types=1);

class DB
{
    private static ?PDO $instance = null;

    private function __construct() {}
    private function __clone() {}

    /**
     * Return the shared PDO instance (lazy-connect).
     */
    public static function conn(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
            );
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_PERSISTENT         => false,
                PDO::MYSQL_ATTR_FOUND_ROWS   => true,
            ];
            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                // Never expose credentials in production
                $msg = APP_ENV === 'development'
                    ? 'Database connection failed: ' . $e->getMessage()
                    : 'Database connection failed. Please contact support.';
                http_response_code(503);
                die(json_encode(['success' => false, 'message' => $msg]));
            }
        }
        return self::$instance;
    }

    /**
     * Shorthand: prepare + execute, return PDOStatement.
     *
     * @param  string $sql
     * @param  array  $params  Positional or named parameters
     */
    public static function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::conn()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Fetch a single row or null.
     */
    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /**
     * Fetch all rows.
     */
    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    /**
     * Fetch a single scalar value.
     */
    public static function scalar(string $sql, array $params = []): mixed
    {
        return self::run($sql, $params)->fetchColumn();
    }

    /**
     * Return the last inserted ID.
     */
    public static function lastId(): string
    {
        return self::conn()->lastInsertId();
    }

    public static function beginTransaction(): void { self::conn()->beginTransaction(); }
    public static function commit(): void           { self::conn()->commit(); }
    public static function rollback(): void         { self::conn()->rollBack(); }
}
