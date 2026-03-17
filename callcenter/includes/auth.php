<?php
// ============================================================
// includes/auth.php — Session management + role guards
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ── Role hierarchy ────────────────────────────────────────────
const ROLE_LEVELS = [
    'super_admin'       => 4,
    'senior_executive'  => 3,
    'executive'         => 2,
    'viewer'            => 1,
];

// ── Current user helpers ─────────────────────────────────────
function current_user(): array {
    return $_SESSION['user'] ?? [];
}

function is_logged_in(): bool {
    return !empty($_SESSION['user']['id']);
}

function current_role(): string {
    return $_SESSION['user']['role'] ?? '';
}

function current_user_id(): int {
    return (int)($_SESSION['user']['id'] ?? 0);
}

function role_level(string $role = ''): int {
    $role = $role ?: current_role();
    return ROLE_LEVELS[$role] ?? 0;
}

// ── Session timeout ───────────────────────────────────────────
function check_session_timeout(): void {
    if (!is_logged_in()) return;
    $timeout = defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 300;
    $last    = $_SESSION['last_activity'] ?? time();
    if ((time() - $last) > $timeout) {
        $name = $_SESSION['user']['name'] ?? 'User';
        session_destroy();
        $url = (defined('BASE_URL') ? BASE_URL : '') . '/login.php?timeout=1';
        header('Location: ' . $url);
        exit;
    }
    $_SESSION['last_activity'] = time();
}

// ── Login ─────────────────────────────────────────────────────
function attempt_login(string $email, string $pass): bool {
    if (!defined('ROOT')) return false;
    require_once ROOT . '/config.php';
    $user = db_row("SELECT * FROM users WHERE email = ? AND status = 'active'", [trim($email)]);
    if (!$user || !password_verify($pass, $user['password'])) return false;

    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id'    => $user['id'],
        'name'  => $user['name'],
        'email' => $user['email'],
        'role'  => $user['role'],
        'phone' => $user['phone'],
    ];
    $_SESSION['last_activity'] = time();
    $_SESSION['csrf_token']    = bin2hex(random_bytes(32));

    // Update last_login
    db_exec("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);
    return true;
}

// ── Logout ────────────────────────────────────────────────────
function logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

// ── Guards ────────────────────────────────────────────────────
function require_login(): void {
    check_session_timeout();
    if (!is_logged_in()) {
        $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '');
        header('Location: ' . (defined('BASE_URL') ? BASE_URL : '') . '/login.php?next=' . $redirect);
        exit;
    }
}

function require_role(string $min_role): void {
    require_login();
    if (role_level() < role_level($min_role)) {
        http_response_code(403);
        include ROOT . '/partials/header.php';
        echo '<div class="container py-5 text-center"><div class="alert alert-danger"><h4>Access Denied</h4>
              <p>You do not have permission to access this page.</p>
              <a href="' . BASE_URL . '/modules/dashboard/index.php" class="btn btn-primary">Back to Dashboard</a>
              </div></div>';
        include ROOT . '/partials/footer.php';
        exit;
    }
}

// ── Fine-grained permission check ────────────────────────────
function can(string $action, string $module = ''): bool {
    $role = current_role();
    $lvl  = role_level();

    // super_admin can do everything
    if ($role === 'super_admin') return true;

    // viewer: read only, no delete/create
    if ($role === 'viewer') {
        return in_array($action, ['view', 'read', 'list', 'run_report']);
    }

    // executive and above: standard create/edit/delete
    if (in_array($action, ['view','read','list','create','edit'])) return true;

    if ($action === 'delete') {
        // Only senior+ can delete most things
        return $lvl >= role_level('senior_executive');
    }

    if ($action === 'assign') {
        return $lvl >= role_level('senior_executive');
    }

    if ($action === 'approve_leave') {
        return $role === 'super_admin';
    }

    if ($action === 'refer_leave') {
        return $lvl >= role_level('senior_executive');
    }

    if ($action === 'manage_users') {
        return $role === 'super_admin';
    }

    if ($action === 'manage_settings') {
        return $role === 'super_admin';
    }

    if ($action === 'team_report') {
        return $lvl >= role_level('senior_executive');
    }

    return false;
}

// ── CSRF ─────────────────────────────────────────────────────
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function verify_csrf(): bool {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function require_csrf(): void {
    if (!verify_csrf()) {
        http_response_code(403);
        die('CSRF token mismatch. Please go back and try again.');
    }
}
