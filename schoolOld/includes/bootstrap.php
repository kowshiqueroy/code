<?php
/**
 * BanglaEdu CMS - Core Bootstrap
 * Loaded by every public page
 */

// ─── Error Reporting (disable in production) ──────────────────────────────────
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// ─── App Constants ────────────────────────────────────────────────────────────
define('APP_ROOT',    dirname(__DIR__));
define('INCLUDE_DIR', __DIR__);
define('ASSET_URL',   '/assets');

// ─── Load Config ─────────────────────────────────────────────────────────────
$config_file = APP_ROOT . '/config.php';
if (!file_exists($config_file)) {
    header('Location: /setup.php');
    exit;
}
require_once $config_file;

// ─── Database Connection ──────────────────────────────────────────────────────
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset='.DB_CHARSET,
            DB_USER, DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    }
    return $pdo;
}

// ─── Settings Helper ──────────────────────────────────────────────────────────
function get_setting(string $key, string $default = ''): string {
    static $cache = [];
    if (!isset($cache[$key])) {
        try {
            $s = db()->prepare("SELECT `value` FROM settings WHERE `key`=?");
            $s->execute([$key]);
            $row = $s->fetch();
            $cache[$key] = $row ? ($row['value'] ?? $default) : $default;
        } catch (Exception $e) {
            $cache[$key] = $default;
        }
    }
    return $cache[$key];
}

// ─── Language System ──────────────────────────────────────────────────────────
session_start();
if (isset($_GET['lang']) && in_array($_GET['lang'], ['en','bn'])) {
    $_SESSION['lang'] = $_GET['lang'];
}
if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = get_setting('default_language', 'en');
}
define('LANG', $_SESSION['lang']);

function t(string $en, string $bn = ''): string {
    return (LANG === 'bn' && $bn !== '') ? $bn : $en;
}

function field(array $row, string $key): string {
    $lang_key = $key . '_' . LANG;
    $en_key   = $key . '_en';
    return $row[$lang_key] ?? $row[$en_key] ?? $row[$key] ?? '';
}

// ─── URL & Request Helpers ────────────────────────────────────────────────────
function current_page(): string {
    return preg_replace('/[^a-z0-9_-]/', '', strtolower($_GET['page'] ?? 'index'));
}

function url(string $page, array $params = []): string {
    $base = '/?page=' . $page;
    foreach ($params as $k => $v) {
        $base .= '&' . urlencode($k) . '=' . urlencode($v);
    }
    return $base;
}

function lang_url(string $lang): string {
    $params = $_GET;
    $params['lang'] = $lang;
    return '/?' . http_build_query($params);
}

function asset(string $path): string {
    return ASSET_URL . '/' . ltrim($path, '/');
}

function upload_url(string $path): string {
    return ASSET_URL . '/uploads/' . ltrim($path, '/');
}

// ─── Security Helpers ─────────────────────────────────────────────────────────
function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function sanitize(string $s): string {
    return htmlspecialchars(strip_tags(trim($s)), ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// ─── Image Helper ─────────────────────────────────────────────────────────────
function img(string $path, string $size = 'medium', string $alt = ''): string {
    if (empty($path)) return '';
    $ext  = pathinfo($path, PATHINFO_EXTENSION);
    $base = pathinfo($path, PATHINFO_FILENAME);
    $dir  = pathinfo($path, PATHINFO_DIRNAME);
    $sized_path = $dir . '/' . $base . '_' . $size . '.' . $ext;
    $full = APP_ROOT . '/assets/uploads/' . $sized_path;
    $src  = file_exists($full) ? upload_url($sized_path) : upload_url($path);
    return '<img src="' . h($src) . '" alt="' . h($alt) . '" loading="lazy">';
}

// ─── Pagination ───────────────────────────────────────────────────────────────
function paginate(int $total, int $per_page, int $current): array {
    $pages = (int)ceil($total / $per_page);
    return ['total'=>$total,'pages'=>$pages,'current'=>$current,'per_page'=>$per_page,'offset'=>($current-1)*$per_page];
}

// ─── Maintenance Mode ─────────────────────────────────────────────────────────
if (get_setting('maintenance_mode') === '1') {
    if (!defined('ADMIN_MODE')) {
        http_response_code(503);
        echo '<!DOCTYPE html><html><body style="font-family:sans-serif;text-align:center;padding:60px"><h1>🔧 Site Under Maintenance</h1><p>We will be back soon. / আমরা শীঘ্রই ফিরে আসব।</p></body></html>';
        exit;
    }
}
