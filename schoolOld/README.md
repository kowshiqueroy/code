# 🏫 BanglaEdu CMS — Educational Website System

**A complete, production-ready Content Management System for Bangladeshi Schools & Colleges**

---

## 🌟 Features

### Public Website
- ✅ Responsive design (mobile, tablet, desktop)
- ✅ **Bilingual**: Bangla ↔ English toggle (visitor-controlled)
- ✅ Hero slider with auto-play and touch swipe
- ✅ Live notice ticker
- ✅ Homepage: Stats, Notices, Events, Principal's Message, Gallery
- ✅ Pages: Home, About, Academic, Administration, Admissions, Students, Gallery, Notices, Contact
- ✅ Clean URLs: `/?page=about`, `/?page=gallery&tab=videos`
- ✅ Government-compliant design (Bangladesh green/red palette)
- ✅ EIIN, Institute Code display
- ✅ Links to official govt websites (MoEdu, NCTB, Education Board)
- ✅ Print-friendly styles

### Admin Panel (`/admin/`)
- ✅ Secure login with bcrypt password hashing
- ✅ Role-based access: **Editor** / **Admin** / **Super Admin**
- ✅ Full CMS: Pages, Menus, Notices, Events, Gallery
- ✅ Teacher management with **300×300 auto-crop** photo processing
- ✅ Media Library with **multi-size image processing** (thumbnail/medium/large)
- ✅ Sliders, Quick Links, Governing Body management
- ✅ Results & Exam Schedules with PDF upload
- ✅ Class Routines management
- ✅ Admissions info with form file uploads
- ✅ Settings panel (colors, logo, EIIN, stats, social links, map embed)
- ✅ Custom CSS/JS injection per page
- ✅ Contact message inbox
- ✅ **AI Content Assistant** (Claude-powered) for bilingual content generation
- ✅ Department management

---

## 📁 Directory Structure

```
school/
├── index.php              # Public front controller
├── setup.php              # Installation wizard
├── config.php             # Auto-generated config (after setup)
├── .htaccess              # URL rewriting + security
├── includes/
│   └── bootstrap.php      # Core functions, DB, language system
├── templates/
│   ├── header.php         # Site header + navigation
│   ├── footer.php         # Site footer
│   ├── pages/             # Page templates
│   │   ├── index.php      # Home page
│   │   ├── about.php      # About (uses default)
│   │   ├── academic.php   # Academic programs
│   │   ├── administration.php  # Teachers/staff
│   │   ├── admissions.php # Admission info
│   │   ├── students.php   # Routines/results
│   │   ├── gallery.php    # Photo/video gallery
│   │   ├── notices.php    # News & notices
│   │   ├── contact.php    # Contact form
│   │   ├── default.php    # Generic template
│   │   └── 404.php        # Not found
│   └── partials/
│       └── sidebar.php    # Right sidebar widget
├── admin/
│   ├── index.php          # Admin controller
│   ├── views/
│   │   ├── login.php      # Login page
│   │   └── layout.php     # Admin layout
│   └── modules/           # One file per admin section
│       ├── dashboard.php
│       ├── pages.php      # Rich content editor
│       ├── menus.php
│       ├── notices.php
│       ├── events.php
│       ├── gallery.php
│       ├── teachers.php   # With photo resize
│       ├── media.php      # Media library
│       ├── sliders.php
│       ├── settings.php
│       ├── users.php
│       ├── ai_assistant.php
│       ├── messages.php
│       ├── results_admin.php
│       ├── routines.php
│       ├── admissions_admin.php
│       ├── departments.php
│       ├── governing.php
│       └── quick_links.php
└── assets/
    ├── css/style.css      # Main stylesheet
    ├── js/app.js          # Main JavaScript
    └── uploads/           # All uploaded files
        ├── photos/        # Teacher/person photos (300×300)
        ├── documents/     # PDFs and documents
        ├── gallery/       # Gallery images
        ├── sliders/       # Slider images
        ├── media/         # Media library files
        └── logos/         # Site logo
```

---

## 🚀 Installation

### Requirements
- PHP 7.4+ (PHP 8.x recommended)
- MySQL 5.7+ / MariaDB 10.3+
- Apache with mod_rewrite **or** Nginx
- GD Library (for image processing)
- 20MB+ upload limit

### Step 1: Upload Files
Upload the entire `school/` folder to your server's web root or a subdirectory.

### Step 2: Run Setup Wizard
Navigate to: `http://yourdomain.com/setup.php`

Fill in:
- Database credentials
- School/college name (English + Bangla)
- Admin username, password, email

