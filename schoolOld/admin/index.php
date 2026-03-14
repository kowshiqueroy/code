<?php
/**
 * BanglaEdu CMS — Admin Panel
 * Full Content Management System
 */

define('ADMIN_MODE', true);
define('ADMIN_ROOT', __DIR__);
define('APP_ROOT', dirname(__DIR__));

require_once APP_ROOT . '/includes/bootstrap.php';

// ─── Session & Auth ───────────────────────────────────────────────────────────
function is_logged_in(): bool {
    return !empty($_SESSION['admin_user_id']) && !empty($_SESSION['admin_role']);
}

function require_login(): void {
    if (!is_logged_in()) {
        header('Location: /admin/?action=login');
        exit;
    }
}

function require_role(string $min_role): void {
    $roles = ['editor' => 1, 'admin' => 2, 'superadmin' => 3];
    $user_level = $roles[$_SESSION['admin_role'] ?? 'editor'] ?? 0;
    $required   = $roles[$min_role] ?? 2;
    if ($user_level < $required) {
        header('HTTP/1.1 403 Forbidden');
        die('<p style="font-family:sans-serif;padding:40px;color:#c00">⛔ Access denied. Insufficient permissions.</p>');
    }
}

// ─── Route ───────────────────────────────────────────────────────────────────
$action = preg_replace('/[^a-z0-9_]/', '', strtolower($_GET['action'] ?? 'dashboard'));
$ajax   = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) || !empty($_GET['ajax']);

// Public actions (no auth required)
$public_actions = ['login', 'logout'];

if (!in_array($action, $public_actions)) {
    require_login();
}

// ─── Dispatch ─────────────────────────────────────────────────────────────────
$admin_title = 'Admin Panel';
$content_file = ADMIN_ROOT . '/modules/' . $action . '.php';

// Handle login/logout
if ($action === 'logout') {
    session_destroy();
    header('Location: /admin/?action=login&msg=logged_out');
    exit;
}

if ($action === 'login') {
    $login_error = '';
    $login_msg = $_GET['msg'] ?? '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!$username || !$password) {
            $login_error = 'Please enter username and password.';
        } else {
            try {
                $stmt = db()->prepare("SELECT * FROM users WHERE (username=? OR email=?) AND is_active=1");
                $stmt->execute([$username, $username]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    $_SESSION['admin_user_id']   = $user['id'];
                    $_SESSION['admin_username']  = $user['username'];
                    $_SESSION['admin_full_name'] = $user['full_name'];
                    $_SESSION['admin_role']      = $user['role'];

                    db()->prepare("UPDATE users SET last_login=NOW() WHERE id=?")
                       ->execute([$user['id']]);

                    header('Location: /admin/');
                    exit;
                } else {
                    $login_error = 'Invalid username or password.';
                    // Rate limiting hint
                    sleep(1);
                }
            } catch (Exception $e) {
                $login_error = 'Database error. Please check configuration.';
            }
        }
    }

    // Render login page
    include ADMIN_ROOT . '/views/login.php';
    exit;
}

// ─── Render admin layout ──────────────────────────────────────────────────────
if (!file_exists($content_file)) {
    $content_file = ADMIN_ROOT . '/modules/dashboard.php';
    $action = 'dashboard';
}

ob_start();
include $content_file;
$page_content = ob_get_clean();

include ADMIN_ROOT . '/views/layout.php';
