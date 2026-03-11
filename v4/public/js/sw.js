/**
 * sw.js — POS Service Worker
 * Strategy: Cache-First for assets, Network-First for API calls.
 * Offline sales are queued in IndexedDB and synced on reconnect.
 */

const SW_VERSION   = 'pos-v1.0.0';
const STATIC_CACHE = `${SW_VERSION}-static`;
const API_CACHE    = `${SW_VERSION}-api`;

// ── Assets to cache on install ────────────────────────────────────────────────
const STATIC_ASSETS = [
  '/',
  '/index.php',
  '/login.php',
  '/pos.php',
  '/public/css/app.css',
  '/public/css/pos.css',
  '/public/css/print.css',
  '/public/js/app.js',
  '/public/js/pos.js',
  '/public/js/db.js',
  '/public/js/sync.js',
  '/public/js/offline.js',
  '/public/images/logo.png',
  '/manifest.json',
  '/offline.html',
];

// API routes that should NOT be cached (always fresh)
const API_ROUTES = [
  '/api/',
  '/app/controllers/',
];

// ── Install: pre-cache all static assets ─────────────────────────────────────
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(STATIC_CACHE).then((cache) => {
      console.log('[SW] Pre-caching static assets');
      return cache.addAll(STATIC_ASSETS).catch((err) => {
        console.warn('[SW] Some assets failed to cache:', err);
        // Cache what we can; don't block install on missing assets
      });
    }).then(() => self.skipWaiting())
  );
});

// ── Activate: clean up old caches ─────────────────────────────────────────────
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys
          .filter((k) => k !== STATIC_CACHE && k !== API_CACHE)
          .map((k) => {
            console.log('[SW] Deleting old cache:', k);
            return caches.delete(k);
          })
      )
    ).then(() => self.clients.claim())
  );
});

// ── Fetch: routing strategy ───────────────────────────────────────────────────
self.addEventListener('fetch', (event) => {
  const { request } = event;
  const url = new URL(request.url);

  // 1. Non-GET requests (POST/PUT/DELETE) → let network handle, don't cache
  if (request.method !== 'GET') {
    event.respondWith(networkOrQueue(request));
    return;
  }

  // 2. API calls → Network First, fall back to cache, then offline placeholder
  if (API_ROUTES.some((r) => url.pathname.startsWith(r))) {
    event.respondWith(networkFirstApi(request));
    return;
  }

  // 3. Navigation requests → Network First, fall back to offline.html
  if (request.mode === 'navigate') {
    event.respondWith(navigationFetch(request));
    return;
  }

  // 4. Static assets → Cache First
  event.respondWith(cacheFirst(request));
});

// ── Strategy: Cache First ─────────────────────────────────────────────────────
async function cacheFirst(request) {
  const cached = await caches.match(request);
  if (cached) return cached;
  try {
    const response = await fetch(request);
    if (response.ok) {
      const cache = await caches.open(STATIC_CACHE);
      cache.put(request, response.clone());
    }
    return response;
  } catch {
    return new Response('Asset unavailable offline.', { status: 503 });
  }
}

// ── Strategy: Network First (API) ─────────────────────────────────────────────
async function networkFirstApi(request) {
  try {
    const response = await fetch(request);
    if (response.ok) {
      const cache = await caches.open(API_CACHE);
      cache.put(request, response.clone());
    }
    return response;
  } catch {
    const cached = await caches.match(request);
    if (cached) return cached;
    return new Response(
      JSON.stringify({ success: false, offline: true, message: 'Offline — cached data unavailable.' }),
      { status: 503, headers: { 'Content-Type': 'application/json' } }
    );
  }
}

// ── Strategy: Navigate ────────────────────────────────────────────────────────
async function navigationFetch(request) {
  try {
    const response = await fetch(request);
    return response;
  } catch {
    const cached = await caches.match(request);
    if (cached) return cached;
    return caches.match('/offline.html');
  }
}

// ── Strategy: POST — queue offline if network fails ───────────────────────────
async function networkOrQueue(request) {
  try {
    return await fetch(request);
  } catch {
    // Only queue sale submissions
    const url = new URL(request.url);
    if (url.pathname.includes('/api/sales') || url.pathname.includes('/pos/sale')) {
      const body = await request.clone().text();
      await queueOfflineSale(body, url.pathname);
      return new Response(
        JSON.stringify({
          success: true,
          offline: true,
          message: 'Sale saved offline. Will sync when connection is restored.',
        }),
        { status: 202, headers: { 'Content-Type': 'application/json' } }
      );
    }
    return new Response(
      JSON.stringify({ success: false, offline: true, message: 'Network unavailable.' }),
      { status: 503, headers: { 'Content-Type': 'application/json' } }
    );
  }
}

// ── IndexedDB Offline Queue ───────────────────────────────────────────────────
const IDB_NAME    = 'pos_offline';
const IDB_VERSION = 1;
const STORE_NAME  = 'sale_queue';

function openIDB() {
  return new Promise((resolve, reject) => {
    const req = indexedDB.open(IDB_NAME, IDB_VERSION);
    req.onupgradeneeded = (e) => {
      const db = e.target.result;
      if (!db.objectStoreNames.contains(STORE_NAME)) {
        const store = db.createObjectStore(STORE_NAME, { keyPath: 'uid' });
        store.createIndex('status', 'status', { unique: false });
        store.createIndex('queued_at', 'queued_at', { unique: false });
      }
    };
    req.onsuccess = () => resolve(req.result);
    req.onerror   = () => reject(req.error);
  });
}

