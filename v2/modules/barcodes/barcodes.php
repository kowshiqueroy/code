<?php
// ============================================================
// modules/barcodes/barcodes.php — Barcode / QR label printer
// ============================================================

$pageTitle = 'Barcode / QR Labels';

// Load products with variants
$products = dbFetchAll(
    "SELECT p.id, p.product_id, p.name, p.category_id, c.name AS category_name
     FROM products p LEFT JOIN categories c ON c.id = p.category_id
     WHERE p.active = 1 ORDER BY p.name"
);
foreach ($products as &$p) {
    $p['variants'] = dbFetchAll(
        'SELECT id, size, color, price, quantity, barcode FROM product_variants WHERE product_id = ? ORDER BY id',
        [$p['id']]
    );
}

require_once BASE_PATH . '/includes/header.php';
?>

<div class="d-flex justify-between align-center mb-2 flex-wrap gap-2">
  <h1>🏷️ Barcode / QR Labels</h1>
  <button class="btn btn-primary no-print" onclick="window.print()">🖨️ Print Labels</button>
</div>

<!-- Config Panel (no-print) -->
<div class="card no-print mb-2">
  <div class="card-title">⚙️ Label Settings</div>
  <div class="form-row cols-3">
    <div class="form-group">
      <label class="form-label">Paper Type</label>
      <select id="paperType" class="form-control" onchange="updatePreview()">
        <option value="a4">A4 (multiple per sheet)</option>
        <option value="sticker">Sticker Paper (2×4 per sheet)</option>
        <option value="single">Single Large Label</option>
      </select>
    </div>
    <div class="form-group">
      <label class="form-label">Code Type</label>
      <select id="codeType" class="form-control" onchange="updatePreview()">
        <option value="barcode">Barcode (Code 39)</option>
        <option value="qr">QR Code</option>
        <option value="both">Both</option>
      </select>
    </div>
    <div class="form-group">
      <label class="form-label">Show Details</label>
      <select id="showDetails" class="form-control" onchange="updatePreview()">
        <option value="full">Full (name, size, color, price)</option>
        <option value="minimal">Minimal (name + code only)</option>
        <option value="none">Code only</option>
      </select>
    </div>
  </div>

  <!-- Product selector -->
  <div class="card-title mt-2">Select Products to Print</div>
  <div style="display:flex;gap:8px;margin-bottom:8px;flex-wrap:wrap">
    <button class="btn btn-ghost btn-sm" onclick="selectAll()">Select All</button>
    <button class="btn btn-ghost btn-sm" onclick="selectNone()">Clear</button>
    <input type="text" id="prodFilter" class="form-control" placeholder="Filter…" style="max-width:200px" oninput="filterProdList()">
  </div>
  <div id="productCheckList" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:6px;max-height:280px;overflow-y:auto">
    <?php foreach ($products as $p): ?>
      <?php foreach ($p['variants'] as $v): ?>
      <label style="display:flex;align-items:flex-start;gap:6px;padding:8px;background:var(--surface2);border-radius:8px;cursor:pointer;font-size:.82rem"
             data-name="<?= e(strtolower($p['name'])) ?>">
        <input type="checkbox" class="variant-check" checked
               data-variant-id="<?= $v['id'] ?>"
               data-product-name="<?= e($p['name']) ?>"
               data-product-id="<?= e($p['product_id']) ?>"
               data-category="<?= e($p['category_name'] ?? '') ?>"
               data-size="<?= e($v['size']) ?>"
               data-color="<?= e($v['color']) ?>"
               data-price="<?= $v['price'] ?>"
               data-barcode="<?= e($v['barcode'] ?? $p['product_id']) ?>"
               onchange="updatePreview()">
        <div>
          <strong><?= e($p['name']) ?></strong>
          <div class="text-muted"><?= e($v['size']??'') ?> <?= e($v['color']??'') ?> — $<?= number_format($v['price'],2) ?></div>
        </div>
      </label>
      <?php endforeach ?>
    <?php endforeach ?>
  </div>
</div>

<!-- Print Preview / Output -->
<div class="card no-print">
  <div class="card-title">Preview</div>
  <div id="labelPreviewWrap" style="background:#fff;padding:10px;border-radius:8px">
    <div id="labelPreview" style="color:#000"></div>
  </div>
</div>

<!-- Actual print container -->
<div id="printLabels" style="display:none"></div>

