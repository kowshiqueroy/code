/**
 * Offline.js — IndexedDB queue + sync UI for Ovijat Call Center
 */
(function() {
  'use strict';

  const DB_NAME    = 'callcenter_offline';
  const DB_VERSION = 1;
  const STORE      = 'pending_items';

  let db = null;

  // ── Open IndexedDB ─────────────────────────────────────
  function openDB() {
    return new Promise((resolve, reject) => {
      if (db) return resolve(db);
      const req = indexedDB.open(DB_NAME, DB_VERSION);
      req.onupgradeneeded = e => {
        const d = e.target.result;
        if (!d.objectStoreNames.contains(STORE)) {
          const store = d.createObjectStore(STORE, { keyPath: 'local_id', autoIncrement: true });
          store.createIndex('type', 'type', { unique: false });
          store.createIndex('status', 'status', { unique: false });
        }
      };
      req.onsuccess = e => { db = e.target.result; resolve(db); };
      req.onerror   = e => reject(e.target.error);
    });
  }

  // ── Queue an item ──────────────────────────────────────
  function queueItem(type, data) {
    return openDB().then(db => new Promise((resolve, reject) => {
      const item = {
        type,
        data,
        status: 'pending',
        created_at: new Date().toISOString(),
        attempts: 0,
      };
      const tx   = db.transaction(STORE, 'readwrite');
      const req  = tx.objectStore(STORE).add(item);
      req.onsuccess = () => {
        updatePendingBadge();
        resolve(req.result);
      };
      req.onerror = () => reject(req.error);
    }));
  }

  // ── Get all pending items ──────────────────────────────
  function getPendingItems() {
    return openDB().then(db => new Promise((resolve, reject) => {
      const tx  = db.transaction(STORE, 'readonly');
      const req = tx.objectStore(STORE).index('status').getAll('pending');
      req.onsuccess = () => resolve(req.result);
      req.onerror   = () => reject(req.error);
    }));
  }

  // ── Get all items (for sync panel) ────────────────────
  function getAllItems() {
    return openDB().then(db => new Promise((resolve, reject) => {
      const tx  = db.transaction(STORE, 'readonly');
      const req = tx.objectStore(STORE).getAll();
      req.onsuccess = () => resolve(req.result);
      req.onerror   = () => reject(req.error);
    }));
  }

  // ── Update item status ─────────────────────────────────
  function updateItemStatus(local_id, status, server_id = null, conflict = null) {
    return openDB().then(db => new Promise((resolve, reject) => {
      const tx    = db.transaction(STORE, 'readwrite');
      const store = tx.objectStore(STORE);
      const getReq = store.get(local_id);
      getReq.onsuccess = () => {
        const item = getReq.result;
        if (!item) return reject('Item not found');
        item.status    = status;
        item.synced_at = new Date().toISOString();
        if (server_id) item.server_id = server_id;
        if (conflict)  item.conflict  = conflict;
        store.put(item);
        updatePendingBadge();
        resolve(item);
      };
      getReq.onerror = () => reject(getReq.error);
    }));
  }

  // ── Delete item ────────────────────────────────────────
  function deleteItem(local_id) {
    return openDB().then(db => new Promise((resolve, reject) => {
      const tx  = db.transaction(STORE, 'readwrite');
      const req = tx.objectStore(STORE).delete(local_id);
      req.onsuccess = () => { updatePendingBadge(); resolve(); };
      req.onerror   = () => reject(req.error);
    }));
  }

  // ── Count pending items ────────────────────────────────
  function countPending() {
    return openDB().then(db => new Promise((resolve, reject) => {
      const tx  = db.transaction(STORE, 'readonly');
      const req = tx.objectStore(STORE).index('status').count('pending');
      req.onsuccess = () => resolve(req.result);
      req.onerror   = () => reject(req.error);
    }));
  }

  // ── Update badge in sidebar ────────────────────────────
  function updatePendingBadge() {
    countPending().then(count => {
      const badge = document.getElementById('syncBadge');
      if (badge) {
        badge.textContent = count || '';
        badge.style.display = count > 0 ? 'inline' : 'none';
      }
    }).catch(() => {});
  }

  // ── Sync one item via API ──────────────────────────────
  function syncItem(item) {
    const apiUrl = window.APP?.apiUrl || '/code/callcenter/api.php';
    const csrfToken = window.APP?.csrfToken || '';

    return fetch(apiUrl + '?action=sync_item', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        csrf_token: csrfToken,
        local_id:   item.local_id,
        type:       item.type,
        data:       item.data,
      }),
    }).then(r => r.json());
  }

  // ── Online/Offline detection ───────────────────────────
  function updateOnlineBadge(isOnline) {
    const badge = document.getElementById('onlineBadge');
    if (!badge) return;
    if (isOnline) {
      badge.className = 'online';
      badge.title = 'Online';
    } else {
      badge.className = 'offline';
      badge.title = 'Offline — changes will be queued';
    }
  }

  window.addEventListener('online',  () => {
    updateOnlineBadge(true);
    promptSync();
  });
  window.addEventListener('offline', () => updateOnlineBadge(false));

  function promptSync() {
    countPending().then(count => {
      if (count > 0) {
        const toast = document.createElement('div');
        toast.className = 'toast align-items-center text-bg-warning border-0 show';
        toast.setAttribute('role', 'alert');
        toast.innerHTML = `
          <div class="d-flex">
            <div class="toast-body">
              <i class="bi bi-cloud-upload me-2"></i>
              You're back online. <strong>${count} item(s)</strong> waiting to sync.
              <a href="${window.APP?.baseUrl || ''}/modules/sync/index.php" class="btn btn-sm btn-dark ms-2">Sync Now</a>
            </div>
            <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button>
          </div>`;
        const container = document.getElementById('toastContainer') ||
          (() => { const d = document.createElement('div'); d.id = 'toastContainer';
            d.className = 'toast-container position-fixed bottom-0 end-0 p-3'; d.style.zIndex = '1100';
            document.body.appendChild(d); return d; })();
        container.appendChild(toast);
        setTimeout(() => toast.remove(), 8000);
      }
    }).catch(() => {});
  }

  // ── Register Service Worker ────────────────────────────
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/code/callcenter/sw.js')
      .then(reg => console.log('SW registered', reg.scope))
      .catch(err => console.warn('SW registration failed', err));
  }

  // ── Public API ─────────────────────────────────────────
  window.OfflineQueue = {
    queue:       queueItem,
    getPending:  getPendingItems,
    getAll:      getAllItems,
    updateStatus:updateItemStatus,
    remove:      deleteItem,
    countPending,
    sync:        syncItem,
    updateBadge: updatePendingBadge,
  };

  // Init on load
  document.addEventListener('DOMContentLoaded', () => {
    updateOnlineBadge(navigator.onLine);
    updatePendingBadge();
  });

})();
