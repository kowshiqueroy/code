<?php
require_once __DIR__ . '/auth.php';
requireAdmin();
$view = (int)($_GET['view'] ?? 0);
$action = $_GET['action'] ?? '';

if ($action === 'delete' && $view && csrf_verify()) {
    db()->prepare("DELETE FROM inquiries WHERE id=?")->execute([$view]);
    flash('Inquiry deleted.','success'); redirect(SITE_URL.'/admin/inquiries.php');
}
if ($view) {
    db()->prepare("UPDATE inquiries SET is_read=1 WHERE id=?")->execute([$view]);
    $inq = db()->prepare("SELECT * FROM inquiries WHERE id=?");
    $inq->execute([$view]); $inq = $inq->fetch();
}
$inquiries = db()->query("SELECT * FROM inquiries ORDER BY created_at DESC")->fetchAll();
require_once __DIR__ . '/partials/admin_header.php';
?>
<div class="admin-page-header">
  <h1>✉️ Inquiries</h1>
  <?php $unread=db()->query("SELECT COUNT(*) FROM inquiries WHERE is_read=0")->fetchColumn(); if($unread):?><p><span class="badge badge-red"><?=$unread?> unread</span></p><?php endif;?>
</div>

<?php if ($view && $inq): ?>
<div class="admin-panel">
  <div class="panel-header-row">
    <h2 class="admin-section-title">Inquiry #<?= $inq['id'] ?></h2>
    <a href="<?= SITE_URL ?>/admin/inquiries.php" class="btn btn-ghost">← Back</a>
  </div>
  <table class="detail-table">
    <tr><th>From</th><td><?= e($inq['name']) ?></td></tr>
    <tr><th>Email</th><td><?= $inq['email']?'<a href="mailto:'.e($inq['email']).'">'.e($inq['email']).'</a>':'—'?></td></tr>
    <tr><th>Phone</th><td><?= e($inq['phone'])?:'—'?></td></tr>
    <tr><th>Subject</th><td><strong><?= e($inq['subject']) ?></strong></td></tr>
    <tr><th>Date</th><td><?= date('d M Y, h:i A', strtotime($inq['created_at'])) ?></td></tr>
    <tr><th>IP</th><td><?= e($inq['ip']) ?></td></tr>
    <tr><th>Message</th><td><div class="detail-text"><?= nl2br(e($inq['message'])) ?></div></td></tr>
  </table>
  <div class="detail-actions" style="margin-top:1.5rem;">
    <?php if($inq['email']):?><a href="mailto:<?= e($inq['email']) ?>?subject=Re: <?= e($inq['subject']) ?>" class="btn btn-primary">✉️ Reply</a><?php endif;?>
    <a href="?action=delete&view=<?= $inq['id'] ?>&csrf_token=<?= csrf_token() ?>" class="btn btn-danger" onclick="return confirm('Delete this inquiry?')">🗑️ Delete</a>
  </div>
</div>
<?php endif; ?>

<div class="admin-panel">
  <h2 class="admin-section-title">All Inquiries (<?= count($inquiries) ?>)</h2>
  <?php if ($inquiries): ?>
    <table class="admin-table">
      <thead><tr><th>Name</th><th>Subject</th><th>Date</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($inquiries as $i): ?>
          <tr class="<?= !$i['is_read']?'row-unread':''?>">
            <td><?= e($i['name']) ?></td>
            <td><?= e(mb_substr($i['subject'],0,50)) ?><?= mb_strlen($i['subject'])>50?'…':''?></td>
            <td><?= date('d M y', strtotime($i['created_at'])) ?></td>
            <td><span class="badge <?= $i['is_read']?'badge-gray':'badge-orange' ?>"><?= $i['is_read']?'Read':'New'?></span></td>
            <td class="table-actions">
              <a href="?view=<?= $i['id'] ?>" class="btn-mini">View</a>
              <a href="?action=delete&view=<?= $i['id'] ?>&csrf_token=<?= csrf_token() ?>" class="btn-mini btn-danger" onclick="return confirm('Delete?')">Del</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?><p class="empty-msg">No inquiries yet.</p><?php endif; ?>
</div>
<?php require_once __DIR__ . '/partials/admin_footer.php'; ?>