async function queueOfflineSale(bodyText, endpoint) {
  const db    = await openIDB();
  const entry = {
    uid:       crypto.randomUUID(),
    endpoint,
    body:      bodyText,
    status:    'pending',
    queued_at: new Date().toISOString(),
    attempts:  0,
  };
  return new Promise((resolve, reject) => {
    const tx  = db.transaction(STORE_NAME, 'readwrite');
    const req = tx.objectStore(STORE_NAME).add(entry);
    req.onsuccess = () => {
      console.log('[SW] Offline sale queued:', entry.uid);
      // Notify all clients
      self.clients.matchAll().then((clients) =>
        clients.forEach((c) =>
          c.postMessage({ type: 'OFFLINE_SALE_QUEUED', uid: entry.uid })
        )
      );
      resolve(entry.uid);
    };
    req.onerror = () => reject(req.error);
  });
}

async function getPendingSales() {
  const db = await openIDB();
  return new Promise((resolve, reject) => {
    const tx  = db.transaction(STORE_NAME, 'readonly');
    const idx = tx.objectStore(STORE_NAME).index('status');
    const req = idx.getAll('pending');
    req.onsuccess = () => resolve(req.result);
    req.onerror   = () => reject(req.error);
  });
}

async function markSynced(uid, serverId) {
  const db = await openIDB();
  return new Promise((resolve, reject) => {
    const tx    = db.transaction(STORE_NAME, 'readwrite');
    const store = tx.objectStore(STORE_NAME);
    const req   = store.get(uid);
    req.onsuccess = () => {
      const record = req.result;
      if (record) {
        record.status    = 'synced';
        record.server_id = serverId;
        record.synced_at = new Date().toISOString();
        store.put(record);
      }
      resolve();
    };
    req.onerror = () => reject(req.error);
  });
}

async function markFailed(uid, error) {
  const db = await openIDB();
  return new Promise((resolve, reject) => {
    const tx    = db.transaction(STORE_NAME, 'readwrite');
    const store = tx.objectStore(STORE_NAME);
    const req   = store.get(uid);
    req.onsuccess = () => {
      const record = req.result;
      if (record) {
        record.attempts++;
        record.last_error = error;
        if (record.attempts >= 5) record.status = 'failed';
        store.put(record);
      }
      resolve();
    };
    req.onerror = () => reject(req.error);
  });
}

// ── Background Sync ───────────────────────────────────────────────────────────
self.addEventListener('sync', (event) => {
  if (event.tag === 'sync-offline-sales') {
    event.waitUntil(syncOfflineSales());
  }
});

async function syncOfflineSales() {
  const pending = await getPendingSales();
  console.log(`[SW] Syncing ${pending.length} offline sale(s)`);

  for (const sale of pending) {
    try {
      const response = await fetch('/api/offline-sync.php', {
        method:  'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Offline-UID': sale.uid,
          'X-Offline-QueuedAt': sale.queued_at,
        },
        body: sale.body,
      });

      if (response.ok) {
        const data = await response.json();
        await markSynced(sale.uid, data.queue_id);
        console.log('[SW] Synced:', sale.uid);
        notifyClients({ type: 'SYNC_SUCCESS', uid: sale.uid, queue_id: data.queue_id });
      } else {
        await markFailed(sale.uid, `HTTP ${response.status}`);
        notifyClients({ type: 'SYNC_FAILED', uid: sale.uid });
      }
    } catch (err) {
      await markFailed(sale.uid, err.message);
      console.error('[SW] Sync failed for', sale.uid, err);
    }
  }
}

// ── Message Handling (from main app) ─────────────────────────────────────────
self.addEventListener('message', (event) => {
  const { type } = event.data || {};

  switch (type) {
    case 'SKIP_WAITING':
      self.skipWaiting();
      break;

    case 'SYNC_NOW':
      syncOfflineSales();
      break;

    case 'GET_PENDING_COUNT':
      getPendingSales().then((sales) => {
        event.source.postMessage({ type: 'PENDING_COUNT', count: sales.length });
      });
      break;

    case 'QUEUE_SALE':
      queueOfflineSale(JSON.stringify(event.data.payload), '/api/sales/create.php')
        .then((uid) => event.source.postMessage({ type: 'SALE_QUEUED', uid }));
      break;
  }
});

// ── Push Notifications (optional) ────────────────────────────────────────────
self.addEventListener('push', (event) => {
  const data = event.data?.json() ?? { title: 'POS Notification', body: '' };
  event.waitUntil(
    self.registration.showNotification(data.title, {
      body: data.body,
      icon: '/public/images/logo.png',
      badge: '/public/images/badge.png',
    })
  );
});

// ── Online: trigger sync ──────────────────────────────────────────────────────
self.addEventListener('online', () => {
  console.log('[SW] Back online — triggering sync');
  if ('SyncManager' in self) {
    self.registration.sync.register('sync-offline-sales');
  } else {
    syncOfflineSales(); // Fallback
  }
});

// ── Helpers ───────────────────────────────────────────────────────────────────
function notifyClients(msg) {
  self.clients.matchAll().then((clients) =>
    clients.forEach((c) => c.postMessage(msg))
  );
}

console.log('[SW] Service Worker loaded:', SW_VERSION);
