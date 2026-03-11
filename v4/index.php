<?php
// ============================================================
// index.php — Application Entry Point
// ============================================================
declare(strict_types=1);

// Block direct access to sensitive dirs
if (strpos($_SERVER['REQUEST_URI'] ?? '', '..') !== false) {
    http_response_code(400);
    exit;
}

require_once __DIR__ . '/config/config.php';

session_start_secure();

// Redirect to login if not authenticated
$page = sanitize_string($_GET['page'] ?? 'pos');
$publicPages = ['login', 'verify'];

if (!is_logged_in() && !in_array($page, $publicPages, true)) {
    header('Location: ' . APP_URL . '/index.php?page=login');
    exit;
}

// CSRF token for meta tag
$csrfToken = csrf_token();

// Get settings
$settings = [];
if (is_logged_in()) {
    try { $settings = get_settings(); } catch (Throwable $e) { $settings = []; }
}

$shopName   = htmlspecialchars($settings['shop_name']    ?? APP_NAME, ENT_QUOTES);
$currency   = htmlspecialchars($settings['currency_symbol'] ?? '৳',   ENT_QUOTES);
$userRole   = htmlspecialchars(current_user_role(),           ENT_QUOTES);
$userName   = htmlspecialchars($_SESSION['user_name']  ?? '', ENT_QUOTES);
$userInitial= mb_strtoupper(mb_substr($userName ?: 'U', 0, 1));

// Role-based nav
$isAdmin = $userRole === 'admin';

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
if (isset($_SERVER['HTTPS'])) {
    header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline' fonts.googleapis.com; font-src 'self' fonts.gstatic.com; img-src 'self' data: blob: *; script-src 'self' 'unsafe-inline'; connect-src 'self';");
}
?>
<!DOCTYPE html>
<html lang="en" data-currency="<?= $currency ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#f5c518">
    <meta name="description" content="<?= $shopName ?> — Point of Sale System">
    <meta name="csrf" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title><?= $shopName ?> — POS</title>

    <link rel="manifest" href="<?= APP_URL ?>/manifest.json">
    <link rel="icon" href="<?= APP_URL ?>/assets/icons/icon-192.png">
    <link rel="apple-touch-icon" href="<?= APP_URL ?>/assets/icons/icon-192.png">

    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/main.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/pos.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/print.css" media="print">

    <!-- Preload critical JS -->
    <link rel="preload" href="<?= APP_URL ?>/assets/js/utils.js" as="script">
    <link rel="preload" href="<?= APP_URL ?>/assets/js/db.js"    as="script">
</head>
<body class="<?= $page === 'pos' ? 'pos-fullscreen' : '' ?>">

