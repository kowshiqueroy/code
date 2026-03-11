<?php
// ============================================================
// modules/finance/finance.php — Expenses, income & balance
// ============================================================

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ── Save entry ─────────────────────────────────────────────────
if ($action === 'save_entry' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireLogin();
    $id = (int)($_POST['entry_id'] ?? 0);
    $data = [
        'type'        => in_array($_POST['type'], ['income','expense']) ? $_POST['type'] : 'expense',
        'category'    => trim($_POST['category'] ?? ''),
        'amount'      => (float)($_POST['amount'] ?? 0),
        'description' => trim($_POST['description'] ?? ''),
        'entry_date'  => $_POST['entry_date'] ?? today(),
        'user_id'     => currentUser()['id'],
    ];
    if ($id) {
        dbUpdate('finance_entries', $data, 'id = ?', [$id]);
        logAction('UPDATE', 'finance', $id, 'Updated finance entry');
        flash('success', 'Entry updated.');
    } else {
        $data['created_at'] = now();
        $newId = dbInsert('finance_entries', $data);
        logAction('CREATE', 'finance', $newId, 'Added finance entry');
        flash('success', 'Entry added.');
    }
    redirect('finance');
}

if ($action === 'delete' && canDelete()) {
    $id = (int)$_GET['id'];
    // Only allow deleting manual entries (no ref_sale_id)
    $entry = dbFetch('SELECT * FROM finance_entries WHERE id = ? AND ref_sale_id IS NULL', [$id]);
    if ($entry) {
        dbDelete('finance_entries', 'id = ?', [$id]);
        logAction('DELETE', 'finance', $id, 'Deleted finance entry');
        flash('success', 'Entry deleted.');
    }
    redirect('finance');
}

// ── Date range filter ─────────────────────────────────────────
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? today();

$entries = dbFetchAll(
    "SELECT fe.*, u.full_name AS staff_name, s.invoice_no
     FROM finance_entries fe
     JOIN users u ON u.id = fe.user_id
     LEFT JOIN sales s ON s.id = fe.ref_sale_id
     WHERE fe.entry_date BETWEEN ? AND ?
     ORDER BY fe.entry_date DESC, fe.id DESC",
    [$from, $to]
);

$totals = dbFetch(
    "SELECT
       SUM(CASE WHEN type='income'  THEN amount ELSE 0 END) AS total_income,
       SUM(CASE WHEN type='expense' THEN amount ELSE 0 END) AS total_expense
     FROM finance_entries WHERE entry_date BETWEEN ? AND ?",
    [$from, $to]
);
$balance = ($totals['total_income'] ?? 0) - ($totals['total_expense'] ?? 0);

$editing = null;
if (!empty($_GET['edit'])) {
    $editing = dbFetch('SELECT * FROM finance_entries WHERE id = ?', [(int)$_GET['edit']]);
}

$pageTitle = 'Finance';
require_once BASE_PATH . '/includes/header.php';
?>

<div class="d-flex justify-between align-center mb-2 flex-wrap gap-2">
  <h1>💰 Finance</h1>
  <button class="btn btn-primary" onclick="openModal('entryModal')">+ Add Entry</button>
</div>

<!-- Stats -->
<div class="stats-grid">
  <div class="stat-card success">
    <div class="stat-label">Total Income</div>
    <div class="stat-value"><?= money($totals['total_income'] ?? 0) ?></div>
  </div>
  <div class="stat-card danger">
    <div class="stat-label">Total Expenses</div>
    <div class="stat-value"><?= money($totals['total_expense'] ?? 0) ?></div>
  </div>
  <div class="stat-card <?= $balance >= 0 ? 'accent' : 'danger' ?>">
    <div class="stat-label">Balance</div>
    <div class="stat-value"><?= money($balance) ?></div>
  </div>
</div>

<!-- Date filter -->
<form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px">
  <input type="hidden" name="page" value="finance">
  <input type="date" name="from" value="<?= e($from) ?>" class="form-control" style="max-width:160px">
  <input type="date" name="to"   value="<?= e($to) ?>"   class="form-control" style="max-width:160px">
  <button type="submit" class="btn btn-ghost">Filter</button>
</form>

<!-- Entries table -->
<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Date</th><th>Type</th><th>Category</th><th>Description</th><th>Staff</th><th class="text-right">Amount</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php if (!$entries): ?>
          <tr><td colspan="7" class="text-muted text-center">No entries for this period.</td></tr>
        <?php endif ?>
        <?php foreach ($entries as $e): ?>
        <tr>
          <td><?= fmtDate($e['entry_date']) ?></td>
          <td><span class="badge badge-<?= $e['type'] === 'income' ? 'success' : 'danger' ?>"><?= $e['type'] ?></span></td>
          <td><?= e($e['category']) ?></td>
          <td>
            <?= e($e['description']) ?>
            <?php if ($e['invoice_no']): ?>
              <br><a href="index.php?page=invoice&id=<?= $e['ref_sale_id'] ?>" style="font-size:.78rem"><?= e($e['invoice_no']) ?></a>
            <?php endif ?>
          </td>
          <td><?= e($e['staff_name']) ?></td>
          <td class="text-right" style="font-weight:700;color:<?= $e['type'] === 'income' ? 'var(--success)' : 'var(--danger)' ?>">
            <?= ($e['type'] === 'expense' ? '−' : '') . money($e['amount']) ?>
          </td>
          <td>
            <?php if (!$e['ref_sale_id']): ?>
              <a href="index.php?page=finance&edit=<?= $e['id'] ?>" class="btn btn-ghost btn-sm">✏️</a>
              <?php if (canDelete()): ?>
                <a href="index.php?page=finance&action=delete&id=<?= $e['id'] ?>" class="btn btn-danger btn-sm" data-confirm="Delete this entry?">🗑️</a>
              <?php endif ?>
            <?php else: ?>
              <span class="text-muted" style="font-size:.75rem">auto</span>
            <?php endif ?>
          </td>
        </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add / Edit Modal -->
<div class="modal-backdrop <?= $editing ? 'open' : '' ?>" id="entryModal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title"><?= $editing ? 'Edit Entry' : 'Add Finance Entry' ?></span>
      <button class="modal-close">✕</button>
    </div>
    <div class="modal-body">
      <form method="POST" id="entryForm">
        <input type="hidden" name="action"   value="save_entry">
        <input type="hidden" name="entry_id" value="<?= $editing['id'] ?? '' ?>">
        <div class="form-row cols-2">
          <div class="form-group">
            <label class="form-label">Type *</label>
            <select name="type" class="form-control" required>
              <option value="income"  <?= ($editing['type'] ?? '') === 'income'  ? 'selected' : '' ?>>Income</option>
              <option value="expense" <?= ($editing['type'] ?? '') === 'expense' ? 'selected' : '' ?>>Expense</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Amount *</label>
            <input type="number" name="amount" class="form-control" step="0.01" min="0" required value="<?= $editing['amount'] ?? '' ?>">
          </div>
        </div>
        <div class="form-row cols-2">
          <div class="form-group">
            <label class="form-label">Category</label>
            <input type="text" name="category" class="form-control" value="<?= e($editing['category'] ?? '') ?>" placeholder="e.g. Rent, Salary…">
          </div>
          <div class="form-group">
            <label class="form-label">Date *</label>
            <input type="date" name="entry_date" class="form-control" required value="<?= $editing['entry_date'] ?? today() ?>">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control"><?= e($editing['description'] ?? '') ?></textarea>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost modal-close">Cancel</button>
      <button type="submit" form="entryForm" class="btn btn-primary">Save</button>
    </div>
  </div>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
