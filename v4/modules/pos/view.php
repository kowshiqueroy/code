<!-- modules/pos/view.php — POS Selling Interface HTML -->
<?php
if (!defined('APP_ROOT')) {
    // Standalone fetch: include config
    require_once dirname(__FILE__, 3) . '/config/config.php';
    session_start_secure();
    require_login();
}
?>
<div class="pos-wrapper">
    <!-- ── Left: Product Catalog ── -->
    <div class="pos-left">
        <!-- Toolbar -->
        <div class="pos-toolbar">
            <div class="pos-search-wrap">
                <span class="pos-search-icon">🔍</span>
                <input type="search"
                       id="product-search"
                       class="form-input"
                       placeholder="Search product or scan barcode… (Ctrl+K)"
                       autocomplete="off"
                       spellcheck="false"
                       aria-label="Search products">
            </div>
            <button class="btn btn-ghost btn-sm" id="btn-refresh-products" title="Refresh product list">↻</button>
        </div>

        <!-- Category Tabs -->
        <div id="category-tabs" role="tablist" aria-label="Product categories">
            <!-- Populated by JS -->
        </div>

        <!-- Product Grid -->
        <div id="product-grid" role="list" aria-label="Products">
            <div style="grid-column:1/-1;text-align:center;padding:60px;color:var(--c-text-faint)">
                Loading products…
            </div>
        </div>
    </div>

    <!-- ── Right: Cart / Checkout ── -->
    <div class="pos-right">
        <!-- Cart Header -->
        <div class="cart-header">
            <span class="cart-title">🛒 Cart</span>
            <button class="cart-clear-btn" onclick="POS.clearCart()" aria-label="Clear cart">Clear all</button>
        </div>

        <!-- Customer -->
        <div class="cart-customer">
            <span class="customer-icon">👤</span>
            <div class="customer-info">
                <span class="customer-name-display" id="customer-name-display">Walk-in Customer</span>
                <span class="loyalty-pts" id="loyalty-points-display"></span>
            </div>
            <button class="customer-search-btn" onclick="Utils.openModal('customer-modal')" aria-label="Find customer">
                + Customer
            </button>
        </div>

        <!-- Cart Items -->
        <ul id="cart-list" role="list" aria-label="Cart items" aria-live="polite"></ul>
        <div id="cart-empty" aria-hidden="true">
            <span id="cart-empty-icon">🛒</span>
            <span>Cart is empty.<br>Tap a product to add it.</span>
        </div>

        <!-- VAT Toggle -->
        <div class="vat-toggle-row">
            <span style="font-size:13px;color:var(--c-text-muted)">Include VAT</span>
            <label class="toggle-switch" aria-label="VAT toggle">
                <input type="checkbox" id="vat-toggle" checked>
                <span class="toggle-slider"></span>
            </label>
        </div>

        <!-- Loyalty Redeem -->
        <div class="cart-totals" style="padding-top:8px;padding-bottom:6px">
            <div class="loyalty-row">
                <span>Redeem Loyalty Points:</span>
                <input type="number" id="loyalty-redeem" class="loyalty-input" min="0" step="100" value="0" placeholder="0">
                <span>pts</span>
            </div>
        </div>

        <!-- Totals -->
        <div class="cart-totals">
            <div class="totals-row">
                <span class="label">Subtotal</span>
                <span class="value" id="cart-subtotal">৳0.00</span>
            </div>
            <div class="totals-row">
                <span class="label">Discount</span>
                <span class="value" id="cart-discount">—</span>
            </div>
            <div class="totals-row">
                <span class="label">VAT</span>
                <span class="value" id="cart-vat">—</span>
            </div>
            <div class="totals-row totals-row--grand">
                <span class="label">TOTAL</span>
                <span class="value" id="cart-total">৳0.00</span>
            </div>
        </div>

        <!-- Payment -->
        <div class="cart-payment">
            <div class="payment-grid">
                <div>
                    <div class="payment-label">Cash</div>
                    <input type="number" id="pay-cash" class="payment-input" placeholder="0.00"
                           min="0" step="0.01" aria-label="Cash payment">
                </div>
                <div>
                    <div class="payment-label">Card</div>
                    <input type="number" id="pay-card" class="payment-input" placeholder="0.00"
                           min="0" step="0.01" aria-label="Card payment">
                </div>
            </div>
            <div class="change-row">
                <span>Change Due</span>
                <span class="change-val" id="pay-change">—</span>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="cart-actions">
            <button class="btn btn-ghost btn-sm" id="btn-park-sale" title="Park this sale (Ctrl+D)">
                ⏸ Park
            </button>
            <button class="btn btn-ghost btn-sm" id="btn-recall-draft" title="Recall a parked sale (Ctrl+R)">
                📋 Recall
            </button>
            <button class="btn btn-secondary btn-sm" id="btn-print-a4" title="Print A4 invoice (Ctrl+P)" aria-label="Print A4">
                🖨 A4
            </button>
            <button class="btn-checkout" id="btn-checkout" aria-label="Checkout (Ctrl+Enter)">
                ✅ Checkout <kbd style="font-size:11px;opacity:0.7">Ctrl+↵</kbd>
            </button>
            <button class="btn btn-secondary btn-sm" id="btn-print-thermal" title="Print thermal (Ctrl+Shift+P)" aria-label="Print thermal" style="grid-column:3">
                🖨 Thermal
            </button>
        </div>
    </div>
</div>

<script data-init>
// Initialize POS with settings from server
POS.init(window.POS_CONFIG?.settings || {});

// Refresh products button
document.getElementById('btn-refresh-products')?.addEventListener('click', async () => {
    await POS.state;
    location.reload();
});
</script>
