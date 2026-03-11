<?php
/**
 * ============================================================
 * POS System — Core Configuration
 * ============================================================
 * FILE: config/config.php
 * Load this file first on EVERY request.
 */

declare(strict_types=1);

// ── Error Reporting (disable in production) ─────────────────
define('APP_ENV', getenv('APP_ENV') ?: 'production');
if (APP_ENV === 'development') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

// ── Path Constants ───────────────────────────────────────────
define('ROOT_PATH',   dirname(__DIR__));
define('CONFIG_PATH', __DIR__);
define('ASSET_PATH',  ROOT_PATH . '/assets');
define('UPLOAD_PATH', ROOT_PATH . '/uploads');
define('LOG_PATH',    ROOT_PATH . '/logs');

// ── Database Credentials ─────────────────────────────────────
// Override via environment variables in production!
define('DB_HOST',    getenv('DB_HOST')    ?: '127.0.0.1');
define('DB_PORT',    getenv('DB_PORT')    ?: '3306');
define('DB_NAME',    getenv('DB_NAME')    ?: 'pos_db');
define('DB_USER',    getenv('DB_USER')    ?: 'pos_user');
define('DB_PASS',    getenv('DB_PASS')    ?: 'StrongPass!123');
define('DB_CHARSET', 'utf8mb4');

// ── Session Config ───────────────────────────────────────────
define('SESSION_NAME',      'pos_sess');
define('SESSION_LIFETIME',  300);   // 5 minutes inactivity
define('SESSION_SECURE',    false); // Set TRUE when using HTTPS
define('SESSION_HTTP_ONLY', true);
define('SESSION_SAME_SITE', 'Strict');

// ── Security ─────────────────────────────────────────────────
define('CSRF_TOKEN_LENGTH', 32);
define('BCRYPT_COST',       12);

// ── App ──────────────────────────────────────────────────────
define('APP_NAME',      'POS System');
define('APP_VERSION',   '1.0.0');
define('APP_TIMEZONE',  'Asia/Dhaka');

date_default_timezone_set(APP_TIMEZONE);

// ── Autoload core helpers ────────────────────────────────────
require_once CONFIG_PATH . '/Database.php';
require_once CONFIG_PATH . '/Session.php';
require_once CONFIG_PATH . '/Security.php';
require_once CONFIG_PATH . '/Helpers.php';
