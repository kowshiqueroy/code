// ============================================================
// sw.js — Service Worker (PWA + Offline-First)
// Version bump the CACHE_NAME to force cache refresh on deploy
// ============================================================

const CACHE_NAME    = 'pos-v1.0.0';
const OFFLINE_PAGE  = '/pos/offline.html';

// Core assets to cache on install (App Shell)
const APP_SHELL = [
    '/pos/',
    '/pos/index.php',
    '/pos/offline.html',
    '/pos/manifest.json',
    '/pos/assets/css/main.css',
    '/pos/assets/css/pos.css',
    '/pos/assets/css/print.css',
    '/pos/assets/js/app.js',
    '/pos/assets/js/pos.js',
    '/pos/assets/js/db.js',
    '/pos/assets/js/sync.js',
    '/pos/assets/js/utils.js',
    '/pos/assets/js/qrcode.min.js',
    '/pos/assets/icons/icon-192.png',
    '/pos/assets/icons/icon-512.png',
];

// API routes that should NEVER be cached
const NETWORK_ONLY = [
    '/pos/api/auth',
    '/pos/api/sales/create',
    '/pos/api/offline/sync',
];

// ── Install: Pre-cache App Shell ───────────────────────────
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => {
            console.log('[SW] Pre-caching app shell');
            // Use addAll but don't fail install on individual misses
            return Promise.allSettled(
                APP_SHELL.map(url => cache.add(url).catch(e =>
                    console.warn('[SW] Failed to cache:', url, e)
                ))
            );
        }).then(() => self.skipWaiting())
    );
});

// ── Activate: Clean old caches ─────────────────────────────
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys =>
            Promise.all(
                keys.filter(k => k !== CACHE_NAME)
                    .map(k => {
                        console.log('[SW] Deleting old cache:', k);
                        return caches.delete(k);
                    })
            )
        ).then(() => self.clients.claim())
    );
});

// ── Fetch: Stale-While-Revalidate + Offline Fallback ───────
self.addEventListener('fetch', event => {
    const { request } = event;
    const url = new URL(request.url);

    // Skip non-GET and cross-origin
    if (request.method !== 'GET' || url.origin !== location.origin) return;

    // Network-only for critical API routes
    if (NETWORK_ONLY.some(p => url.pathname.startsWith(p))) {
        event.respondWith(
            fetch(request).catch(() =>
                new Response(JSON.stringify({ error: 'offline', offline: true }),
                    { status: 503, headers: { 'Content-Type': 'application/json' } })
            )
        );
        return;
    }

    // API routes: Network-first, return offline JSON on failure
    if (url.pathname.startsWith('/pos/api/')) {
        event.respondWith(
            fetch(request)
                .then(res => {
                    // Cache successful GET API responses briefly
                    if (res.ok) {
                        const resClone = res.clone();
                        caches.open(CACHE_NAME).then(c => c.put(request, resClone));
                    }
                    return res;
                })
                .catch(() =>
                    caches.match(request).then(cached =>
                        cached || new Response(JSON.stringify({ error: 'offline', offline: true }),
                            { status: 503, headers: { 'Content-Type': 'application/json' } })
                    )
                )
        );
        return;
    }

    // App Shell / static assets: Cache-first, then network, then offline page
    event.respondWith(
        caches.match(request).then(cached => {
            const networkFetch = fetch(request).then(res => {
                if (res.ok && res.type !== 'opaque') {
                    const clone = res.clone();
                    caches.open(CACHE_NAME).then(c => c.put(request, clone));
                }
                return res;
            }).catch(() => null);

            return cached || networkFetch || caches.match(OFFLINE_PAGE);
        })
    );
});

// ── Background Sync (Offline Sale Queue) ───────────────────
self.addEventListener('sync', event => {
    if (event.tag === 'sync-offline-sales') {
        console.log('[SW] Background sync triggered: sync-offline-sales');
        event.waitUntil(syncOfflineSales());
    }
});

async function syncOfflineSales() {
    // Notify all clients to attempt sync
    const clients = await self.clients.matchAll({ type: 'window' });
    clients.forEach(client =>
        client.postMessage({ type: 'TRIGGER_SYNC' })
    );
}

// ── Push Notifications (low-stock alerts, optional) ────────
self.addEventListener('push', event => {
    const data = event.data ? event.data.json() : { title: 'POS Alert', body: '' };
    event.waitUntil(
        self.registration.showNotification(data.title || 'POS', {
            body: data.body || '',
            icon: '/pos/assets/icons/icon-192.png',
            badge: '/pos/assets/icons/icon-192.png',
            data: data.url || '/pos/',
        })
    );
});

self.addEventListener('notificationclick', event => {
    event.notification.close();
    event.waitUntil(clients.openWindow(event.notification.data || '/pos/'));
});

// ── Message Handler (from app) ─────────────────────────────
self.addEventListener('message', event => {
    const { type } = event.data || {};

    if (type === 'SKIP_WAITING') {
        self.skipWaiting();
    }

    if (type === 'CACHE_URLS') {
        const { urls } = event.data;
        event.waitUntil(
            caches.open(CACHE_NAME).then(cache => cache.addAll(urls))
        );
    }
});
