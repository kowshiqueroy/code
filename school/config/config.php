<?php
// ============================================================
// config.php — Database & Site Configuration
// ============================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'school_db');
define('DB_CHARSET', 'utf8mb4');

// Site base path (auto-detected; override if needed)
define('SITE_ROOT', dirname(__DIR__));
define('BASE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\'));
define('UPLOAD_PATH', SITE_ROOT . '/uploads/');
define('UPLOAD_URL', BASE_URL . '/uploads/');

// Image sizes (width x height, 0 = proportional)
define('IMG_THUMB',  ['w' => 150, 'h' => 150]);
define('IMG_MEDIUM', ['w' => 600, 'h' => 400]);
define('IMG_LARGE',  ['w' => 1200, 'h' => 800]);
define('IMG_PORTRAIT', ['w' => 300, 'h' => 300]); // staff/teacher photos 1:1

// Session
define('SESSION_NAME', 'school_admin_sess');
define('ADMIN_PATH', BASE_URL . '/admin/');

// Timezone
date_default_timezone_set('Asia/Dhaka');

// Error display (set false in production)
define('DEBUG_MODE', true);
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// ── PDO Connection ─────────────────────────────────────────
function getDB(): PDO {
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
            if (DEBUG_MODE) {
                die('<div style="font-family:monospace;background:#fee;padding:20px;border:2px solid red;margin:20px"><b>DB Error:</b> ' . htmlspecialchars($e->getMessage()) . '</div>');
            } else {
                die('Database connection failed. Please contact administrator.');
            }
        }
    }
    return $pdo;
}
