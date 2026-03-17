<?php
// Called from every module. $page_title must be set before including.
if (!defined('ROOT')) die('Direct access not allowed.');
require_once ROOT . '/config.php';
require_once ROOT . '/includes/auth.php';
require_once ROOT . '/includes/helpers.php';
require_login();

$page_title  = $page_title  ?? 'Ovijat Call Center';
$active_page = $active_page ?? '';
$user        = current_user();

// Pending sidebar badge counts
$pending_callbacks = (int) db_val(
    "SELECT COUNT(*) FROM callbacks WHERE assigned_to = ? AND status = 'pending' AND scheduled_at <= NOW()",
    [$user['id']]
);
$pending_tasks = (int) db_val(
    "SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status IN ('pending','in_progress')",
    [$user['id']]
);
$pending_sync = 0; // handled by JS (IndexedDB count)

$flashes = get_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= h($page_title) ?> — <?= h(APP_NAME) ?></title>
  <link rel="manifest" href="<?= BASE_URL ?>/manifest.json">
  <meta name="theme-color" content="#1e2330">
  <!-- Bootstrap 5 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <!-- App CSS -->
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css">
</head>
<body>

<!-- ── Top Navbar ─────────────────────────────────────────── -->
<nav class="navbar app-topbar px-3 py-0" id="topbar">
  <!-- Hamburger (mobile) -->
  <button class="btn btn-link text-white p-0 me-3 d-lg-none" type="button"
          data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas">
    <i class="bi bi-list fs-5"></i>
  </button>

  <!-- App name / breadcrumb (desktop: toggle sidebar) -->
  <button class="btn btn-link text-white p-0 me-3 d-none d-lg-block" id="sidebarToggle">
    <i class="bi bi-list fs-5"></i>
  </button>
  <span class="navbar-brand text-white fw-semibold mb-0 me-auto"><?= h(APP_NAME) ?></span>

  <!-- Global search -->
  <div class="d-none d-md-block me-3" style="width:280px">
    <div class="input-group input-group-sm">
      <span class="input-group-text bg-dark border-secondary text-muted">
        <i class="bi bi-search"></i>
      </span>
      <input type="text" class="form-control bg-dark border-secondary text-white"
             id="globalSearch" placeholder="Search contacts, numbers…"
             autocomplete="off">
      <div class="dropdown-menu shadow p-0" id="globalSearchResults"
           style="width:360px;max-height:400px;overflow-y:auto;display:none;position:absolute;top:100%;left:0;z-index:9999"></div>
    </div>
  </div>

  <!-- Online/Offline badge -->
  <span class="badge me-2" id="onlineBadge" style="font-size:.7rem">
    <i class="bi bi-wifi me-1"></i><span id="onlineText">Online</span>
  </span>

  <!-- Notifications -->
  <?php if ($pending_callbacks > 0): ?>
  <a href="<?= BASE_URL ?>/modules/callbacks/index.php"
     class="btn btn-sm btn-outline-warning me-2 position-relative" title="Overdue callbacks">
    <i class="bi bi-bell-fill"></i>
    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
          style="font-size:.6rem"><?= $pending_callbacks ?></span>
  </a>
  <?php endif ?>

  <!-- User dropdown -->
  <div class="dropdown">
    <button class="btn btn-sm btn-outline-light dropdown-toggle d-flex align-items-center gap-1"
            type="button" data-bs-toggle="dropdown">
      <i class="bi bi-person-circle"></i>
      <span class="d-none d-sm-inline"><?= h($user['name']) ?></span>
    </button>
    <ul class="dropdown-menu dropdown-menu-end shadow">
      <li><h6 class="dropdown-header"><?= h($user['name']) ?><br>
          <small class="fw-normal"><?= role_badge($user['role']) ?></small></h6></li>
      <li><hr class="dropdown-divider my-1"></li>
      <?php if (can('manage_settings')): ?>
      <li><a class="dropdown-item" href="<?= BASE_URL ?>/modules/settings/index.php">
          <i class="bi bi-gear me-2"></i>Settings</a></li>
      <?php endif ?>
      <li><a class="dropdown-item" href="<?= BASE_URL ?>/modules/attendance/index.php">
          <i class="bi bi-person-check me-2"></i>My Attendance</a></li>
      <li><hr class="dropdown-divider my-1"></li>
      <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>/logout.php">
          <i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
    </ul>
  </div>
</nav>

<!-- ── Layout wrapper ────────────────────────────────────── -->
<div class="app-layout">

  <!-- ── Sidebar (desktop) ──────────────────────────────── -->
  <aside class="app-sidebar d-none d-lg-flex flex-column" id="desktopSidebar">
    <?php include ROOT . '/partials/sidebar.php'; ?>
  </aside>

  <!-- ── Sidebar offcanvas (mobile) ─────────────────────── -->
  <div class="offcanvas offcanvas-start app-sidebar-offcanvas" tabindex="-1" id="sidebarOffcanvas">
    <div class="offcanvas-header border-bottom border-secondary py-2">
      <span class="text-white fw-semibold"><?= h(APP_NAME) ?></span>
      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body p-0">
      <?php include ROOT . '/partials/sidebar.php'; ?>
    </div>
  </div>

  <!-- ── Main content ───────────────────────────────────── -->
  <main class="app-main" id="appMain">

    <!-- Flash messages -->
    <?php foreach ($flashes as $f): ?>
    <div class="alert alert-<?= $f['type'] ?> alert-dismissible fade show flash-alert mx-3 mt-3 mb-0 py-2" role="alert">
      <?= $f['msg'] ?>
      <button type="button" class="btn-close py-2" data-bs-dismiss="alert"></button>
    </div>
    <?php endforeach ?>

    <!-- Toast container (JS-triggered flashes) -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastContainer" style="z-index:9999"></div>

    <!-- Idle timeout overlay -->
    <div class="modal fade" id="idleModal" tabindex="-1" data-bs-backdrop="static">
      <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content border-warning">
          <div class="modal-body text-center py-4">
            <i class="bi bi-clock-history fs-1 text-warning mb-3 d-block"></i>
            <h6 class="fw-bold">Session Expiring</h6>
            <p class="text-muted mb-3">You'll be logged out in <strong id="idleCountdown">60</strong> seconds due to inactivity.</p>
            <button class="btn btn-primary btn-sm px-4" id="stayLoggedIn">Stay Logged In</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Page content starts here -->
