// ============================================================
// pos.js — POS Selling Interface (SPA Core)
// Handles: product grid, cart, payments, invoices, drafts
// ============================================================
'use strict';

const POS = (() => {
    // ── State ─────────────────────────────────────────────
    let state = {
        products: [],           // full product list
        filteredProducts: [],   // after search
        cart: [],               // { id, variant_id, name, variant, price, qty, discount, vat }
        customer: null,         // { id, name, phone, loyalty_points }
        loyaltyRedeemed: 0,     // points to redeem
        vatEnabled: true,
        activeCategory: 'all',
        barcodeBuffer: '',
        barcodeTimer: null,
        draftId: null,          // if recalling a draft
        settings: {},
    };

    // ── Cache DOM refs ─────────────────────────────────────
    const dom = {};

    function cacheDom() {
        dom.productGrid    = Utils.$('#product-grid');
        dom.cartList       = Utils.$('#cart-list');
        dom.cartEmpty      = Utils.$('#cart-empty');
        dom.searchInput    = Utils.$('#product-search');
        dom.categoryTabs   = Utils.$('#category-tabs');
        dom.subtotal       = Utils.$('#cart-subtotal');
        dom.discountRow    = Utils.$('#cart-discount');
        dom.vatRow         = Utils.$('#cart-vat');
        dom.totalEl        = Utils.$('#cart-total');
        dom.cashInput      = Utils.$('#pay-cash');
        dom.cardInput      = Utils.$('#pay-card');
        dom.changeEl       = Utils.$('#pay-change');
        dom.customerName   = Utils.$('#customer-name-display');
        dom.loyaltyPts     = Utils.$('#loyalty-points-display');
        dom.redeemInput    = Utils.$('#loyalty-redeem');
        dom.vatToggle      = Utils.$('#vat-toggle');
        dom.checkoutBtn    = Utils.$('#btn-checkout');
        dom.printA4Btn     = Utils.$('#btn-print-a4');
        dom.printThermalBtn= Utils.$('#btn-print-thermal');
        dom.parkBtn        = Utils.$('#btn-park-sale');
        dom.recallBtn      = Utils.$('#btn-recall-draft');
        dom.lastInvoice    = null; // set after checkout
    }

    // ── Load Products ─────────────────────────────────────
    async function loadProducts() {
        try {
            const data = await Utils.apiFetch('/pos/api/products/list.php');
            state.products = data.products || [];
            state.filteredProducts = [...state.products];

            // Cache in IndexedDB for offline use
            await PosDB.products.cacheAll(state.products);

            renderCategoryTabs();
            renderProductGrid();
        } catch (err) {
            // Fallback: use IndexedDB cache
            console.warn('[POS] Online load failed, using cache:', err);
            state.products = await PosDB.products.search('');
            state.filteredProducts = [...state.products];
            renderCategoryTabs();
            renderProductGrid();
        }
    }

    // ── Category Tabs ─────────────────────────────────────
    function renderCategoryTabs() {
        if (!dom.categoryTabs) return;
        const cats = ['all', ...new Set(state.products.map(p => p.category_name).filter(Boolean))];
        dom.categoryTabs.innerHTML = cats.map(c => `
            <button class="cat-tab ${c === state.activeCategory ? 'cat-tab--active' : ''}"
                    data-cat="${Utils.h(c)}">
                ${c === 'all' ? '🏷️ All' : Utils.h(c)}
            </button>
        `).join('');

        dom.categoryTabs.addEventListener('click', e => {
            const btn = e.target.closest('.cat-tab');
            if (!btn) return;
            state.activeCategory = btn.dataset.cat;
            Utils.$$('.cat-tab', dom.categoryTabs).forEach(b =>
                b.classList.toggle('cat-tab--active', b === btn)
            );
            filterProducts();
        });
    }

    // ── Filter Products ───────────────────────────────────
    function filterProducts() {
        const q = (dom.searchInput?.value || '').toLowerCase().trim();
        state.filteredProducts = state.products.filter(p => {
            const matchCat = state.activeCategory === 'all' || p.category_name === state.activeCategory;
            const matchQ   = !q || p.name.toLowerCase().includes(q) || (p.barcode || '').includes(q);
            return matchCat && matchQ;
        });
        renderProductGrid();
    }

    // ── Product Grid ──────────────────────────────────────
    function renderProductGrid() {
        if (!dom.productGrid) return;
        if (state.filteredProducts.length === 0) {
            dom.productGrid.innerHTML = '<p class="pos-empty">No products found.</p>';
            return;
        }

        dom.productGrid.innerHTML = state.filteredProducts.map(p => {
            const price = Utils.formatCurrency(p.display_price || p.base_price);
            const hasDiscount = p.discount_pct > 0;
            const origPrice = hasDiscount
                ? `<span class="product-orig">${Utils.formatCurrency(p.base_price)}</span>` : '';
            const badge = hasDiscount
                ? `<span class="discount-badge">-${p.discount_pct}%</span>` : '';
            const lowStock = p.total_stock <= 5 && p.total_stock > 0
                ? '<span class="low-stock-badge">Low</span>' : '';
            const outStock = p.total_stock === 0
                ? 'pos-product--outofstock' : '';
            const imgEl = p.image_url
                ? `<img src="${Utils.h(p.image_url)}" alt="${Utils.h(p.name)}" loading="lazy">`
                : `<div class="product-icon">${p.category_icon || '📦'}</div>`;

            return `
            <article class="pos-product ${outStock}" data-id="${p.id}" role="button" tabindex="0"
                     aria-label="${Utils.h(p.name)} ${price}" data-barcode="${Utils.h(p.barcode || '')}">
                <div class="product-img">${imgEl}${badge}${lowStock}</div>
                <div class="product-info">
                    <h4 class="product-name">${Utils.h(p.name)}</h4>
                    <p class="product-price">${price}${origPrice}</p>
                    <p class="product-stock">Stock: ${p.total_stock ?? '—'}</p>
                </div>
            </article>`;
        }).join('');

        // Click / Enter to add
        dom.productGrid.addEventListener('click', handleProductClick);
        dom.productGrid.addEventListener('keydown', e => {
            if (e.key === 'Enter' || e.key === ' ') handleProductClick(e);
        });
    }

    function handleProductClick(e) {
        const card = e.target.closest('.pos-product');
        if (!card || card.classList.contains('pos-product--outofstock')) return;
        const product = state.products.find(p => p.id === parseInt(card.dataset.id));
        if (!product) return;

        // If product has variants, show variant modal
        if (product.variants && product.variants.length > 1) {
            openVariantModal(product);
        } else {
            addToCart(product, product.variants?.[0] || null);
        }
    }

    // ── Variant Modal ─────────────────────────────────────
    function openVariantModal(product) {
        const modal = Utils.$('#variant-modal');
        if (!modal) return;
        Utils.$('#variant-modal-title', modal).textContent = product.name;
        const list = Utils.$('#variant-list', modal);
        list.innerHTML = product.variants.map(v => `
            <button class="variant-btn" data-variant-id="${v.id}">
                <span class="variant-label">${Utils.h(v.variant_label)}</span>
                <span class="variant-price">${Utils.formatCurrency(v.price || product.base_price)}</span>
                <span class="variant-stock">Qty: ${v.stock_qty}</span>
            </button>
        `).join('');

        list.onclick = e => {
            const btn = e.target.closest('.variant-btn');
            if (!btn) return;
            const variant = product.variants.find(v => v.id === parseInt(btn.dataset.variantId));
            addToCart(product, variant);
            Utils.closeModal('variant-modal');
        };
        Utils.openModal('variant-modal');
    }

    // ── Cart Operations ───────────────────────────────────
    function addToCart(product, variant = null) {
        const price = variant?.price ?? parseFloat(product.display_price || product.base_price);
        const existingIdx = state.cart.findIndex(i =>
            i.product_id === product.id && i.variant_id === (variant?.id || null)
        );

        if (existingIdx >= 0) {
            state.cart[existingIdx].qty++;
        } else {
            state.cart.push({
                _key:       `${product.id}_${variant?.id || 0}`,
                product_id: product.id,
                variant_id: variant?.id || null,
                name:       product.name,
                variant_label: variant?.variant_label || '',
                barcode:    product.barcode,
                price,
                qty:        1,
                discount:   parseFloat(product.discount_amt || 0),
                vat_pct:    parseFloat(product.vat_pct || state.settings.default_vat || 0),
                image_url:  product.image_url,
            });
        }

        renderCart();
        SyncEngine.showToast(`${product.name} added`, 'success', 1200);
        playBeep();
    }

    function removeFromCart(key) {
        state.cart = state.cart.filter(i => i._key !== key);
        renderCart();
    }

    function updateCartQty(key, delta) {
        const item = state.cart.find(i => i._key === key);
        if (!item) return;
        item.qty = Math.max(0.001, item.qty + delta);
        if (item.qty <= 0) { removeFromCart(key); return; }
        renderCart();
    }

    function setCartQty(key, val) {
        const item = state.cart.find(i => i._key === key);
        if (!item) return;
        const qty = parseFloat(val) || 0;
        if (qty <= 0) { removeFromCart(key); return; }
        item.qty = qty;
        renderCart();
    }

    function clearCart() {
        state.cart = [];
        state.customer = null;
        state.loyaltyRedeemed = 0;
        state.draftId = null;
        if (dom.cashInput)  dom.cashInput.value = '';
        if (dom.cardInput)  dom.cardInput.value = '';
        if (dom.redeemInput) dom.redeemInput.value = '';
        renderCart();
        updateCustomerDisplay();
    }

    // ── Cart Rendering ────────────────────────────────────
    function renderCart() {
        if (!dom.cartList) return;

        if (state.cart.length === 0) {
            dom.cartList.innerHTML = '';
            if (dom.cartEmpty) dom.cartEmpty.hidden = false;
            updateTotals();
            return;
        }
        if (dom.cartEmpty) dom.cartEmpty.hidden = true;

        dom.cartList.innerHTML = state.cart.map(item => {
            const lineTotal = Utils.round2((item.price - item.discount) * item.qty);
            return `
            <li class="cart-item" data-key="${Utils.h(item._key)}">
                <div class="cart-item-main">
                    <div class="cart-item-info">
                        <span class="cart-item-name">${Utils.h(item.name)}</span>
                        ${item.variant_label ? `<span class="cart-item-variant">${Utils.h(item.variant_label)}</span>` : ''}
                    </div>
                    <button class="cart-item-remove" aria-label="Remove" data-action="remove">×</button>
                </div>
                <div class="cart-item-controls">
                    <button class="qty-btn" data-action="dec" aria-label="Decrease">−</button>
                    <input class="qty-input" type="number" min="0.001" step="0.001"
                           value="${item.qty}" data-action="qty-input" aria-label="Quantity">
                    <button class="qty-btn" data-action="inc" aria-label="Increase">+</button>
                    <span class="cart-item-price">
                        ${Utils.formatCurrency(item.price)}
                        ${item.discount > 0 ? `<span class="cart-disc">-${Utils.formatCurrency(item.discount)}</span>` : ''}
                    </span>
                    <span class="cart-item-total">${Utils.formatCurrency(lineTotal)}</span>
                </div>
            </li>`;
        }).join('');

        // Event delegation for cart controls
        dom.cartList.onclick = e => {
            const btn = e.target.closest('[data-action]');
            if (!btn) return;
            const li  = btn.closest('.cart-item');
            const key = li?.dataset.key;
            const action = btn.dataset.action;
            if (action === 'remove') removeFromCart(key);
            else if (action === 'dec') updateCartQty(key, -1);
            else if (action === 'inc') updateCartQty(key, 1);
        };
        dom.cartList.oninput = e => {
            if (e.target.dataset.action === 'qty-input') {
                const li = e.target.closest('.cart-item');
                setCartQty(li?.dataset.key, e.target.value);
            }
        };

        updateTotals();
    }

    // ── Totals Calculation ────────────────────────────────
    function calculateTotals() {
        let subtotal = 0, discountAmt = 0, vatAmt = 0;
        state.cart.forEach(item => {
            const lineBase = item.price * item.qty;
            const lineDisc = item.discount * item.qty;
            const lineNet  = lineBase - lineDisc;
            const lineVat  = state.vatEnabled ? Utils.round2(lineNet * (item.vat_pct / 100)) : 0;
            subtotal    += lineBase;
            discountAmt += lineDisc;
            vatAmt      += lineVat;
        });
        const redeemVal = Utils.round2(
            state.loyaltyRedeemed / parseFloat(state.settings.loyalty_redeem || 100)
        );
        const grandTotal = Utils.round2(subtotal - discountAmt + vatAmt - redeemVal);
        return { subtotal, discountAmt, vatAmt, redeemVal, grandTotal };
    }

    function updateTotals() {
        const { subtotal, discountAmt, vatAmt, redeemVal, grandTotal } = calculateTotals();

        if (dom.subtotal)    dom.subtotal.textContent  = Utils.formatCurrency(subtotal);
        if (dom.discountRow) dom.discountRow.textContent= discountAmt > 0 ? `− ${Utils.formatCurrency(discountAmt)}` : '—';
        if (dom.vatRow)      dom.vatRow.textContent    = vatAmt > 0 ? `+ ${Utils.formatCurrency(vatAmt)}` : '—';
        if (dom.totalEl)     dom.totalEl.textContent   = Utils.formatCurrency(grandTotal);

        updateChange();
    }

    function updateChange() {
        const { grandTotal } = calculateTotals();
        const cash = parseFloat(dom.cashInput?.value || 0);
        const card = parseFloat(dom.cardInput?.value || 0);
        const paid = cash + card;
        const change = Utils.round2(paid - grandTotal);
        if (dom.changeEl) {
            dom.changeEl.textContent = change >= 0 ? Utils.formatCurrency(change) : '—';
            dom.changeEl.classList.toggle('change--positive', change > 0);
        }
    }

    // ── Customer CRM ──────────────────────────────────────
    async function lookupCustomer(phone) {
        try {
            const data = await Utils.apiFetch(`/pos/api/customers/search.php?phone=${encodeURIComponent(phone)}`);
            if (data.customer) {
                state.customer = data.customer;
                updateCustomerDisplay();
            }
        } catch {}
    }

    function updateCustomerDisplay() {
        if (dom.customerName) {
            dom.customerName.textContent = state.customer
                ? state.customer.name
                : 'Walk-in Customer';
        }
        if (dom.loyaltyPts) {
            dom.loyaltyPts.textContent = state.customer
                ? `${state.customer.loyalty_points} pts`
                : '—';
        }
    }

    // ── Checkout ──────────────────────────────────────────
    async function processCheckout() {
        if (state.cart.length === 0) {
            SyncEngine.showToast('Cart is empty.', 'warning');
            return;
        }

        const { subtotal, discountAmt, vatAmt, redeemVal, grandTotal } = calculateTotals();
        const cash   = parseFloat(dom.cashInput?.value || 0);
        const card   = parseFloat(dom.cardInput?.value || 0);
        const paid   = cash + card;
        const change = Utils.round2(paid - grandTotal);

        if (paid < grandTotal - 0.01) {
            SyncEngine.showToast('Insufficient payment. Please enter cash/card amount.', 'warning');
            return;
        }

        const saleData = {
            local_id:          PosDB.uuid(),
            invoice_no:        `INV-${Date.now()}`,    // server will overwrite if online
            customer:          state.customer,
            cart:              state.cart,
            subtotal, discountAmt, vatAmt,
            loyalty_redeemed:  state.loyaltyRedeemed,
            loyalty_redeemed_val: redeemVal,
            grand_total:       grandTotal,
            payment_cash:      cash,
            payment_card:      card,
            payment_other:     0,
            change_amt:        change,
            vat_enabled:       state.vatEnabled,
            sale_date:         Utils.nowISO(),
        };

        if (SyncEngine.isOnline()) {
            try {
                const result = await Utils.apiFetch('/pos/api/sales/create.php', {
                    method: 'POST',
                    body: JSON.stringify(saleData),
                });
                saleData.invoice_no     = result.invoice_no;
                saleData.loyalty_earned = result.loyalty_earned;
                state.lastInvoice = { ...saleData, ...result };
                showCheckoutSuccess(state.lastInvoice);
            } catch (err) {
                // Fallback to offline queue
                await saveOfflineSale(saleData);
            }
        } else {
            await saveOfflineSale(saleData);
        }
    }

    async function saveOfflineSale(saleData) {
        await PosDB.sales.save(saleData);
        await SyncEngine.updatePendingBadge();
        state.lastInvoice = saleData;
        showCheckoutSuccess(saleData, true);
    }

    function showCheckoutSuccess(sale, isOffline = false) {
        const modal = Utils.$('#checkout-success-modal');
        if (modal) {
            Utils.$('#success-invoice-no', modal).textContent = sale.invoice_no;
            Utils.$('#success-total',      modal).textContent = Utils.formatCurrency(sale.grand_total);
            Utils.$('#success-change',     modal).textContent = Utils.formatCurrency(sale.change_amt || 0);
            if (isOffline) {
                Utils.$('#success-offline-note', modal).hidden = false;
            }
            Utils.openModal('checkout-success-modal');
        }
        if (!isOffline) clearCart();
    }

    // ── Invoice Printing ──────────────────────────────────
    function buildInvoiceHTML(sale, format = 'a4') {
        const settings  = state.settings;
        const dateStr   = Utils.formatDate(new Date(sale.sale_date || Date.now()), 'DD/MM/YYYY HH:mm');
        const qrDataUrl = Utils.generateQRDataURL(
            `${window.location.origin}/pos/verify.php?inv=${encodeURIComponent(sale.invoice_no)}`
        );

        const itemRows = (sale.cart || []).map(item => `
            <tr>
                <td>${Utils.h(item.name)}${item.variant_label ? ` <small>(${Utils.h(item.variant_label)})</small>` : ''}</td>
                <td class="text-right">${Utils.formatCurrency(item.price)}</td>
                <td class="text-center">${item.qty}</td>
                <td class="text-right">${Utils.formatCurrency((item.price - item.discount) * item.qty)}</td>
            </tr>
        `).join('');

        const copyHTML = `
        <div class="invoice-copy">
            <div class="invoice-header">
                ${settings.shop_logo_url ? `<img src="${Utils.h(settings.shop_logo_url)}" class="inv-logo" alt="Logo">` : ''}
                <h2>${Utils.h(settings.shop_name || 'My Shop')}</h2>
                <p>${Utils.h(settings.shop_address || '')}</p>
                <p>${Utils.h(settings.shop_phone || '')}</p>
            </div>
            <div class="invoice-meta">
                <table class="inv-meta-table"><tbody>
                    <tr><th>Invoice #</th><td>${Utils.h(sale.invoice_no)}</td></tr>
                    <tr><th>Date</th><td>${dateStr}</td></tr>
                    <tr><th>Customer</th><td>${Utils.h(sale.customer?.name || 'Walk-in')}</td></tr>
                    <tr><th>Phone</th><td>${Utils.h(sale.customer?.phone || '—')}</td></tr>
                </tbody></table>
            </div>
            <table class="inv-items">
                <thead><tr><th>Item</th><th>Price</th><th>Qty</th><th>Total</th></tr></thead>
                <tbody>${itemRows}</tbody>
            </table>
            <div class="inv-totals">
                <table><tbody>
                    <tr><td>Subtotal</td><td>${Utils.formatCurrency(sale.subtotal)}</td></tr>
                    ${sale.discountAmt > 0 ? `<tr><td>Discount</td><td>− ${Utils.formatCurrency(sale.discountAmt)}</td></tr>` : ''}
                    ${sale.vatAmt > 0 ? `<tr><td>VAT</td><td>+ ${Utils.formatCurrency(sale.vatAmt)}</td></tr>` : ''}
                    ${sale.loyalty_redeemed_val > 0 ? `<tr><td>Loyalty Redemption</td><td>− ${Utils.formatCurrency(sale.loyalty_redeemed_val)}</td></tr>` : ''}
                    <tr class="inv-grand"><td><strong>TOTAL</strong></td><td><strong>${Utils.formatCurrency(sale.grand_total)}</strong></td></tr>
                    <tr><td>Paid (Cash)</td><td>${Utils.formatCurrency(sale.payment_cash)}</td></tr>
                    <tr><td>Paid (Card)</td><td>${Utils.formatCurrency(sale.payment_card)}</td></tr>
                    <tr><td>Change</td><td>${Utils.formatCurrency(sale.change_amt)}</td></tr>
                </tbody></table>
            </div>
            ${sale.loyalty_earned > 0 ? `<p class="inv-loyalty">Loyalty Points Earned: <strong>+${sale.loyalty_earned}</strong></p>` : ''}
            <div class="inv-footer">
                ${qrDataUrl ? `<img src="${qrDataUrl}" class="inv-qr" alt="Verify QR">` : ''}
                <p>${Utils.h(settings.invoice_note || 'Thank you!')}</p>
            </div>
        </div>`;

        if (format === 'a4') {
            // Two copies side by side
            return `<div class="inv-a4-wrap">${copyHTML}${copyHTML.replace('Customer', 'Showroom')}</div>`;
        }
        return copyHTML; // thermal: single copy
    }

    function printInvoice(format = 'a4') {
        if (!state.lastInvoice) {
            SyncEngine.showToast('No recent invoice to print.', 'warning');
            return;
        }
        const html = buildInvoiceHTML(state.lastInvoice, format);
        const win  = window.open('', '_blank', 'width=900,height=700');
        const thermalCss = format === 'thermal'
            ? `body{width:80mm;margin:0;font-size:11px} .inv-logo{width:40mm}`
            : '';
        win.document.write(`<!DOCTYPE html><html><head>
            <meta charset="utf-8"><title>Invoice ${Utils.h(state.lastInvoice.invoice_no)}</title>
            <link rel="stylesheet" href="/pos/assets/css/print.css">
            <style>${thermalCss}</style>
        </head><body>${html}</body></html>`);
        win.document.close();
        win.onload = () => { win.focus(); win.print(); };
    }

    // ── Draft (Park) / Recall ─────────────────────────────
    async function parkSale() {
        if (state.cart.length === 0) {
            SyncEngine.showToast('Nothing to park.', 'warning');
            return;
        }
        const label = `Draft ${Utils.formatDate(new Date(), 'HH:mm')}`;
        if (state.draftId) {
            await PosDB.drafts.update(state.draftId, label, { cart: state.cart, customer: state.customer });
        } else {
            await PosDB.drafts.save(label, { cart: state.cart, customer: state.customer });
        }
        SyncEngine.showToast('Sale parked successfully.', 'success');
        clearCart();
    }

    async function recallDraft() {
        const drafts = await PosDB.drafts.getAll();
        if (drafts.length === 0) {
            SyncEngine.showToast('No parked sales.', 'info');
            return;
        }
        openDraftModal(drafts);
    }

    function openDraftModal(drafts) {
        const modal = Utils.$('#draft-modal');
        if (!modal) return;
        const list = Utils.$('#draft-list', modal);
        list.innerHTML = drafts.map(d => `
            <div class="draft-item" data-id="${d.id}">
                <span class="draft-label">${Utils.h(d.label)}</span>
                <span class="draft-time">${Utils.formatDate(d.updated_at, 'DD/MM HH:mm')}</span>
                <span class="draft-items">${d.cart_data?.cart?.length || 0} items</span>
                <button class="btn-sm btn-primary" data-action="recall">Recall</button>
                <button class="btn-sm btn-danger"  data-action="delete">Delete</button>
            </div>
        `).join('');

        list.onclick = async e => {
            const btn = e.target.closest('[data-action]');
            if (!btn) return;
            const id = parseInt(btn.closest('.draft-item').dataset.id);
            if (btn.dataset.action === 'recall') {
                const draft = await PosDB.drafts.get(id);
                state.cart     = draft.cart_data.cart || [];
                state.customer = draft.cart_data.customer || null;
                state.draftId  = id;
                renderCart();
                updateCustomerDisplay();
                Utils.closeModal('draft-modal');
                SyncEngine.showToast('Draft recalled.', 'success');
            } else if (btn.dataset.action === 'delete') {
                await PosDB.drafts.delete(id);
                btn.closest('.draft-item').remove();
            }
        };
        Utils.openModal('draft-modal');
    }

    // ── Barcode Scanner ───────────────────────────────────
    function initBarcodeScanner() {
        document.addEventListener('keypress', async e => {
            // If focus is on an input (other than search), skip
            const tag = document.activeElement.tagName.toLowerCase();
            if (['input','textarea','select'].includes(tag) &&
                document.activeElement.id !== 'product-search') return;

            clearTimeout(state.barcodeTimer);
            state.barcodeBuffer += e.key;
            state.barcodeTimer = setTimeout(async () => {
                if (state.barcodeBuffer.length >= 6) {
                    const code = state.barcodeBuffer.trim();
                    state.barcodeBuffer = '';

                    // Search product by barcode
                    let product = state.products.find(p => p.barcode === code);
                    if (!product) {
                        // Try IndexedDB
                        product = await PosDB.products.getByBarcode(code);
                    }
                    if (product) {
                        addToCart(product, product.variants?.[0] || null);
                        if (dom.searchInput) dom.searchInput.value = '';
                    } else {
                        SyncEngine.showToast(`Barcode "${code}" not found.`, 'warning');
                    }
                }
                state.barcodeBuffer = '';
            }, 100); // rapid keystrokes = scanner
        });
    }

    // ── Audio Feedback ────────────────────────────────────
    function playBeep() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.frequency.value = 880;
            gain.gain.setValueAtTime(0.3, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.1);
            osc.start(ctx.currentTime);
            osc.stop(ctx.currentTime + 0.1);
        } catch {}
    }

    // ── Keyboard Shortcuts ────────────────────────────────
    function initShortcuts() {
        Utils.registerShortcut('ctrl+enter', processCheckout);
        Utils.registerShortcut('ctrl+p',     () => printInvoice('a4'));
        Utils.registerShortcut('ctrl+shift+p', () => printInvoice('thermal'));
        Utils.registerShortcut('ctrl+d',     parkSale);
        Utils.registerShortcut('ctrl+r',     recallDraft);
        Utils.registerShortcut('escape',     () => {
            // Close any open modal
            Utils.$$('.modal--open').forEach(m => {
                m.hidden = true;
                m.classList.remove('modal--open');
            });
            document.body.classList.remove('modal-open');
        });
        // Focus search
        Utils.registerShortcut('ctrl+k', () => dom.searchInput?.focus());
    }

    // ── Event Bindings ────────────────────────────────────
    function bindEvents() {
        // Search
        dom.searchInput?.addEventListener('input',
            Utils.debounce(() => filterProducts(), 200)
        );

        // VAT toggle
        dom.vatToggle?.addEventListener('change', e => {
            state.vatEnabled = e.target.checked;
            updateTotals();
        });

        // Payment inputs
        dom.cashInput?.addEventListener('input', updateChange);
        dom.cardInput?.addEventListener('input', updateChange);

        // Loyalty redemption
        dom.redeemInput?.addEventListener('input', e => {
            const pts = parseInt(e.target.value) || 0;
            const max = state.customer?.loyalty_points || 0;
            state.loyaltyRedeemed = Math.min(pts, max);
            e.target.value = state.loyaltyRedeemed;
            updateTotals();
        });

        // Customer phone lookup
        const phoneInput = Utils.$('#customer-phone-input');
        phoneInput?.addEventListener('keydown', e => {
            if (e.key === 'Enter') lookupCustomer(e.target.value.trim());
        });

        // Checkout, print, park, recall
        dom.checkoutBtn?.addEventListener('click',      processCheckout);
        dom.printA4Btn?.addEventListener('click',       () => printInvoice('a4'));
        dom.printThermalBtn?.addEventListener('click',  () => printInvoice('thermal'));
        dom.parkBtn?.addEventListener('click',          parkSale);
        dom.recallBtn?.addEventListener('click',        recallDraft);

        // Post-checkout: clear cart
        Utils.$('#success-new-sale-btn')?.addEventListener('click', () => {
            Utils.closeModal('checkout-success-modal');
            clearCart();
        });
        Utils.$('#success-print-a4-btn')?.addEventListener('click', () => printInvoice('a4'));
        Utils.$('#success-print-thermal-btn')?.addEventListener('click', () => printInvoice('thermal'));

        // Modal close on backdrop click
        document.addEventListener('click', e => {
            if (e.target.classList.contains('modal-backdrop')) {
                Utils.closeModal(e.target.closest('.modal')?.id);
            }
        });
    }

    // ── Init ──────────────────────────────────────────────
    async function init(settings = {}) {
        state.settings = settings;
        cacheDom();
        bindEvents();
        initBarcodeScanner();
        initShortcuts();
        await loadProducts();
        updateCustomerDisplay();
        updateTotals();
    }

    return { init, addToCart, clearCart, state };
})();

window.POS = POS;
