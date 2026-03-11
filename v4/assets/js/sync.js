// ============================================================
// sync.js — Offline → Online Sync Engine
// Monitors connectivity, uploads queued sales, triggers SW sync
// ============================================================
'use strict';

const SyncEngine = (() => {
    let _isSyncing = false;
    let _statusListeners = [];
    let _isOnline = navigator.onLine;

    // ── Status Banner ─────────────────────────────────────
    function updateStatusBanner(online) {
        _isOnline = online;
        const banner = document.getElementById('offline-banner');
        const indicator = document.getElementById('conn-indicator');

        if (banner) {
            banner.hidden = online;
            banner.setAttribute('aria-hidden', String(online));
        }
        if (indicator) {
            indicator.className = `conn-dot ${online ? 'online' : 'offline'}`;
            indicator.title = online ? 'Online' : 'Offline';
        }
        _statusListeners.forEach(fn => fn(online));
    }

    // ── Connectivity Listeners ────────────────────────────
    window.addEventListener('online',  () => {
        updateStatusBanner(true);
        attemptSync();
    });
    window.addEventListener('offline', () => updateStatusBanner(false));

    // ── Register Sync with Service Worker ─────────────────
    async function registerBackgroundSync() {
        if ('serviceWorker' in navigator && 'SyncManager' in window) {
            try {
                const reg = await navigator.serviceWorker.ready;
                await reg.sync.register('sync-offline-sales');
                console.log('[Sync] Background sync registered');
            } catch (e) {
                console.warn('[Sync] Background sync not supported:', e);
            }
        }
    }

    // ── Main Sync Routine ─────────────────────────────────
    async function attemptSync() {
        if (!navigator.onLine || _isSyncing) return;

        const pending = await PosDB.sales.getPending();
        if (pending.length === 0) return;

        _isSyncing = true;
        showSyncIndicator(true, `Syncing ${pending.length} offline sale(s)…`);

        let successCount = 0;
        let errorCount   = 0;

        for (const sale of pending) {
            try {
                const result = await uploadSale(sale);
                if (result.success) {
                    await PosDB.sales.markSynced(sale.local_id);
                    await PosDB.syncLog.add({
                        local_id: sale.local_id,
                        result: 'synced',
                        server_msg: result.message || ''
                    });
                    successCount++;
                } else {
                    await PosDB.syncLog.add({
                        local_id: sale.local_id,
                        result: 'error',
                        server_msg: result.message || 'Server rejected'
                    });
                    errorCount++;
                }
            } catch (err) {
                console.error('[Sync] Upload failed for', sale.local_id, err);
                await PosDB.syncLog.add({
                    local_id: sale.local_id,
                    result: 'error',
                    server_msg: err.message
                });
                errorCount++;
            }
        }

        _isSyncing = false;
        showSyncIndicator(false);

        if (successCount > 0) {
            showToast(
                `✅ ${successCount} offline sale(s) uploaded for admin review.`,
                'success', 5000
            );
        }
        if (errorCount > 0) {
            showToast(
                `⚠️ ${errorCount} sale(s) failed to sync. Will retry.`,
                'warning', 5000
            );
        }

        // Update pending badge
        updatePendingBadge();
    }

    // ── Upload Single Sale to Server ──────────────────────
    async function uploadSale(sale) {
        const response = await fetch('/pos/api/offline/sync.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': document.querySelector('meta[name="csrf"]')?.content || '',
            },
            body: JSON.stringify({
                local_id:    sale.local_id,
                queued_at:   sale.created_at,
                device_info: sale.device_info,
                payload:     sale,
            }),
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        return response.json();
    }

    // ── Pending Badge ─────────────────────────────────────
    async function updatePendingBadge() {
        const count = await PosDB.sales.count();
        const badge = document.getElementById('offline-queue-badge');
        if (badge) {
            badge.textContent = count;
            badge.hidden = count === 0;
        }
    }

    // ── UI Helpers ────────────────────────────────────────
    function showSyncIndicator(visible, message = '') {
        const el = document.getElementById('sync-indicator');
        if (!el) return;
        el.hidden = !visible;
        if (visible && message) el.textContent = message;
    }

    function showToast(message, type = 'info', duration = 3000) {
        const container = document.getElementById('toast-container');
        if (!container) return;

        const toast = document.createElement('div');
        toast.className = `toast toast--${type}`;
        toast.textContent = message;
        toast.setAttribute('role', 'alert');

        container.appendChild(toast);
        requestAnimationFrame(() => toast.classList.add('toast--visible'));

        setTimeout(() => {
            toast.classList.remove('toast--visible');
            toast.addEventListener('transitionend', () => toast.remove(), { once: true });
        }, duration);
    }

    // ── Service Worker Message Handler ────────────────────
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.addEventListener('message', event => {
            if (event.data?.type === 'TRIGGER_SYNC') {
                attemptSync();
            }
        });
    }

    // ── Init ──────────────────────────────────────────────
    function init() {
        updateStatusBanner(navigator.onLine);
        updatePendingBadge();

        // Poll every 30s as fallback
        setInterval(() => {
            if (navigator.onLine) attemptSync();
        }, 30000);
    }

    // ── Public API ────────────────────────────────────────
    return {
        init,
        isOnline: () => _isOnline,
        forceSync: attemptSync,
        registerBackgroundSync,
        updatePendingBadge,
        showToast,
        onStatusChange: (fn) => _statusListeners.push(fn),
    };
})();

window.SyncEngine = SyncEngine;
