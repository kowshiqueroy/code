/**
 * app.js — Global Application JavaScript
 * Handles: Service Worker, Online/Offline UI, Inactivity Auto-Logout, CSRF helpers
 */

'use strict';

/* ── Constants ────────────────────────────────────────────────────────────── */
const POS_CONFIG = {
  inactivityMs:  5 * 60 * 1000,   // 5 min → match PHP SESSION_LIFETIME
  warningMs:     60 * 1000,        // Show warning 1 min before logout
  swPath:        '/public/js/sw.js',
  swScope:       '/',
};

/* ═══════════════════════════════════════════════════════════════════════════
   SERVICE WORKER REGISTRATION
   ═══════════════════════════════════════════════════════════════════════════ */
(async function registerServiceWorker() {
  if (!('serviceWorker' in navigator)) return;

  try {
    const reg = await navigator.serviceWorker.register(
      POS_CONFIG.swPath,
      { scope: POS_CONFIG.swScope, updateViaCache: 'none' }
    );

    // New SW waiting → prompt user to refresh
    reg.addEventListener('updatefound', () => {
      const newWorker = reg.installing;
      newWorker.addEventListener('statechange', () => {
        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
          showUpdateBanner();
        }
      });
    });

    // Listen for messages from SW
    navigator.serviceWorker.addEventListener('message', handleSwMessage);

    console.log('[App] Service Worker registered:', reg.scope);
  } catch (err) {
    console.error('[App] SW registration failed:', err);
  }
})();

function showUpdateBanner() {
  const banner = document.createElement('div');
  banner.id    = 'sw-update-banner';
  banner.innerHTML = `
    <span>A new version is available.</span>
    <button onclick="applySwUpdate()">Update Now</button>
  `;
  banner.style.cssText = `
    position:fixed;bottom:0;left:0;right:0;z-index:9999;
    background:#7c6af7;color:#fff;display:flex;align-items:center;
    justify-content:center;gap:16px;padding:12px;font-size:14px;
  `;
  document.body.appendChild(banner);
}

async function applySwUpdate() {
  const reg = await navigator.serviceWorker.getRegistration();
  if (reg?.waiting) {
    reg.waiting.postMessage({ type: 'SKIP_WAITING' });
    navigator.serviceWorker.addEventListener('controllerchange', () => location.reload());
  }
}

function handleSwMessage(event) {
  const { type, uid, count } = event.data || {};
  switch (type) {
    case 'OFFLINE_SALE_QUEUED':
      updateOfflineBadge(1, 'add');
      showToast('Sale saved offline (' + uid?.slice(0, 8) + '…)', 'warn');
      break;
    case 'SYNC_SUCCESS':
      updateOfflineBadge(1, 'remove');
      showToast('Offline sale synced successfully', 'success');
      break;
    case 'SYNC_FAILED':
      showToast('Sync failed — will retry', 'error');
      break;
    case 'PENDING_COUNT':
      updateOfflineBadge(count);
      break;
  }
}

/* ═══════════════════════════════════════════════════════════════════════════
   ONLINE / OFFLINE DETECTION
   ═══════════════════════════════════════════════════════════════════════════ */
let _offlineCount = 0;

function updateConnectionStatus() {
  const online   = navigator.onLine;
  const indicator = document.getElementById('connection-status');
  const banner    = document.getElementById('offline-banner');

  if (indicator) {
    indicator.className = online ? 'conn-online' : 'conn-offline';
    indicator.textContent = online ? '● Online' : '● Offline';
    indicator.title = online ? 'Connected' : 'Offline — sales saved locally';
  }

  if (banner) {
    banner.hidden = online;
  }

  if (!online) {
    document.body.classList.add('is-offline');
  } else {
    document.body.classList.remove('is-offline');
    // Trigger SW sync when back online
    if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
      navigator.serviceWorker.controller.postMessage({ type: 'SYNC_NOW' });
    }
    // Fallback sync via Background Sync API
    navigator.serviceWorker.ready.then((reg) => {
      if ('sync' in reg) {
        reg.sync.register('sync-offline-sales').catch(() => {});
      }
    });
  }
}

function updateOfflineBadge(delta = 0, mode = 'set') {
  const badge = document.getElementById('offline-badge');
  if (!badge) return;
  if (mode === 'add')    _offlineCount += delta;
  if (mode === 'remove') _offlineCount = Math.max(0, _offlineCount - delta);
  if (mode === 'set')    _offlineCount = delta;
  badge.textContent = _offlineCount;
  badge.hidden = _offlineCount === 0;
}

// Request pending count from SW on load
async function fetchPendingCount() {
  if (!navigator.serviceWorker.controller) return;
  navigator.serviceWorker.controller.postMessage({ type: 'GET_PENDING_COUNT' });
}

window.addEventListener('online',  updateConnectionStatus);
window.addEventListener('offline', updateConnectionStatus);
document.addEventListener('DOMContentLoaded', () => {
  updateConnectionStatus();
  fetchPendingCount();
});

/* ═══════════════════════════════════════════════════════════════════════════
   INACTIVITY AUTO-LOGOUT (matches PHP SESSION_LIFETIME = 5 min)
   ═══════════════════════════════════════════════════════════════════════════ */
let _inactivityTimer  = null;
let _warningTimer     = null;
let _countdownInterval = null;
let _warningVisible   = false;