<?php if (is_logged_in()): ?>
<!-- ── App Shell ─────────────────────────────────────── -->
<div class="app-shell">

    <!-- Header -->
    <header class="app-header" role="banner">
        <button class="nav-toggle btn-ghost btn-sm" aria-label="Toggle navigation" id="nav-toggle">☰</button>
        <a href="<?= APP_URL ?>/?page=pos" class="header-brand" data-page="pos">
            <div class="header-brand-icon">P</div>
            <?= $shopName ?>
        </a>
        <div class="header-spacer"></div>

        <!-- Offline queue badge -->
        <div style="position:relative">
            <button class="btn-ghost btn-sm" data-page="sync" title="Offline Queue" style="font-size:16px">📡</button>
            <span id="offline-queue-badge" class="nav-badge" hidden style="position:absolute;top:-4px;right:-4px">0</span>
        </div>

        <!-- Install PWA -->
        <button id="install-pwa-btn" class="btn-ghost btn-sm" hidden title="Install App" style="font-size:16px">📲</button>

        <div class="header-actions">
            <span id="session-timer" class="session-timer"></span>
            <span id="conn-indicator" class="conn-dot offline" title="Checking..."></span>
            <div class="header-user">
                <div class="header-avatar"><?= $userInitial ?></div>
                <span><?= $userName ?></span>
                <span class="badge badge-muted" style="font-size:9px;padding:1px 5px"><?= strtoupper($userRole) ?></span>
            </div>
            <a href="<?= APP_URL ?>/api/auth/logout.php" class="btn-ghost btn-sm" style="font-size:13px" title="Logout">↪ Out</a>
        </div>
    </header>

    <!-- Offline Banner -->
    <div id="offline-banner" class="offline-banner" hidden role="alert" aria-live="polite">
        📴 <strong>OFFLINE MODE</strong> — Sales will be saved locally and synced when connection is restored.
    </div>

    <!-- Sync Indicator -->
    <div id="sync-indicator" hidden style="font-size:12px;padding:6px 16px"></div>

    <!-- Side Navigation -->
    <nav id="side-nav" role="navigation" aria-label="Main navigation">
        <div class="nav-section">
            <div class="nav-section-label">POS</div>
            <button class="nav-link <?= $page==='pos' ? 'nav-link--active' : '' ?>" data-page="pos">
                <span class="nav-link-icon">🏪</span> Cash Register
            </button>
        </div>
        <div class="nav-section">
            <div class="nav-section-label">Inventory</div>
            <button class="nav-link <?= $page==='products' ? 'nav-link--active' : '' ?>" data-page="products">
                <span class="nav-link-icon">📦</span> Products
            </button>
            <button class="nav-link <?= $page==='inventory' ? 'nav-link--active' : '' ?>" data-page="inventory">
                <span class="nav-link-icon">📊</span> Stock & Barcodes
            </button>
            <button class="nav-link <?= $page==='customers' ? 'nav-link--active' : '' ?>" data-page="customers">
                <span class="nav-link-icon">👥</span> Customers
            </button>
        </div>
        <div class="nav-section">
            <div class="nav-section-label">Finance</div>
            <button class="nav-link <?= $page==='sales' ? 'nav-link--active' : '' ?>" data-page="sales">
                <span class="nav-link-icon">🧾</span> Sales History
            </button>
            <button class="nav-link <?= $page==='finance' ? 'nav-link--active' : '' ?>" data-page="finance">
                <span class="nav-link-icon">💰</span> Ledger / Petty Cash
            </button>
            <button class="nav-link <?= $page==='reports' ? 'nav-link--active' : '' ?>" data-page="reports">
                <span class="nav-link-icon">📈</span> Reports
            </button>
        </div>
        <?php if ($isAdmin): ?>
        <div class="nav-section">
            <div class="nav-section-label">Admin</div>
            <button class="nav-link <?= $page==='users' ? 'nav-link--active' : '' ?>" data-page="users">
                <span class="nav-link-icon">👤</span> Users & Roles
            </button>
            <button class="nav-link" data-page="sync" style="position:relative">
                <span class="nav-link-icon">🔄</span> Offline Sync Queue
                <span id="sync-nav-badge" class="nav-badge" hidden>!</span>
            </button>
            <button class="nav-link <?= $page==='settings' ? 'nav-link--active' : '' ?>" data-page="settings">
                <span class="nav-link-icon">⚙️</span> Settings
            </button>
        </div>
        <?php endif; ?>
    </nav>

    <!-- Main Content -->
    <main id="main-content" role="main" aria-label="Page content">
        <!-- Content loaded by SPA router -->
        <div class="page-loading-placeholder" style="display:flex;align-items:center;justify-content:center;height:200px;color:var(--c-text-faint)">
            Loading…
        </div>
    </main>

    <!-- Footer -->
    <footer class="app-footer" role="contentinfo">
        <span><?= $shopName ?> &copy; <?= date('Y') ?></span>
        <span>v<?= APP_VER ?> &mdash; <?= htmlspecialchars($userRole, ENT_QUOTES) ?> mode</span>
        <span style="font-size:10px">Ctrl+K: Search &nbsp;|&nbsp; Ctrl+Enter: Checkout &nbsp;|&nbsp; Ctrl+D: Park</span>
    </footer>

</div><!-- end .app-shell -->

<?php else: ?>
<!-- ── Login Page (no shell) ─────────────────────────── -->
<div id="main-content">
    <?php include __DIR__ . '/modules/auth/login.php'; ?>
