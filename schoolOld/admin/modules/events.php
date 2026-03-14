<?php
/**
 * Admin Events Module
 */
$admin_title = 'Events';
$mode = $_GET['mode'] ?? 'list';
$msg  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        trim($_POST['title_en']??''), trim($_POST['title_bn']??''),
        $_POST['description_en']??'', $_POST['description_bn']??'',
        $_POST['event_date']??null, $_POST['event_time']??null,
        trim($_POST['venue_en']??''), trim($_POST['venue_bn']??''),
        (int)!empty($_POST['is_published']),
    ];
    $edit_id = (int)($_POST['edit_id']??0);
    if ($data[0]) {
        if ($edit_id) {
            db()->prepare("UPDATE events SET title_en=?,title_bn=?,description_en=?,description_bn=?,event_date=?,event_time=?,venue_en=?,venue_bn=?,is_published=? WHERE id=?")
               ->execute([...$data,$edit_id]);
        } else {
            db()->prepare("INSERT INTO events (title_en,title_bn,description_en,description_bn,event_date,event_time,venue_en,venue_bn,is_published) VALUES (?,?,?,?,?,?,?,?,?)")
               ->execute($data);
        }
        $msg = 'Event saved.'; $mode = 'list';
    }
}
if (isset($_GET['delete'])) { db()->prepare("DELETE FROM events WHERE id=?")->execute([(int)$_GET['delete']]); header('Location: /admin/?action=events'); exit; }
$edit_row = null;
if ($mode === 'edit') { $s=db()->prepare("SELECT * FROM events WHERE id=?"); $s->execute([(int)($_GET['id']??0)]); $edit_row=$s->fetch(); }
$events_list = db()->query("SELECT * FROM events ORDER BY event_date DESC LIMIT 50")->fetchAll();
?>
<?php if ($msg): ?><div class="alert alert-success">✅ <?= h($msg) ?></div><?php endif; ?>
<div style="display:grid;grid-template-columns:3fr 2fr;gap:24px">
  <div class="panel">
    <div class="panel-header">
      <div class="panel-title">📅 Events</div>
      <a href="/admin/?action=events&mode=add" class="btn btn-primary btn-sm">➕ Add</a>
    </div>
    <div class="table-wrap">
      <table class="admin-table">
        <thead><tr><th>Event</th><th>Date</th><th>Venue</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach ($events_list as $e): ?>
          <tr>
            <td><div style="font-weight:600"><?= h($e['title_en']) ?></div></td>
            <td><?= $e['event_date'] ? date('d M Y', strtotime($e['event_date'])) : '—' ?></td>
            <td style="font-size:.82rem;color:var(--muted)"><?= h($e['venue_en']) ?></td>
            <td><span class="status-badge <?= $e['is_published']?'status-published':'status-draft' ?>"><?= $e['is_published']?'Published':'Draft' ?></span></td>
            <td>
              <div class="actions">
                <a href="/admin/?action=events&mode=edit&id=<?= $e['id'] ?>" class="btn btn-xs btn-secondary">✏️</a>
                <a href="/admin/?action=events&delete=<?= $e['id'] ?>" class="btn btn-xs btn-danger" onclick="return confirm('Delete?')">🗑️</a>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="panel">
    <div class="panel-header"><div class="panel-title"><?= $edit_row?'✏️ Edit':'➕ Add' ?> Event</div></div>
    <div class="panel-body">
      <form method="POST">
        <?php if ($edit_row): ?><input type="hidden" name="edit_id" value="<?= $edit_row['id'] ?>"><?php endif; ?>
        <div class="form-group"><label class="form-label">Title (English) *</label><input type="text" name="title_en" class="form-control" required value="<?= h($edit_row['title_en']??'') ?>"></div>
        <div class="form-group"><label class="form-label">Title (Bangla)</label><input type="text" name="title_bn" class="form-control" value="<?= h($edit_row['title_bn']??'') ?>"></div>
        <div class="form-row col-2">
          <div class="form-group"><label class="form-label">Date *</label><input type="date" name="event_date" class="form-control" required value="<?= h($edit_row['event_date']??'') ?>"></div>
          <div class="form-group"><label class="form-label">Time</label><input type="time" name="event_time" class="form-control" value="<?= h($edit_row['event_time']??'') ?>"></div>
        </div>
        <div class="form-group"><label class="form-label">Venue (English)</label><input type="text" name="venue_en" class="form-control" value="<?= h($edit_row['venue_en']??'') ?>"></div>
        <div class="form-group"><label class="form-label">Venue (Bangla)</label><input type="text" name="venue_bn" class="form-control" value="<?= h($edit_row['venue_bn']??'') ?>"></div>
        <div class="form-group"><label class="form-label">Description</label><textarea name="description_en" class="form-control" rows="4"><?= h($edit_row['description_en']??'') ?></textarea></div>
        <label class="form-check" style="margin-bottom:16px"><input type="checkbox" name="is_published" value="1" <?= ($edit_row['is_published']??1)?'checked':'' ?>> Published</label>
        <div style="display:flex;gap:10px">
          <button type="submit" class="btn btn-primary">💾 Save</button>
          <a href="/admin/?action=events" class="btn btn-secondary">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</div>
