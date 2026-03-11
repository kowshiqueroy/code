<?php
// ============================================================
// modules/invoices/invoice.php — Invoice viewer + print
// ============================================================

$id   = (int)($_GET['id'] ?? 0);
$sale = dbFetch(
    "SELECT s.*, u.full_name AS staff_name, c.name AS customer_name, c.phone AS customer_phone
     FROM sales s
     JOIN users u ON u.id = s.user_id
     LEFT JOIN customers c ON c.id = s.customer_id
     WHERE s.id = ?",
    [$id]
);

if (!$sale) { flash('error', 'Invoice not found.'); redirect('sales'); }

$items = dbFetchAll(
    'SELECT * FROM sale_items WHERE sale_id = ? ORDER BY id',
    [$id]
);

$qrData   = BASE_URL . '/index.php?page=invoice_verify&inv=' . urlencode($sale['invoice_no']);
$qrImgUrl = qrUrl($qrData, 120);

$pageTitle = 'Invoice ' . $sale['invoice_no'];
require_once BASE_PATH . '/includes/header.php';
?>

<!-- ── Screen view ─────────────────────────────────────────── -->
<div class="no-print">
  <div class="d-flex justify-between align-center mb-2 flex-wrap gap-2">
    <h1>🧾 <?= e($sale['invoice_no']) ?></h1>
    <div style="display:flex;gap:8px">
      <a href="index.php?page=sales" class="btn btn-ghost">← Back</a>
      <button class="btn btn-primary" onclick="window.print()">🖨️ Print</button>
    </div>
  </div>

  <!-- Summary card -->
  <div class="card mb-2">
    <div class="form-row cols-2">
      <div>
        <div class="text-muted" style="font-size:.8rem">Customer</div>
        <div><strong><?= e($sale['customer_name'] ?? 'Walk-in') ?></strong></div>
        <div class="text-muted" style="font-size:.8rem"><?= e($sale['customer_phone'] ?? '') ?></div>
      </div>
      <div>
        <div class="text-muted" style="font-size:.8rem">Staff / Date</div>
        <div><strong><?= e($sale['staff_name']) ?></strong></div>
        <div class="text-muted" style="font-size:.8rem"><?= fmtDateTime($sale['created_at']) ?></div>
      </div>
      <div>
        <div class="text-muted" style="font-size:.8rem">Status</div>
        <span class="badge badge-<?= $sale['status'] === 'completed' ? 'success' : 'warning' ?>"><?= $sale['status'] ?></span>
      </div>
      <div>
        <div class="text-muted" style="font-size:.8rem">Payment</div>
        <div><?= e($sale['payment_method']) ?></div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Item</th><th>Size</th><th>Color</th><th class="text-right">Qty</th><th class="text-right">Price</th><th class="text-right">Total</th></tr>
        </thead>
        <tbody>
          <?php foreach ($items as $item): ?>
          <tr>
            <td><?= e($item['product_name']) ?></td>
            <td><?= e($item['size']) ?></td>
            <td><?= e($item['color']) ?></td>
            <td class="text-right"><?= $item['qty'] ?></td>
            <td class="text-right"><?= money($item['unit_price']) ?></td>
            <td class="text-right"><?= money($item['total_price']) ?></td>
          </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
    <div style="text-align:right;padding-top:12px;border-top:1px solid var(--border);margin-top:12px">
      <div class="summary-row"><span>Subtotal:</span><span><?= money($sale['subtotal']) ?></span></div>
      <?php if ($sale['discount_amount'] > 0): ?>
      <div class="summary-row"><span>Discount (<?= $sale['discount_pct'] ?>%):</span><span>−<?= money($sale['discount_amount']) ?></span></div>
      <?php endif ?>
      <?php if ($sale['points_value'] > 0): ?>
      <div class="summary-row"><span>Points (<?= $sale['points_used'] ?> pts):</span><span>−<?= money($sale['points_value']) ?></span></div>
      <?php endif ?>
      <?php if ($sale['vat_amount'] > 0): ?>
      <div class="summary-row"><span>VAT (<?= $sale['vat_rate'] ?>%):</span><span><?= money($sale['vat_amount']) ?></span></div>
      <?php endif ?>
      <div class="summary-row total"><span>TOTAL:</span><span><?= money($sale['total']) ?></span></div>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     PRINT TEMPLATE — A4 Landscape · Two copies side-by-side
     ═══════════════════════════════════════════════════════ -->
