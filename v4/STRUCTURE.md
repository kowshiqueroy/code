# POS & Store Management System — Directory Structure

```
pos-system/                          ← Document root parent
│
├── config/                          ← Core configuration (non-public)
│   ├── config.php                   ← Constants: DB, security, paths, autoloader
│   └── DB.php                       ← PDO singleton (DB::run, DB::one, etc.)
│
├── app/
│   ├── controllers/                 ← Request handlers (one file per resource)
│   │   ├── AuthController.php       ← Login, logout, session
│   │   ├── ProductController.php    ← CRUD products + variants
│   │   ├── SaleController.php       ← POS checkout, draft park/recall
│   │   ├── CustomerController.php   ← CRM, loyalty points
│   │   ├── FinanceController.php    ← Ledger entries, petty cash
│   │   ├── ReportController.php     ← Date-range reports
│   │   ├── UserController.php       ← User management (admin)
│   │   ├── SettingsController.php   ← Shop settings
│   │   └── OfflineSyncController.php ← Offline queue review + approve
│   │
│   ├── models/                      ← Data-access layer (PDO queries)
│   │   ├── Product.php
│   │   ├── Sale.php
│   │   ├── Customer.php
│   │   ├── Finance.php
│   │   └── User.php
│   │
│   ├── views/                       ← PHP template partials
│   │   ├── layout/
│   │   │   ├── header.php           ← Common header + nav + connection indicator
│   │   │   └── footer.php           ← Session timer + SW registration script
│   │   ├── pos/
│   │   │   ├── register.php         ← SPA shell (product grid + cart panel)
│   │   │   └── invoice-a4.php       ← A4 landscape, 2-up invoice template
│   │   │   └── invoice-thermal.php  ← Thermal receipt template
│   │   ├── products/
│   │   ├── reports/
│   │   └── auth/
│   │       └── login.php
│   │
│   └── helpers/                     ← Utility classes (autoloaded)
│       ├── Auth.php                 ← Session, login, inactivity timeout
│       ├── CSRF.php                 ← Token generate + verify
│       ├── Sanitize.php             ← Input sanitization
│       ├── AuditLog.php             ← Transparent audit trail writer
│       ├── Response.php             ← JSON API response helper
│       ├── Barcode.php              ← Auto-generate EAN-13 barcodes
│       └── Finance.php             ← Ledger math, balance calculation
│
├── api/                             ← JSON API endpoints (called by JS)
│   ├── products/
│   │   ├── search.php               ← POS product search (name / barcode)
│   │   ├── create.php
│   │   └── stock.php
│   ├── sales/
│   │   ├── create.php               ← Submit sale + write ledger
│   │   ├── draft.php                ← Park / recall drafts
│   │   └── void.php
│   ├── customers/
│   │   └── lookup.php
│   ├── finance/
│   │   └── entry.php
│   └── offline-sync.php             ← Receive queued offline sales
│
├── database/
│   └── schema.sql                   ← Full database schema (run once)
│
├── public/                          ← Web-accessible static assets
│   ├── css/
│   │   ├── app.css                  ← CSS variables, reset, layout, components
│   │   ├── pos.css                  ← POS-specific grid & cart styles
│   │   └── print.css                ← @media print — A4, thermal, sticker
│   ├── js/
│   │   ├── app.js                   ← Global: SW registration, online/offline, inactivity
│   │   ├── pos.js                   ← POS SPA: product grid, cart, payments, drafts
│   │   ├── db.js                    ← IndexedDB wrapper (offline sales store)
│   │   ├── sync.js                  ← Offline → server sync logic
│   │   ├── barcode.js               ← Scanner listener + QR code renderer
│   │   ├── reports.js               ← Date pickers, report rendering
│   │   └── sw.js                    ← Service Worker
│   └── images/
│       ├── logo.png
│       ├── icon-192.png
│       └── icon-512.png
│
├── storage/                         ← Server-side storage (not web accessible)
│   ├── logs/
│   │   ├── php_errors.log
│   │   └── sync.log
│   └── cache/
│
├── offline/                         ← Offline fallback assets (pre-cached)
│
├── index.php                        ← Entry point / dashboard redirect
├── login.php                        ← Login page
├── pos.php                          ← POS SPA shell page
├── products.php                     ← Product management
├── customers.php                    ← CRM
├── finance.php                      ← Finance ledger
├── reports.php                      ← Reports center
├── users.php                        ← User management (admin only)
├── settings.php                     ← Shop settings (admin only)
├── offline.html                     ← Offline fallback page (static)
├── manifest.json                    ← PWA manifest
└── setup.php                        ← One-time installer (DELETE AFTER USE)
```

## Security Perimeter

- `config/`, `app/`, `database/`, `storage/` → protected by `.htaccess` (Deny from all)
- `public/` → only web-accessible directory for static assets
- `api/` → web accessible but every endpoint checks Auth + CSRF
- `setup.php` → delete after installation

## Data Flow: Offline Sale

```
User submits sale (offline)
        ↓
  [pos.js] detects navigator.onLine === false
        ↓
  Generates offline_uid (UUID v4)
        ↓
  Stores full cart in IndexedDB via [db.js]
        ↓
  Prints A4/thermal invoice from localStorage snapshot
        ↓
  Service Worker [sw.js] listens for 'sync' event on reconnect
        ↓
  [sync.js] POSTs to /api/offline-sync.php with payload
        ↓
  Server stores in offline_sync_queue (status=pending)
        ↓
  Admin reviews in /admin/offline-queue
        ↓
  Admin approves → merges into sales + finance_ledger
```

## Ledger Formula

```
Opening Balance
+ Sale Cash
+ Sale Card (tracked separately)
+ Cash In (petty cash income)
- Expenses
- Owner Withdrawals
= Closing Balance
```
