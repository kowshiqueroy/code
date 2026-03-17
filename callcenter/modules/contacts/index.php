<?php
require_once dirname(__DIR__, 2) . '/config.php';
require_once ROOT . '/includes/auth.php';
require_once ROOT . '/includes/helpers.php';
require_login();

$page_title  = 'Contacts';
$active_page = 'contacts';

// ── Filters ───────────────────────────────────────────────────
$f_q      = clean($_GET['q']      ?? '');
$f_type   = clean($_GET['type']   ?? '');
$f_status = clean($_GET['status'] ?? 'active');
$f_group  = (int)($_GET['group']  ?? 0);
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 25;

$where  = [];
$params = [];

if ($f_q) {
    $q = '%' . $f_q . '%';
    $where[] = "(c.name LIKE ? OR c.phone LIKE ? OR c.alt_phone LIKE ? OR c.email LIKE ? OR c.company LIKE ?)";
    $params  = array_merge($params, [$q,$q,$q,$q,$q]);
}
if ($f_type)   { $where[] = "c.contact_type = ?"; $params[] = $f_type; }
if ($f_status) { $where[] = "c.status = ?";        $params[] = $f_status; }
if ($f_group) {
    $where[] = "EXISTS (SELECT 1 FROM sales_group_members sgm WHERE sgm.contact_id=c.id AND sgm.group_id=? AND sgm.is_active=1)";
    $params[] = $f_group;
}

$whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = (int) db_val("SELECT COUNT(*) FROM contacts c $whereStr", $params);
$p     = paginate($total, $page, $per_page);

$contacts = db_rows(
    "SELECT c.id, c.name, c.phone, c.alt_phone, c.email, c.company, c.contact_type, c.status, c.created_at,
            (SELECT MAX(ca.started_at) FROM calls ca WHERE ca.contact_id=c.id) AS last_call,
            (SELECT u.name FROM calls ca2 JOIN users u ON u.id=ca2.agent_id WHERE ca2.contact_id=c.id ORDER BY ca2.started_at DESC LIMIT 1) AS last_agent,
            (SELECT COUNT(*) FROM feedback_threads ft WHERE ft.contact_id=c.id AND ft.status IN ('open','in_progress')) AS open_threads,
            (SELECT COUNT(*) FROM callbacks cb WHERE cb.contact_id=c.id AND cb.status='pending' AND cb.scheduled_at < NOW()) AS overdue_cb,
            (SELECT GROUP_CONCAT(tag SEPARATOR ', ') FROM contact_tags WHERE contact_id=c.id) AS tags
     FROM contacts c
     $whereStr
     ORDER BY c.name ASC
     LIMIT ? OFFSET ?",
    array_merge($params, [$p['per_page'], $p['offset']])
);

$contact_types = ['internal_staff','sr','asm','dsm','tsm','dealer','distributor','shop_owner','customer','other'];

require ROOT . '/partials/header.php';
?>

<div class="page-header no-print">
  <i class="bi bi-person-lines-fill text-primary"></i>
  <h5>Contacts</h5>
  <div class="ms-auto d-flex gap-2">
    <button data-print class="btn btn-sm btn-outline-secondary no-print">
      <i class="bi bi-printer"></i>
    </button>
    <?php if (can('create', 'contacts')): ?>
    <a href="<?= BASE_URL ?>/modules/contacts/form.php" class="btn btn-sm btn-primary">
      <i class="bi bi-person-plus me-1"></i>New Contact
    </a>
    <?php endif ?>
  </div>
</div>