function resetInactivityTimer() {
  clearTimeout(_inactivityTimer);
  clearTimeout(_warningTimer);

  if (_warningVisible) dismissInactivityWarning();

  // Show warning 1 minute before logout
  _warningTimer = setTimeout(() => {
    showInactivityWarning();
  }, POS_CONFIG.inactivityMs - POS_CONFIG.warningMs);

  // Actual logout
  _inactivityTimer = setTimeout(() => {
    performAutoLogout();
  }, POS_CONFIG.inactivityMs);
}

function showInactivityWarning() {
  _warningVisible = true;
  let remaining = Math.floor(POS_CONFIG.warningMs / 1000);

  const modal = document.createElement('div');
  modal.id    = 'inactivity-modal';
  modal.innerHTML = `
    <div class="inact-overlay"></div>
    <div class="inact-dialog">
      <div class="inact-icon">⏱️</div>
      <h3>Session Expiring</h3>
      <p>You will be logged out in <strong id="inact-countdown">${remaining}s</strong> due to inactivity.</p>
      <button onclick="extendSession()">Stay Logged In</button>
    </div>
  `;
  document.body.appendChild(modal);

  _countdownInterval = setInterval(() => {
    remaining--;
    const el = document.getElementById('inact-countdown');
    if (el) el.textContent = remaining + 's';
    if (remaining <= 0) clearInterval(_countdownInterval);
  }, 1000);
}

function dismissInactivityWarning() {
  _warningVisible = false;
  clearInterval(_countdownInterval);
  const modal = document.getElementById('inactivity-modal');
  if (modal) modal.remove();
}

function extendSession() {
  dismissInactivityWarning();
  resetInactivityTimer();
  // Ping server to extend PHP session
  fetch('/api/session-ping.php', { method: 'POST', credentials: 'same-origin' }).catch(() => {});
}

function performAutoLogout() {
  // Save any draft cart before logging out (for POS page)
  if (typeof POS !== 'undefined' && POS.saveDraftBeforeLogout) {
    POS.saveDraftBeforeLogout();
  }
  window.location.href = '/login.php?reason=timeout';
}

// Listen for user activity
['mousedown', 'mousemove', 'keydown', 'touchstart', 'scroll', 'click']
  .forEach((evt) => document.addEventListener(evt, resetInactivityTimer, { passive: true }));

document.addEventListener('DOMContentLoaded', resetInactivityTimer);

/* ═══════════════════════════════════════════════════════════════════════════
   TOAST NOTIFICATIONS
   ═══════════════════════════════════════════════════════════════════════════ */
const _toastQueue = [];
let   _toastActive = false;

/**
 * showToast(message, type = 'info' | 'success' | 'warn' | 'error', durationMs)
 */
function showToast(message, type = 'info', duration = 3500) {
  _toastQueue.push({ message, type, duration });
  if (!_toastActive) processToastQueue();
}

function processToastQueue() {
  if (!_toastQueue.length) { _toastActive = false; return; }
  _toastActive = true;
  const { message, type, duration } = _toastQueue.shift();

  let container = document.getElementById('toast-container');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toast-container';
    document.body.appendChild(container);
  }

  const toast = document.createElement('div');
  toast.className = `toast toast-${type}`;
  toast.textContent = message;
  container.appendChild(toast);

  requestAnimationFrame(() => toast.classList.add('toast-show'));

  setTimeout(() => {
    toast.classList.remove('toast-show');
    toast.addEventListener('transitionend', () => {
      toast.remove();
      processToastQueue();
    }, { once: true });
  }, duration);
}

/* ═══════════════════════════════════════════════════════════════════════════
   CSRF TOKEN HELPER
   ═══════════════════════════════════════════════════════════════════════════ */
let _csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

function getCsrfToken() { return _csrfToken; }

function refreshCsrfToken() {
  return fetch('/api/csrf-token.php', { credentials: 'same-origin' })
    .then((r) => r.json())
    .then((d) => { _csrfToken = d.token; return _csrfToken; });
}

/**
 * Authenticated JSON fetch with CSRF header.
 */
async function apiPost(url, data = {}) {
  const resp = await fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type':   'application/json',
      'X-CSRF-Token':   getCsrfToken(),
      'X-Requested-With': 'XMLHttpRequest',
    },
    credentials: 'same-origin',
    body: JSON.stringify(data),
  });

  if (resp.status === 401) {
    window.location.href = '/login.php?reason=unauthorized';
    return null;
  }
  if (resp.status === 403) {
    showToast('Session expired. Please log in again.', 'error');
    await refreshCsrfToken();
    return null;
  }

  const json = await resp.json();
  if (json?.token) _csrfToken = json.token; // rotate token returned by server
  return json;
}

async function apiGet(url) {
  const resp = await fetch(url, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    credentials: 'same-origin',
  });
  if (resp.status === 401) { window.location.href = '/login.php'; return null; }
  return resp.json();
}

/* ═══════════════════════════════════════════════════════════════════════════
   GLOBAL EXPORTS
   ═══════════════════════════════════════════════════════════════════════════ */
window.App = {
  showToast,
  apiPost,
  apiGet,
  getCsrfToken,
  refreshCsrfToken,
  resetInactivityTimer,
  updateConnectionStatus,
};
