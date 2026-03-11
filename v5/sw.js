/**
 * ============================================================
 * POS System — Service Worker  (sw.js — place in root /sw.js)
 * ============================================================
 * Strategy:
 *  • App Shell   → Cache-First  (HTML, CSS, JS, icons)
 *  • API calls   → Network-First with offline fallback
 *  • Images      → Stale-While-Revalidate
 *  • Offline sales → Queue in IndexedDB, sync on reconnect
 */

'use strict';

// ── Cache names & version ────────────────────────────────────
const CACHE_VERSION  = 'pos-v1.0.0';
const SHELL_CACHE    = `${CACHE_VERSION}-shell`;
const DATA_CACHE     = `${CACHE_VERSION}-data`;
const IMAGE_CACHE    = `${CACHE_VERSION}-images`;

// ── Files to pre-cache on install ───────────────────────────
const SHELL_FILES = [
  '/',
  '/index.php',
  '/login.php',
  '/assets/css/app.css',
  '/assets/css/pos.css',
  '/assets/css/print.css',
  '/assets/js/app.js',
  '/assets/js/pos.js',
  '/assets/js/db.js',       // IndexedDB wrapper
  '/assets/js/offline.js',  // Offline queue manager
  '/assets/js/qrcode.js',   // QR code generator
  '/assets/js/barcode.js',  // Barcode renderer
  '/manifest.json',
  '/assets/icons/icon-192.png',
  '/assets/icons/icon-512.png',
  '/offline.html',          // Shown when completely offline + no cache
];

// ── API routes — network-first ───────────────────────────────
const API_ROUTES = [
  '/api/',
];

// ── IndexedDB names ──────────────────────────────────────────
const IDB_NAME           = 'pos_offline';
const IDB_VERSION        = 1;
const STORE_SYNC_QUEUE   = 'sync_queue';
const STORE_PRODUCTS     = 'products_cache';
const STORE_SETTINGS     = 'settings_cache';
const STORE_DRAFTS       = 'drafts';

// ─────────────────────────────────────────────────────────────
// INSTALL — Pre-cache app shell
// ─────────────────────────────────────────────────────────────
self.addEventListener('install', (event) => {
  console.log('[SW] Installing…');
  event.waitUntil(
    caches.open(SHELL_CACHE)
      .then(cache => {
        console.log('[SW] Caching shell files');
        return cache.addAll(SHELL_FILES);
      })
      .then(() => self.skipWaiting())
      .catch(err => console.warn('[SW] Install cache error:', err))
  );
});

// ─────────────────────────────────────────────────────────────
// ACTIVATE — Clean up old caches, claim clients
// ─────────────────────────────────────────────────────────────
self.addEventListener('activate', (event) => {
  console.log('[SW] Activating…');
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(
        keys
          .filter(k => k.startsWith('pos-') && k !== SHELL_CACHE && k !== DATA_CACHE && k !== IMAGE_CACHE)
          .map(k => {
            console.log('[SW] Deleting old cache:', k);
            return caches.delete(k);
          })
      )
    ).then(() => self.clients.claim())
  );
});

// ─────────────────────────────────────────────────────────────
// FETCH — Route requests to appropriate strategies
// ─────────────────────────────────────────────────────────────
self.addEventListener('fetch', (event) => {
  const { request } = event;
  const url = new URL(request.url);

  // Only handle same-origin requests
  if (url.origin !== self.location.origin) return;

  // POST to /api/sales — queue offline if network fails
  if (request.method === 'POST' && url.pathname.startsWith('/api/sales')) {
    event.respondWith(networkOrQueue(request));
    return;
  }

  // API routes — Network-First
  if (API_ROUTES.some(r => url.pathname.startsWith(r))) {
    event.respondWith(networkFirst(request, DATA_CACHE));
    return;
  }

  // Images — Stale-While-Revalidate
  if (/\.(png|jpg|jpeg|gif|webp|svg|ico)$/.test(url.pathname)) {
    event.respondWith(staleWhileRevalidate(request, IMAGE_CACHE));
    return;
  }

  // App Shell — Cache-First
  event.respondWith(cacheFirst(request, SHELL_CACHE));
});

