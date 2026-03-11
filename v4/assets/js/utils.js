// ============================================================
// utils.js — Shared Utility Functions
// ============================================================
'use strict';

const Utils = (() => {

    // ── Currency Formatting ───────────────────────────────
    const currencySymbol = document.documentElement.dataset.currency || '৳';

    function formatCurrency(amount, symbol = currencySymbol) {
        const num = parseFloat(amount) || 0;
        return `${symbol}${num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    }

    function parseCurrency(str) {
        return parseFloat(String(str).replace(/[^0-9.-]/g, '')) || 0;
    }

    // ── Date / Time ───────────────────────────────────────
    function formatDate(date, fmt = 'DD/MM/YYYY') {
        const d = date instanceof Date ? date : new Date(date);
        if (isNaN(d)) return '';
        const pad = n => String(n).padStart(2, '0');
        return fmt
            .replace('YYYY', d.getFullYear())
            .replace('MM', pad(d.getMonth() + 1))
            .replace('DD', pad(d.getDate()))
            .replace('HH', pad(d.getHours()))
            .replace('mm', pad(d.getMinutes()))
            .replace('ss', pad(d.getSeconds()));
    }

    function nowISO() {
        return new Date().toISOString();
    }

    // ── Debounce ──────────────────────────────────────────
    function debounce(fn, delay = 300) {
        let timer;
        return function (...args) {
            clearTimeout(timer);
            timer = setTimeout(() => fn.apply(this, args), delay);
        };
    }

    // ── Throttle ─────────────────────────────────────────
    function throttle(fn, limit = 100) {
        let inThrottle;
        return function (...args) {
            if (!inThrottle) {
                fn.apply(this, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    }

    // ── DOM Helpers ───────────────────────────────────────
    function $(selector, parent = document) {
        return parent.querySelector(selector);
    }

    function $$(selector, parent = document) {
        return Array.from(parent.querySelectorAll(selector));
    }

    function el(tag, attrs = {}, ...children) {
        const element = document.createElement(tag);
        for (const [k, v] of Object.entries(attrs)) {
            if (k === 'className') element.className = v;
            else if (k === 'innerHTML') element.innerHTML = v;
            else if (k === 'text') element.textContent = v;
            else if (k.startsWith('on')) element.addEventListener(k.slice(2).toLowerCase(), v);
            else element.setAttribute(k, v);
        }
        children.forEach(child => {
            if (child instanceof Node) element.appendChild(child);
            else if (child != null) element.appendChild(document.createTextNode(String(child)));
        });
        return element;
    }

    function clearEl(element) {
        while (element.firstChild) element.removeChild(element.firstChild);
    }

    // ── Template Literal HTML Escape ──────────────────────
    function h(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // ── Number Helpers ────────────────────────────────────
    function round2(n) { return Math.round((parseFloat(n) || 0) * 100) / 100; }
    function clamp(n, min, max) { return Math.min(Math.max(n, min), max); }

    // ── Local Storage (fallback for simple settings) ──────
    const storage = {
        get(key, def = null) {
            try { const v = localStorage.getItem(key); return v !== null ? JSON.parse(v) : def; }
            catch { return def; }
        },
        set(key, value) {
            try { localStorage.setItem(key, JSON.stringify(value)); return true; }
            catch { return false; }
        },
        remove(key) { try { localStorage.removeItem(key); } catch {} },
    };

    // ── Fetch Wrapper ─────────────────────────────────────
    async function apiFetch(url, options = {}) {
        const csrf = document.querySelector('meta[name="csrf"]')?.content || '';
        const defaults = {
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrf,
            },
        };
        const merged = {
            ...defaults,
            ...options,
            headers: { ...defaults.headers, ...(options.headers || {}) },
        };
        const response = await fetch(url, merged);
        if (!response.ok && response.status !== 422) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
    }

    // ── QR Code Generator (pure JS, canvas-based) ─────────
    // Simple QR encoding using the qrcode-generator algorithm
    function generateQRDataURL(text, size = 200) {
        // Requires qrcode.min.js to be loaded
        if (typeof qrcode === 'undefined') {
            console.warn('qrcode library not loaded');
            return '';
        }
        const qr = qrcode(0, 'M');
        qr.addData(text);
        qr.make();
        return qr.createDataURL(4, 0);
    }

    // ── Barcode Renderer (using Canvas) ───────────────────
    function renderBarcode(canvas, code, options = {}) {
        const {
            barWidth = 2,
            barHeight = 60,
            fontSize = 12,
            textMargin = 4,
            background = '#ffffff',
            lineColor = '#000000',
        } = options;

        // Simple Code39-style renderer
        const CODE39_CHARS = {
            '0':'000110100','1':'100100001','2':'001100001','3':'101100000',
            '4':'000110001','5':'100110000','6':'001110000','7':'000100101',
            '8':'100100100','9':'001100100',
            'A':'100001001','B':'001001001','C':'101001000','D':'000011001',
            '-':'010000101',' ':'011000100','*':'010010100',
        };

        const str = '*' + String(code).toUpperCase() + '*';
        let bars = [];
        for (const ch of str) {
            const pattern = CODE39_CHARS[ch] || CODE39_CHARS['0'];
            pattern.split('').forEach((b, i) => {
                bars.push({ wide: b === '1', space: i % 2 === 1 });
            });
            bars.push({ wide: false, space: true }); // inter-char gap
        }

        const totalWidth = bars.reduce((s, b) => s + (b.wide ? barWidth * 3 : barWidth), 0);
        canvas.width  = totalWidth + 20;
        canvas.height = barHeight + fontSize + textMargin + 10;

        const ctx = canvas.getContext('2d');
        ctx.fillStyle = background;
        ctx.fillRect(0, 0, canvas.width, canvas.height);

        let x = 10;
        ctx.fillStyle = lineColor;
        bars.forEach(bar => {
            const w = bar.wide ? barWidth * 3 : barWidth;
            if (!bar.space) ctx.fillRect(x, 5, w, barHeight);
            x += w;
        });

        // Text below
        ctx.fillStyle = lineColor;
        ctx.font = `${fontSize}px monospace`;
        ctx.textAlign = 'center';
        ctx.fillText(String(code), canvas.width / 2, barHeight + textMargin + fontSize);
    }

    // ── Keyboard Shortcut Helper ──────────────────────────
    const shortcuts = {};
    document.addEventListener('keydown', e => {
        const key = [
            e.ctrlKey  ? 'ctrl'  : '',
            e.altKey   ? 'alt'   : '',
            e.shiftKey ? 'shift' : '',
            e.key.toLowerCase()
        ].filter(Boolean).join('+');
        if (shortcuts[key]) {
            e.preventDefault();
            shortcuts[key]();
        }
    });

    function registerShortcut(combo, fn) {
        shortcuts[combo.toLowerCase()] = fn;
    }

    // ── Modal Helper ──────────────────────────────────────
    function openModal(id) {
        const m = document.getElementById(id);
        if (!m) return;
        m.hidden = false;
        m.classList.add('modal--open');
        document.body.classList.add('modal-open');
        // Trap focus
        const focusable = m.querySelectorAll('button, input, select, textarea, [tabindex]:not([tabindex="-1"])');
        if (focusable[0]) focusable[0].focus();
    }

    function closeModal(id) {
        const m = document.getElementById(id);
        if (!m) return;
        m.hidden = true;
        m.classList.remove('modal--open');
        document.body.classList.remove('modal-open');
    }

    // ── Print Helpers ─────────────────────────────────────
    function printElement(elementId, extraCss = '') {
        const el = document.getElementById(elementId);
        if (!el) return;
        const win = window.open('', '_blank', 'width=900,height=700');
        win.document.write(`<!DOCTYPE html><html><head>
            <meta charset="utf-8">
            <title>Print</title>
            <link rel="stylesheet" href="/pos/assets/css/print.css">
            <style>${extraCss}</style>
        </head><body>${el.outerHTML}</body></html>`);
        win.document.close();
        win.onload = () => { win.focus(); win.print(); win.close(); };
    }

    // ── Inactivity Timer ─────────────────────────────────
    let _inactivityTimer = null;
    const INACTIVITY_MS  = 5 * 60 * 1000; // 5 minutes

    function resetInactivityTimer() {
        clearTimeout(_inactivityTimer);
        _inactivityTimer = setTimeout(() => {
            // Ping server to destroy session, then redirect
            fetch('/pos/api/auth/logout.php', { method: 'POST' })
                .finally(() => {
                    alert('Session expired due to inactivity. Please log in again.');
                    window.location.href = '/pos/index.php?page=login&timeout=1';
                });
        }, INACTIVITY_MS);
    }

    function initInactivityWatcher() {
        ['mousemove', 'keydown', 'mousedown', 'touchstart', 'scroll'].forEach(evt =>
            document.addEventListener(evt, resetInactivityTimer, { passive: true })
        );
        resetInactivityTimer();
    }

    return {
        $, $$, el, h, clearEl,
        formatCurrency, parseCurrency,
        formatDate, nowISO,
        debounce, throttle,
        round2, clamp,
        storage,
        apiFetch,
        generateQRDataURL,
        renderBarcode,
        registerShortcut,
        openModal, closeModal,
        printElement,
        initInactivityWatcher,
    };
})();

window.Utils = Utils;
