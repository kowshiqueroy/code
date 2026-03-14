<?php
/**
 * Admin Contact Messages
 */
$admin_title = 'Contact Messages';

if (isset($_GET['delete'])) { db()->prepare("DELETE FROM contact_messages WHERE id=?")->execute([(int)$_GET['delete']]); header('Location: /admin/?action=messages'); exit; }

$view_id = (int)($_GET['view'] ?? 0);
$message = null;
if ($view_id) {
    $s = db()->prepare("SELECT * FROM contact_messages WHERE id=?"); $s->execute([$view_id]); $message = $s->fetch();
    if ($message) db()->prepare("UPDATE contact_messages SET is_read=1 WHERE id=?")->execute([$view_id]);
}

$msgs = db()->query("SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT 100")->fetchAll();
?>

<?php if ($message): ?>
<div class="panel" style="max-width:700px">
  <div class="panel-header">
    <div class="panel-title">✉️ Message from <?= h($message['name']) ?></div>
    <a href="/admin/?action=messages" class="btn btn-secondary btn-sm">← Back</a>
  </div>
  <div class="panel-body">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;padding:16px;background:var(--bg);border-radius:8px">
      <div><span style="font-size:.78rem;color:var(--muted);display:block">Name</span><strong><?= h($message['name']) ?></strong></div>
      <div><span style="font-size:.78rem;color:var(--muted);display:block">Email</span><a href="mailto:<?= h($message['email']) ?>"><?= h($message['email']?:'N/A') ?></a></div>
      <div><span style="font-size:.78rem;color:var(--muted);display:block">Phone</span><?= h($message['phone']?:'N/A') ?></div>
      <div><span style="font-size:.78rem;color:var(--muted);display:block">Date</span><?= date('d M Y H:i', strtotime($message['created_at'])) ?></div>
    </div>
    <?php if ($message['subject']): ?><div style="margin-bottom:12px"><strong>Subject:</strong> <?= h($message['subject']) ?></div><?php endif; ?>
    <div style="background:var(--bg);border-radius:8px;padding:16px;white-space:pre-wrap;font-size:.92rem"><?= h($message['message']) ?></div>
    <div style="margin-top:16px;display:flex;gap:10px">
      <?php if ($message['email']): ?><a href="mailto:<?= h($message['email']) ?>?subject=Re: <?= urlencode($message['subject']??'Your message') ?>" class="btn btn-primary">↩️ Reply via Email</a><?php endif; ?>
      <a href="/admin/?action=messages&delete=<?= $message['id'] ?>" class="btn btn-danger" onclick="return confirm('Delete?')">🗑️ Delete</a>
    </div>
  </div>
</div>
<?php else: ?>
<div class="panel">
  <div class="panel-header"><div class="panel-title">✉️ All Messages (<?= count($msgs) ?>)</div></div>
  <div class="table-wrap">
    <table class="admin-table">
      <thead><tr><th>Name</th><th>Email</th><th>Subject</th><th>Date</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($msgs as $m): ?>
        <tr style="<?= !$m['is_read']?'font-weight:700;background:#fffbeb':'' ?>">
          <td><?= h($m['name']) ?><?= !$m['is_read']?' <span style="background:var(--secondary);color:#fff;font-size:.65rem;padding:2px 6px;border-radius:4px;margin-left:4px">NEW</span>':'' ?></td>
          <td style="font-size:.85rem"><?= h($m['email']?:'—') ?></td>
          <td style="font-size:.85rem"><?= h(mb_substr($m['subject']?:'(no subject)',0,40)) ?></td>
          <td style="font-size:.78rem;color:var(--muted)"><?= date('d M Y', strtotime($m['created_at'])) ?></td>
          <td>
            <div class="actions">
              <a href="/admin/?action=messages&view=<?= $m['id'] ?>" class="btn btn-xs btn-secondary">👁️ View</a>
              <a href="/admin/?action=messages&delete=<?= $m['id'] ?>" class="btn btn-xs btn-danger" onclick="return confirm('Delete?')">🗑️</a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($msgs)): ?><tr><td colspan="5" style="text-align:center;padding:30px;color:var(--muted)">No messages yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
