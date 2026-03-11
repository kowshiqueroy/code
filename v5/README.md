# POS & Store Management System
## Directory Structure

```
pos-system/                          ← Document root
│
├── 📄 index.php                     ← App shell (SPA entry, requires login)
├── 📄 login.php                     ← Login page
├── 📄 logout.php                    ← Destroys session, redirects
├── 📄 setup.php                     ← One-time installer (DELETE AFTER SETUP)
├── 📄 offline.html                  ← Fallback page shown when fully offline
├── 📄 sw.js                         ← Service Worker (MUST be at root)
├── 📄 manifest.json                 ← PWA manifest
├── 📄 .htaccess                     ← Apache rewrites + security headers
├── 📄 database.sql                  ← Full DB schema (raw SQL)
│
├── 📁 config/                       ← Core framework files (no direct web access)
│   ├── config.php                   ← Main config (constants, loads all below)
│   ├── Database.php                 ← PDO singleton + query helpers
│   ├── Session.php                  ← Secure session + CSRF + auto-logout
│   ├── Security.php                 ← Sanitisation, hashing, tokens
│   ├── Helpers.php                  ← Barcode gen, invoice gen, JSON response
│   └── .setup_done                  ← Created by setup.php (blocks re-run)
│
├── 📁 assets/
│   ├── 📁 css/
│   │   ├── app.css                  ← Design system (variables, layout, components)
│   │   ├── pos.css                  ← POS-specific styles (grid, cart, numpad)
│   │   └── print.css                ← Print media (A4, Thermal, Barcode sheets)
│   │
│   ├── 📁 js/
│   │   ├── app.js                   ← SPA router, module loader, inactivity timer
│   │   ├── pos.js                   ← Cash register logic (cart, payment, invoice)
│   │   ├── db.js                    ← IndexedDB wrapper (offline data)
│   │   ├── offline.js               ← Network detection, sync queue, banner
│   │   ├── qrcode.js                ← QR code generator (canvas-based, no CDN)
│   │   └── barcode.js               ← Barcode renderer (Code128, EAN-13)
│   │
│   └── 📁 icons/                    ← PWA icons (72, 96, 128, 192, 512 px)
│
├── 📁 modules/                      ← Feature modules (each has view + API)
│   │
│   ├── 📁 dashboard/
│   │   └── index.php                ← KPI cards, recent sales, low stock alerts
│   │
│   ├── 📁 pos/
│   │   ├── index.php                ← Full-screen POS SPA view
│   │   └── drafts.php               ← Parked sales management
│   │
│   ├── 📁 products/
│   │   ├── index.php                ← Product list + search
│   │   ├── add.php                  ← Add product (with variants)
│   │   ├── edit.php                 ← Edit product
│   │   ├── barcodes.php             ← Barcode sheet generator
│   │   └── categories.php           ← Category management
│   │
│   ├── 📁 sales/
│   │   ├── index.php                ← Sales history + search
│   │   ├── view.php                 ← Single sale detail + reprint
│   │   └── returns.php              ← Process refunds / returns
│   │
│   ├── 📁 customers/
│   │   ├── index.php                ← Customer CRM list
│   │   ├── view.php                 ← Customer profile + history
│   │   └── loyalty.php              ← Points ledger
│   │
│   ├── 📁 finance/
│   │   ├── index.php                ← Dashboard (cash balance, summary)
│   │   ├── expenses.php             ← Petty cash expenses
│   │   ├── cash-in.php              ← Record external money in
│   │   ├── cash-out.php             ← Record cash withdrawal
│   │   ├── ledger.php               ← Full showroom ledger view
│   │   └── sr-ledger.php            ← Per-SR transaction view
│   │
│   ├── 📁 reports/
│   │   ├── sales.php                ← Sales report (date range)
│   │   ├── inventory.php            ← Stock levels report
│   │   ├── expenses.php             ← Expense report
│   │   ├── sr-performance.php       ← SR performance comparison
│   │   └── balance-sheet.php        ← Full balance sheet
│   │
│   ├── 📁 offline-sync/
│   │   └── index.php                ← Admin review queue for offline orders
│   │
│   ├── 📁 users/
│   │   ├── index.php                ← User management (admin only)
│   │   ├── add.php                  ← Add user / SR
│   │   └── audit-log.php            ← Full audit trail viewer
│   │
│   └── 📁 settings/
│       └── index.php                ← Shop settings panel
│
├── 📁 api/                          ← JSON API endpoints (AJAX / fetch calls)
│   ├── 📁 products/
│   │   ├── search.php               ← GET: search by name/barcode
│   │   ├── get.php                  ← GET: single product details
│   │   ├── save.php                 ← POST: create/update product
│   │   └── delete.php               ← POST: soft-delete product
│   │
│   ├── 📁 sales/
│   │   ├── create.php               ← POST: process a sale
│   │   ├── draft-save.php           ← POST: park/save draft
│   │   ├── draft-load.php           ← GET:  load draft list
│   │   └── offline-sync.php         ← POST: receive offline queue payloads
│   │
│   ├── 📁 customers/
│   │   ├── search.php               ← GET: search customers
│   │   └── save.php                 ← POST: create/update customer
│   │
│   ├── 📁 finance/
│   │   ├── save-expense.php
│   │   └── save-cash-entry.php
│   │
│   └── 📁 sync/
│       └── confirm.php              ← POST: admin confirms offline order
│
├── 📁 templates/                    ← Reusable HTML partials
│   ├── header.php
│   ├── footer.php
│   ├── sidebar.php
│   ├── 📁 invoices/
│   │   ├── a4.php                   ← A4 landscape (2-copy) invoice template
│   │   └── thermal.php              ← Thermal receipt template
│   └── 📁 barcodes/
│       ├── a4.php                   ← A4 barcode sheet template
│       ├── sticker.php              ← Sticker roll template
│       └── custom.php               ← Custom paper template
│
├── 📁 uploads/
│   ├── 📁 logos/                    ← Shop logos
│   └── 📁 products/                 ← Product images
│
└── 📁 logs/
    └── app.log                      ← Application error log
```

---

## Quick Start

1. Upload to your web server / localhost
2. Navigate to `http://yoursite.com/setup.php`
3. Fill in DB credentials, shop info, admin account
4. Click **Install Now**
5. Login at `http://yoursite.com/login.php`
6. The `setup.php` is auto-locked after completion

## Security Checklist

- [ ] Change default admin password immediately after setup
- [ ] Move `config/` above web root if possible  
- [ ] Set `SESSION_SECURE = true` when HTTPS is active
- [ ] Configure `.htaccess` to deny direct access to `/config/`, `/logs/`
- [ ] Delete or protect `setup.php` after installation
- [ ] Set proper DB user permissions (no GRANT, no DROP in production user)
- [ ] Enable HTTPS and add HSTS header

## Environment Variables (Production)

```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=pos_db
DB_USER=pos_user
DB_PASS=YourStrongPasswordHere
APP_ENV=production
```

## Tech Stack

| Layer       | Technology                              |
|-------------|-----------------------------------------|
| Backend     | PHP 8.1+ (strict, no frameworks)        |
| Database    | MySQL 8.0+ via PDO prepared statements  |
| Frontend    | Vanilla JS ES6+, CSS3 Custom Properties |
| Offline     | Service Worker + IndexedDB              |
| PWA         | manifest.json + sw.js                   |
| Print       | CSS @media print + @page rules          |
| Security    | CSRF tokens, bcrypt, PDO, sessions      |