// ─────────────────────────────────────────────────────────────
// STRATEGIES
// ─────────────────────────────────────────────────────────────

/** Cache-First: serve from cache, fall back to network */
async function cacheFirst(request, cacheName) {
  const cached = await caches.match(request);
  if (cached) return cached;

  try {
    const response = await fetch(request);
    if (response.ok) {
      const cache = await caches.open(cacheName);
      cache.put(request, response.clone());
    }
    return response;
  } catch {
    // If HTML request fails entirely, return offline page
    if (request.headers.get('Accept')?.includes('text/html')) {
      return caches.match('/offline.html');
    }
    return new Response('Offline', { status: 503 });
  }
}

/** Network-First: try network, fall back to cache */
async function networkFirst(request, cacheName) {
  try {
    const response = await fetch(request);
    if (response.ok) {
      const cache = await caches.open(cacheName);
      cache.put(request, response.clone());
    }
    return response;
  } catch {
    const cached = await caches.match(request);
    return cached ?? new Response(
      JSON.stringify({ error: 'offline', message: 'You are offline.' }),
      { status: 503, headers: { 'Content-Type': 'application/json' } }
    );
  }
}

/** Stale-While-Revalidate: serve cache immediately, update in background */
async function staleWhileRevalidate(request, cacheName) {
  const cache  = await caches.open(cacheName);
  const cached = await cache.match(request);

  const fetchPromise = fetch(request).then(response => {
    if (response.ok) cache.put(request, response.clone());
    return response;
  }).catch(() => cached);

  return cached ?? fetchPromise;
}

/**
 * Network-Or-Queue: attempt POST to server.
 * On failure, serialize the sale into IndexedDB sync queue.
 */
async function networkOrQueue(request) {
  try {
    const response = await fetch(request.clone());
    return response;
  } catch {
    // Clone body before reading
    const body = await request.clone().text();
    await addToSyncQueue({
      url:       request.url,
      method:    request.method,
      headers:   Object.fromEntries(request.headers.entries()),
      body,
      queuedAt:  Date.now(),
      uuid:      crypto.randomUUID(),
    });

    return new Response(
      JSON.stringify({
        success:  true,
        offline:  true,
        message:  'Sale queued for sync when connection is restored.',
      }),
      { status: 202, headers: { 'Content-Type': 'application/json' } }
    );
  }
}

// ─────────────────────────────────────────────────────────────
// BACKGROUND SYNC
// ─────────────────────────────────────────────────────────────
self.addEventListener('sync', (event) => {
  if (event.tag === 'pos-offline-sync') {
    console.log('[SW] Background sync triggered');
    event.waitUntil(replayQueuedRequests());
  }
});

/** Replay all queued offline sales against the server */
async function replayQueuedRequests() {
  const queue = await getSyncQueue();
  if (!queue.length) return;

  console.log(`[SW] Replaying ${queue.length} queued request(s)`);

  for (const item of queue) {
    try {
      const response = await fetch(item.url, {
        method:  item.method,
        headers: { ...item.headers, 'X-Offline-UUID': item.uuid },
        body:    item.body,
      });

      if (response.ok) {
        await removeFromSyncQueue(item.id);
        console.log('[SW] Synced item:', item.uuid);

        // Notify open clients
        const clients = await self.clients.matchAll({ type: 'window' });
        clients.forEach(c => c.postMessage({
          type:  'SYNC_SUCCESS',
          uuid:  item.uuid,
        }));
      }
    } catch (err) {
      console.warn('[SW] Sync failed for item:', item.uuid, err);
    }
  }
}