Click **Install** — the database tables and default content will be created automatically.

### Step 3: Delete setup.php
**Important**: Delete `setup.php` after installation for security.

### Step 4: Login to Admin
Go to `http://yourdomain.com/admin/` and log in with your admin credentials.

---

## ⚙️ Configuration

### Localhost Development
Works at any path, including `/code/school/`. No path changes needed — all URLs use root-relative paths with `.htaccess`.

For XAMPP/WAMP: Place in `htdocs/school/` and access at `http://localhost/school/`

### Environment-based URL
The `SITE_URL` is auto-detected from `$_SERVER['HTTP_HOST']` — no manual configuration needed.

### PHP Configuration (php.ini or .htaccess)
```ini
upload_max_filesize = 20M
post_max_size = 25M
max_execution_time = 60
memory_limit = 256M
```

---

## 🎨 Customization

### Colors
Go to **Admin → Settings → Theme** to change primary/secondary/accent colors without touching code.

### Custom Code
- **Per-page**: Admin → Pages → Edit → Custom CSS/JS fields
- **Site-wide**: Admin → Settings → Advanced → Header/Footer code injection

### Adding New Pages
1. Go to Admin → Pages → Add New
2. Set slug (e.g., `achievements`)
3. Write content using the rich editor
4. Add to menu: Admin → Menus → Add Item → select the page

### Image Resize Dimensions
Images are auto-processed to these sizes:

| Context | Thumbnail | Medium | Large |
|---------|-----------|--------|-------|
| General | 200×200 | 600×400 | 1200×800 |
| Teacher/Person | 150×150 | **300×300 (1:1 crop)** | 600×600 |
| Gallery | 250×200 | 700×500 | 1400×1000 |
| Banner/Slider | 300×200 | 800×400 | **1400×600** |

---

## 🌐 URL Structure

All public pages use query parameters for maximum server compatibility:

| Page | URL |
|------|-----|
| Home | `/?page=index` or `/` |
| About | `/?page=about` |
| Gallery Photos | `/?page=gallery&tab=photos` |
| Gallery Videos | `/?page=gallery&tab=videos` |
| Single Notice | `/?page=notices&id=5` |
| Student Routines | `/?page=students&tab=routines` |
| Teachers | `/?page=administration&tab=teachers` |
| Contact | `/?page=contact` |
| Admin | `/admin/` |
| Admin Section | `/admin/?action=notices` |

---

## 🔒 Security Features

- Bcrypt password hashing
- Role-based access control (Editor / Admin / Super Admin)
- Input sanitization on all forms
- Prepared statements (PDO) — no SQL injection
- File upload type whitelisting
- Direct template/includes access blocked via `.htaccess`
- `config.php` protected from direct access
- CSRF token support (session-based)

---

## 🤖 AI Assistant

The AI Content Assistant (Admin → AI Assistant) uses the **Claude API** to generate bilingual content. It works out of the box — no API key needed as the system routes through the CMS backend.

**Content types supported:**
- General page content
- Official notices
- Announcements
- Principal's message
- About Us / History
- Admission information

---

## 📱 Responsive Breakpoints

| Breakpoint | Layout |
|------------|--------|
| > 1100px | Full desktop with sidebar |
| 900–1100px | Compressed desktop |
| 640–900px | Tablet (hamburger menu) |
| < 640px | Mobile (single column) |

---

## 🏛️ Government Compliance

- ✅ Bangladesh national color scheme (green #006B3F, red #F42A41)
- ✅ EIIN and Institute Code display
- ✅ Links to MoEdu, NCTB, Education Board Bangladesh
- ✅ Accessibility (ARIA labels, skip links, keyboard navigation)
- ✅ Print-friendly styles
- ✅ Bangla language support (Hind Siliguri, Noto Sans Bengali fonts)

---

## 🔧 Troubleshooting

**White screen / 500 error**: Enable PHP error display temporarily:
```php
ini_set('display_errors', 1);
error_reporting(E_ALL);
```

**Images not uploading**: Check `assets/uploads/` is writable:
```bash
chmod -R 755 assets/uploads/
```

**GD not available**: Install PHP GD extension:
```bash
sudo apt install php-gd   # Ubuntu/Debian
```

**mod_rewrite not working**: Enable mod_rewrite and `AllowOverride All` in Apache config.

---

## 📄 License

Free to use for educational institutions in Bangladesh.
Built with ❤️ for Bangladeshi schools and colleges.

**BanglaEdu CMS v1.0** | Core PHP | No heavy frameworks | Fast & lightweight
