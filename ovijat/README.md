# OVIJAT GROUP — Complete Web System
### Production-Ready Core PHP + MySQL Corporate Website

---

## 📁 COMPLETE FILE STRUCTURE

```
ovijat/
│
├── index.php                        ← Front Controller / Router (ALL public traffic)
├── setup.php                        ← ⚠️ Run ONCE to install DB & seed data, then DELETE
├── .htaccess                        ← Apache routing, security headers, caching
├── robots.txt                       ← Blocks /admin/ from search engines
│
├── includes/
│   └── config.php                   ← DB (PDO singleton), image processing, helpers
│
├── public/                          ← All public page templates
│   ├── home.php                     ← Homepage (hero, stats, products, rice, concerns, global, mgmt)
│   ├── products.php                 ← Product catalog top-level categories
│   ├── category.php                 ← Sub-categories + products within a category
│   ├── product_detail.php           ← Single product page
│   ├── rice.php                     ← Premium Rice Showcase
│   ├── concerns.php                 ← Sister Concerns
│   ├── global.php                   ← Global Presence map
│   ├── management.php               ← Leadership profiles
│   ├── contact.php                  ← Contact page + inquiry form
│   ├── careers.php                  ← Job listings (auto-expires)
│   ├── apply.php                    ← Job application form (TEXT ONLY, no file uploads)
│   └── partials/
│       ├── header.php               ← Site header: ticker, topbar, nav, popup
│       └── footer.php               ← Site footer: links, contact, social
│
├── admin/                           ← 🔐 Admin Panel (session protected)
│   ├── index.php                    ← Login page
│   ├── auth.php                     ← Session guard (include in every admin page)
│   ├── dashboard.php                ← Stats overview + recent activity
│   ├── settings.php                 ← Site name, logo, contact, social, SEO, lang
│   ├── banners.php                  ← Hero slider banners (CRUD + image upload)
│   ├── ticker.php                   ← News ticker items (CRUD + date gates)
│   ├── popup.php                    ← Event popups (CRUD + strict date gating)
│   ├── categories.php               ← Product categories (parent + sub)
│   ├── products.php                 ← Products (full CRUD + paginated list)
│   ├── rice.php                     ← Rice Showcase entries
│   ├── concerns.php                 ← Sister Concerns
│   ├── global.php                   ← Global Presence countries
│   ├── management.php               ← Management Profiles
│   ├── contacts.php                 ← Local + Export Sales Contacts
│   ├── jobs.php                     ← Job Listings (with expiry dates)
│   ├── applications.php             ← View/manage job applications
│   ├── inquiries.php                ← View/manage contact inquiries
│   ├── .htaccess                    ← Disable indexing, add no-cache headers
│   └── partials/
│       ├── admin_header.php         ← Admin layout: sidebar + topbar
│       └── admin_footer.php         ← Closes admin layout + loads admin.js
│
├── assets/
│   ├── css/
│   │   ├── main.css                 ← Public CSS (983 lines, full responsive)
│   │   └── admin.css                ← Admin CSS (330 lines)
│   └── js/
│       ├── main.js                  ← Public JS: loader, slider, nav, hamburger
│       └── admin.js                 ← Admin JS: sidebar, image preview, confirmations
│
└── uploads/                         ← All admin-uploaded media (auto-organized)
    ├── .htaccess                    ← ⚠️ CRITICAL: Blocks PHP execution in uploads
    ├── banners/                     ← Hero banners → 1600×640px (auto-cropped)
    ├── logos/                       ← Site logo → 400×160px (letterbox)
    ├── products/                    ← Products + categories → 600×600px (square crop)
    ├── management/                  ← Portraits → 400×480px (crop)
    ├── concerns/                    ← Logos → 300×180px (crop)
    ├── popup/                       ← Popup images → 800×600px (crop)
    └── rice/                        ← Rice showcase → 700×500px (crop)
```

---

## 🚀 INSTALLATION STEPS

### Step 1 — Server Requirements
- PHP 8.1+ with GD extension enabled (`php_gd2` / `ext-gd`)
- MySQL 5.7+ or MariaDB 10.3+
- Apache with `mod_rewrite` enabled
- `AllowOverride All` in Apache config for `.htaccess`

### Step 2 — Configure Database
Open `includes/config.php` and update:
```php
define('DB_HOST', 'localhost');     // your DB host
define('DB_NAME', 'ovijat_db');    // your DB name
define('DB_USER', 'root');         // your DB username
define('DB_PASS', '');             // your DB password
```

### Step 3 — Configure Site URL
In `includes/config.php`:
```php
define('SITE_URL', 'http://localhost/ovijat');  // your full URL, no trailing slash
```

### Step 4 — Set Upload Permissions
```bash
chmod 755 uploads/
chmod 755 uploads/banners/ uploads/logos/ uploads/products/
chmod 755 uploads/management/ uploads/concerns/ uploads/popup/ uploads/rice/
```

### Step 5 — Run Setup
Visit: `http://yoursite.com/ovijat/setup.php`

This will:
- Create all 15 database tables
- Insert default settings
- Create admin account: **username: admin | password: Admin@123**
- Insert demo data for all sections

### Step 6 — ⚠️ DELETE setup.php
```bash
rm setup.php
```

### Step 7 — Change Admin Password
Login at `/admin/` → This system uses bcrypt password hashing.
To change password, run in MySQL:
```sql
UPDATE admins SET password = '$2y$10$...' WHERE username = 'admin';
```
Or add a change-password page (see extension notes below).

---

## 🔐 SECURITY ARCHITECTURE

