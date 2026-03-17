<?php
require_once __DIR__.'/auth.php'; requireAdmin();
$stats=[
    'products'    =>db()->query("SELECT COUNT(*) FROM products WHERE active=1")->fetchColumn(),
    'jobs'        =>db()->query("SELECT COUNT(*) FROM jobs WHERE active=1 AND expires_at>=CURDATE()")->fetchColumn(),
    'applications'=>db()->query("SELECT COUNT(*) FROM job_applications WHERE is_read=0")->fetchColumn(),
    'inquiries'   =>db()->query("SELECT COUNT(*) FROM inquiries WHERE is_read=0")->fetchColumn(),
];
try{$stats['promos']=db()->query("SELECT COUNT(*) FROM promotions WHERE active=1")->fetchColumn();}catch(Exception $e){$stats['promos']=0;}
try{$todayVisitors=db()->query("SELECT COUNT(DISTINCT ip) FROM visitor_logs WHERE DATE(created_at)=CURDATE()")->fetchColumn();}catch(Exception $e){$todayVisitors=0;}

$recentApps=db()->query("SELECT a.*,j.title_en FROM job_applications a LEFT JOIN jobs j ON a.job_id=j.id ORDER BY a.created_at DESC LIMIT 5")->fetchAll();
$recentInqs=db()->query("SELECT * FROM inquiries ORDER BY created_at DESC LIMIT 5")->fetchAll();
require_once __DIR__.'/partials/admin_header.php';
?>
<div class="admin-page-header">
  <h1>Dashboard</h1>
  <p>Welcome, <strong><?= e($_SESSION['admin_user']) ?></strong> — <?= date('l, d F Y') ?></p>
</div>
<div class="stat-cards">
  <div class="stat-card blue"><div class="sc-icon">📦</div><div class="sc-num"><?= $stats['products']?></div><div class="sc-label">Active Products</div></div>
  <div class="stat-card orange"><div class="sc-icon">💼</div><div class="sc-num"><?= $stats['jobs']?></div><div class="sc-label">Open Jobs</div></div>
  <div class="stat-card purple"><div class="sc-icon">📝</div><div class="sc-num"><?= $stats['applications']?></div><div class="sc-label">New Applications</div></div>
  <div class="stat-card red"><div class="sc-icon">✉️</div><div class="sc-num"><?= $stats['inquiries']?></div><div class="sc-label">Unread Inquiries</div></div>
  <div class="stat-card green"><div class="sc-icon">🎯</div><div class="sc-num"><?= $stats['promos']?></div><div class="sc-label">Active Promos</div></div>
  <div class="stat-card teal"><div class="sc-icon">👁️</div><div class="sc-num"><?= $todayVisitors?></div><div class="sc-label">Visitors Today</div></div>
</div>
<div class="admin-quick-links" style="margin-bottom:2rem">
  <h2 class="admin-section-title">Quick Actions</h2>
  <div class="quick-links-grid">
    <a href="products.php" class="quick-link-card">📦 Products</a>
    <a href="categories.php" class="quick-link-card">🏷️ Categories</a>
    <a href="banners.php" class="quick-link-card">🖼️ Banners</a>
    <a href="promotions.php" class="quick-link-card">🎯 Promotions</a>
    <a href="testimonials.php" class="quick-link-card">⭐ Testimonials</a>
    <a href="rice.php" class="quick-link-card">🌾 Rice</a>
    <a href="concerns.php" class="quick-link-card">🏭 Concerns</a>
    <a href="global.php" class="quick-link-card">🌍 Global</a>
    <a href="management.php" class="quick-link-card">👤 Management</a>
    <a href="ticker.php" class="quick-link-card">📢 Ticker</a>
    <a href="popup.php" class="quick-link-card">🎉 Popup</a>
    <a href="jobs.php" class="quick-link-card">💼 Jobs</a>
    <a href="applications.php" class="quick-link-card">📝 Applications</a>
    <a href="inquiries.php" class="quick-link-card">✉️ Inquiries</a>
    <a href="contacts.php" class="quick-link-card">📞 Contacts</a>
    <a href="users.php" class="quick-link-card">👥 Users</a>
    <a href="logs.php" class="quick-link-card">📊 Logs</a>
    <a href="partners.php" class="quick-link-card">🤝 Partners</a>
    <a href="settings.php" class="quick-link-card">⚙️ Settings</a>
  </div>
</div>
<div class="admin-two-col">
  <div class="admin-panel">
    <h2 class="admin-section-title">Recent Applications <span class="badge badge-orange"><?= $stats['applications']?> new</span></h2>
    <?php if($recentApps):?><table class="admin-table"><thead><tr><th>Name</th><th>Job</th><th>Date</th><th></th></tr></thead><tbody><?php foreach($recentApps as $a):?><tr class="<?= !$a['is_read']?'row-unread':''?>"><td><?= e($a['name'])?></td><td><?= e(mb_substr($a['title_en']??'—',0,25))?></td><td><?= date('d M',strtotime($a['created_at']))?></td><td><a href="applications.php?view=<?=$a['id']?>" class="btn-mini">View</a></td></tr><?php endforeach;?></tbody></table><?php else:?><p class="empty-msg">No applications yet.</p><?php endif;?>
    <a href="applications.php" class="view-all-link">All Applications →</a>
  </div>
  <div class="admin-panel">
    <h2 class="admin-section-title">Recent Inquiries <span class="badge badge-red"><?= $stats['inquiries']?> unread</span></h2>
    <?php if($recentInqs):?><table class="admin-table"><thead><tr><th>Name</th><th>Subject</th><th>Date</th><th></th></tr></thead><tbody><?php foreach($recentInqs as $inq):?><tr class="<?= !$inq['is_read']?'row-unread':''?>"><td><?= e($inq['name'])?></td><td><?= e(mb_substr($inq['subject'],0,28))?></td><td><?= date('d M',strtotime($inq['created_at']))?></td><td><a href="inquiries.php?view=<?=$inq['id']?>" class="btn-mini">View</a></td></tr><?php endforeach;?></tbody></table><?php else:?><p class="empty-msg">No inquiries yet.</p><?php endif;?>
    <a href="inquiries.php" class="view-all-link">All Inquiries →</a>
  </div>
</div>
<?php require_once __DIR__.'/partials/admin_footer.php'; ?>