<div class="invoice-page" id="printInvoice">

  <?php foreach (['Customer Copy', 'Showroom Copy'] as $copyLabel): ?>
  <div class="invoice-copy">
    <div class="inv-header">
      <div>
        <div class="inv-title"><?= APP_NAME ?></div>
        <div style="font-size:9pt;color:#555"><?= BASE_URL ?></div>
      </div>
      <div style="text-align:right">
        <div class="copy-label"><?= $copyLabel ?></div>
        <div style="font-size:9pt"><strong>Invoice:</strong> <?= e($sale['invoice_no']) ?></div>
        <div style="font-size:9pt"><strong>Date:</strong> <?= fmtDateTime($sale['created_at']) ?></div>
        <div style="font-size:9pt"><strong>Staff:</strong> <?= e($sale['staff_name']) ?></div>
      </div>
    </div>

    <!-- Customer block -->
    <div style="margin-bottom:4mm;font-size:9pt">
      <strong>Customer:</strong> <?= e($sale['customer_name'] ?? 'Walk-in') ?>
      <?php if ($sale['customer_phone']): ?> | <?= e($sale['customer_phone']) ?><?php endif ?>
    </div>

    <!-- Items table -->
    <table class="inv-table">
      <thead>
        <tr>
          <th>#</th><th>Item</th><th>Size</th><th>Color</th>
          <th style="text-align:right">Qty</th>
          <th style="text-align:right">Unit</th>
          <th style="text-align:right">Total</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $i => $item): ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td><?= e($item['product_name']) ?></td>
          <td><?= e($item['size']) ?></td>
          <td><?= e($item['color']) ?></td>
          <td style="text-align:right"><?= $item['qty'] ?></td>
          <td style="text-align:right"><?= money($item['unit_price']) ?></td>
          <td style="text-align:right"><?= money($item['total_price']) ?></td>
        </tr>
        <?php endforeach ?>
      </tbody>
    </table>

    <!-- Totals -->
    <div class="inv-totals">
      <div class="inv-total-row"><span>Subtotal</span><span><?= money($sale['subtotal']) ?></span></div>
      <?php if ($sale['discount_amount'] > 0): ?>
      <div class="inv-total-row"><span>Discount (<?= $sale['discount_pct'] ?>%)</span><span>−<?= money($sale['discount_amount']) ?></span></div>
      <?php endif ?>
      <?php if ($sale['points_value'] > 0): ?>
      <div class="inv-total-row"><span>Points Redeemed</span><span>−<?= money($sale['points_value']) ?></span></div>
      <?php endif ?>
      <?php if ($sale['vat_amount'] > 0): ?>
      <div class="inv-total-row"><span>VAT (<?= $sale['vat_rate'] ?>%)</span><span><?= money($sale['vat_amount']) ?></span></div>
      <?php endif ?>
      <div class="inv-grand">TOTAL: <?= money($sale['total']) ?></div>
      <div style="font-size:8pt;margin-top:2mm">Payment: <?= e($sale['payment_method']) ?></div>
    </div>

    <!-- QR Code -->
    <div class="inv-qr">
      <img src="<?= $qrImgUrl ?>" width="90" height="90" alt="QR">
      <div style="font-size:7pt;color:#777">Scan to verify invoice</div>
    </div>

    <!-- Footer -->
    <div class="inv-footer">
      Thank you for your purchase! · <?= APP_NAME ?>
      <?php if ($sale['notes']): ?><br><em><?= e($sale['notes']) ?></em><?php endif ?>
    </div>
  </div>
  <?php endforeach ?>

</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
