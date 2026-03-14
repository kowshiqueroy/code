<?php
// admin/pages/dashboard.php
$db = getDB();
$counts = [];
foreach (['notices','staff','gallery_albums','gallery_images','banners','honorees','media','pages','users','job_applications'] as $t) {
    try { $counts[$t] = (int)$db->query("SELECT COUNT(*) FROM $t")->fetchColumn(); } catch(Exception $e) { $counts[$t] = 0; }
}
$recentNotices = $db->query("SELECT * FROM notices ORDER BY created_at DESC LIMIT 6")->fetchAll();
$recentApps    = $db->query("SELECT * FROM job_applications ORDER BY created_at DESC LIMIT 5")->fetchAll();
$pendingApps   = (int)$db->query("SELECT COUNT(*) FROM job_applications WHERE status='pending'")->fetchColumn();
?>

<div class="stat-grid">
  <div class="stat-card"><div class="stat-icon">📌</div><div><div class="stat-num"><?= $counts['notices'] ?></div><div class="stat-label">Notices</div></div></div>
  <div class="stat-card" style="border-left-color:#e07b00"><div class="stat-icon">👥</div><div><div class="stat-num"><?= $counts['staff'] ?></div><div class="stat-label">Staff</div></div></div>
  <div class="stat-card" style="border-left-color:#5b21b6"><div class="stat-icon">📷</div><div><div class="stat-num"><?= $counts['gallery_images'] ?></div><div class="stat-label">Gallery Images</div></div></div>
  <div class="stat-card" style="border-left-color:#0369a1"><div class="stat-icon">📁</div><div><div class="stat-num"><?= $counts['media'] ?></div><div class="stat-label">Media Files</div></div></div>
  <div class="stat-card" style="border-left-color:#f42a41"><div class="stat-icon">📝</div><div><div class="stat-num"><?= $pendingApps ?></div><div class="stat-label">Pending Applications</div></div></div>
  <div class="stat-card" style="border-left-color:#fdc800"><div class="stat-icon">📄</div><div><div class="stat-num"><?= $counts['pages'] ?></div><div class="stat-label">CMS Pages</div></div></div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
  <div class="acard">
    <div class="acard-header">
      <div class="acard-title">📌 Recent Notices</div>
      <a href="?section=notices&action=add" class="btn btn-primary btn-sm">+ Add</a>
    </div>
    <div class="atable-wrap">
      <table class="atable">
        <thead><tr><th>Title</th><th>Type</th><th>Date</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($recentNotices as $n): ?>
          <tr>
            <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><a href="?section=notices&action=edit&id=<?= $n['id'] ?>"><?= h(mb_substr($n['title_en'],0,45)) ?></a></td>
            <td><span class="badge badge-info"><?= h($n['type']) ?></span></td>
            <td><?= date('d M', strtotime($n['created_at'])) ?></td>
            <td><?= $n['is_active'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-gray">Off</span>' ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="acard-footer"><a href="?section=notices" class="btn btn-light btn-sm">View All Notices</a></div>
  </div>

  <div class="acard">
    <div class="acard-header">
      <div class="acard-title">📝 Recent Job Applications</div>
      <?php if ($pendingApps): ?><span class="badge badge-danger"><?= $pendingApps ?> Pending</span><?php endif; ?>
    </div>
    <div class="atable-wrap">
      <table class="atable">
        <thead><tr><th>Applicant</th><th>Position</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($recentApps as $app): ?>
          <tr>
            <td><?= h($app['applicant_name']) ?></td>
            <td style="max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= h($app['position'] ?: '—') ?></td>
            <td>
              <?php $sc = ['pending'=>'badge-warning','reviewed'=>'badge-info','shortlisted'=>'badge-success','rejected'=>'badge-danger']; ?>
              <span class="badge <?= $sc[$app['status']] ?? 'badge-gray' ?>"><?= h($app['status']) ?></span>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$recentApps): ?><tr><td colspan="3" style="text-align:center;color:#999;padding:20px">No applications yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <div class="acard-footer"><a href="?section=applications" class="btn btn-light btn-sm">View All Applications</a></div>
  </div>
</div>

<!-- Quick Actions -->
<div class="acard" style="margin-top:20px">
  <div class="acard-header"><div class="acard-title">⚡ Quick Actions</div></div>
  <div class="acard-body" style="display:flex;gap:10px;flex-wrap:wrap">
    <a href="?section=notices&action=add" class="btn btn-primary">+ Add Notice</a>
    <a href="?section=staff&action=add" class="btn btn-secondary">+ Add Staff</a>
    <a href="?section=gallery&action=add_album" class="btn btn-warning">+ Add Album</a>
    <a href="?section=banners&action=add" class="btn btn-success">+ Add Banner</a>
    <a href="?section=settings" class="btn btn-light">⚙️ Settings</a>
    <a href="<?= BASE_URL ?>/?page=index" target="_blank" class="btn btn-light">🌐 View Site</a>
  </div>
</div>
