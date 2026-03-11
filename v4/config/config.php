<?php
/**
 * config.php — Core Configuration
 * POS & Store Management System
 */

declare(strict_types=1);

// ── Environment ──────────────────────────────────────────────────────────────
define('APP_ENV',     getenv('APP_ENV') ?: 'production'); // 'development' | 'production'
define('APP_VERSION', '1.0.0');
define('APP_NAME',    'POS Store Manager');
define('BASE_URL',    rtrim(getenv('APP_BASE_URL') ?: 'http://localhost/code', '/'));
define('BASE_PATH',   dirname(__DIR__));

// ── Database ─────────────────────────────────────────────────────────────────
define('DB_HOST',     getenv('DB_HOST')     ?: '127.0.0.1');
define('DB_PORT',     (int)(getenv('DB_PORT') ?: 3306));
define('DB_NAME',     getenv('DB_NAME')     ?: 'pos_store_db');
define('DB_USER',     getenv('DB_USER')     ?: 'root');
define('DB_PASS',     getenv('DB_PASS')     ?: '');
define('DB_CHARSET',  'utf8mb4');

// ── Security ─────────────────────────────────────────────────────────────────
define('SESSION_NAME',      'pos_sess');
define('SESSION_LIFETIME',  300);         // 5 minutes inactivity -> auto-logout
define('CSRF_TOKEN_TTL',    3600);        // 1 hour
define('PASSWORD_ALGO',     PASSWORD_BCRYPT);
define('PASSWORD_COST',     12);

// ── Storage Paths ─────────────────────────────────────────────────────────────
define('STORAGE_PATH', BASE_PATH . '/storage');
define('LOG_PATH',     STORAGE_PATH . '/logs');
define('CACHE_PATH',   STORAGE_PATH . '/cache');

// ── Upload ───────────────────────────────────────────────────────────────────
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024); // 5 MB
define('UPLOAD_ALLOWED',  ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);

// ── Error Handling ────────────────────────────────────────────────────────────
if (APP_ENV === 'development') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}
ini_set('log_errors', '1');
ini_set('error_log', LOG_PATH . '/php_errors.log');

// ── Session Config (called before session_start) ──────────────────────────────
ini_set('session.name',             SESSION_NAME);
ini_set('session.gc_maxlifetime',   (string)SESSION_LIFETIME);
ini_set('session.cookie_httponly',  '1');
ini_set('session.cookie_samesite',  'Strict');
ini_set('session.use_strict_mode',  '1');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', '1');
}
session_set_cookie_params([
    'lifetime' => SESSION_LIFETIME,
    'path'     => '/',
    'samesite' => 'Strict',
    'httponly' => true,
]);

// ── Timezone ─────────────────────────────────────────────────────────────────
date_default_timezone_set('UTC'); // Overridden after DB settings load

// ── Autoloader (simple PSR-4 style) ──────────────────────────────────────────
spl_autoload_register(function (string $class): void {
    $map = [
        'DB'       => BASE_PATH . '/config/DB.php',
        'Auth'     => BASE_PATH . '/app/helpers/Auth.php',
        'CSRF'     => BASE_PATH . '/app/helpers/CSRF.php',
        'Sanitize' => BASE_PATH . '/app/helpers/Sanitize.php',
        'AuditLog' => BASE_PATH . '/app/helpers/AuditLog.php',
        'Response' => BASE_PATH . '/app/helpers/Response.php',
        'Barcode'  => BASE_PATH . '/app/helpers/Barcode.php',
        'Finance'  => BASE_PATH . '/app/helpers/Finance.php',
    ];
    if (isset($map[$class]) && file_exists($map[$class])) {
        require_once $map[$class];
    }
});
