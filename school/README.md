# 🏫 Bangladesh School/College Website — Complete PHP System

A fully modular, bilingual (বাংলা/English), responsive educational website system for Bangladeshi schools and colleges.

---

## 📁 Directory Structure

```
school/
├── index.php                   ← Public router (entry point)
├── setup.php                   ← ONE-TIME installation wizard (DELETE AFTER USE)
├── .htaccess                   ← URL rewriting + security
│
├── config/
│   └── config.php              ← Database + site constants
│
├── includes/
│   └── functions.php           ← All helper functions (DB, image, auth, etc.)
│
├── public/
│   ├── includes/
│   │   ├── header.php          ← Site header, nav, ticker
│   │   └── footer.php          ← Footer with contact, links
│   └── pages/
│       ├── home.php            ← Homepage
│       ├── about.php           ← About Us
│       ├── academic.php        ← Academic (routine, exam, results, departments)
│       ├── administration.php  ← Admin panel (principal, teachers, staff, gov body)
│       ├── admission.php       ← Admissions (rules, forms, fees, jobs)
│       ├── notices.php         ← All notices with filters
│       ├── notice_detail.php   ← Single notice view
│       ├── gallery.php         ← Photo gallery (albums + lightbox)
│       ├── apply.php           ← Online job application form
│       ├── cms_page.php        ← Generic CMS page renderer
│       ├── sidebar_widget.php  ← Reusable sidebar
│       └── 404.php             ← 404 page
│
├── assets/
│   ├── css/
│   │   └── public.css          ← Full responsive public stylesheet
│   ├── js/
│   │   └── public.js           ← Slider, tabs, lightbox, mobile nav
│   └── img/
│       ├── placeholder.png     ← Default image placeholder
│       ├── bd-logo.png         ← Bangladesh government logo (add manually)
│       └── moe-logo.png        ← Ministry of Education logo (add manually)
│
├── admin/
│   ├── index.php               ← Admin panel router + layout
│   ├── login.php               ← Admin login page
│   ├── .htaccess               ← Admin security
│   ├── assets/
│   │   ├── css/admin.css       ← Admin panel styles
│   │   └── js/admin.js         ← Admin interactions
│   └── pages/
│       ├── dashboard.php       ← Statistics + recent activity
│       ├── notices.php         ← Notice CRUD
│       ├── staff.php           ← Staff CRUD + photo upload
│       ├── gallery.php         ← Album + image management
│       ├── banners.php         ← Homepage banner/slider
│       ├── honorees.php        ← Student/Teacher of the year
│       ├── pages.php           ← CMS page editor
│       ├── menus.php           ← Navigation menu builder
│       ├── academic.php        ← Routines, exams, results, departments
│       ├── admissions.php      ← Admission info management
│       ├── applications.php    ← Job application review
│       ├── media.php           ← Media library
│       ├── settings.php        ← Site-wide settings
│       └── users.php           ← Admin user management
│
└── uploads/
    ├── .htaccess               ← Block PHP execution in uploads
    ├── images/                 ← All uploaded images (auto-resized)
    │   └── placeholder.png
    └── documents/              ← PDFs, Word files
```

---

## 🚀 Quick Installation

### 1. Upload Files
Upload all files to your web server (e.g., `/public_html/` or `/code/school/`).

### 2. Create Database
Create a MySQL database (e.g., `school_db`) in cPanel or phpMyAdmin.

### 3. Run Setup
Visit: `http://yoursite.com/setup.php`  
Or locally: `http://localhost/code/school/setup.php`

Fill in:
- Database credentials
- Institute name (English + Bangla)
- Admin password

### 4. ⚠️ DELETE setup.php
After installation, **delete `setup.php`** for security!

### 5. Login to Admin Panel
Visit: `http://yoursite.com/admin/`  
Username: `admin` | Password: (what you set)

---

## 🌐 URL Structure