</div>
<?php endif; ?>

<!-- ── Toast Container ───────────────────────────────────── -->
<div id="toast-container" aria-live="polite" aria-atomic="true"></div>

<!-- ── Global Modals ─────────────────────────────────────── -->
<!-- Variant Selection Modal -->
<div id="variant-modal" class="modal" hidden role="dialog" aria-modal="true" aria-labelledby="variant-modal-title">
    <div class="modal-backdrop"></div>
    <div class="modal-box">
        <button class="modal-close" onclick="Utils.closeModal('variant-modal')" aria-label="Close">×</button>
        <h2 class="modal-title" id="variant-modal-title">Select Variant</h2>
        <div id="variant-list"></div>
    </div>
</div>

<!-- Draft Recall Modal -->
<div id="draft-modal" class="modal" hidden role="dialog" aria-modal="true" aria-labelledby="draft-modal-title">
    <div class="modal-backdrop"></div>
    <div class="modal-box">
        <button class="modal-close" onclick="Utils.closeModal('draft-modal')" aria-label="Close">×</button>
        <h2 class="modal-title" id="draft-modal-title">Parked Sales</h2>
        <div id="draft-list"></div>
    </div>
</div>

<!-- Checkout Success Modal -->
<div id="checkout-success-modal" class="modal" hidden role="dialog" aria-modal="true">
    <div class="modal-backdrop"></div>
    <div class="modal-box success-modal-box">
        <div class="success-icon">✅</div>
        <p class="success-invoice">Invoice: <strong id="success-invoice-no"></strong></p>
        <div class="success-total" id="success-total"></div>
        <p class="success-change">Change: <span id="success-change"></span></p>
        <div class="success-actions">
            <button class="btn btn-primary btn-sm" id="success-print-a4-btn">🖨 A4</button>
            <button class="btn btn-secondary btn-sm" id="success-print-thermal-btn">🖨 Thermal</button>
            <button class="btn btn-success" id="success-new-sale-btn">+ New Sale</button>
        </div>
        <div class="success-offline-note" id="success-offline-note" hidden>
            ⚠️ Saved offline. Will sync to server when connection is restored and require admin confirmation.
        </div>
    </div>
</div>

<!-- Customer Search Modal -->
<div id="customer-modal" class="modal" hidden role="dialog" aria-modal="true" aria-labelledby="customer-modal-title">
    <div class="modal-backdrop"></div>
    <div class="modal-box">
        <button class="modal-close" onclick="Utils.closeModal('customer-modal')" aria-label="Close">×</button>
        <h2 class="modal-title" id="customer-modal-title">Find / Add Customer</h2>
        <div class="form-group">
            <label class="form-label">Phone Number</label>
            <input type="tel" id="customer-phone-input" class="form-input" placeholder="Enter phone number" autocomplete="tel">
        </div>
        <div id="customer-search-result"></div>
        <button class="btn btn-primary btn-full" onclick="Utils.$('#customer-phone-input').dispatchEvent(new KeyboardEvent('keydown',{key:'Enter',bubbles:true}))">
            🔍 Search / Add
        </button>
    </div>
</div>

<!-- Scripts -->
<script src="<?= APP_URL ?>/assets/js/utils.js"></script>
<script src="<?= APP_URL ?>/assets/js/db.js"></script>
<script src="<?= APP_URL ?>/assets/js/sync.js"></script>
<script src="<?= APP_URL ?>/assets/js/pos.js"></script>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>

<script>
// Pass PHP data to JS
window.POS_CONFIG = {
    appUrl: <?= json_encode(APP_URL) ?>,
    userRole: <?= json_encode($userRole) ?>,
    userId: <?= json_encode((int)($_SESSION['user_id'] ?? 0)) ?>,
    settings: <?= json_encode($settings) ?>,
};

// Mobile nav toggle
document.getElementById('nav-toggle')?.addEventListener('click', () => {
    document.getElementById('side-nav').classList.toggle('nav-open');
});
</script>

</body>
</html>