<div class="page-body">

  <!-- ── Filter Bar ──────────────────────────────────── -->
  <form method="get" class="card mb-3 no-print">
    <div class="card-body py-2 px-3">
      <div class="row g-2 align-items-end">
        <div class="col-12 col-md-3">
          <input type="text" class="form-control form-control-sm" name="q"
                 value="<?= h($f_q) ?>" placeholder="Name, phone, email, company…" autofocus>
        </div>
        <div class="col-6 col-md-2">
          <select class="form-select form-select-sm" name="type">
            <option value="">All Types</option>
            <?php foreach ($contact_types as $t): ?>
            <option value="<?= $t ?>" <?= $f_type===$t?'selected':'' ?>><?= ucwords(str_replace('_',' ',$t)) ?></option>
            <?php endforeach ?>
          </select>
        </div>
        <div class="col-6 col-md-2">
          <select class="form-select form-select-sm" name="status">
            <option value="">All Status</option>
            <option value="active"   <?= $f_status==='active'  ?'selected':'' ?>>Active</option>
            <option value="inactive" <?= $f_status==='inactive'?'selected':'' ?>>Inactive</option>
            <option value="blocked"  <?= $f_status==='blocked' ?'selected':'' ?>>Blocked</option>
            <option value="former"   <?= $f_status==='former'  ?'selected':'' ?>>Former</option>
          </select>
        </div>
        <div class="col-auto">
          <button type="submit" class="btn btn-primary btn-sm">Search</button>
          <a href="?" class="btn btn-outline-secondary btn-sm">Clear</a>
        </div>
        <div class="col-auto ms-auto text-muted small"><?= number_format($total) ?> contacts</div>
      </div>
    </div>
  </form>

  <!-- ── Table ─────────────────────────────────────────── -->
  <div class="card">
    <div class="card-header d-flex align-items-center gap-2 no-print">
      <input type="text" class="form-control form-control-sm" style="max-width:200px"
             placeholder="Quick filter…" data-filter-table="contactsTable">
      <button class="btn btn-sm btn-outline-success ms-auto" id="bulkCopyWA">
        <i class="bi bi-whatsapp me-1"></i>Copy Selected
      </button>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0" id="contactsTable">
        <thead class="table-light">
          <tr>
            <th class="no-print"><input type="checkbox" id="selectAll"></th>
            <th>Name</th>
            <th>Phone</th>
            <th>Company</th>
            <th>Type</th>
            <th>Status</th>
            <th>Last Interaction</th>
            <th>Alerts</th>
            <th class="no-print">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($contacts as $c): ?>
          <?php
          $waText = sprintf("%s | %s | %s | %s",
            $c['name'], $c['phone'], $c['company']??'', ucfirst($c['contact_type']));
          ?>
          <tr data-wa-text="<?= h($waText) ?>">
            <td class="no-print"><input type="checkbox" class="row-check"></td>
            <td>
              <a href="<?= BASE_URL ?>/modules/contacts/view.php?id=<?= $c['id'] ?>" class="fw-semibold">
                <?= h($c['name']) ?>
              </a>
              <?php if ($c['tags']): ?>
              <div class="mt-1">
                <?php foreach (explode(', ', $c['tags']) as $tag): ?>
                <span class="badge bg-light text-dark border" style="font-size:.65rem"><?= h($tag) ?></span>
                <?php endforeach ?>
              </div>
              <?php endif ?>
            </td>
            <td>
              <div><?= h($c['phone']) ?></div>
              <?php if ($c['alt_phone']): ?>
              <div class="text-muted small"><?= h($c['alt_phone']) ?></div>
              <?php endif ?>
            </td>
            <td class="text-muted"><?= h($c['company'] ?? '—') ?></td>
            <td><?= type_badge($c['contact_type']) ?></td>
            <td><?= status_badge($c['status']) ?></td>
            <td>
              <?php if ($c['last_call']): ?>
              <div class="small"><?= time_ago($c['last_call']) ?></div>
              <?php if ($c['last_agent']): ?><div class="text-muted" style="font-size:.7rem"><?= h($c['last_agent']) ?></div><?php endif ?>
              <?php else: ?>
              <span class="text-muted small">Never</span>
              <?php endif ?>
            </td>
            <td>
              <?php if ($c['open_threads'] > 0): ?>
              <span class="badge bg-danger me-1" title="Open threads">
                <i class="bi bi-chat-square-text"></i> <?= $c['open_threads'] ?>
              </span>
              <?php endif ?>
              <?php if ($c['overdue_cb'] > 0): ?>
              <span class="badge bg-warning text-dark" title="Overdue callbacks">
                <i class="bi bi-alarm"></i> <?= $c['overdue_cb'] ?>
              </span>
              <?php endif ?>
            </td>
            <td class="no-print table-action-btns">
              <a href="<?= BASE_URL ?>/modules/workspace/index.php?contact_id=<?= $c['id'] ?>"
                 class="btn btn-sm btn-outline-primary" title="Call">
                <i class="bi bi-telephone-plus"></i>
              </a>
              <a href="<?= BASE_URL ?>/modules/contacts/view.php?id=<?= $c['id'] ?>"
                 class="btn btn-sm btn-outline-secondary" title="View">
                <i class="bi bi-eye"></i>
              </a>
              <?php if (can('edit')): ?>
              <a href="<?= BASE_URL ?>/modules/contacts/form.php?id=<?= $c['id'] ?>"
                 class="btn btn-sm btn-outline-warning" title="Edit">
                <i class="bi bi-pencil"></i>
              </a>
              <?php endif ?>
            </td>
          </tr>
          <?php endforeach ?>
          <?php if (empty($contacts)): ?>
          <tr><td colspan="9" class="text-center text-muted py-4">No contacts found</td></tr>
          <?php endif ?>
        </tbody>
      </table>
    </div>
    <?php if ($p['pages'] > 1): ?>
    <div class="card-footer d-flex justify-content-between align-items-center no-print">
      <small class="text-muted"><?= $p['offset']+1 ?>–<?= min($p['offset']+$p['per_page'],$total) ?> of <?= $total ?></small>
      <?php
      $urlBase = '?' . http_build_query(array_filter(['q'=>$f_q,'type'=>$f_type,'status'=>$f_status]));
      echo pagination_html($p, $urlBase);
      ?>
    </div>
    <?php endif ?>
  </div>

</div>

<script>
document.getElementById('selectAll')?.addEventListener('change', function() {
  document.querySelectorAll('.row-check').forEach(cb => cb.checked = this.checked);
});
</script>

<?php require ROOT . '/partials/footer.php'; ?>
