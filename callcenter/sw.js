/* Service Worker — Ovijat Call Center */
const CACHE_NAME = 'callcenter-v1';
const APP_SHELL = [
  '/code/callcenter/login.php',
  '/code/callcenter/assets/css/app.css',
  '/code/callcenter/assets/js/app.js',
  '/code/callcenter/assets/js/offline.js',
];

// Install: cache app shell
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => cache.addAll(APP_SHELL))
  );
  self.skipWaiting();
});

// Activate: clean old caches
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k)))
    )
  );
  self.clients.claim();
});

// Fetch: network first, fall back to cache
self.addEventListener('fetch', event => {
  const req = event.request;

  // Only intercept GET requests to same origin
  if (req.method !== 'GET' || !req.url.startsWith(self.location.origin)) return;

  // API calls — network only (no cache)
  if (req.url.includes('api.php')) {
    event.respondWith(
      fetch(req).catch(() => new Response(
        JSON.stringify({error: 'offline', message: 'You are offline'}),
        {headers: {'Content-Type': 'application/json'}}
      ))
    );
    return;
  }

  // Static assets — cache first
  if (req.url.includes('/assets/')) {
    event.respondWith(
      caches.match(req).then(cached => cached || fetch(req).then(resp => {
        const clone = resp.clone();
        caches.open(CACHE_NAME).then(c => c.put(req, clone));
        return resp;
      }))
    );
    return;
  }

  // Pages — network first, cache fallback
  event.respondWith(
    fetch(req).then(resp => {
      const clone = resp.clone();
      caches.open(CACHE_NAME).then(c => c.put(req, clone));
      return resp;
    }).catch(() =>
      caches.match(req).then(cached =>
        cached || caches.match('/code/callcenter/login.php')
      )
    )
  );
});

// Message: force cache clear
self.addEventListener('message', event => {
  if (event.data === 'clearCache') {
    caches.delete(CACHE_NAME).then(() => event.ports[0]?.postMessage('cleared'));
  }
});
