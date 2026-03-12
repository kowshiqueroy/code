<?php
// ============================================================
// modules/barcodes/barcodes.php — Professional Label Printer
// ============================================================
$products = dbFetchAll(
    "SELECT p.id, p.product_id, p.name, c.name AS category_name
     FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.active = 1 ORDER BY p.name"
);
foreach ($products as &$p) {
    $p['variants'] = dbFetchAll(
        'SELECT id, size, color, price,cost, quantity, barcode FROM product_variants WHERE product_id = ? ORDER BY id',
        [$p['id']]
    );
}
$S   = getAllSettings();
$cur = $S['currency_symbol'] ?? '$';
$companyName = $S['shop_name'] ?? '.....................';

$pageTitle = 'Print Barcodes';
require_once BASE_PATH . '/includes/header.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Libre+Barcode+39+Text&display=swap" rel="stylesheet">

<div class="d-flex justify-between align-center mb-2 flex-wrap gap-2 no-print">
  <h1>🏷️ Print Barcode Labels</h1>
  <button class="btn btn-primary no-print" onclick="window.print()" style="font-size:1.1rem; padding: 8px 24px;">🖨️ Print Labels</button>
</div>

<div class="card no-print mb-2">
  <div class="card-title" style="border-bottom: 1px solid var(--border); padding-bottom: 10px; margin-bottom: 15px;">⚙️ Layout & Paper Settings</div>
  
  <div class="form-row cols-4 mb-2">
    <div class="form-group">
      <label class="form-label text-muted">Paper Size</label>
      <select id="paperSize" class="form-control" onchange="togglePaperSettings(); renderLabels();">
        <option value="a4">A4 Sheet (210 x 297mm)</option>
        <option value="custom">Custom Paper / Roll</option>
      </select>
    </div>
    
    <div class="form-group custom-paper-setting" style="display: none;">
      <label class="form-label text-muted">Paper Width (mm)</label>
      <input type="number" id="paperW" class="form-control" value="80" min="20" oninput="renderLabels()">
    </div>
    <div class="form-group custom-paper-setting" style="display: none;">
      <label class="form-label text-muted">Paper Height (mm)</label>
      <input type="number" id="paperH" class="form-control" value="40" min="20" oninput="renderLabels()">
    </div>

    <div class="form-group">
      <label class="form-label text-muted">Layout Format</label>
      <select id="columns" class="form-control" onchange="renderLabels()">
        <option value="1">1 Column (One below another)</option>
        <option value="2">2 Columns (Side-by-side grid)</option>
        <option value="3">3 Columns (Side-by-side grid)</option>
        <option value="4">4 Columns (Side-by-side grid)</option>
        <option value="5">5 Columns (Side-by-side grid)</option>
      </select>
    </div>
  </div>

  <div class="form-row cols-4 mb-2" style="background:var(--surface2); padding:10px; border-radius:8px;">
    <div class="form-group mb-0">
      <label class="form-label text-muted">Label Width (mm)</label>
      <input type="number" id="labelW" class="form-control" value="50" min="10" oninput="renderLabels()">
    </div>
    <div class="form-group mb-0">
      <label class="form-label text-muted">Label Height (mm)</label>
      <input type="number" id="labelH" class="form-control" value="50" min="10" oninput="renderLabels()">
    </div>
    <div class="form-group mb-0">
      <label class="form-label text-muted">Gap Between Labels (mm)</label>
      <input type="number" id="labelGap" class="form-control" value="2" min="0" oninput="renderLabels()">
    </div>
    <div class="form-group mb-0">
      <label class="form-label text-muted">Barcode Size: <span id="bcSizeVal">32</span>px</label>
      <input type="range" id="bcSize" class="form-control" value="32" min="16" max="72" oninput="document.getElementById('bcSizeVal').innerText=this.value; renderLabels()">
    </div>
  </div>

  <div class="form-group mb-2">
      <label class="form-label text-muted">Label Information to Show</label>
      <select id="detailLevel" class="form-control" style="max-width:300px;" onchange="renderLabels()">
        <option value="full">Full (Name, Variant, Price, Barcode)</option>
        <option value="minimal">Minimal (Name, Barcode)</option>
        <option value="code">Barcode Only</option>
      </select>
  </div>

  <hr style="border-color: var(--border); margin: 15px 0;">

  <div class="card-title">📦 Select Items to Print</div>
  <div style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap">
    <button class="btn btn-outline btn-sm" onclick="selectAll(true)">Select All</button>
    <button class="btn btn-outline btn-sm" onclick="selectAll(false)">Clear All</button>
    <button class="btn btn-outline btn-sm" onclick="useStockQty()">Print Stock Qty</button>
    <input type="text" id="prodFilter" class="form-control form-control-sm" placeholder="Search item..." style="max-width:200px" oninput="filterList()">
  </div>
  
  <div id="variantList" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:8px;max-height:300px;overflow-y:auto;padding-right:5px;">
    <?php foreach ($products as $p): ?>
      <?php foreach ($p['variants'] as $v): ?>
      <div class="variant-row" style="display:flex;align-items:center;gap:10px;padding:8px;background:var(--surface2);border:1px solid var(--border);border-radius:6px;font-size:.85rem"
           data-search="<?= e(strtolower($p['name'].' '.$v['size'].' '.$v['color'])) ?>">
        
        <input type="checkbox" class="vcheck" style="width:18px;height:18px;cursor:pointer;" checked
               data-vid="<?= $v['id'] ?>"
               data-name="<?= e($p['name']) ?>"
               data-size="<?= e($v['size']??'') ?>"
               data-color="<?= e($v['color']??'') ?>"
               data-price="<?= $v['price'] ?>"
                data-cost="<?= $v['cost'] ?>"
               data-stock="<?= $v['quantity'] ?>"
               data-barcode="<?= e($v['barcode']??'') ?>"
               onchange="renderLabels()">
               
        <div style="flex:1; overflow:hidden;">
          <strong style="display:block; white-space:nowrap; text-overflow:ellipsis; overflow:hidden; color:var(--text);"><?= e($p['name']) ?></strong>
          <div class="text-muted" style="font-size:0.75rem;">
            <?= e($v['size']??'') ?> <?= e($v['color']??'') ?> — <span style="color:var(--success); font-weight:bold;"><?= $cur . number_format($v['price'],2) ?></span><span style=" font-weight:bold;"></span> <?= $cur . number_format($v['cost'],2) ?></span>
          </div>
         
        </div>
        
        <input type="number" class="vqty form-control form-control-sm" value="1" min="1" max="999"
               style="width:55px; text-align:center;" title="Copies" oninput="renderLabels()">
      </div>
      <?php endforeach ?>
    <?php endforeach ?>
  </div>
