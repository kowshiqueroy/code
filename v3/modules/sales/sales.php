<?php
// ============================================================
// modules/sales/sales.php — Sales list & management
// ============================================================

$action = $_GET['action'] ?? '';

if ($action === 'cancel' && canDelete()) {
    $id = (int)$_GET['id'];
    dbUpdate('sales', ['status' => 'cancelled'], 'id = ?', [$id]);
    logAction('CANCEL', 'sales', $id, 'Cancelled sale');
    flash('success', 'Sale cancelled.');
    redirect('sales');
}

$from   = $_GET['from']   ?? date('Y-m-01');
$to     = $_GET['to']     ?? today();
$status = $_GET['status'] ?? '';
$page   = max(1, (int)($_GET['p'] ?? 1));

$where  = 'DATE(s.created_at) BETWEEN ? AND ?';
$params = [$from, $to];
if ($status) { $where .= ' AND s.status = ?'; $params[] = $status; }

$paged = paginate(
    "SELECT s.*, u.full_name AS staff, c.name AS customer_name
     FROM sales s
     JOIN users u ON u.id = s.user_id
     LEFT JOIN customers c ON c.id = s.customer_id
     WHERE $where ORDER BY s.id DESC",
    $params, $page, 25
);

$pageTitle = 'Sales';
require_once BASE_PATH . '/includes/header.php';
?>

<div class="d-flex justify-between align-center mb-2 flex-wrap gap-2">
  <h1>🧾 Sales</h1>
  <a href="index.php?page=pos" class="btn btn-primary">+ New Sale</a>
</div>

<form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px">
  <input type="hidden" name="page" value="sales">
  <input type="date" name="from" value="<?= e($from) ?>" class="form-control" style="max-width:150px">
  <input type="date" name="to"   value="<?= e($to) ?>"   class="form-control" style="max-width:150px">
  <select name="status" class="form-control" style="max-width:130px">
    <option value="">All Statuses</option>
    <option value="completed" <?= $status==='completed'?'selected':'' ?>>Completed</option>
    <option value="draft"     <?= $status==='draft'    ?'selected':'' ?>>Draft</option>
    <option value="cancelled" <?= $status==='cancelled'?'selected':'' ?>>Cancelled</option>
  </select>
  <button type="submit" class="btn btn-ghost">Filter</button>
</form>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Invoice</th><th>Customer</th><th>Staff</th><th>Date</th><th>Payment</th><th class="text-right">Total</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($paged['rows'] as $s): ?>
        <tr>
          <td><strong><?= e($s['invoice_no']) ?></strong></td>
          <td><?= e($s['customer_name'] ?? 'Walk-in') ?></td>
          <td><?= e($s['staff']) ?></td>
          <td style="font-size:.82rem"><?= fmtDateTime($s['created_at']) ?></td>
          <td><?= e($s['payment_method']) ?></td>
          <td class="text-right font-weight:700"><?= money($s['total']) ?></td>
          <td>
            <span class="badge badge-<?= ['completed'=>'success','draft'=>'warning','cancelled'=>'danger'][$s['status']] ?? 'grey' ?>"><?= $s['status'] ?></span>
            <?php if ($s['status'] === 'draft'): ?>
            <a href="index.php?page=pos&id=<?= $s['id'] ?>" class="btn btn-sm btn-primary">POS</a>
            <?php endif ?>
          </td>
          <td style="white-space:nowrap">
            <a href="index.php?page=invoice&id=<?= $s['id'] ?>" class="btn btn-ghost btn-sm">🧾 View</a>
            <?php if ($s['status'] !== 'cancelled' && canDelete()): ?>
            <a href="index.php?page=sales&action=cancel&id=<?= $s['id'] ?>" class="btn btn-danger btn-sm" data-confirm="Cancel this sale?">✕</a>
            <?php endif ?>
          </td>
        </tr>
        <?php endforeach ?>
        <?php if (!$paged['rows']): ?><tr><td colspan="8" class="text-muted text-center">No sales found.</td></tr><?php endif ?>
      </tbody>
    </table>
  </div>

  <?php if ($paged['last_page'] > 1): ?>
  <div class="pagination">
    <?php for ($i = 1; $i <= $paged['last_page']; $i++): ?>
      <a href="?page=sales&p=<?= $i ?>&from=<?= $from ?>&to=<?= $to ?>&status=<?= urlencode($status) ?>"
         class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor ?>
  </div>
  <?php endif ?>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
