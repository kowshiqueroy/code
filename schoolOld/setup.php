<?php
/**
 * BanglaEdu CMS - Setup & Installation
 * Educational Website System for Bangladeshi Schools & Colleges
 * Version 1.0
 */

define('SETUP_MODE', true);
define('APP_ROOT', __DIR__);

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 2) {
    $db_host     = trim($_POST['db_host'] ?? 'localhost');
    $db_name     = trim($_POST['db_name'] ?? '');
    $db_user     = trim($_POST['db_user'] ?? '');
    $db_pass     = trim($_POST['db_pass'] ?? '');
    $site_name   = trim($_POST['site_name'] ?? 'School Name');
    $site_name_bn= trim($_POST['site_name_bn'] ?? '');
    $admin_user  = trim($_POST['admin_user'] ?? 'admin');
    $admin_pass  = trim($_POST['admin_pass'] ?? '');
    $admin_email = trim($_POST['admin_email'] ?? '');

    if (empty($db_name) || empty($db_user) || empty($admin_pass) || empty($admin_email)) {
        $error = 'Please fill in all required fields.';
    } else {
        try {
            $pdo = new PDO("mysql:host=$db_host;charset=utf8mb4", $db_user, $db_pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `$db_name`");

            // ─── SCHEMA ───────────────────────────────────────────────────────────
            $schema = "
            CREATE TABLE IF NOT EXISTS settings (
                `key` VARCHAR(100) PRIMARY KEY,
                `value` TEXT,
                `group` VARCHAR(50) DEFAULT 'general',
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                email VARCHAR(100) UNIQUE NOT NULL,
                full_name VARCHAR(100),
                role ENUM('superadmin','admin','editor') DEFAULT 'editor',
                is_active TINYINT(1) DEFAULT 1,
                last_login DATETIME,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS menus (
                id INT AUTO_INCREMENT PRIMARY KEY,
                parent_id INT DEFAULT 0,
                title_en VARCHAR(100) NOT NULL,
                title_bn VARCHAR(150),
                slug VARCHAR(100),
                url VARCHAR(255),
                page_id INT DEFAULT 0,
                icon VARCHAR(50),
                sort_order INT DEFAULT 0,
                is_active TINYINT(1) DEFAULT 1,
                target VARCHAR(10) DEFAULT '_self',
                menu_location VARCHAR(50) DEFAULT 'main'
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS pages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                slug VARCHAR(100) UNIQUE NOT NULL,
                title_en VARCHAR(200) NOT NULL,
                title_bn VARCHAR(200),
                content_en LONGTEXT,
                content_bn LONGTEXT,
                meta_title VARCHAR(200),
                meta_description TEXT,
                template VARCHAR(50) DEFAULT 'default',
                is_published TINYINT(1) DEFAULT 1,
                show_in_menu TINYINT(1) DEFAULT 0,
                custom_css TEXT,
                custom_js TEXT,
                featured_image VARCHAR(255),
                sort_order INT DEFAULT 0,
                created_by INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS notices (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title_en VARCHAR(255) NOT NULL,
                title_bn VARCHAR(255),
                content_en TEXT,
                content_bn TEXT,
                attachment VARCHAR(255),
                category VARCHAR(50) DEFAULT 'general',
                is_important TINYINT(1) DEFAULT 0,
                is_published TINYINT(1) DEFAULT 1,
                publish_date DATE,
                expire_date DATE,
                created_by INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS events (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title_en VARCHAR(255) NOT NULL,
                title_bn VARCHAR(255),
                description_en TEXT,
                description_bn TEXT,
                event_date DATE NOT NULL,
                event_time TIME,
                venue_en VARCHAR(200),
                venue_bn VARCHAR(200),
                image VARCHAR(255),
                is_published TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS teachers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name_en VARCHAR(100) NOT NULL,
                name_bn VARCHAR(100),
                designation_en VARCHAR(100),
                designation_bn VARCHAR(100),
                department_en VARCHAR(100),
                department_bn VARCHAR(100),
                qualification TEXT,
                email VARCHAR(100),
                phone VARCHAR(20),
                photo VARCHAR(255),
                bio_en TEXT,
                bio_bn TEXT,
                is_principal TINYINT(1) DEFAULT 0,
                sort_order INT DEFAULT 0,
                is_active TINYINT(1) DEFAULT 1,
                joined_date DATE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS gallery (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title_en VARCHAR(200),
                title_bn VARCHAR(200),
                description_en TEXT,
                description_bn TEXT,
                media_type ENUM('image','video') DEFAULT 'image',
                file_path VARCHAR(255),
                thumbnail VARCHAR(255),
                video_url VARCHAR(255),
                album_id INT DEFAULT 0,
                sort_order INT DEFAULT 0,
                is_published TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS albums (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title_en VARCHAR(200) NOT NULL,
                title_bn VARCHAR(200),
                description_en TEXT,
                description_bn TEXT,
                cover_image VARCHAR(255),
                media_type ENUM('image','video','mixed') DEFAULT 'image',
                is_published TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS sliders (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title_en VARCHAR(200),
                title_bn VARCHAR(200),
                subtitle_en VARCHAR(200),
                subtitle_bn VARCHAR(200),
                image VARCHAR(255) NOT NULL,
                link VARCHAR(255),
                sort_order INT DEFAULT 0,
                is_active TINYINT(1) DEFAULT 1
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS quick_links (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title_en VARCHAR(100) NOT NULL,
                title_bn VARCHAR(100),
                url VARCHAR(255) NOT NULL,
                icon VARCHAR(50),
                sort_order INT DEFAULT 0,
                is_active TINYINT(1) DEFAULT 1
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS departments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name_en VARCHAR(100) NOT NULL,
                name_bn VARCHAR(100),
                description_en TEXT,
                description_bn TEXT,
                head_teacher_id INT,
                sort_order INT DEFAULT 0,
                is_active TINYINT(1) DEFAULT 1
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS results (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title_en VARCHAR(200) NOT NULL,
                title_bn VARCHAR(200),
                exam_year VARCHAR(10),
                exam_type VARCHAR(50),
                file_path VARCHAR(255),
                external_link VARCHAR(255),
                is_published TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS routines (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title_en VARCHAR(200) NOT NULL,
                title_bn VARCHAR(200),
                class_name VARCHAR(50),
                routine_type ENUM('class','exam') DEFAULT 'class',
                file_path VARCHAR(255),
                academic_year VARCHAR(10),
                is_published TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS admission_info (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title_en VARCHAR(200) NOT NULL,
                title_bn VARCHAR(200),
                content_en LONGTEXT,
                content_bn LONGTEXT,
                class_name VARCHAR(50),
                academic_year VARCHAR(10),
                form_file VARCHAR(255),
                last_date DATE,
                is_active TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS media_library (
                id INT AUTO_INCREMENT PRIMARY KEY,
                filename VARCHAR(255) NOT NULL,
                original_name VARCHAR(255),
                file_path VARCHAR(255) NOT NULL,
                thumb_path VARCHAR(255),
                medium_path VARCHAR(255),
                large_path VARCHAR(255),
                file_type VARCHAR(50),
                file_size INT,
                mime_type VARCHAR(100),
                alt_text VARCHAR(255),
                uploaded_by INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS contact_messages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(100),
                phone VARCHAR(20),
                subject VARCHAR(200),
                message TEXT NOT NULL,
                is_read TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS governing_body (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name_en VARCHAR(100) NOT NULL,
                name_bn VARCHAR(100),
                designation_en VARCHAR(100),
                designation_bn VARCHAR(100),
                photo VARCHAR(255),
                sort_order INT DEFAULT 0,
                is_active TINYINT(1) DEFAULT 1
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ";

            foreach (explode(';', $schema) as $q) {
                $q = trim($q);
                if ($q) $pdo->exec($q);
            }

            // ─── DEFAULT SETTINGS ─────────────────────────────────────────────────
            $defaults = [
                ['site_name_en',       $site_name,         'general'],
                ['site_name_bn',       $site_name_bn ?: $site_name, 'general'],
                ['site_tagline_en',    'Excellence in Education', 'general'],
                ['site_tagline_bn',    'শিক্ষায় শ্রেষ্ঠত্ব',      'general'],
                ['site_email',         $admin_email,        'general'],
                ['site_phone',         '',                  'general'],
                ['site_address_en',    'Bangladesh',        'general'],
                ['site_address_bn',    'বাংলাদেশ',           'general'],
                ['site_logo',          '',                  'general'],
                ['site_favicon',       '',                  'general'],
                ['google_map_embed',   '',                  'contact'],
                ['facebook_url',       '',                  'social'],
                ['default_language',   'en',               'general'],
                ['maintenance_mode',   '0',                'general'],
                ['google_analytics',   '',                  'advanced'],
                ['custom_header_code', '',                  'advanced'],
                ['custom_footer_code', '',                  'advanced'],
                ['institute_type',     'school',           'general'],
                ['established_year',   '',                  'general'],
                ['eiin_number',        '',                  'general'],
                ['institute_code',     '',                  'general'],
                ['primary_color',      '#006B3F',          'theme'],
                ['secondary_color',    '#F42A41',          'theme'],
                ['accent_color',       '#F7A600',          'theme'],
            ];

            $stmt = $pdo->prepare("INSERT IGNORE INTO settings (`key`,`value`,`group`) VALUES (?,?,?)");
            foreach ($defaults as $d) $stmt->execute($d);

            // ─── ADMIN USER ───────────────────────────────────────────────────────
            $hashed = password_hash($admin_pass, PASSWORD_BCRYPT);
            $pdo->prepare("INSERT IGNORE INTO users (username,password,email,full_name,role) VALUES (?,?,?,?,?)")
                ->execute([$admin_user, $hashed, $admin_email, 'Administrator', 'superadmin']);

            // ─── DEFAULT MENUS ────────────────────────────────────────────────────
            $menus = [
                [0,'Home','হোম','index','',1,'main'],
                [0,'About Us','আমাদের সম্পর্কে','about','',2,'main'],
                [0,'Academic','একাডেমিক','academic','',3,'main'],
                [0,'Administration','প্রশাসন','administration','',4,'main'],
                [0,'Admissions','ভর্তি','admissions','',5,'main'],
                [0,'Students','শিক্ষার্থী','students','',6,'main'],
                [0,'Gallery','গ্যালারি','gallery','',7,'main'],
                [0,'News & Notices','সংবাদ ও বিজ্ঞপ্তি','notices','',8,'main'],
                [0,'Contact','যোগাযোগ','contact','',9,'main'],
            ];
            $sm = $pdo->prepare("INSERT IGNORE INTO menus (parent_id,title_en,title_bn,slug,url,sort_order,menu_location) VALUES (?,?,?,?,?,?,?)");
            foreach ($menus as $m) $sm->execute($m);

            // ─── DEFAULT PAGES ────────────────────────────────────────────────────
            $pages_data = [
                ['index','Home','হোম','<p>Welcome to our institution.</p>','<p>আমাদের প্রতিষ্ঠানে আপনাকে স্বাগতম।</p>','home'],
                ['about','About Us','আমাদের সম্পর্কে','<h2>About Our Institution</h2><p>Founded with a vision to provide quality education...</p>','<h2>আমাদের প্রতিষ্ঠান সম্পর্কে</h2><p>মানসম্পন্ন শিক্ষা প্রদানের লক্ষ্যে প্রতিষ্ঠিত...</p>','default'],
                ['academic','Academic','একাডেমিক','<h2>Academic Programs</h2>','<h2>একাডেমিক কার্যক্রম</h2>','default'],
                ['administration','Administration','প্রশাসন','<h2>Administration</h2>','<h2>প্রশাসন</h2>','teachers'],
                ['admissions','Admissions','ভর্তি','<h2>Admissions</h2>','<h2>ভর্তি তথ্য</h2>','default'],
                ['students','Students','শিক্ষার্থী','<h2>Student Resources</h2>','<h2>শিক্ষার্থী তথ্য</h2>','default'],
                ['gallery','Gallery','গ্যালারি','<h2>Gallery</h2>','<h2>গ্যালারি</h2>','gallery'],
                ['notices','News & Notices','সংবাদ ও বিজ্ঞপ্তি','<h2>Notices</h2>','<h2>বিজ্ঞপ্তি</h2>','notices'],
                ['contact','Contact Us','যোগাযোগ করুন','<h2>Contact Us</h2>','<h2>যোগাযোগ করুন</h2>','contact'],
                ['results','Exam Results','পরীক্ষার ফলাফল','<h2>Results</h2>','<h2>ফলাফল</h2>','results'],
            ];
            $sp = $pdo->prepare("INSERT IGNORE INTO pages (slug,title_en,title_bn,content_en,content_bn,template,is_published) VALUES (?,?,?,?,?,?,1)");
            foreach ($pages_data as $p) $sp->execute($p);

            // ─── WRITE CONFIG ─────────────────────────────────────────────────────
            $config = "<?php\n// BanglaEdu CMS Configuration - Auto-generated by Setup\ndefine('DB_HOST', " . var_export($db_host,true) . ");\ndefine('DB_NAME', " . var_export($db_name,true) . ");\ndefine('DB_USER', " . var_export($db_user,true) . ");\ndefine('DB_PASS', " . var_export($db_pass,true) . ");\ndefine('DB_CHARSET', 'utf8mb4');\ndefine('SITE_URL', (isset(\$_SERVER['HTTPS']) && \$_SERVER['HTTPS']==='on' ? 'https' : 'http') . '://' . (\$_SERVER['HTTP_HOST'] ?? 'localhost'));\ndefine('APP_VERSION', '1.0.0');\ndefine('UPLOAD_MAX_SIZE', 10 * 1024 * 1024); // 10MB\n";
            file_put_contents(APP_ROOT . '/config.php', $config);

            header('Location: setup.php?step=3');
            exit;
        } catch (Exception $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>BanglaEdu CMS — Setup</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',sans-serif;background:linear-gradient(135deg,#006B3F 0%,#004d2e 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.card{background:#fff;border-radius:16px;max-width:600px;width:100%;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.3)}
.card-header{background:linear-gradient(135deg,#006B3F,#009954);padding:32px;color:#fff;text-align:center}
.card-header h1{font-size:1.8rem;margin-bottom:8px}
.card-header p{opacity:.85;font-size:.95rem}
.card-body{padding:32px}
.steps{display:flex;gap:0;margin-bottom:28px}
.step{flex:1;text-align:center;padding:10px 0;font-size:.8rem;font-weight:600;color:#999;border-bottom:3px solid #eee;transition:.3s}
.step.active{color:#006B3F;border-color:#006B3F}
.step.done{color:#28a745;border-color:#28a745}
.form-group{margin-bottom:18px}
label{display:block;font-weight:600;font-size:.88rem;color:#333;margin-bottom:6px}
input,select{width:100%;padding:10px 14px;border:2px solid #e0e0e0;border-radius:8px;font-size:.9rem;transition:.2s;outline:none}
input:focus,select:focus{border-color:#006B3F}
.row{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.btn{width:100%;padding:14px;background:linear-gradient(135deg,#006B3F,#009954);color:#fff;border:none;border-radius:8px;font-size:1rem;font-weight:700;cursor:pointer;transition:.2s;margin-top:8px}
.btn:hover{transform:translateY(-1px);box-shadow:0 4px 12px rgba(0,107,63,.4)}
.alert{padding:12px 16px;border-radius:8px;margin-bottom:20px;font-size:.9rem}
.alert-error{background:#fff0f0;border:1px solid #ffcccc;color:#c00}
.alert-success{background:#f0fff4;border:1px solid #b3ffd9;color:#006B3F}
.success-icon{font-size:4rem;text-align:center;margin:20px 0}
.info-text{text-align:center;color:#666;line-height:1.7;margin-bottom:24px}
.links a{display:block;text-align:center;padding:12px;background:#f5f5f5;border-radius:8px;color:#006B3F;text-decoration:none;font-weight:600;margin-bottom:10px;transition:.2s}
.links a:hover{background:#006B3F;color:#fff}
h3{color:#333;margin-bottom:18px;font-size:1.1rem}
.divider{border:none;border-top:1px solid #eee;margin:20px 0}
</style>
</head>
<body>
<div class="card">
  <div class="card-header">
    <h1>🏫 BanglaEdu CMS</h1>
    <p>Educational Website System for Bangladeshi Schools & Colleges</p>
  </div>
  <div class="card-body">
    <div class="steps">
      <div class="step <?= $step>=1?($step>1?'done':'active'):'' ?>">1. Welcome</div>
      <div class="step <?= $step>=2?($step>2?'done':'active'):'' ?>">2. Configure</div>
      <div class="step <?= $step>=3?'active':'' ?>">3. Done</div>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($step === 1): ?>
    <h3>Welcome to Installation</h3>
    <p class="info-text">This wizard will set up BanglaEdu CMS on your server.<br>Please ensure you have your database credentials ready.</p>
    <div style="background:#f8fff8;border:1px solid #d4edda;border-radius:8px;padding:16px;margin-bottom:20px">
      <strong>Requirements Check:</strong>
      <ul style="list-style:none;margin-top:10px">
        <?php
        $checks = [
            'PHP >= 7.4' => version_compare(PHP_VERSION,'7.4','>='),
            'PDO MySQL'  => extension_loaded('pdo_mysql'),
            'GD Library' => extension_loaded('gd'),
            'Writable uploads/' => is_writable(APP_ROOT.'/assets/uploads') || mkdir(APP_ROOT.'/assets/uploads',0755,true),
        ];
        foreach ($checks as $label => $ok):
        ?>
        <li style="padding:4px 0">
          <?= $ok ? '✅' : '❌' ?> <?= $label ?>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <a href="?step=2" class="btn" style="display:block;text-align:center;text-decoration:none">Continue →</a>

    <?php elseif ($step === 2): ?>
    <form method="POST">
      <h3>Database & Site Settings</h3>
      <div class="row">
        <div class="form-group"><label>DB Host *</label><input name="db_host" value="localhost" required></div>
        <div class="form-group"><label>DB Name *</label><input name="db_name" placeholder="school_db" required></div>
      </div>
      <div class="row">
        <div class="form-group"><label>DB Username *</label><input name="db_user" required></div>
        <div class="form-group"><label>DB Password</label><input name="db_pass" type="password"></div>
      </div>
      <hr class="divider">
      <div class="row">
        <div class="form-group"><label>School Name (English) *</label><input name="site_name" placeholder="ABC School & College" required></div>
        <div class="form-group"><label>School Name (Bangla)</label><input name="site_name_bn" placeholder="এবিসি স্কুল ও কলেজ"></div>
      </div>
      <div class="form-group">
        <label>Institute Type</label>
        <select name="institute_type">
          <option value="school">School</option>
          <option value="college">College</option>
          <option value="school_college">School & College</option>
          <option value="madrasha">Madrasha</option>
          <option value="university">University</option>
        </select>
      </div>
      <hr class="divider">
      <div class="row">
        <div class="form-group"><label>Admin Username *</label><input name="admin_user" value="admin" required></div>
        <div class="form-group"><label>Admin Password *</label><input name="admin_pass" type="password" required></div>
      </div>
      <div class="form-group"><label>Admin Email *</label><input name="admin_email" type="email" required></div>
      <button type="submit" class="btn">Install BanglaEdu CMS →</button>
    </form>

    <?php elseif ($step === 3): ?>
    <div class="success-icon">🎉</div>
    <p class="info-text"><strong>Installation Successful!</strong><br>
    BanglaEdu CMS has been installed. Please delete or rename <code>setup.php</code> for security.</p>
    <div class="links">
      <a href="index.php">🌐 View Public Website</a>
      <a href="admin/">⚙️ Open Admin Panel</a>
    </div>
    <p style="text-align:center;color:#c00;font-size:.85rem;margin-top:16px">⚠️ Delete setup.php after installation!</p>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
