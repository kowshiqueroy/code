// ============================================================
// app.js — Application Bootstrap, SPA Router, SW Registration
// ============================================================
'use strict';

const App = (() => {

    // ── Service Worker Registration ───────────────────────
    async function registerServiceWorker() {
        if (!('serviceWorker' in navigator)) return;
        try {
            const reg = await navigator.serviceWorker.register('/pos/sw.js', { scope: '/pos/' });
            console.log('[App] SW registered. Scope:', reg.scope);

            // Check for updates
            reg.addEventListener('updatefound', () => {
                const newWorker = reg.installing;
                newWorker.addEventListener('statechange', () => {
                    if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                        showUpdateBanner();
                    }
                });
            });

            // Listen for controller change (after skip waiting)
            navigator.serviceWorker.addEventListener('controllerchange', () => {
                window.location.reload();
            });

        } catch (err) {
            console.warn('[App] SW registration failed:', err);
        }
    }

    function showUpdateBanner() {
        const banner = document.createElement('div');
        banner.className = 'update-banner';
        banner.innerHTML = `
            <span>🔄 New version available!</span>
            <button onclick="navigator.serviceWorker.ready.then(r=>{
                r.waiting?.postMessage({type:'SKIP_WAITING'})
            })">Update Now</button>`;
        document.body.prepend(banner);
    }

    // ── SPA Router ────────────────────────────────────────
    const routes = {
        'pos':       loadPOSView,
        'products':  loadView('products'),
        'inventory': loadView('inventory'),
        'customers': loadView('customers'),
        'sales':     loadView('sales'),
        'finance':   loadView('finance'),
        'reports':   loadView('reports'),
        'settings':  loadView('settings'),
        'users':     loadView('users'),
        'sync':      loadView('sync'),
        'login':     loadLoginView,
    };

    let currentPage = null;

    function getPage() {
        const params = new URLSearchParams(window.location.search);
        return params.get('page') || 'pos';
    }

    async function navigate(page, pushState = true) {
        if (page === currentPage) return;
        currentPage = page;

        // Update active nav
        Utils.$$('.nav-link').forEach(a => {
            a.classList.toggle('nav-link--active', a.dataset.page === page);
        });

        if (pushState) {
            const url = new URL(window.location.href);
            url.searchParams.set('page', page);
            history.pushState({ page }, '', url.toString());
        }

        const main = Utils.$('#main-content');
        if (!main) return;

        main.classList.add('page-loading');

        const handler = routes[page] || loadView(page);
        try {
            await handler(main);
        } catch (err) {
            main.innerHTML = `<div class="error-view">
                <h2>⚠️ Page Error</h2>
                <p>${Utils.h(err.message)}</p>
            </div>`;
            console.error('[App] Route error:', err);
        } finally {
            main.classList.remove('page-loading');
        }
    }

    function loadView(page) {
        return async function (container) {
            const res = await fetch(`/pos/modules/${page}/view.php`);
            if (!res.ok) throw new Error(`Failed to load page: ${page}`);
            container.innerHTML = await res.text();
            // Run any inline init scripts
            Utils.$$('script[data-init]', container).forEach(s => {
                try { eval(s.textContent); } catch {}
            });
        };
    }

    async function loadPOSView(container) {
        const res = await fetch('/pos/modules/pos/view.php');
        if (!res.ok) throw new Error('Failed to load POS view');
        container.innerHTML = await res.text();
        // Init POS with settings
        const settingsRes = await Utils.apiFetch('/pos/api/settings/get.php');
        await POS.init(settingsRes.settings || {});
    }

    async function loadLoginView(container) {
        const res = await fetch('/pos/modules/auth/login.php');
        if (!res.ok) throw new Error('Failed to load login');
        container.innerHTML = await res.text();
    }

    // ── Back/Forward Navigation ───────────────────────────
    window.addEventListener('popstate', e => {
        navigate(e.state?.page || getPage(), false);
    });

    // ── Nav Link Clicks ───────────────────────────────────
    document.addEventListener('click', e => {
        const link = e.target.closest('[data-page]');
        if (!link) return;
        e.preventDefault();
        navigate(link.dataset.page);
    });

    // ── Session Timeout Indicator ─────────────────────────
    function initSessionTimer() {
        const timerEl = Utils.$('#session-timer');
        if (!timerEl) return;

        let remaining = 300; // 5 min
        const interval = setInterval(() => {
            remaining--;
            if (remaining <= 0) {
                clearInterval(interval);
                return;
            }
            if (remaining <= 60) {
                timerEl.textContent = `Session: ${remaining}s`;
                timerEl.classList.add('session-warning');
            }
        }, 1000);

        // Reset on activity
        ['mousemove', 'keydown', 'click'].forEach(evt =>
            document.addEventListener(evt, () => { remaining = 300; timerEl.classList.remove('session-warning'); }, { passive: true })
        );
    }

    // ── Install Prompt (PWA) ──────────────────────────────
    let _installPrompt = null;
    window.addEventListener('beforeinstallprompt', e => {
        e.preventDefault();
        _installPrompt = e;
        const btn = Utils.$('#install-pwa-btn');
        if (btn) {
            btn.hidden = false;
            btn.addEventListener('click', async () => {
                _installPrompt.prompt();
                const { outcome } = await _installPrompt.userChoice;
                if (outcome === 'accepted') btn.hidden = true;
                _installPrompt = null;
            });
        }
    });

    // ── Init ──────────────────────────────────────────────
    async function init() {
        // Register SW
        await registerServiceWorker();

        // Init sync engine
        SyncEngine.init();
        await SyncEngine.registerBackgroundSync();

        // Init inactivity watcher
        Utils.initInactivityWatcher();

        // Init session timer UI
        initSessionTimer();

        // Load initial page
        const page = getPage();
        await navigate(page, false);

        console.log('[App] Initialized. Page:', page);
    }

    return { init, navigate };
})();

// ── Boot on DOM ready ───────────────────────────────────────
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => App.init());
} else {
    App.init();
}
