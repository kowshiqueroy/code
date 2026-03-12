<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<title>Offline POS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
:root {
  --bg:#0f1117; --surface:#1a1d27; --surface2:#252836; --border:#2e3245;
  --accent:#4f8ef7; --success:#22c55e; --danger:#ef4444; --warning:#f59e0b;
  --text:#e8eaf0; --muted:#8b90a7; --radius:10px;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'DM Sans',system-ui,sans-serif;background:var(--bg);color:var(--text);min-height:100vh}
.top-bar{background:var(--surface);border-bottom:1px solid var(--border);padding:10px 16px;
  display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap}
.logo{font-size:1.1rem;font-weight:800;color:var(--accent)}
.status-badge{display:inline-flex;align-items:center;gap:5px;font-size:.8rem;font-weight:700;
  padding:4px 10px;border-radius:99px;background:rgba(239,68,68,.15);color:var(--danger)}
.status-badge.online{background:rgba(34,197,94,.15);color:var(--success)}
.status-dot{width:8px;height:8px;border-radius:50%;background:currentColor}
.main{display:grid;grid-template-columns:1fr;gap:12px;padding:12px}
@media(min-width:900px){.main{grid-template-columns:1fr 360px}}
.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:16px}
.card-title{font-weight:700;margin-bottom:12px;font-size:.95rem}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:9px 16px;
  border:none;border-radius:var(--radius);font-size:.88rem;font-weight:600;cursor:pointer;
  transition:opacity .15s;white-space:nowrap}