| Layer | Implementation |
|-------|----------------|
| **SQL Injection** | PDO prepared statements on all queries |
| **XSS** | `htmlspecialchars()` via `e()` on all output |
| **CSRF** | Token per session, verified on every POST |
| **File Upload** | MIME type check + GD re-encoding + `.htaccess` PHP block |
| **Path Traversal** | Filenames are UUID-generated, user input never used as path |
| **Session** | `session_regenerate_id()` on login, IP binding |
| **Admin Auth** | Session guard `requireAdmin()` on every admin page |
| **PHP in Uploads** | `uploads/.htaccess` blocks ALL script execution |
| **Directory Listing** | Disabled via `Options -Indexes` everywhere |
| **Server Headers** | X-Content-Type-Options, X-Frame-Options, X-XSS-Protection |

---

## 🖼️ IMAGE PROCESSING SYSTEM

All uploaded images are automatically processed by `processUploadedImage()` in `config.php`:

| Section | Output Size | Method |
|---------|-------------|--------|
| Banner | 1600 × 640 px | Smart center-crop |
| Product | 600 × 600 px | Square center-crop |
| Logo | 400 × 160 px | Fit (letterbox, no crop) |
| Management | 400 × 480 px | Portrait center-crop |
| Concern Logo | 300 × 180 px | Landscape center-crop |
| Popup | 800 × 600 px | Center-crop |
| Rice | 700 × 500 px | Center-crop |
| Sales Contact | 300 × 360 px | Portrait center-crop |

All images are **converted to WebP** (85% quality) for optimal performance.
Old images are automatically deleted when replaced.

---

## 🌐 ROUTING LOGIC

All public traffic → `index.php` (front controller) via `.htaccess`

| URL | Route | File |
|-----|-------|------|
| `/` or `/?page=home` | Homepage | `public/home.php` |
| `/?page=products` | All categories | `public/products.php` |
| `/?page=category&id=X` | Sub-cats + products | `public/category.php` |
| `/?page=product&id=X` | Product detail | `public/product_detail.php` |
| `/?page=rice` | Rice showcase | `public/rice.php` |
| `/?page=concerns` | Sister concerns | `public/concerns.php` |
| `/?page=global` | Global presence | `public/global.php` |
| `/?page=management` | Leadership | `public/management.php` |
| `/?page=contact` | Contact + form | `public/contact.php` |
| `/?page=careers` | Job listings | `public/careers.php` |
| `/?page=apply&job=X` | Application form | `public/apply.php` |
| `/?page=lang&l=en\|bn` | Language toggle | `index.php` (cookie + redirect) |

Admin routes are direct file access: `/admin/dashboard.php`, etc.

---

## 🗣️ BILINGUAL SYSTEM (EN/BN)

- Default language stored in `settings` table (`default_lang`)
- User preference stored in cookie `ovijat_lang` (1-year expiry)
- Toggle via header: `/?page=lang&l=en` or `/?page=lang&l=bn`
- DB columns follow pattern: `field_en` / `field_bn`
- Helper `t($row, 'field')` auto-picks correct column based on active language
- Helper `lang()` reads cookie → fallback to DB default

---

## 📢 NEWS TICKER

- Items stored in `ticker_items` table with optional `start_date` / `end_date`
- Global on/off switch via `settings.ticker_enabled`
- Animation: Pure CSS `@keyframes ticker` marquee — **zero JS, zero layout shift**
- Pauses on hover via CSS `animation-play-state`
- Admin: `/admin/ticker.php`

---

## 🎉 EVENT POPUP

- Stored in `event_popups` with `start_date` and `end_date` (strict DB gating)
- PHP checks date range before rendering popup HTML
- JS checks `localStorage` key `ovijat_popup_seen_YYYY-MM-DD`
- Shows **once per day per browser** — resets at midnight
- Renders with CSS animation (`popupIn` keyframe)
- Admin: `/admin/popup.php`

---

## 💼 JOB PORTAL

- Jobs have `expires_at` date — automatically hidden from public past deadline
- Application form: **text fields only** — `enctype` is NOT `multipart/form-data`
- `$_FILES` check: any file upload attempt is blocked server-side
- Skills are a whitelist-validated checkbox array stored as JSON
- Admin can filter applications by job
- Admin: `/admin/jobs.php` + `/admin/applications.php`

---

## 🎨 DESIGN SYSTEM

| Token | Value | Usage |
|-------|-------|-------|
| `--green-deep` | `#0d3b2e` | Primary dark, headers, footer |
| `--green-mid` | `#1a5c44` | Buttons, links, nav |
| `--green-light` | `#2d8c62` | Accents, hover states |
| `--gold` | `#c9a84c` | Premium accents, CTAs |
| `--gold-light` | `#e8c86a` | Light gold highlights |

**Typography:**
- Display/Headings: `Playfair Display` (serif elegance)
- Body/UI: `DM Sans` (modern, clean)
- Bengali: `Hind Siliguri` (optimized Bangla)

---

## ⚙️ EXTENSION NOTES

**To add admin password change:**
Create `admin/change_password.php` with:
```php
// Verify old password, hash new with password_hash(), UPDATE admins SET password=?
```

**To add sitemap:**
Create `sitemap.php` that queries all categories, products, jobs and outputs XML.

**To add email notifications:**
Use PHP `mail()` or integrate PHPMailer in `contact.php` and `apply.php` after successful insert.

**To use a CDN for uploads:**
Change `UPLOAD_URL` in `config.php` to your CDN domain.

---

## 📊 DATABASE TABLES (15 total)

`settings` · `admins` · `ticker_items` · `event_popups` · `banners` ·
`product_categories` · `products` · `sister_concerns` · `global_presence` ·
`rice_products` · `management` · `sales_contacts` · `inquiries` · `jobs` · `job_applications`

---

*Built with Core PHP · PDO MySQL · Vanilla JS · Pure CSS · No frameworks*
*~5,500 lines of production code across 44 files*