// ─────────────────────────────────────────────────────────────
// MESSAGE HANDLER — Commands from the app
// ─────────────────────────────────────────────────────────────
self.addEventListener('message', async (event) => {
  const { type, payload } = event.data ?? {};

  switch (type) {
    case 'SKIP_WAITING':
      self.skipWaiting();
      break;

    case 'CACHE_PRODUCTS':
      // App sends product list → store in IDB for offline use
      await storeInIDB(STORE_PRODUCTS, 'all', payload);
      event.source?.postMessage({ type: 'PRODUCTS_CACHED', count: payload?.length ?? 0 });
      break;

    case 'CACHE_SETTINGS':
      await storeInIDB(STORE_SETTINGS, 'shop', payload);
      break;

    case 'GET_QUEUE_COUNT':
      const queue = await getSyncQueue();
      event.source?.postMessage({ type: 'QUEUE_COUNT', count: queue.length });
      break;

    case 'FORCE_SYNC':
      await replayQueuedRequests();
      break;
  }
});

// ─────────────────────────────────────────────────────────────
// IndexedDB HELPERS
// ─────────────────────────────────────────────────────────────

function openIDB() {
  return new Promise((resolve, reject) => {
    const req = indexedDB.open(IDB_NAME, IDB_VERSION);

    req.onupgradeneeded = (e) => {
      const db = e.target.result;

      if (!db.objectStoreNames.contains(STORE_SYNC_QUEUE)) {
        const store = db.createObjectStore(STORE_SYNC_QUEUE, { keyPath: 'id', autoIncrement: true });
        store.createIndex('uuid',     'uuid',     { unique: true });
        store.createIndex('queuedAt', 'queuedAt', { unique: false });
      }

      if (!db.objectStoreNames.contains(STORE_PRODUCTS)) {
        db.createObjectStore(STORE_PRODUCTS, { keyPath: 'key' });
      }

      if (!db.objectStoreNames.contains(STORE_SETTINGS)) {
        db.createObjectStore(STORE_SETTINGS, { keyPath: 'key' });
      }

      if (!db.objectStoreNames.contains(STORE_DRAFTS)) {
        const drafts = db.createObjectStore(STORE_DRAFTS, { keyPath: 'id', autoIncrement: true });
        drafts.createIndex('name', 'name', { unique: false });
      }
    };

    req.onsuccess = () => resolve(req.result);
    req.onerror   = () => reject(req.error);
  });
}

async function addToSyncQueue(item) {
  const db = await openIDB();
  return new Promise((resolve, reject) => {
    const tx   = db.transaction(STORE_SYNC_QUEUE, 'readwrite');
    const req  = tx.objectStore(STORE_SYNC_QUEUE).add(item);
    req.onsuccess = () => resolve(req.result);
    req.onerror   = () => reject(req.error);
  });
}

async function getSyncQueue() {
  const db = await openIDB();
  return new Promise((resolve, reject) => {
    const tx  = db.transaction(STORE_SYNC_QUEUE, 'readonly');
    const req = tx.objectStore(STORE_SYNC_QUEUE).getAll();
    req.onsuccess = () => resolve(req.result);
    req.onerror   = () => reject(req.error);
  });
}

async function removeFromSyncQueue(id) {
  const db = await openIDB();
  return new Promise((resolve, reject) => {
    const tx  = db.transaction(STORE_SYNC_QUEUE, 'readwrite');
    const req = tx.objectStore(STORE_SYNC_QUEUE).delete(id);
    req.onsuccess = () => resolve();
    req.onerror   = () => reject(req.error);
  });
}

async function storeInIDB(storeName, key, value) {
  const db = await openIDB();
  return new Promise((resolve, reject) => {
    const tx  = db.transaction(storeName, 'readwrite');
    const req = tx.objectStore(storeName).put({ key, value, updatedAt: Date.now() });
    req.onsuccess = () => resolve();
    req.onerror   = () => reject(req.error);
  });
}

console.log('[SW] Service Worker script loaded — version:', CACHE_VERSION);
