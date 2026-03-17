<?php
// Sidebar nav — included by both desktop sidebar and mobile offcanvas
// $active_page must be set in each module before including header.php
if (!defined('ROOT')) die();
$u      = current_user();
$role   = current_role();
$lvl    = role_level();
$active = $active_page ?? '';

$cb_count   = $pending_callbacks ?? 0;
$task_count = $pending_tasks     ?? 0;

// Guard: header.php includes sidebar twice (desktop + mobile offcanvas)
if (!function_exists('nav_item')) {
    function nav_item(string $href, string $icon, string $label, string $active_key, string $current, int $badge = 0): string {
        $is_active = ($current === $active_key) ? ' active' : '';
        $b = $badge > 0 ? ' <span class="badge bg-danger ms-auto">' . $badge . '</span>' : '';
        return '<li><a href="' . $href . '" class="sidebar-link' . $is_active . '">'
             . '<i class="bi ' . $icon . '"></i> <span>' . $label . '</span>' . $b . '</a></li>';
    }
}

$base = BASE_URL;
?>

<div class="sidebar-inner">
  <!-- User info -->
  <div class="sidebar-user">
    <div class="sidebar-user-avatar"><i class="bi bi-person-fill"></i></div>
    <div class="sidebar-user-info">
      <div class="fw-semibold text-truncate"><?= h($u['name']) ?></div>
      <div class="small text-muted"><?= ucwords(str_replace('_',' ',$role)) ?></div>
    </div>
  </div>

  <nav class="sidebar-nav">
    <ul class="list-unstyled mb-0">

      <!-- Dashboard — all roles -->
      <?= nav_item("$base/modules/dashboard/index.php", 'bi-speedometer2', 'Dashboard', 'dashboard', $active) ?>

      <?php if ($lvl >= 2): // executive+ ?>
      <!-- Workspace — executive+ -->
      <?= nav_item("$base/modules/workspace/index.php", 'bi-telephone-inbound-fill', 'Call Workspace', 'workspace', $active) ?>
      <?php endif ?>

      <li class="sidebar-section">CONTACTS</li>

      <!-- Contacts — all logged-in -->
      <?= nav_item("$base/modules/contacts/index.php", 'bi-person-lines-fill', 'Contacts', 'contacts', $active) ?>

      <?php if ($lvl >= 2): ?>
      <?= nav_item("$base/modules/staff/index.php", 'bi-building', 'Internal Staff', 'staff', $active) ?>
      <?php endif ?>

      <?= nav_item("$base/modules/sales_network/index.php", 'bi-diagram-3-fill', 'Sales Network', 'sales_network', $active) ?>

      <li class="sidebar-section">CALLS & MSGS</li>

      <?php if ($lvl >= 2): ?>
      <?= nav_item("$base/modules/calls/index.php", 'bi-telephone-fill', 'Call History', 'calls', $active) ?>
      <?= nav_item("$base/modules/callbacks/index.php", 'bi-clock-history', 'Callbacks', 'callbacks', $active, $cb_count) ?>
      <?= nav_item("$base/modules/sms/index.php", 'bi-chat-dots-fill', 'SMS', 'sms', $active) ?>
      <?= nav_item("$base/modules/feedback/index.php", 'bi-chat-square-text-fill', 'Feedback Threads', 'feedback', $active) ?>
      <?php endif ?>

      <li class="sidebar-section">CAMPAIGNS</li>

      <?= nav_item("$base/modules/campaigns/index.php", 'bi-megaphone-fill', 'Campaigns', 'campaigns', $active) ?>
      <?= nav_item("$base/modules/scripts/index.php", 'bi-file-text-fill', 'Scripts', 'scripts', $active) ?>

      <li class="sidebar-section">WORK</li>

      <?php if ($lvl >= 2): ?>
      <?= nav_item("$base/modules/tasks/index.php", 'bi-check2-square', 'Tasks', 'tasks', $active, $task_count) ?>
      <?= nav_item("$base/modules/attendance/index.php", 'bi-person-check-fill', 'Attendance', 'attendance', $active) ?>
      <?php endif ?>

      <li class="sidebar-section">REPORTS</li>

      <?= nav_item("$base/modules/reports/index.php", 'bi-bar-chart-fill', 'Reports', 'reports', $active) ?>

      <?php if ($lvl >= 3): // senior+ ?>
      <?= nav_item("$base/modules/reports/executive.php", 'bi-graph-up', 'Executive Report', 'exec_report', $active) ?>
      <?= nav_item("$base/modules/reports/monthly.php", 'bi-calendar3', 'Monthly Report', 'monthly_report', $active) ?>
      <?php endif ?>

      <?php if ($lvl >= 2): ?>
      <?= nav_item("$base/modules/sync/index.php", 'bi-cloud-upload-fill', 'Offline Sync', 'sync', $active) ?>
      <?php endif ?>

      <?php if ($lvl >= 4): // super_admin only ?>
      <li class="sidebar-section">ADMIN</li>
      <?= nav_item("$base/modules/users/index.php", 'bi-people-fill', 'Users', 'users', $active) ?>
      <?= nav_item("$base/modules/settings/index.php", 'bi-gear-fill', 'Settings', 'settings', $active) ?>
      <?= nav_item("$base/modules/logs/index.php", 'bi-journal-text', 'Audit Logs', 'logs', $active) ?>
      <?php endif ?>

    </ul>
  </nav>

  <!-- Logout at bottom -->
  <div class="sidebar-footer">
    <a href="<?= $base ?>/logout.php" class="sidebar-link text-danger-emphasis">
      <i class="bi bi-box-arrow-right"></i> <span>Logout</span>
    </a>
  </div>
</div>
