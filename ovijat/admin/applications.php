<?php
require_once __DIR__ . '/auth.php';
requireAdmin();

$view   = (int)($_GET['view'] ?? 0);
$jobFilter = (int)($_GET['job'] ?? 0);
$action = $_GET['action'] ?? '';

if ($action === 'delete' && $view && csrf_verify()) {
    db()->prepare("DELETE FROM job_applications WHERE id=?")->execute([$view]);
    flash('Application deleted.','success'); redirect(SITE_URL.'/admin/applications.php');
}

// Mark read when viewing
if ($view) {
    db()->prepare("UPDATE job_applications SET is_read=1 WHERE id=?")->execute([$view]);
    $app = db()->prepare("SELECT a.*, j.title_en job_title FROM job_applications a LEFT JOIN jobs j ON a.job_id=j.id WHERE a.id=?");
    $app->execute([$view]);
    $app = $app->fetch();
}

$where = $jobFilter ? "WHERE job_id=$jobFilter" : '';
$apps  = db()->query("SELECT a.*, j.title_en job_title FROM job_applications a LEFT JOIN jobs j ON a.job_id=j.id $where ORDER BY a.created_at DESC")->fetchAll();
$jobs  = db()->query("SELECT id, title_en FROM jobs ORDER BY created_at DESC")->fetchAll();

// Skills map
$skills = ['excel'=>'Excel','google_sheets'=>'Google Sheets','photoshop'=>'Photoshop','email'=>'Email','tally'=>'Tally','word'=>'Word','powerpoint'=>'PowerPoint','internet'=>'Internet','typing'=>'Typing','social_media'=>'Social Media'];

require_once __DIR__ . '/partials/admin_header.php';
?>
<div class="admin-page-header">
  <h1>📝 Job Applications</h1>
  <p>Review and manage applications received through the careers portal.</p>
</div>

<?php if ($view && $app): ?>
<!-- Application Detail View -->
<div class="admin-panel">
  <div class="panel-header-row">
    <h2 class="admin-section-title">Application #<?= $app['id'] ?></h2>
    <a href="<?= SITE_URL ?>/admin/applications.php" class="btn btn-ghost">← Back to List</a>
  </div>
  <div class="application-detail">
    <div class="detail-grid">
      <div class="detail-section">
        <h3>👤 Applicant Details</h3>
        <table class="detail-table">
          <tr><th>Name</th><td><?= e($app['name']) ?></td></tr>
          <tr><th>Phone</th><td><?= e($app['phone']) ?></td></tr>
          <tr><th>Email</th><td><?= $app['email'] ? '<a href="mailto:'.e($app['email']).'">'.e($app['email']).'</a>' : '—' ?></td></tr>
          <tr><th>Applied For</th><td><?= e($app['job_title'] ?? 'N/A') ?></td></tr>
          <tr><th>Date</th><td><?= date('d M Y, h:i A', strtotime($app['created_at'])) ?></td></tr>
          <tr><th>IP</th><td><?= e($app['ip']) ?></td></tr>
        </table>
      </div>
      <div class="detail-section">
        <h3>🖥️ Computer Skills</h3>
        <div class="skills-display">
          <?php
          $chosen = json_decode($app['skills'] ?? '[]', true) ?? [];
          foreach ($skills as $key => $label):
          ?>
            <span class="skill-tag <?= in_array($key,$chosen) ? 'skill-yes' : 'skill-no' ?>">
              <?= in_array($key,$chosen) ? '✓' : '✗' ?> <?= e($label) ?>
            </span>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <div class="detail-section full-width">
      <h3>🎓 Academic Qualifications</h3>
      <div class="detail-text"><?= nl2br(e($app['academic_qualifications'])) ?></div>
    </div>
    <div class="detail-section full-width">
      <h3>💼 Work Experience</h3>
      <div class="detail-text"><?= nl2br(e($app['work_experience'])) ?></div>
    </div>
    <div class="detail-section full-width">
      <h3>📄 Cover Letter</h3>
      <div class="detail-text detail-cover"><?= nl2br(e($app['cover_letter'])) ?></div>
    </div>
    <div class="detail-actions">
      <?php if ($app['email']): ?>
        <a href="mailto:<?= e($app['email']) ?>?subject=Re: Application for <?= e($app['job_title']) ?>" class="btn btn-primary">✉️ Reply via Email</a>
      <?php endif; ?>
      <a href="<?= SITE_URL ?>/admin/applications.php?action=delete&view=<?= $app['id'] ?>&csrf_token=<?= csrf_token() ?>"
         class="btn btn-danger" onclick="return confirm('Permanently delete this application?')">🗑️ Delete</a>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Application List -->
<div class="admin-panel">
  <div class="panel-header-row">
    <h2 class="admin-section-title">All Applications (<?= count($apps) ?>)</h2>
    <!-- Filter by job -->
    <form method="GET" class="inline-filter">
      <select name="job" class="form-input form-input-sm" onchange="this.form.submit()">
        <option value="">— All Jobs —</option>
        <?php foreach ($jobs as $j): ?>
          <option value="<?= $j['id'] ?>" <?= $jobFilter == $j['id'] ? 'selected' : '' ?>><?= e($j['title_en']) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>
  <?php if ($apps): ?>
    <table class="admin-table">
      <thead><tr><th>Name</th><th>Job</th><th>Phone</th><th>Date</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($apps as $a): ?>
          <tr class="<?= !$a['is_read'] ? 'row-unread' : '' ?>">
            <td><?= e($a['name']) ?></td>
            <td><?= e($a['job_title'] ?? '—') ?></td>
            <td><a href="tel:<?= e($a['phone']) ?>"><?= e($a['phone']) ?></a></td>
            <td><?= date('d M y', strtotime($a['created_at'])) ?></td>
            <td><span class="badge <?= $a['is_read'] ? 'badge-gray' : 'badge-orange' ?>"><?= $a['is_read'] ? 'Read' : 'New' ?></span></td>
            <td class="table-actions">
              <a href="?view=<?= $a['id'] ?>" class="btn-mini">View</a>
              <a href="?action=delete&view=<?= $a['id'] ?>&csrf_token=<?= csrf_token() ?>" class="btn-mini btn-danger" onclick="return confirm('Delete?')">Del</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?><p class="empty-msg">No applications <?= $jobFilter ? 'for this job' : 'yet' ?>.</p><?php endif; ?>
</div>

<?php require_once __DIR__ . '/partials/admin_footer.php'; ?>