.btn:active{opacity:.8}.btn:disabled{opacity:.45;cursor:not-allowed}
.btn-primary{background:var(--accent);color:#fff}
.btn-success{background:var(--success);color:#fff}
.btn-danger{background:var(--danger);color:#fff}
.btn-warning{background:var(--warning);color:#000}
.btn-ghost{background:transparent;border:1px solid var(--border);color:var(--text)}
.btn-sm{padding:5px 10px;font-size:.78rem}
.btn-block{width:100%}
.form-control{width:100%;padding:9px 12px;background:var(--surface2);border:1px solid var(--border);
  border-radius:var(--radius);color:var(--text);font-size:.9rem}
.form-control:focus{outline:none;border-color:var(--accent)}
.form-label{display:block;font-size:.78rem;font-weight:600;color:var(--muted);margin-bottom:5px}
.form-group{margin-bottom:10px}
.product-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:8px}
@media(min-width:480px){.product-grid{grid-template-columns:repeat(3,1fr)}}
.tile{background:var(--surface);border:2px solid var(--border);border-radius:var(--radius);
  padding:10px;cursor:pointer;transition:border-color .15s;user-select:none}
.tile:hover{border-color:var(--accent)}
.tile:active{transform:scale(.96)}
.tile-name{font-size:.83rem;font-weight:700;margin-bottom:3px}
.tile-price{font-size:1rem;font-weight:800;color:var(--accent)}
.tile-stock{font-size:.7rem;color:var(--muted);margin-top:3px}
.cart-items{max-height:300px;overflow-y:auto}
.cart-item{display:flex;align-items:center;gap:6px;padding:8px 0;
  border-bottom:1px solid var(--border);font-size:.85rem}
.item-name{flex:1}
.qty-btn{width:24px;height:24px;border-radius:5px;background:var(--surface2);
  border:1px solid var(--border);color:var(--text);cursor:pointer;font-weight:700;
  display:flex;align-items:center;justify-content:center}
.qty-num{width:30px;text-align:center;background:none;border:none;color:var(--text);font-weight:700}
.item-total{min-width:55px;text-align:right;font-weight:700}
.item-del{background:none;border:none;color:var(--danger);cursor:pointer}
.summary-row{display:flex;justify-content:space-between;font-size:.88rem;margin-bottom:5px}
.summary-total{font-size:1.2rem;font-weight:800;color:var(--success);
  border-top:1px solid var(--border);padding-top:8px;margin-top:4px}
.badge{display:inline-block;font-size:.7rem;font-weight:700;padding:2px 7px;border-radius:99px}
.badge-info{background:rgba(79,142,247,.15);color:var(--accent)}
.badge-warn{background:rgba(245,158,11,.15);color:var(--warning)}
.badge-ok{background:rgba(34,197,94,.15);color:var(--success)}
.flash{padding:10px 14px;border-radius:var(--radius);margin-bottom:10px;font-size:.88rem;font-weight:500}
.flash-success{background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);color:var(--success)}
.flash-error{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:var(--danger)}
.modal-backdrop{display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:200;
  align-items:center;justify-content:center;padding:12px}
.modal-backdrop.open{display:flex}
.modal{background:var(--surface);border:1px solid var(--border);border-radius:14px;
  width:100%;max-width:460px;max-height:90vh;overflow-y:auto}
.modal-hdr{padding:16px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;font-weight:700}
.modal-body{padding:16px}
.modal-ftr{padding:12px 16px;border-top:1px solid var(--border);display:flex;gap:8px;justify-content:flex-end}
.modal-close{background:none;border:none;color:var(--muted);cursor:pointer;font-size:1.2rem}
.tabs{display:flex;gap:4px;margin-bottom:12px;border-bottom:1px solid var(--border);padding-bottom:8px}
.tab-btn{padding:6px 12px;border-radius:7px;border:none;background:none;color:var(--muted);
  cursor:pointer;font-weight:600;font-size:.85rem;transition:all .15s}
.tab-btn.active{background:var(--accent);color:#fff}
.pending-list{max-height:260px;overflow-y:auto}
.pending-item{display:flex;align-items:center;justify-content:space-between;
  padding:8px 0;border-bottom:1px solid var(--border);font-size:.85rem}
.payment-checks{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:10px}
.pay-opt{display:flex;align-items:center;gap:5px;padding:7px 12px;border-radius:8px;
  border:1px solid var(--border);cursor:pointer;user-select:none;font-size:.85rem;font-weight:600;
  transition:border-color .15s}
.pay-opt.selected{border-color:var(--accent);background:rgba(79,142,247,.1)}
table{width:100%;border-collapse:collapse;font-size:.82rem}
th{background:var(--surface2);padding:8px;text-align:left;font-size:.75rem;text-transform:uppercase;color:var(--muted);border-bottom:1px solid var(--border)}
td{padding:8px;border-bottom:1px solid var(--border)}
.no-print{} 
@media print {
  .no-print,.top-bar,.sync-panel,.products-panel{display:none!important}
  body{background:#fff;color:#000}
  .print-invoice{display:block!important}
  .invoice-wrap{display:grid;grid-template-columns:1fr 1fr;width:297mm;height:210mm}
  .inv-copy{border:1px solid #ccc;padding:10mm 8mm;font-family:Arial,sans-serif;font-size:9pt;color:#000}
  .inv-copy+.inv-copy{border-left:2px dashed #888}
}
.print-invoice{display:none}
</style>
</head>
<body>

<!-- Top Bar -->
<div class="top-bar no-print">
  <div class="logo">🛒 Offline POS</div>
  <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
    <div class="status-badge" id="statusBadge">
      <div class="status-dot"></div> <span id="statusText">Offline</span>
    </div>
    <button class="btn btn-ghost btn-sm" onclick="openModal('syncModal')">⟳ Sync (<span id="pendingCount">0</span>)</button>
    <button class="btn btn-ghost btn-sm" onclick="openModal('draftModal')">📋 Drafts</button>
    <a href="index.php?page=pos" class="btn btn-ghost btn-sm">← Online POS</a>
  </div>
</div>

<div id="flashArea" style="padding:0 12px"></div>

<div class="main">

  <!-- Left: Products -->
  <div class="products-panel no-print">
    <div class="card" style="margin-bottom:10px">
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <input type="text" id="productSearch" class="form-control" placeholder="Search products…" style="flex:1;min-width:120px" oninput="filterProducts()">
        <input type="text" id="barcodeInput" class="form-control" placeholder="Scan barcode…" style="max-width:150px"
               onkeydown="if(event.key==='Enter'){lookupBarcode(this.value);this.value=''}">
      </div>
    </div>
    <div class="product-grid" id="productGrid">
      <div class="tile" style="grid-column:1/-1;text-align:center;color:var(--muted)">Loading products…</div>
    </div>
  </div>

  <!-- Right: Cart -->
  <div>
    <div class="card">
      <div class="card-title">🛒 Cart &nbsp;<span class="badge badge-info" id="cartCount">0</span></div>
      <div class="cart-items" id="cartItems">
        <div style="color:var(--muted);text-align:center;padding:20px">Cart is empty</div>
      </div>

      <!-- Customer -->
      <div style="border-top:1px solid var(--border);padding-top:10px;margin-top:10px">
        <div style="display:flex;gap:6px;margin-bottom:6px">
          <input type="text" id="custPhone" class="form-control" placeholder="Customer phone…" style="flex:1">
          <input type="text" id="custName"  class="form-control" placeholder="Name" style="flex:1">
        </div>
        <div id="pointsRow" style="display:none">
          <label class="form-label">Points: <strong id="custPoints">0</strong> &nbsp;Redeem:</label>
          <input type="number" id="redeemPoints" class="form-control" min="0" value="0" oninput="calcTotals()">
        </div>
      </div>

      <!-- Discount / VAT -->
      <div style="border-top:1px solid var(--border);padding-top:10px;margin-top:8px" id="discVatPanel">
        <div style="display:flex;gap:6px;margin-bottom:6px;flex-wrap:wrap">
          <div style="flex:1;min-width:120px">
            <label class="form-label">Discount</label>
            <div style="display:flex;gap:4px">
              <select id="discType" class="form-control" style="max-width:70px" onchange="calcTotals()">
                <option value="percent">%</option>
                <option value="amount">$</option>
              </select>
              <input type="number" id="discValue" class="form-control" value="0" min="0" step="0.01" oninput="calcTotals()">
            </div>
          </div>
          <div style="flex:1;min-width:120px">
            <label class="form-label">VAT</label>
            <div style="display:flex;gap:4px">
              <select id="vatType" class="form-control" style="max-width:70px" onchange="calcTotals()">
                <option value="percent">%</option>
                <option value="amount">$</option>
              </select>
              <input type="number" id="vatValue" class="form-control" value="0" min="0" step="0.01" oninput="calcTotals()">
            </div>
          </div>
        </div>
      </div>

      <!-- Payment methods (multi-select) -->
      <div style="border-top:1px solid var(--border);padding-top:10px;margin-top:8px">
        <label class="form-label">Payment Methods (select all that apply)</label>
        <div class="payment-checks" id="payMethods">
          <div class="pay-opt selected" data-pay="cash"     onclick="togglePay(this)">💵 Cash</div>
          <div class="pay-opt"          data-pay="card"     onclick="togglePay(this)">💳 Card</div>
          <div class="pay-opt"          data-pay="transfer" onclick="togglePay(this)">🏦 Transfer</div>
        </div>
        <div id="splitPanel" style="display:none">
          <!-- Split amounts shown dynamically -->
        </div>
      </div>

      <!-- Summary -->
      <div style="border-top:1px solid var(--border);padding-top:10px;margin-top:8px">
        <div class="summary-row"><span>Subtotal</span><span id="sumSubtotal">$0.00</span></div>
        <div class="summary-row"><span>Discount</span><span id="sumDiscount">$0.00</span></div>
        <div class="summary-row"><span>Points</span><span id="sumPoints">$0.00</span></div>
        <div class="summary-row"><span>VAT</span><span id="sumVat">$0.00</span></div>
        <div class="summary-row summary-total"><span>TOTAL</span><span id="sumTotal">$0.00</span></div>
      </div>

      <!-- Action buttons -->
      <div style="display:flex;gap:8px;margin-top:10px">
        <button class="btn btn-warning" style="flex:1" onclick="saveDraft()">📋 Draft</button>
        <button class="btn btn-success" style="flex:1" onclick="finalizeSale()">✅ Sell</button>
      </div>
      <button class="btn btn-ghost btn-block" style="margin-top:6px" onclick="clearCart()">✕ Clear</button>
    </div>
  </div>
</div>

<!-- ── Sync Modal ──────────────────────────────────────────── -->
<div class="modal-backdrop" id="syncModal">
  <div class="modal">
    <div class="modal-hdr">⟳ Sync Pending Sales <button class="modal-close" onclick="closeModal('syncModal')">✕</button></div>
    <div class="modal-body">
      <div id="syncStatus" style="margin-bottom:10px"></div>
      <div id="syncList" class="pending-list"></div>
    </div>
    <div class="modal-ftr">
      <button class="btn btn-ghost btn-sm" onclick="closeModal('syncModal')">Close</button>
      <button class="btn btn-primary btn-sm" onclick="syncAll()" id="syncAllBtn">Sync All</button>
    </div>
  </div>
</div>

<!-- ── Drafts Modal ─────────────────────────────────────────── -->
<div class="modal-backdrop" id="draftModal">
  <div class="modal">
    <div class="modal-hdr">📋 Saved Drafts <button class="modal-close" onclick="closeModal('draftModal')">✕</button></div>
    <div class="modal-body">
      <div id="draftList" class="pending-list"></div>
    </div>
    <div class="modal-ftr">
      <button class="btn btn-ghost btn-sm" onclick="closeModal('draftModal')">Close</button>
    </div>
  </div>
</div>

<!-- ── Variant Modal ─────────────────────────────────────────── -->
<div class="modal-backdrop" id="variantModal">
  <div class="modal">
    <div class="modal-hdr">Select Variant <button class="modal-close" onclick="closeModal('variantModal')">✕</button></div>
    <div class="modal-body" id="variantModalBody"></div>
  </div>
</div>

<!-- ── Print Invoice (hidden until print) ─────────────────── -->
<div class="print-invoice" id="printInvoice"></div>

<script>
// ═══════════════════════════════════════════════════════════
// OFFLINE POS — Full client-side implementation
// Data persisted in localStorage
// ═══════════════════════════════════════════════════════════

const STORAGE_KEYS = {
  products:  'offpos_products',
  pending:   'offpos_pending',
  drafts:    'offpos_drafts',
  settings:  'offpos_settings',
  customers: 'offpos_customers',
};

// ── State ───────────────────────────────────────────────────
let products  = JSON.parse(localStorage.getItem(STORAGE_KEYS.products)  || '[]');
let pending   = JSON.parse(localStorage.getItem(STORAGE_KEYS.pending)   || '[]');
let drafts    = JSON.parse(localStorage.getItem(STORAGE_KEYS.drafts)    || '[]');
let settings  = JSON.parse(localStorage.getItem(STORAGE_KEYS.settings)  || '{}');
let customers = JSON.parse(localStorage.getItem(STORAGE_KEYS.customers) || '[]');
let cart      = [];
let currentCustomer = null;

// ── Network status ──────────────────────────────────────────
function updateNetStatus() {
  const online = navigator.onLine;
  const badge  = document.getElementById('statusBadge');
  const text   = document.getElementById('statusText');
  badge.classList.toggle('online', online);
  text.textContent = online ? 'Online' : 'Offline';
  if (online && pending.length > 0) {
    showFlash('info', `You are online! ${pending.length} sale(s) pending sync.`);
  }
}
window.addEventListener('online',  () => { updateNetStatus(); syncAll(); });
window.addEventListener('offline', updateNetStatus);
updateNetStatus();

// ── Load product catalog from server (when online) ──────────
async function refreshProducts() {
  if (!navigator.onLine) { renderProducts(); return; }
  try {
    const r = await fetch('index.php?page=offline_data&action=catalog');
    if (r.ok) {
      const data = await r.json();
      products  = data.products  || products;
      customers = data.customers || customers;
      settings  = data.settings  || settings;
      localStorage.setItem(STORAGE_KEYS.products,  JSON.stringify(products));
      localStorage.setItem(STORAGE_KEYS.customers, JSON.stringify(customers));
      localStorage.setItem(STORAGE_KEYS.settings,  JSON.stringify(settings));
    }
  } catch(e) {}
  renderProducts();
  applySettings();
}

// ── Apply settings to UI ────────────────────────────────────
function applySettings() {
  const disc = settings.discount_enabled == '1';
  const vat  = settings.vat_enabled      == '1';
  document.getElementById('discVatPanel').style.display = (disc || vat) ? '' : 'none';
  if (settings.vat_default) document.getElementById('vatValue').value = settings.vat_default;
  if (settings.discount_default) document.getElementById('discValue').value = settings.discount_default;
}

// ── Render product grid ──────────────────────────────────────
function renderProducts() {
  const q   = document.getElementById('productSearch').value.toLowerCase();
  const grid = document.getElementById('productGrid');
  const filtered = products.filter(p =>
    (!q || p.name.toLowerCase().includes(q)) && p.total_stock > 0
  );
  if (!filtered.length) {
    grid.innerHTML = '<div style="color:var(--muted);text-align:center;padding:20px;grid-column:1/-1">No products found.</div>';
    return;
  }
  grid.innerHTML = filtered.map(p => `
    <div class="tile" onclick="addToCart(${p.id})">
      <div class="tile-name">${esc(p.name)}</div>
      <div class="tile-price">$${fmtNum(p.min_price)}</div>
      <div class="tile-stock">Stock: ${p.total_stock}</div>
    </div>
  `).join('');
}

function filterProducts() { renderProducts(); }

function lookupBarcode(code) {
  if (!code) return;
  const allVariants = products.flatMap(p => (p.variants||[]).map(v => ({...v, pname: p.name})));
  const v = allVariants.find(v => v.barcode === code);
  if (v) addVariantToCart(v);
  else showFlash('error', 'Barcode not found: ' + code);
}

// ── Cart ────────────────────────────────────────────────────
function addToCart(productId) {
  const p = products.find(x => x.id == productId);
  if (!p) return;
  const variants = (p.variants || []).filter(v => v.quantity > 0);
  if (variants.length === 0) return;
  if (variants.length === 1) { addVariantToCart({...variants[0], pname: p.name}); return; }
  showVariantPicker(p, variants);
}

function showVariantPicker(p, variants) {
  document.getElementById('variantModalBody').innerHTML = variants.map(v => `
    <button class="btn btn-ghost btn-block" style="justify-content:space-between;margin-bottom:6px"
            onclick="addVariantToCart({variant_id:${v.id},pname:'${esc(p.name)}',name:'${esc(p.name)}',size:'${esc(v.size||'')}',color:'${esc(v.color||'')}',price:${v.price},quantity:${v.quantity},barcode:'${esc(v.barcode||'')}'}); closeModal('variantModal')">
      <span>${esc(v.size||'—')} / ${esc(v.color||'—')}</span>
      <strong>$${fmtNum(v.price)} &nbsp; Qty: ${v.quantity}</strong>
    </button>
  `).join('');
  openModal('variantModal');
}

function addVariantToCart(v) {
  const existing = cart.find(i => i.variant_id == v.variant_id || (i.variant_id == v.id));
  const vid = v.variant_id || v.id;
  const maxQty = v.quantity;
  if (existing) {
    if (existing.qty < maxQty) existing.qty++;
  } else {
    cart.push({ variant_id: vid, name: v.pname || v.name, size: v.size||'', color: v.color||'',
                price: parseFloat(v.price), quantity: maxQty, qty: 1 });
  }
  renderCart();
}

function updateQty(vid, qty) {
  qty = parseInt(qty);
  const item = cart.find(i => i.variant_id == vid);
  if (!item) return;
  if (qty <= 0) { cart = cart.filter(i => i.variant_id != vid); }
  else { item.qty = Math.min(qty, item.quantity); }
  renderCart();
}

function clearCart() {
  cart = [];
  currentCustomer = null;
  document.getElementById('custPhone').value = '';
  document.getElementById('custName').value  = '';
  document.getElementById('redeemPoints').value = 0;
  document.getElementById('pointsRow').style.display = 'none';
  renderCart();
}

function renderCart() {
  const el = document.getElementById('cartItems');
  document.getElementById('cartCount').textContent = cart.reduce((s,i) => s+i.qty, 0);
  if (!cart.length) {
    el.innerHTML = '<div style="color:var(--muted);text-align:center;padding:20px">Cart is empty</div>';
    calcTotals(); return;
  }
  el.innerHTML = cart.map(item => `
    <div class="cart-item">
      <div class="item-name">
        ${esc(item.name)}
        <div style="font-size:.72rem;color:var(--muted)">${esc(item.size)} ${esc(item.color)}</div>
      </div>
      <button class="qty-btn" onclick="updateQty(${item.variant_id}, ${item.qty-1})">−</button>
      <input class="qty-num" type="number" value="${item.qty}" min="1" max="${item.quantity}"
             onchange="updateQty(${item.variant_id}, this.value)" style="width:32px">
      <button class="qty-btn" onclick="updateQty(${item.variant_id}, ${item.qty+1})">+</button>
      <div class="item-total">$${fmtNum(item.price*item.qty)}</div>
      <button class="item-del" onclick="updateQty(${item.variant_id},0)">✕</button>
    </div>
  `).join('');
  calcTotals();
}

// ── Calculations ────────────────────────────────────────────
function calcTotals() {
  const sub       = cart.reduce((s,i) => s + i.price * i.qty, 0);
  const discType  = document.getElementById('discType').value;
  const discVal   = parseFloat(document.getElementById('discValue').value) || 0;
  const vatType   = document.getElementById('vatType').value;
  const vatVal    = parseFloat(document.getElementById('vatValue').value) || 0;
  const ptUsed    = parseInt(document.getElementById('redeemPoints').value) || 0;
  const ptRate    = parseFloat(settings.points_redeem_rate || 0.01);
  const ptValue   = ptUsed * ptRate;

  const discAmt   = discType === 'percent' ? sub * (discVal/100) : Math.min(discVal, sub);
  const afterDisc = sub - discAmt - ptValue;
  const vatAmt    = vatType === 'percent' ? afterDisc * (vatVal/100) : vatVal;
  const total     = Math.max(0, afterDisc + vatAmt);

  document.getElementById('sumSubtotal').textContent = '$' + fmtNum(sub);
  document.getElementById('sumDiscount').textContent = '$' + fmtNum(discAmt);
  document.getElementById('sumPoints').textContent   = '$' + fmtNum(ptValue);
  document.getElementById('sumVat').textContent      = '$' + fmtNum(vatAmt);
  document.getElementById('sumTotal').textContent    = '$' + fmtNum(total);
}

// ── Payment methods ──────────────────────────────────────────
function togglePay(el) { el.classList.toggle('selected'); }
function getSelectedPayments() {
  return [...document.querySelectorAll('.pay-opt.selected')].map(el => el.dataset.pay);
}

// ── Customer lookup ──────────────────────────────────────────
document.getElementById('custPhone').addEventListener('blur', function() {
  const phone = this.value.trim();
  currentCustomer = customers.find(c => c.phone === phone) || null;
  if (currentCustomer) {
    document.getElementById('custName').value = currentCustomer.name;
    document.getElementById('custPoints').textContent = currentCustomer.points || 0;
    document.getElementById('pointsRow').style.display = settings.points_enabled == '1' ? '' : 'none';
  }
});

// ── Build sale object ────────────────────────────────────────
function buildSale(status) {
  const sub      = cart.reduce((s,i) => s + i.price * i.qty, 0);
  const discType = document.getElementById('discType').value;
  const discVal  = parseFloat(document.getElementById('discValue').value) || 0;
  const vatType  = document.getElementById('vatType').value;
  const vatVal   = parseFloat(document.getElementById('vatValue').value) || 0;
  const ptUsed   = parseInt(document.getElementById('redeemPoints').value) || 0;
  const ptRate   = parseFloat(settings.points_redeem_rate || 0.01);
  const ptValue  = ptUsed * ptRate;
  const discAmt  = discType === 'percent' ? sub * (discVal/100) : Math.min(discVal, sub);
  const afterD   = sub - discAmt - ptValue;
  const vatAmt   = vatType === 'percent' ? afterD * (vatVal/100) : vatVal;
  const total    = Math.max(0, afterD + vatAmt);

  return {
    id:              'OFF-' + Date.now(),
    created_at:      new Date().toISOString(),
    status:          status,
    customer_name:   document.getElementById('custName').value  || 'Walk-in',
    customer_phone:  document.getElementById('custPhone').value || '',
    customer_id:     currentCustomer?.id || null,
    items:           JSON.parse(JSON.stringify(cart)),
    subtotal:        sub,
    discount_type:   discType,
    discount_val:    discVal,
    discount_amount: discAmt,
    points_used:     ptUsed,
    points_value:    ptValue,
    vat_type:        vatType,
    vat_val:         vatVal,
    vat_amount:      vatAmt,
    total:           total,
    payment_methods: getSelectedPayments(),
    synced:          false,
  };
}

// ── Save draft ───────────────────────────────────────────────
function saveDraft() {
  if (!cart.length) { showFlash('error', 'Cart is empty.'); return; }
  const sale = buildSale('draft');
  drafts.push(sale);
  localStorage.setItem(STORAGE_KEYS.drafts, JSON.stringify(drafts));
  showFlash('success', 'Draft saved!');
  clearCart();
  renderDraftList();
}

// ── Finalize sale ────────────────────────────────────────────
function finalizeSale() {
  if (!cart.length) { showFlash('error', 'Cart is empty.'); return; }
  if (!getSelectedPayments().length) { showFlash('error', 'Select a payment method.'); return; }
  const sale = buildSale('completed');
  pending.push(sale);
  localStorage.setItem(STORAGE_KEYS.pending, JSON.stringify(pending));
  document.getElementById('pendingCount').textContent = pending.length;
  buildPrintInvoice(sale);
  showFlash('success', 'Sale saved! Printing...');
  clearCart();
  renderSyncList();
  setTimeout(() => window.print(), 300);
  if (navigator.onLine) setTimeout(syncAll, 800);
}

// ── Print invoice ────────────────────────────────────────────
function buildPrintInvoice(sale) {
  const shop = settings.shop_name || 'POS System';
  const footer = settings.invoice_footer || 'Thank you!';
  const rows = sale.items.map((item,i) => `
    <tr><td>${i+1}</td><td>${esc(item.name)}</td><td>${esc(item.size)}</td><td>${esc(item.color)}</td>
    <td align="right">${item.qty}</td><td align="right">$${fmtNum(item.price)}</td>
    <td align="right">$${fmtNum(item.price*item.qty)}</td></tr>
  `).join('');

  const invoiceCopy = (label) => `
    <div class="inv-copy">
      <div style="display:flex;justify-content:space-between;margin-bottom:5mm">
        <div><div style="font-size:14pt;font-weight:900">${esc(shop)}</div>
          <div style="font-size:8pt;color:#555">${esc(settings.shop_address||'')}</div></div>
        <div style="text-align:right">
          <div style="font-size:7pt;border:1px solid #333;display:inline-block;padding:1px 5px">${label}</div>
          <div style="font-size:8pt"><b>Invoice:</b> ${esc(sale.id)}</div>
          <div style="font-size:8pt"><b>Date:</b> ${new Date(sale.created_at).toLocaleString()}</div>
        </div>
      </div>
      <div style="font-size:8pt;margin-bottom:3mm"><b>Customer:</b> ${esc(sale.customer_name)} ${sale.customer_phone ? '| '+esc(sale.customer_phone) : ''}</div>
      <table style="width:100%;border-collapse:collapse;font-size:8pt">
        <thead><tr style="background:#f0f0f0">
          <th style="padding:3px 5px;border:1px solid #ccc">#</th>
          <th style="padding:3px 5px;border:1px solid #ccc">Item</th>
          <th style="padding:3px 5px;border:1px solid #ccc">Size</th>
          <th style="padding:3px 5px;border:1px solid #ccc">Color</th>
          <th style="padding:3px 5px;border:1px solid #ccc" align="right">Qty</th>
          <th style="padding:3px 5px;border:1px solid #ccc" align="right">Price</th>
          <th style="padding:3px 5px;border:1px solid #ccc" align="right">Total</th>
        </tr></thead>
        <tbody>${rows}</tbody>
      </table>
      <div style="text-align:right;margin-top:3mm;font-size:8pt">
        <div>Subtotal: $${fmtNum(sale.subtotal)}</div>
        ${sale.discount_amount>0?`<div>Discount: -$${fmtNum(sale.discount_amount)}</div>`:''}
        ${sale.points_value>0?`<div>Points: -$${fmtNum(sale.points_value)}</div>`:''}
        ${sale.vat_amount>0?`<div>VAT: $${fmtNum(sale.vat_amount)}</div>`:''}
        <div style="font-size:12pt;font-weight:900;margin-top:2mm">TOTAL: $${fmtNum(sale.total)}</div>
        <div style="font-size:8pt">Payment: ${sale.payment_methods.join(', ')}</div>
      </div>
      <div style="text-align:center;margin-top:4mm;font-size:8pt;color:#555">
        <div>⚠️ OFFLINE SALE — Will sync when online</div>
        <div>${footer}</div>
      </div>
    </div>`;

  document.getElementById('printInvoice').innerHTML =
    `<div class="invoice-wrap">${invoiceCopy('Customer Copy')}${invoiceCopy('Showroom Copy')}</div>`;
}

// ── Sync to server ───────────────────────────────────────────
async function syncAll() {
  if (!navigator.onLine || !pending.length) {
    document.getElementById('syncStatus').innerHTML =
      `<div class="flash flash-${pending.length?'error':'success'}">${pending.length?'No internet connection.':'Nothing to sync ✅'}</div>`;
    renderSyncList(); return;
  }
  const btn = document.getElementById('syncAllBtn');
  btn.disabled = true; btn.textContent = 'Syncing…';

  let synced = 0, failed = 0;
  const remaining = [];

  for (const sale of pending) {
    try {
      const resp = await fetch('index.php?page=offline_data&action=sync_sale', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(sale),
      });
      const result = await resp.json();
      if (result.success) { synced++; }
      else { remaining.push(sale); failed++; }
    } catch (e) { remaining.push(sale); failed++; }
  }

  pending = remaining;
  localStorage.setItem(STORAGE_KEYS.pending, JSON.stringify(pending));
  document.getElementById('pendingCount').textContent = pending.length;

  const msg = synced ? `✅ ${synced} sale(s) synced.` : '';
  const err = failed ? ` ❌ ${failed} failed.` : '';
  document.getElementById('syncStatus').innerHTML =
    `<div class="flash flash-${failed?'error':'success'}">${msg}${err}</div>`;

  btn.disabled = false; btn.textContent = 'Sync All';
  renderSyncList();
}

// ── Render sync/draft lists ──────────────────────────────────
function renderSyncList() {
  const el = document.getElementById('syncList');
  if (!pending.length) { el.innerHTML = '<div style="color:var(--muted);text-align:center;padding:16px">No pending sales.</div>'; return; }
  el.innerHTML = `<table>
    <thead><tr><th>ID</th><th>Customer</th><th>Items</th><th>Total</th><th></th></tr></thead>
    <tbody>${pending.map((s,i) => `
      <tr>
        <td style="font-size:.75rem">${s.id}</td>
        <td>${esc(s.customer_name)}</td>
        <td>${s.items.length}</td>
        <td>$${fmtNum(s.total)}</td>
        <td><button class="btn btn-danger btn-sm" onclick="deletePending(${i})">✕</button></td>
      </tr>`).join('')}
    </tbody></table>`;
}

function renderDraftList() {
  const el = document.getElementById('draftList');
  if (!drafts.length) { el.innerHTML = '<div style="color:var(--muted);text-align:center;padding:16px">No drafts.</div>'; return; }
  el.innerHTML = `<table>
    <thead><tr><th>ID</th><th>Customer</th><th>Total</th><th></th></tr></thead>
    <tbody>${drafts.map((s,i) => `
      <tr>
        <td style="font-size:.75rem">${s.id}</td>
        <td>${esc(s.customer_name)}</td>
        <td>$${fmtNum(s.total)}</td>
        <td>
          <button class="btn btn-ghost btn-sm" onclick="loadDraft(${i})">Load</button>
          <button class="btn btn-success btn-sm" onclick="pushDraftToSale(${i})">Sell</button>
          <button class="btn btn-danger btn-sm"  onclick="deleteDraft(${i})">✕</button>
        </td>
      </tr>`).join('')}
    </tbody></table>`;
}

function loadDraft(i) {
  const d = drafts[i];
  cart = JSON.parse(JSON.stringify(d.items));
  document.getElementById('custName').value    = d.customer_name || '';
  document.getElementById('custPhone').value   = d.customer_phone || '';
  document.getElementById('discValue').value   = d.discount_val  || 0;
  document.getElementById('discType').value    = d.discount_type || 'percent';
  document.getElementById('vatValue').value    = d.vat_val       || 0;
  document.getElementById('redeemPoints').value= d.points_used   || 0;
  renderCart(); closeModal('draftModal');
}

function pushDraftToSale(i) {
  loadDraft(i);
  deleteDraft(i);
  setTimeout(finalizeSale, 100);
}

function deleteDraft(i) {
  drafts.splice(i, 1);
  localStorage.setItem(STORAGE_KEYS.drafts, JSON.stringify(drafts));
  renderDraftList();
}

function deletePending(i) {
  if (!confirm('Remove this pending sale? It will NOT be synced.')) return;
  pending.splice(i, 1);
  localStorage.setItem(STORAGE_KEYS.pending, JSON.stringify(pending));
  document.getElementById('pendingCount').textContent = pending.length;
  renderSyncList();
}

// ── Helpers ──────────────────────────────────────────────────
function fmtNum(n) { return parseFloat(n||0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g,','); }
function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function openModal(id)  { document.getElementById(id)?.classList.add('open'); }
function closeModal(id) { document.getElementById(id)?.classList.remove('open'); }
function showFlash(type, msg) {
  const div = document.createElement('div');
  div.className = `flash flash-${type==='info'?'success':type}`;
  div.textContent = msg;
  document.getElementById('flashArea').appendChild(div);
  setTimeout(() => { div.style.opacity='0'; div.style.transition='opacity .4s'; setTimeout(()=>div.remove(),400); }, 3500);
}

// ── Init ────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('pendingCount').textContent = pending.length;
  refreshProducts();
  renderSyncList();
  renderDraftList();

  // Open sync modal auto if coming online
  document.addEventListener('click', e => {
    if (e.target.classList.contains('modal-backdrop')) closeModal(e.target.id);
  });
});
</script>
</body>
</html>