</div>

<div class="card no-print">
  <div class="card-title">👁️ Print Preview <span class="text-muted" style="font-size:0.8rem;font-weight:normal;">(Dashed borders represent sticker edges)</span></div>
  <div id="previewContainer" style="background:#e9ecef; padding:20px; border-radius:8px; overflow:auto; max-height:600px; display:flex; justify-content:center;">
      <div id="previewArea"></div>
  </div>
</div>

<div id="printArea"></div>

<style>
/* ── UI Logic ── */
.lbl-inner {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  width: 100%; height: 100%; overflow: hidden; box-sizing: border-box;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
  color: #000; text-align: center; line-height: 1.2;
}
.lbl-title { font-weight: bold; font-size: 12pt; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; width: 100%; }
.lbl-meta  { font-size: 10pt; color: #333; }
.lbl-cost { font-size: 9pt; font-weight: 900; margin-top: 2px; }
.lbl-discount { font-size: 10pt; font-weight: 700; margin-top: 0px; }
.lbl-price { font-size: 12pt; font-weight: 900; margin-top: 2px; }

/* The actual barcode font class */
.lbl-barcode {
  font-family: 'Libre Barcode 39 Text', monospace;
  white-space: nowrap; /* STRICTLY PREVENTS WRAPPING */
  overflow: hidden;    /* Prevents spilling outside the sticker */
  margin-top: 3px;
  line-height: 0.9;
  letter-spacing: 0;
  display: block;
  max-width: 100%;     /* Keeps it inside the box width */
}

/* Print Overrides */
@media print {
  body, html { background: #fff !important; margin:0 !important; padding:0 !important; color: #000 !important; }
  .no-print, .app-header, .app-footer, .side-nav { display: none !important; }
  .app-main { margin:0 !important; padding:0 !important; background: transparent !important;}
  
  #previewContainer { display: none !important; }
  #printArea { display: block !important; }
  
  * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
}
</style>

<script>
const CURRENCY = '<?= addslashes($cur) ?>';
const COMPANY = '<?= addslashes($companyName) ?>';

// Toggle UI inputs for Custom Paper
function togglePaperSettings() {
  const isCustom = document.getElementById('paperSize').value === 'custom';
  document.querySelectorAll('.custom-paper-setting').forEach(el => {
    el.style.display = isCustom ? 'block' : 'none';
  });
}

// Gather selected items
function getChecked() {
  return [...document.querySelectorAll('.vcheck:checked')].map(el => {
    const row  = el.closest('.variant-row');
    const qty  = parseInt(row.querySelector('.vqty').value) || 1;
    return { 
      vid: el.dataset.vid, 
      name: el.dataset.name, 
      size: el.dataset.size, 
      color: el.dataset.color,
      price: el.dataset.price, 
      cost: el.dataset.cost,
      barcode: el.dataset.barcode,
      qty 
    };
  });
}

function selectAll(on) {
  document.querySelectorAll('.vcheck').forEach(c => c.checked = on);
  renderLabels();
}

function useStockQty() {
  document.querySelectorAll('.variant-row').forEach(row => {
    const stock = parseInt(row.querySelector('.vcheck').dataset.stock) || 1;
    row.querySelector('.vqty').value = Math.max(1, stock);
  });
  renderLabels();
}

function filterList() {
  const q = document.getElementById('prodFilter').value.toLowerCase();
  document.querySelectorAll('.variant-row').forEach(row => {
    row.style.display = !q || row.dataset.search?.includes(q) ? '' : 'none';
  });
}

function esc(s) {
  return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Build and Inject Layout
function renderLabels() {
  const paperSize = document.getElementById('paperSize').value;
  const paperW    = document.getElementById('paperW').value || 80;
  const paperH    = document.getElementById('paperH').value || 40;
  
  const cols      = document.getElementById('columns').value || 1;
  const labelW    = document.getElementById('labelW').value || 50;
  const labelH    = document.getElementById('labelH').value || 30;
  const gap       = document.getElementById('labelGap').value || 2;
  
  const bcSize    = document.getElementById('bcSize').value || 32;
  const detail    = document.getElementById('detailLevel').value;

  const variants = getChecked();
  if (!variants.length) {
    document.getElementById('previewArea').innerHTML = '<p class="text-muted">No products selected.</p>';
    document.getElementById('printArea').innerHTML = '';
    return;
  }

  // Multiply items by their requested copy count
  const expanded = variants.flatMap(v => Array(v.qty).fill(v));

  // Determine Physical Print CSS
  let pageCss = '';
  if (paperSize === 'a4') {
    // Standard A4 Printer
    pageCss = `@page { size: A4 portrait; margin: 5mm; }`;
  } else {
    // Custom Label Printer Roll
    pageCss = `@page { size: ${paperW}mm ${paperH}mm; margin: 0mm; }`;
  }

  // The dynamic grid and box CSS
  const dynamicCss = `
    <style>
      ${pageCss}
      
      .print-grid {
        display: grid;
        grid-template-columns: repeat(${cols}, ${labelW}mm);
        gap: ${gap}mm;
        justify-content: center;
        margin: 0 auto;
        background: #fff;
      }

      .single-label {
        width: ${labelW}mm;
        height: ${labelH}mm;
        box-sizing: border-box;
        overflow: hidden;
        background: #fff;
        padding: 2mm;
      }

      /* In screen view, show a dashed border to represent the physical sticker cut */
      @media screen {
        .single-label { border: 1px dashed #999; }
        .print-grid { padding: 10px; border: 1px solid #ddd; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
      }

      /* In physical print, remove borders so they don't print ink on the edges */
      @media print {
        .single-label { border: none !important; }
      }
    </style>
  `;

  // Generate HTML for each label
  const labelsHtml = expanded.map(v => {
    // Fallback barcode to Variant ID if missing
    const codeStr = (v.barcode && v.barcode.trim() !== '') ? v.barcode.trim() : String(v.vid).padStart(6, '0');
    
    let content = `<div class="lbl-inner">`;
    
    if (detail === 'full' || detail === 'minimal') {
      content += `<div class="lbl-title">${esc(v.name)}</div>`;
    }
    
    if (detail === 'full') {
      let meta = [];
      if (v.size) meta.push(esc(v.size));
      if (v.color) meta.push(esc(v.color));
      if (meta.length > 0) content += `<div class="lbl-meta">${meta.join(', ')}</div>`;
      content += `<div class="lbl-cost">${CURRENCY}${parseFloat(v.cost).toFixed(2)}</div>`;  
         content += `<div class="lbl-discount">${CURRENCY}${parseFloat(v.cost-v.price).toFixed(2)}</div>`;  
      content += `<div class="lbl-price">${CURRENCY}${parseFloat(v.price).toFixed(2)}</div>`;
    }
    
    // Libre Barcode 39 Text needs asterisks wrapping the text to scan properly
    content += `<div class="lbl-barcode" style="font-size: ${bcSize}px;">*${codeStr}*</div>`;
    

      content += `<div class="lbl-code">${COMPANY}</div>`;
    
    
    content += `</div>`;
    
    return `<div class="single-label">${content}</div>`;
  }).join('');

  const finalHtml = dynamicCss + `<div class="print-grid">${labelsHtml}</div>`;
  
  document.getElementById('previewArea').innerHTML = finalHtml;
  document.getElementById('printArea').innerHTML = finalHtml;
}

// Initial render
document.addEventListener('DOMContentLoaded', () => {
  togglePaperSettings();
  renderLabels();
});
</script>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>