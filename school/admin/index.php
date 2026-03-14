<?php
// admin/index.php
session_name('school_admin_sess');
session_start();
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

// Route sub-pages
$adminPage = preg_replace('/[^a-z0-9_\-]/', '', strtolower($_GET['section'] ?? 'dashboard'));

$sections = [
    'dashboard'    => 'Dashboard',
    'notices'      => 'Notices',
    'staff'        => 'Staff',
    'gallery'      => 'Gallery',
    'banners'      => 'Banners',
    'honorees'     => 'Honorees',
    'pages'        => 'Pages',
    'menus'        => 'Menus',
    'academic'     => 'Academic',
    'admissions'   => 'Admissions',
    'media'        => 'Media Library',
    'applications' => 'Job Applications',
    'settings'     => 'Settings',
    'users'        => 'Users',
    'logout'       => 'Logout',
];

// Handle logout
if ($adminPage === 'logout') {
    session_destroy();
    redirect(ADMIN_PATH . 'login.php');
}

$pageFile = __DIR__ . '/pages/' . $adminPage . '.php';
if (!file_exists($pageFile)) { $adminPage = 'dashboard'; $pageFile = __DIR__ . '/pages/dashboard.php'; }

$flash  = getFlash();
$user   = currentUser();
$siteName = getSetting('site_name_en', 'School');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h(ucfirst($adminPage)) ?> — Admin Panel</title>
<link rel="stylesheet" href="<?= ADMIN_PATH ?>assets/css/admin.css">
</head>
<body>
<div class="admin-layout">

  <!-- Sidebar -->
  <aside class="admin-sidebar" id="adminSidebar">
    <div class="sidebar-logo">
      <span>🏫</span>
      <div>
        <strong><?= h(mb_substr($siteName, 0, 25)) ?></strong>
        <small>Admin Panel</small>
      </div>
    </div>
    <nav class="sidebar-nav">
      <div class="nav-group">
        <span class="nav-group-label">Main</span>
        <a href="?section=dashboard"    class="nav-link <?= $adminPage==='dashboard'?'active':'' ?>">📊 Dashboard</a>
        <a href="?section=notices"      class="nav-link <?= $adminPage==='notices'?'active':'' ?>">📌 Notices</a>
        <a href="?section=banners"      class="nav-link <?= $adminPage==='banners'?'active':'' ?>">🖼️ Banners</a>
        <a href="?section=gallery"      class="nav-link <?= $adminPage==='gallery'?'active':'' ?>">📷 Gallery</a>
        <a href="?section=honorees"     class="nav-link <?= $adminPage==='honorees'?'active':'' ?>">⭐ Honorees</a>
      </div>
      <div class="nav-group">
        <span class="nav-group-label">People</span>
        <a href="?section=staff"        class="nav-link <?= $adminPage==='staff'?'active':'' ?>">👥 Staff</a>
        <a href="?section=applications" class="nav-link <?= $adminPage==='applications'?'active':'' ?>">📝 Job Applications</a>
      </div>
      <div class="nav-group">
        <span class="nav-group-label">Academic</span>
        <a href="?section=academic"     class="nav-link <?= $adminPage==='academic'?'active':'' ?>">🎓 Academic</a>
        <a href="?section=admissions"   class="nav-link <?= $adminPage==='admissions'?'active':'' ?>">🏫 Admissions</a>
      </div>
      <div class="nav-group">
        <span class="nav-group-label">CMS</span>
        <a href="?section=pages"        class="nav-link <?= $adminPage==='pages'?'active':'' ?>">📄 Pages</a>
        <a href="?section=menus"        class="nav-link <?= $adminPage==='menus'?'active':'' ?>">☰ Menus</a>
        <a href="?section=media"        class="nav-link <?= $adminPage==='media'?'active':'' ?>">📁 Media</a>
      </div>
      <div class="nav-group">
        <span class="nav-group-label">System</span>
        <a href="?section=settings"     class="nav-link <?= $adminPage==='settings'?'active':'' ?>">⚙️ Settings</a>
        <?php if (can('superadmin')): ?>
        <a href="?section=users"        class="nav-link <?= $adminPage==='users'?'active':'' ?>">👤 Users</a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/?page=index" target="_blank" class="nav-link">🌐 View Site</a>
        <a href="?section=logout" class="nav-link nav-link-danger">🚪 Logout</a>
      </div>
    </nav>
  </aside>

  <!-- Main Content -->
  <div class="admin-main">
    <!-- Top Bar -->
    <header class="admin-topbar">
      <button class="sidebar-toggle" id="sidebarToggle">☰</button>
      <div class="topbar-title"><?= h(ucfirst(str_replace('_',' ',$adminPage))) ?></div>
      <div class="topbar-right">
        <span class="user-chip">👤 <?= h($user['full_name'] ?? 'Admin') ?></span>
      </div>
    </header>

    <!-- Flash -->
    <?php if ($flash): ?>
    <div class="flash-msg flash-<?= h($flash['type']) ?>"><?= h($flash['msg']) ?> <button onclick="this.parentElement.remove()">✕</button></div>
    <?php endif; ?>

    <!-- Page Content -->
    <div class="admin-content">
      <?php require_once $pageFile; ?>
    </div>
  </div>
</div>
<script src="<?= ADMIN_PATH ?>assets/js/admin.js"></script>
</body>
</html>
