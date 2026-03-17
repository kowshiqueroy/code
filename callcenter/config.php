<?php
// ============================================================
// config.php — Central configuration for Ovijat Call Center
// ============================================================

define('ROOT',       __DIR__);
define('APP_NAME',   'Ovijat Call Center');
define('APP_VERSION','1.0.0');
define('BASE_URL',   'http://localhost/code/callcenter');

// Database
define('DB_HOST',    'localhost');
define('DB_NAME',    'callcenter_db');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');

// Session
define('SESSION_TIMEOUT', 300); // 5 minutes idle → auto-logout
define('SESSION_WARN',    60);  // warn 60s before timeout

// Timezone
date_default_timezone_set('Asia/Dhaka');

// ── PDO Singleton ────────────────────────────────────────────
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            die('<div style="font-family:sans-serif;padding:2rem;color:#c0392b"><h2>Database Connection Failed</h2><p>' . htmlspecialchars($e->getMessage()) . '</p><p>Please run <a href="' . BASE_URL . '/setup.php">setup.php</a> first.</p></div>');
        }
    }
    return $pdo;
}

// ── Shorthand query helpers ──────────────────────────────────
function db_row(string $sql, array $params = []): array|false {
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetch();
}

function db_rows(string $sql, array $params = []): array {
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

function db_val(string $sql, array $params = []): mixed {
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchColumn();
}

function db_exec(string $sql, array $params = []): int {
    $st = db()->prepare($sql);
    $st->execute($params);
    return (int) db()->lastInsertId() ?: $st->rowCount();
}

// ── Setting helper ───────────────────────────────────────────
function setting(string $key, string $default = ''): string {
    static $cache = [];
    if (!isset($cache[$key])) {
        $val = db_val("SELECT value FROM settings WHERE `key` = ?", [$key]);
        $cache[$key] = ($val !== false) ? $val : $default;
    }
    return $cache[$key];
}
