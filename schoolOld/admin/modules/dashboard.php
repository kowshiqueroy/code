<?php
/**
 * Admin Dashboard Module
 */
$admin_title = 'Dashboard';

// Stats
try {
    $stats = [
        'pages'    => db()->query("SELECT COUNT(*) FROM pages")->fetchColumn(),
        'notices'  => db()->query("SELECT COUNT(*) FROM notices WHERE is_published=1")->fetchColumn(),
        'teachers' => db()->query("SELECT COUNT(*) FROM teachers WHERE is_active=1")->fetchColumn(),
        'messages' => db()->query("SELECT COUNT(*) FROM contact_messages WHERE is_read=0")->fetchColumn(),
        'gallery'  => db()->query("SELECT COUNT(*) FROM gallery")->fetchColumn(),
        'events'   => db()->query("SELECT COUNT(*) FROM events WHERE is_published=1")->fetchColumn(),
    ];
    $recent_notices  = db()->query("SELECT * FROM notices ORDER BY created_at DESC LIMIT 5")->fetchAll();
    $recent_messages = db()->query("SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT 5")->fetchAll();
} catch (Exception $e) {
    $stats = array_fill_keys(['pages','notices','teachers','messages','gallery','events'], 0);
    $recent_notices = [];
    $recent_messages = [];
}
?>

<!-- Stat Cards -->
<div class="stat-cards">
  <div class="stat-card">
    <div class="sc-icon" style="background:#dbeafe">📄</div>
    <div>
      <div class="sc-num"><?= (int)$stats['pages'] ?></div>
      <div class="sc-label">Total Pages</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="sc-icon" style="background:#fef3c7">📋</div>
    <div>
      <div class="sc-num"><?= (int)$stats['notices'] ?></div>
      <div class="sc-label">Published Notices</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="sc-icon" style="background:#d1fae5">👩‍🏫</div>
    <div>
      <div class="sc-num"><?= (int)$stats['teachers'] ?></div>
      <div class="sc-label">Active Teachers</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="sc-icon" style="background:#fde8ea">✉️</div>
    <div>
      <div class="sc-num"><?= (int)$stats['messages'] ?></div>
      <div class="sc-label">Unread Messages</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="sc-icon" style="background:#ede9fe">🖼️</div>
    <div>
      <div class="sc-num"><?= (int)$stats['gallery'] ?></div>
      <div class="sc-label">Gallery Items</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="sc-icon" style="background:#fef3c7">📅</div>
    <div>
      <div class="sc-num"><?= (int)$stats['events'] ?></div>
      <div class="sc-label">Active Events</div>
    </div>
  </div>
  <!-- Site Info -->
  <div class="stat-card" style="grid-column:span 2">
    <div class="sc-icon" style="background:var(--primary-light)">🏫</div>
    <div>
      <div style="font-weight:700;font-size:.95rem;margin-bottom:4px"><?= h(get_setting('site_name_en','Your School')) ?></div>
      <div style="font-size:.78rem;color:var(--muted)"><?= h(get_setting('site_tagline_en','')) ?></div>
      <div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap">
        <a href="/" target="_blank" class="btn btn-sm btn-secondary">🌐 View Site</a>
        <a href="/admin/?action=settings" class="btn btn-sm btn-primary">⚙️ Settings</a>
      </div>
    </div>
  </div>
</div>

<!-- Quick Actions -->
<div class="panel" style="margin-bottom:24px">
  <div class="panel-header">
    <div class="panel-title">⚡ Quick Actions</div>
  </div>
  <div class="panel-body" style="display:flex;gap:10px;flex-wrap:wrap">
    <a href="/admin/?action=notices&mode=add" class="btn btn-primary">➕ Add Notice</a>
    <a href="/admin/?action=pages&mode=add" class="btn btn-secondary">📄 Add Page</a>
    <a href="/admin/?action=teachers&mode=add" class="btn btn-secondary">👩‍🏫 Add Teacher</a>
    <a href="/admin/?action=gallery&mode=add" class="btn btn-secondary">🖼️ Upload Media</a>
    <a href="/admin/?action=sliders&mode=add" class="btn btn-secondary">🎠 Add Slider</a>
    <a href="/admin/?action=events&mode=add" class="btn btn-secondary">📅 Add Event</a>
    <a href="/admin/?action=ai_assistant" class="btn" style="background:#f5f0ff;color:#7c3aed;border-color:#ddd6fe">🤖 AI Assistant</a>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px">
  <!-- Recent Notices -->
  <div class="panel">
    <div class="panel-header">
      <div class="panel-title">📋 Recent Notices</div>
      <a href="/admin/?action=notices" class="btn btn-sm btn-secondary">View All</a>
    </div>
    <div class="panel-body" style="padding:0">
      <?php if ($recent_notices): ?>
      <table class="admin-table">
        <thead><tr><th>Title</th><th>Status</th><th>Date</th></tr></thead>
        <tbody>
          <?php foreach ($recent_notices as $n): ?>
          <tr>
            <td><?= h(mb_substr($n['title_en'],0,40)) ?><?= mb_strlen($n['title_en'])>40?'…':'' ?></td>
            <td><span class="status-badge <?= $n['is_published']?'status-published':'status-draft' ?>"><?= $n['is_published']?'Live':'Draft' ?></span></td>
            <td style="font-size:.78rem;color:var(--muted)"><?= date('d M', strtotime($n['created_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
      <p style="padding:20px;color:var(--muted);text-align:center">No notices yet.</p>
      <?php endif; ?>
    </div>
  </div>

  <!-- Recent Messages -->
  <div class="panel">
    <div class="panel-header">
      <div class="panel-title">✉️ Recent Messages</div>
      <a href="/admin/?action=messages" class="btn btn-sm btn-secondary">View All</a>
    </div>
    <div class="panel-body" style="padding:0">
      <?php if ($recent_messages): ?>
      <table class="admin-table">
        <thead><tr><th>Name</th><th>Subject</th><th>Date</th></tr></thead>
        <tbody>
          <?php foreach ($recent_messages as $m): ?>
          <tr style="<?= !$m['is_read']?'font-weight:600':'' ?>">
            <td><?= h($m['name']) ?></td>
            <td style="font-size:.85rem"><?= h(mb_substr($m['subject']?:'(no subject)',0,30)) ?></td>
            <td style="font-size:.78rem;color:var(--muted)"><?= date('d M', strtotime($m['created_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
      <p style="padding:20px;color:var(--muted);text-align:center">No messages yet.</p>
      <?php endif; ?>
    </div>
  </div>
</div>