<style>
/* ── Label print styles ─────────────────────────────────── */
.label-sheet-a4 {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 4mm;
  padding: 8mm;
  width: 210mm;
  background: #fff;
}
.label-sheet-sticker {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 3mm;
  padding: 6mm;
  width: 210mm;
  background: #fff;
}
.label-sheet-single {
  display: flex;
  flex-wrap: wrap;
  gap: 6mm;
  padding: 10mm;
  background: #fff;
}
.label-card {
  border: 1px solid #ddd;
  border-radius: 4px;
  padding: 6px 8px;
  text-align: center;
  font-family: Arial, sans-serif;
  font-size: 8pt;
  color: #000;
  background: #fff;
  break-inside: avoid;
}
.label-card.single { width: 60mm; min-height: 35mm; padding: 8px; font-size: 9pt; }
.label-name  { font-weight: 900; font-size: 9pt; line-height: 1.2; margin-bottom: 2px; }
.label-meta  { color: #555; font-size: 7pt; margin-bottom: 3px; }
.label-price { font-size: 11pt; font-weight: 900; }
.label-barcode-text {
  font-family: 'Libre Barcode 39', monospace;
  font-size: 28px;
  line-height: 1;
  letter-spacing: 1px;
  margin: 3px 0;
}
.label-code-val { font-size: 7pt; color: #555; font-family: monospace; }

@media print {
  body { background: #fff !important; }
  .no-print, .app-header, .app-footer, .side-nav { display: none !important; }
  .app-main { margin: 0 !important; padding: 0 !important; }
  #printLabels { display: block !important; }
  #labelPreviewWrap, .card { display: none !important; }
  .label-sheet-a4, .label-sheet-sticker, .label-sheet-single { page-break-after: always; }
}
</style>

<link href="https://fonts.googleapis.com/css2?family=Libre+Barcode+39&display=swap" rel="stylesheet">

<script>
function getCheckedVariants() {
  return [...document.querySelectorAll('.variant-check:checked')].map(el => ({
    variant_id:   el.dataset.variantId,
    product_name: el.dataset.productName,
    product_id:   el.dataset.productId,
    category:     el.dataset.category,
    size:         el.dataset.size,
    color:        el.dataset.color,
    price:        el.dataset.price,
    barcode:      el.dataset.barcode,
  }));
}

function buildLabel(v, paperType, codeType, showDetails) {
  const isLarge  = paperType === 'single';
  const cls      = `label-card${isLarge ? ' single' : ''}`;
  const code     = v.barcode || v.product_id;
  const qrUrl    = `https://api.qrserver.com/v1/create-qr-code/?size=80x80&data=${encodeURIComponent(code)}`;
  const size     = isLarge ? 80 : 60;

  let details = '';
  if (showDetails === 'full') {
    details = `<div class="label-name">${esc(v.product_name)}</div>`;
    if (v.size || v.color) details += `<div class="label-meta">${esc(v.size)} ${esc(v.color)}</div>`;
    details += `<div class="label-price">$${parseFloat(v.price).toFixed(2)}</div>`;
  } else if (showDetails === 'minimal') {
    details = `<div class="label-name">${esc(v.product_name)}</div>`;
  }

  let codeHtml = '';
  if (codeType === 'barcode' || codeType === 'both') {
    codeHtml += `<div class="label-barcode-text">*${esc(code.replace(/[^A-Z0-9\-]/gi,'').toUpperCase())}*</div>`;
    codeHtml += `<div class="label-code-val">${esc(code)}</div>`;
  }
  if (codeType === 'qr' || codeType === 'both') {
    codeHtml += `<div><img src="${qrUrl}" width="${size}" height="${size}" alt="${esc(code)}"></div>`;
    if (codeType === 'qr') codeHtml += `<div class="label-code-val">${esc(code)}</div>`;
  }

  return `<div class="${cls}">${details}${codeHtml}</div>`;
}

function updatePreview() {
  const paperType   = document.getElementById('paperType').value;
  const codeType    = document.getElementById('codeType').value;
  const showDetails = document.getElementById('showDetails').value;
  const variants    = getCheckedVariants();

  const sheetClass = {
    a4:      'label-sheet-a4',
    sticker: 'label-sheet-sticker',
    single:  'label-sheet-single',
  }[paperType];

  if (!variants.length) {
    document.getElementById('labelPreview').innerHTML = '<p style="color:#999;padding:20px">No variants selected.</p>';
    document.getElementById('printLabels').innerHTML  = '';
    return;
  }

  const labels = variants.map(v => buildLabel(v, paperType, codeType, showDetails)).join('');
  const sheet  = `<div class="${sheetClass}">${labels}</div>`;

  document.getElementById('labelPreview').innerHTML  = sheet;
  document.getElementById('printLabels').innerHTML   = sheet;
}

function selectAll()  { document.querySelectorAll('.variant-check').forEach(c => c.checked = true);  updatePreview(); }
function selectNone() { document.querySelectorAll('.variant-check').forEach(c => c.checked = false); updatePreview(); }

function filterProdList() {
  const q = document.getElementById('prodFilter').value.toLowerCase();
  document.querySelectorAll('#productCheckList label').forEach(el => {
    el.style.display = (!q || el.dataset.name?.includes(q)) ? '' : 'none';
  });
}

function esc(s) {
  return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Init
document.addEventListener('DOMContentLoaded', updatePreview);
</script>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