| Page | URL |
|------|-----|
| Home | `?page=index` |
| About | `?page=about` |
| Academic | `?page=academic` |
| Academic → Routine | `?page=academic&sub=routine` |
| Academic → Results | `?page=academic&sub=results` |
| Administration | `?page=administration` |
| Admins → Teachers | `?page=administration&sub=teachers` |
| Admission | `?page=admission` |
| Admission → Jobs | `?page=admission&sub=jobs` |
| Notices | `?page=notices` |
| Notices (filtered) | `?page=notices&type=job` |
| Gallery | `?page=gallery` |
| Gallery Album | `?page=gallery&album=1` |
| Notice Detail | `?page=notice_detail&id=1` |
| Apply for Job | `?page=apply&notice_id=1` |

**Localhost:** `http://localhost/code/school/?page=index`  
**Subdomain:** `http://school.example.com/?page=index`

---

## 🌏 Language System

- Default language set in Admin → Settings → General
- Visitors can toggle **বাংলা ↔ English** via the topbar or footer
- Language stored in cookie (`lang`) for 1 year
- All content has `title_en` / `title_bn` fields in database
- Helper functions: `t($en, $bn)`, `field($row, 'title')`, `getLang()`

---

## 🖼️ Image Auto-Resizing

PHP GD automatically resizes uploaded images:

| Mode | Size | Crop | Used For |
|------|------|------|----------|
| `portrait` | 300×300 | Yes (1:1) | Staff/teacher photos |
| `banner` | 1200×600 + med + thumb | Yes | Banners |
| `general` (default) | thumb 150×150, medium 600×400, large 1200×800 | thumb only | Gallery, notices |

All images saved as **WebP** for optimal size.

---

## ⚙️ Admin Panel Features

| Section | Features |
|---------|----------|
| Dashboard | Stats, recent notices, pending applications |
| Notices | CRUD, bilingual, types, pin, urgent, file upload, rich text |
| Staff | Photo upload (1:1 crop), categories, sort order |
| Gallery | Albums + multi-image upload, lightbox |
| Banners | Homepage slider management |
| Honorees | Student/Teacher of the Year |
| Pages | CMS page editor with rich text |
| Menus | Drag-and-drop menu builder |
| Academic | Routines, exam schedules, results, departments |
| Admissions | Rules, forms, fee info |
| Applications | View/filter/status job applications + CV download |
| Media | Library with copy-URL, delete |
| Settings | General, Design (colors, fonts, logo), Contact, Display toggles, Social |
| Users | Role-based: editor / admin / superadmin |

---

## 🎨 Design & Branding

Colors configurable in Admin → Settings → Design:
- **Primary**: Government Green `#006a4e` (default — BD government standard)
- **Secondary**: Bangladesh Red `#f42a41`
- **Accent**: Gold `#fdc800`

Font sizes: Small (14px), Medium (16px), Large (18px)

---

## 🔒 Security Features

- Password hashing with `password_hash()` (bcrypt)
- PDO prepared statements (SQL injection prevention)
- `htmlspecialchars()` on all output (XSS prevention)
- CSRF tokens (admin forms)
- File upload type validation (mime_content_type)
- PHP execution blocked in uploads folder
- Directory listing disabled
- `.htaccess` security headers

---

## 🛠️ Server Requirements

| Requirement | Minimum |
|-------------|---------|
| PHP | 8.0+ |
| MySQL | 5.7+ / MariaDB 10.3+ |
| PHP Extensions | PDO, PDO_MySQL, GD, fileinfo |
| Apache/Nginx | mod_rewrite enabled |
| Disk Space | 100MB+ recommended |
| PHP Memory | 128MB minimum |

---

## 📝 Developer Notes

- **Config**: Edit `config/config.php` for DB credentials
- **Base URL**: Auto-detected; works on subfolders
- **Localhost**: Works at `localhost/code/school/`
- **Sessions**: Public uses `school_public`, Admin uses `school_admin_sess`
- **Extensibility**: Add new pages in `public/pages/`, register in `index.php` `$pageMap`
- **Custom Pages**: Create via Admin → Pages, auto-routed by slug

---

## 📞 Adding Government Logos

Place these in `assets/img/`:
- `bd-logo.png` — Bangladesh government emblem
- `moe-logo.png` — Ministry of Education logo

Download from: https://moedu.gov.bd

---

## 👨‍💻 Developer Credit

Built with ❤️ for Bangladeshi educational institutions.  
Pure PHP, no heavy frameworks — fast, reliable, and easy to deploy.

**Customization**: Contact your developer to add custom features.
