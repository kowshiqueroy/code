/**
 * ============================================================
 * POS System — Offline Manager  (assets/js/offline.js)
 * ============================================================
 * • Detects online/offline transitions
 * • Shows/hides persistent UI warning banner
 * • Registers Background Sync when available
 * • Manually replays queued sales when reconnected
 * • Notifies the main app of sync progress
 */

'use strict';

const OfflineManager = (() => {

  let _isOnline = navigator.onLine;
  let _queueCount = 0;
  let _syncInProgress = false;
  const _listeners = {};

  // ── Event emitter helpers ────────────────────────────────
  function on(event, fn) {
    (_listeners[event] ??= []).push(fn);
  }

  function emit(event, data) {
    (_listeners[event] ?? []).forEach(fn => fn(data));
  }

  // ── Banner element ────────────────────────────────────────
  function getBanner() {
    let el = document.getElementById('offline-banner');
    if (!el) {
      el = document.createElement('div');
      el.id = 'offline-banner';
      el.setAttribute('role', 'alert');
      el.setAttribute('aria-live', 'assertive');
      el.innerHTML = `
        <span class="offline-icon">📡</span>
        <span class="offline-text">
          You are <strong>offline</strong>. Sales will be saved locally and synced when reconnected.
        </span>
        <span class="offline-queue" id="offline-queue-count"></span>
        <button class="offline-dismiss" onclick="OfflineManager.dismissBanner()" aria-label="Dismiss">×</button>
      `;
      document.body.prepend(el);
    }
    return el;
  }

  function updateBanner() {
    const banner = getBanner();
    const countEl = document.getElementById('offline-queue-count');

    if (!_isOnline) {
      banner.classList.add('visible', 'is-offline');
      banner.classList.remove('is-syncing', 'is-synced');
      if (countEl && _queueCount > 0) {
        countEl.textContent = `${_queueCount} sale(s) queued`;
        countEl.style.display = 'inline';
      }
    } else if (_syncInProgress) {
      banner.classList.add('visible', 'is-syncing');
      banner.classList.remove('is-offline', 'is-synced');
      banner.querySelector('.offline-text').innerHTML =
        '<strong>Reconnected!</strong> Syncing offline sales to server…';
    } else if (_queueCount > 0) {
      banner.classList.add('visible', 'is-synced');
      banner.classList.remove('is-offline', 'is-syncing');
      banner.querySelector('.offline-text').innerHTML =
        `<strong>Online</strong> — ${_queueCount} sale(s) pending admin review.`;
    } else {
      banner.classList.remove('visible');
    }
  }

  // ── Sync queued sales to server ───────────────────────────
  async function syncQueue() {
    if (_syncInProgress || !_isOnline) return;

    const pending = await PosDB.syncQueue.getPending();
    if (!pending.length) return;

    _syncInProgress = true;
    _queueCount     = pending.length;
    updateBanner();
    emit('sync:start', { count: pending.length });

    let synced = 0;
    let failed = 0;

    for (const item of pending) {
      try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

        const response = await fetch('/api/sales/offline-sync.php', {
          method:  'POST',
          headers: {
            'Content-Type':    'application/json',
            'X-CSRF-Token':    csrfToken,
            'X-Offline-UUID':  item.uuid,
          },
          body: item.payload,
        });

        const result = await response.json();

        if (response.ok && result.success) {
          await PosDB.syncQueue.markSynced(item.localId);
          synced++;
          emit('sync:item:success', { uuid: item.uuid, saleId: result.data?.queueId });
        } else {
          await PosDB.syncQueue.markFailed(item.localId);
          failed++;
          emit('sync:item:fail', { uuid: item.uuid, error: result.error });
        }
      } catch (err) {
        await PosDB.syncQueue.markFailed(item.localId);
        failed++;
        emit('sync:item:fail', { uuid: item.uuid, error: err.message });
      }
    }

    _syncInProgress = false;
    _queueCount     = (await PosDB.syncQueue.getPending()).length;

    updateBanner();
    emit('sync:complete', { synced, failed, remaining: _queueCount });

    if (synced > 0) {
      showToast(`✅ ${synced} offline sale(s) sent for admin review.`, 'success');
    }
    if (failed > 0) {
      showToast(`⚠️ ${failed} sale(s) could not be synced. Will retry.`, 'warning');
    }
  }

  // ── Toast notification ────────────────────────────────────
  function showToast(message, type = 'info', duration = 4000) {
    let container = document.getElementById('toast-container');
    if (!container) {
      container = document.createElement('div');
      container.id = 'toast-container';
      document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast toast--${type}`;
    toast.textContent = message;
    container.appendChild(toast);

    requestAnimationFrame(() => toast.classList.add('toast--visible'));

    setTimeout(() => {
      toast.classList.remove('toast--visible');
      setTimeout(() => toast.remove(), 300);
    }, duration);
  }

  // ── Handle online event ───────────────────────────────────
  async function handleOnline() {
    _isOnline = true;
    console.log('[Offline] Connection restored');
    emit('online');

    // Update queue count
    _queueCount = (await PosDB.syncQueue.getPending()).length;

    // Register background sync if supported
    if ('serviceWorker' in navigator && 'SyncManager' in window) {
      const sw = await navigator.serviceWorker.ready;
      try {
        await sw.sync.register('pos-offline-sync');
        console.log('[Offline] Background sync registered');
      } catch {
        // Fallback to manual sync
        await syncQueue();
      }
    } else {
      await syncQueue();
    }

    updateBanner();
  }

  // ── Handle offline event ──────────────────────────────────
  function handleOffline() {
    _isOnline = false;
    console.log('[Offline] Connection lost');
    emit('offline');
    updateBanner();
  }

  // ── Listen for messages from Service Worker ───────────────
  function setupSWMessageListener() {
    if (!('serviceWorker' in navigator)) return;

    navigator.serviceWorker.addEventListener('message', async (event) => {
      const { type, uuid, count } = event.data ?? {};

      switch (type) {
        case 'SYNC_SUCCESS':
          console.log('[Offline] SW synced item:', uuid);
          _queueCount = Math.max(0, _queueCount - 1);
          updateBanner();
          break;

        case 'QUEUE_COUNT':
          _queueCount = count;
          updateBanner();
          break;
      }
    });
  }

  // ── Expose safe POST helper (queues on failure) ───────────
  async function safePost(url, data) {
    const uuid       = crypto.randomUUID();
    const csrfToken  = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    if (!_isOnline) {
      await PosDB.syncQueue.enqueue({ ...data, _offline_uuid: uuid }, uuid);
      _queueCount++;
      updateBanner();
      return { success: true, offline: true, uuid };
    }

    try {
      const response = await fetch(url, {
        method:  'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken,
        },
        body: JSON.stringify({ ...data, _offline_uuid: uuid }),
      });

      const result = await response.json();

      if (!response.ok) {
        throw new Error(result.error ?? 'Server error');
      }

      return result;

    } catch (err) {
      console.warn('[Offline] POST failed, queuing:', err.message);
      await PosDB.syncQueue.enqueue({ ...data, _offline_uuid: uuid }, uuid);
      _queueCount++;
      updateBanner();
      return { success: true, offline: true, uuid };
    }
  }

  // ── Init ──────────────────────────────────────────────────
  async function init() {
    // Register event listeners
    window.addEventListener('online',  handleOnline);
    window.addEventListener('offline', handleOffline);

    // Get initial queue count
    _queueCount = (await PosDB.syncQueue.getPending()).length;

    // Register Service Worker
    if ('serviceWorker' in navigator) {
      try {
        const reg = await navigator.serviceWorker.register('/sw.js');
        console.log('[SW] Registered, scope:', reg.scope);

        // Check for updates
        reg.addEventListener('updatefound', () => {
          const newWorker = reg.installing;
          newWorker?.addEventListener('statechange', () => {
            if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
              showToast('🔄 App update available. Refresh to get the latest version.', 'info', 8000);
            }
          });
        });

        setupSWMessageListener();
      } catch (err) {
        console.warn('[SW] Registration failed:', err);
      }
    }

    // Initial banner state
    if (!_isOnline || _queueCount > 0) {
      updateBanner();
    }

    console.log(`[Offline] Manager ready. Online: ${_isOnline}, Queue: ${_queueCount}`);
  }

  // ── Public API ────────────────────────────────────────────
  return {
    init,
    on,
    emit,
    syncQueue,
    safePost,
    showToast,
    dismissBanner: () => getBanner().classList.remove('visible'),
    get isOnline()   { return _isOnline;   },
    get queueCount() { return _queueCount; },
  };

})();

// Auto-init when DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => OfflineManager.init());
} else {
  OfflineManager.init();
}

window.OfflineManager = OfflineManager;
