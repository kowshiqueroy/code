<?php
/**
 * OVIJAT GROUP — admin_header.php v2.0
 */
$currentAdminPage=basename($_SERVER['PHP_SELF']);
$flash=getFlash();
$nav=[
  ['file'=>'dashboard.php',    'icon'=>'🏠','label'=>'Dashboard'],
  ['file'=>'settings.php',     'icon'=>'⚙️','label'=>'Site Settings'],
  ['file'=>'banners.php',      'icon'=>'🖼️','label'=>'Hero Banners'],
  ['file'=>'ticker.php',       'icon'=>'📢','label'=>'News Ticker'],
  ['file'=>'popup.php',        'icon'=>'🎉','label'=>'Event Popup'],
  ['file'=>'promotions.php',   'icon'=>'🎯','label'=>'Promotions'],
  ['file'=>'partners.php',   'icon'=>'🤝','label'=>'Partners'],
  ['file'=>'testimonials.php', 'icon'=>'⭐','label'=>'Testimonials'],
  ['file'=>'categories.php',   'icon'=>'🏷️','label'=>'Categories'],
  ['file'=>'products.php',     'icon'=>'📦','label'=>'Products'],
  ['file'=>'rice.php',         'icon'=>'🌾','label'=>'Rice Showcase'],
  ['file'=>'concerns.php',     'icon'=>'🏭','label'=>'Sister Concerns'],
  ['file'=>'global.php',       'icon'=>'🌍','label'=>'Global Presence'],
  ['file'=>'management.php',   'icon'=>'👤','label'=>'Management'],
  ['file'=>'contacts.php',     'icon'=>'📞','label'=>'Sales Contacts'],
  ['file'=>'jobs.php',         'icon'=>'💼','label'=>'Jobs'],
  ['file'=>'applications.php', 'icon'=>'📝','label'=>'Applications'],
  ['file'=>'inquiries.php',    'icon'=>'✉️','label'=>'Inquiries'],
  ['file'=>'users.php',        'icon'=>'👥','label'=>'Users'],
  ['file'=>'logs.php',         'icon'=>'📊','label'=>'Logs'],
];
// Counts for badge
$unreadInq=0; try{$unreadInq=(int)db()->query("SELECT COUNT(*) FROM inquiries WHERE is_read=0")->fetchColumn();}catch(Exception $e){}
$unreadApps=0; try{$unreadApps=(int)db()->query("SELECT COUNT(*) FROM job_applications WHERE is_read=0")->fetchColumn();}catch(Exception $e){}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Admin Panel — Ovijat Group</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin.css">
</head>
<body class="admin-body">

<aside class="admin-sidebar" id="adminSidebar">
  <div class="sidebar-brand">
    <span class="brand-name">OVIJAT</span>
    <span class="brand-sub">Admin Panel</span>
    <button class="sidebar-close-btn" id="sidebarClose" aria-label="Close">✕</button>
  </div>
  <nav class="sidebar-nav">
    <?php foreach($nav as $item):
      $isCur=$currentAdminPage===$item['file'];
      $badge='';
      if($item['file']==='inquiries.php'&&$unreadInq) $badge='<span style="background:var(--red);color:#fff;border-radius:50%;font-size:.65rem;padding:1px 5px;margin-left:auto">'.$unreadInq.'</span>';
      if($item['file']==='applications.php'&&$unreadApps) $badge='<span style="background:var(--admin-orange);color:#fff;border-radius:50%;font-size:.65rem;padding:1px 5px;margin-left:auto">'.$unreadApps.'</span>';
    ?>
      <a href="<?= SITE_URL ?>/admin/<?= $item['file'] ?>" class="sidebar-link <?= $isCur?'active':'' ?>">
        <span class="nav-icon"><?= $item['icon'] ?></span>
        <span><?= $item['label'] ?></span>
        <?= $badge ?>
      </a>
    <?php endforeach; ?>
  </nav>
  <div class="sidebar-footer">
    <a href="<?= SITE_URL ?>/" target="_blank" class="sidebar-link">🌐 View Site</a>
    <a href="<?= SITE_URL ?>/admin/auth.php?action=logout" class="sidebar-link logout">🚪 Logout</a>
  </div>
</aside>

<div class="admin-main" id="adminMain">
  <div class="admin-topbar">
    <button class="topbar-menu-btn" id="sidebarToggle" aria-label="Sidebar">☰</button>
    <div class="topbar-right">
      <span class="topbar-user">👤 <?= e($_SESSION['admin_user']??'Admin') ?></span>
      <a href="<?= SITE_URL ?>/admin/auth.php?action=logout" class="topbar-logout">Logout</a>
    </div>
  </div>
  <?php if($flash): ?>
    <div class="alert alert-<?= $flash['type']==='success'?'success':'danger' ?> flash-msg">
      <?= e($flash['msg']) ?>
    </div>
  <?php endif; ?>
  <div class="admin-content">
