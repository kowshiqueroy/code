<?php
// ============================================================
// setup.php — Installation & Reset Script
// ============================================================
// Access: http://yoursite/setup.php
// DELETE this file after installation!
// ============================================================

define('SETUP_MODE', true);
$config_path = __DIR__ . '/config/config.php';

// Step handling
$step = $_GET['step'] ?? 'welcome';
$messages = [];

// ── Load config if exists ──────────────────────────────────
if (file_exists($config_path)) {
    require_once $config_path;
}

// ── Functions ──────────────────────────────────────────────
function testConnection($host, $user, $pass, $dbname = ''): array {
    try {
        $dsn = "mysql:host=$host" . ($dbname ? ";dbname=$dbname" : '') . ";charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        return ['ok' => true, 'pdo' => $pdo];
    } catch (PDOException $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

function runSchema(PDO $pdo): void {
    $sql = <<<SQL
-- ─── Settings ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `settings` (
  `key`   VARCHAR(100) PRIMARY KEY,
  `value` LONGTEXT,
  `group` VARCHAR(50) DEFAULT 'general'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Users (Admin) ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `users` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `username`   VARCHAR(60) UNIQUE NOT NULL,
  `password`   VARCHAR(255) NOT NULL,
  `full_name`  VARCHAR(120),
  `email`      VARCHAR(120),
  `role`       ENUM('superadmin','admin','editor') DEFAULT 'editor',
  `status`     TINYINT(1) DEFAULT 1,
  `last_login` DATETIME,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Menus ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `menus` (
  `id`        INT AUTO_INCREMENT PRIMARY KEY,
  `parent_id` INT DEFAULT 0,
  `title_en`  VARCHAR(120) NOT NULL,
  `title_bn`  VARCHAR(120) NOT NULL,
  `page_slug` VARCHAR(120),
  `url`       VARCHAR(255),
  `icon`      VARCHAR(80),
  `target`    VARCHAR(20) DEFAULT '_self',
  `sort_order` INT DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `menu_location` VARCHAR(50) DEFAULT 'main'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Pages ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `pages` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `slug`        VARCHAR(120) UNIQUE NOT NULL,
  `title_en`    VARCHAR(255) NOT NULL,
  `title_bn`    VARCHAR(255) NOT NULL,
  `content_en`  LONGTEXT,
  `content_bn`  LONGTEXT,
  `meta_desc`   TEXT,
  `template`    VARCHAR(60) DEFAULT 'default',
  `is_active`   TINYINT(1) DEFAULT 1,
  `sort_order`  INT DEFAULT 0,
  `created_by`  INT,
  `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Notices ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `notices` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `title_en`    VARCHAR(255) NOT NULL,
  `title_bn`    VARCHAR(255) NOT NULL,
  `content_en`  LONGTEXT,
  `content_bn`  LONGTEXT,
  `type`        ENUM('notice','news','event','job','result','exam','admission','circular') DEFAULT 'notice',
  `file_url`    VARCHAR(500),
  `is_pinned`   TINYINT(1) DEFAULT 0,
  `is_urgent`   TINYINT(1) DEFAULT 0,
  `is_active`   TINYINT(1) DEFAULT 1,
  `publish_date` DATE,
  `expire_date`  DATE,
  `created_by`  INT,
  `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Staff / Personnel ────────────────────────────────────
CREATE TABLE IF NOT EXISTS `staff` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `name_en`       VARCHAR(150) NOT NULL,
  `name_bn`       VARCHAR(150) NOT NULL,
  `designation_en` VARCHAR(150),
  `designation_bn` VARCHAR(150),
  `department_en` VARCHAR(100),
  `department_bn` VARCHAR(100),
  `category`      ENUM('principal','vice_principal','teacher','staff','governing_body','committee') DEFAULT 'teacher',
  `qualification` VARCHAR(255),
  `subject_en`    VARCHAR(150),
  `subject_bn`    VARCHAR(150),
  `phone`         VARCHAR(30),
  `email`         VARCHAR(120),
  `photo`         VARCHAR(255),
  `joining_date`  DATE,
  `bio_en`        TEXT,
  `bio_bn`        TEXT,
  `sort_order`    INT DEFAULT 0,
  `is_active`     TINYINT(1) DEFAULT 1,
  `is_featured`   TINYINT(1) DEFAULT 0,
  `created_at`    DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Gallery ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `gallery_albums` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `title_en`    VARCHAR(200) NOT NULL,
  `title_bn`    VARCHAR(200) NOT NULL,
  `description_en` TEXT,
  `description_bn` TEXT,
  `cover_image` VARCHAR(255),
  `album_date`  DATE,
  `is_active`   TINYINT(1) DEFAULT 1,
  `sort_order`  INT DEFAULT 0,
  `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `gallery_images` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `album_id`   INT NOT NULL,
  `title_en`   VARCHAR(200),
  `title_bn`   VARCHAR(200),
  `filename`   VARCHAR(255) NOT NULL,
  `thumb`      VARCHAR(255),
  `medium`     VARCHAR(255),
  `large`      VARCHAR(255),
  `sort_order` INT DEFAULT 0,
  `is_active`  TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`album_id`) REFERENCES `gallery_albums`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Academic Departments ─────────────────────────────────
CREATE TABLE IF NOT EXISTS `departments` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `name_en`     VARCHAR(150) NOT NULL,
  `name_bn`     VARCHAR(150) NOT NULL,
  `head_id`     INT,
  `description_en` TEXT,
  `description_bn` TEXT,
  `is_active`   TINYINT(1) DEFAULT 1,
  `sort_order`  INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Academic Classes & Routines ─────────────────────────
CREATE TABLE IF NOT EXISTS `class_routines` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `class_name_en` VARCHAR(100) NOT NULL,
  `class_name_bn` VARCHAR(100) NOT NULL,
  `section`     VARCHAR(20),
  `file_url`    VARCHAR(500),
  `session_year` VARCHAR(20),
  `is_active`   TINYINT(1) DEFAULT 1,
  `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Exam Schedules ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS `exam_schedules` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `title_en`    VARCHAR(200) NOT NULL,
  `title_bn`    VARCHAR(200) NOT NULL,
  `exam_type`   VARCHAR(80),
  `class_name`  VARCHAR(80),
  `session_year` VARCHAR(20),
  `start_date`  DATE,
  `end_date`    DATE,
  `file_url`    VARCHAR(500),
  `is_active`   TINYINT(1) DEFAULT 1,
  `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Results ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `results` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `title_en`    VARCHAR(200) NOT NULL,
  `title_bn`    VARCHAR(200) NOT NULL,
  `class_name`  VARCHAR(80),
  `exam_type`   VARCHAR(80),
  `session_year` VARCHAR(20),
  `publish_date` DATE,
  `file_url`    VARCHAR(500),
  `ext_link`    VARCHAR(500),
  `is_active`   TINYINT(1) DEFAULT 1,
  `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Admission Rules / Forms ──────────────────────────────
CREATE TABLE IF NOT EXISTS `admissions` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `title_en`    VARCHAR(200) NOT NULL,
  `title_bn`    VARCHAR(200) NOT NULL,
  `content_en`  LONGTEXT,
  `content_bn`  LONGTEXT,
  `session_year` VARCHAR(20),
  `class_name`  VARCHAR(80),
  `type`        ENUM('rules','form','fee','contact','info') DEFAULT 'info',
  `file_url`    VARCHAR(500),
  `is_active`   TINYINT(1) DEFAULT 1,
  `sort_order`  INT DEFAULT 0,
  `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Media Library ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `media` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `filename`    VARCHAR(255) NOT NULL,
  `original_name` VARCHAR(255),
  `mime_type`   VARCHAR(80),
  `file_size`   INT,
  `thumb`       VARCHAR(255),
  `medium`      VARCHAR(255),
  `large`       VARCHAR(255),
  `alt_text`    VARCHAR(255),
  `folder`      VARCHAR(100) DEFAULT 'general',
  `uploaded_by` INT,
  `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Banners / Slider ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS `banners` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `title_en`   VARCHAR(200),
  `title_bn`   VARCHAR(200),
  `subtitle_en` VARCHAR(300),
  `subtitle_bn` VARCHAR(300),
  `image`      VARCHAR(255),
  `link`       VARCHAR(300),
  `sort_order` INT DEFAULT 0,
  `is_active`  TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Student/Teacher of the Year ─────────────────────────
CREATE TABLE IF NOT EXISTS `honorees` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `name_en`     VARCHAR(150) NOT NULL,
  `name_bn`     VARCHAR(150) NOT NULL,
  `type`        ENUM('student','teacher') DEFAULT 'student',
  `year`        VARCHAR(10),
  `class_name`  VARCHAR(80),
  `achievement` VARCHAR(300),
  `photo`       VARCHAR(255),
  `is_active`   TINYINT(1) DEFAULT 1,
  `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Job Applications ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS `job_applications` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `notice_id`    INT,
  `applicant_name` VARCHAR(150) NOT NULL,
  `email`        VARCHAR(120),
  `phone`        VARCHAR(30),
  `position`     VARCHAR(150),
  `message`      TEXT,
  `cv_file`      VARCHAR(255),
  `status`       ENUM('pending','reviewed','shortlisted','rejected') DEFAULT 'pending',
  `created_at`   DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SQL;

    // Split and execute each statement
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    foreach ($statements as $stmt) {
        if (!empty($stmt)) {
            $pdo->exec($stmt);
        }
    }
}

function seedDefaultData(PDO $pdo, array $data): void {
    // Admin user
    $hash = password_hash($data['admin_pass'], PASSWORD_DEFAULT);
    $pdo->exec("DELETE FROM users WHERE username = 'admin'");
    $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, role) VALUES (?, ?, ?, ?, 'superadmin')");
    $stmt->execute(['admin', $hash, $data['admin_name'], $data['admin_email']]);

    // Default settings
    $settings = [
        ['site_name_en',    $data['site_name_en'],    'general'],
        ['site_name_bn',    $data['site_name_bn'],    'general'],
        ['site_tagline_en', $data['tagline_en'],       'general'],
        ['site_tagline_bn', $data['tagline_bn'],       'general'],
        ['institute_type',  $data['inst_type'],        'general'],
        ['address_en',      $data['address_en'],       'contact'],
        ['address_bn',      $data['address_bn'],       'contact'],
        ['phone',           $data['phone'],             'contact'],
        ['email',           $data['email'],             'contact'],
        ['google_map_embed','',                         'contact'],
        ['default_lang',    'bn',                      'general'],
        ['logo',            '',                        'design'],
        ['favicon',         '',                        'design'],
        ['primary_color',   '#006a4e',                 'design'],
        ['secondary_color', '#f42a41',                 'design'],
        ['font_size',       'medium',                  'design'],
        ['show_news_ticker','1',                       'display'],
        ['show_banner',     '1',                       'display'],
        ['show_principal_msg','1',                     'display'],
        ['show_honorees',   '1',                       'display'],
        ['show_notices',    '1',                       'display'],
        ['show_gallery',    '1',                       'display'],
        ['facebook_url',    '',                        'social'],
        ['youtube_url',     '',                        'social'],
        ['developer_name',  'Your Company',            'general'],
        ['developer_url',   '#',                       'general'],
        ['established_year',$data['estd_year'],        'general'],
        ['eiin_number',     '',                        'general'],
        ['institute_code',  '',                        'general'],
    ];
    $stmt = $pdo->prepare("INSERT INTO settings (`key`,`value`,`group`) VALUES (?,?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`), `group`=VALUES(`group`)");
    foreach ($settings as $s) { $stmt->execute($s); }

    // Default menus
    $menus = [
        [0, 'Home',        'হোম',          'index',      0, 'main'],
        [0, 'About Us',    'আমাদের সম্পর্কে','about',     1, 'main'],
        [0, 'Academic',    'শিক্ষা কার্যক্রম','academic',  2, 'main'],
        [0, 'Administration','প্রশাসন',     'administration',3,'main'],
        [0, 'Admission',   'ভর্তি তথ্য',   'admission',  4, 'main'],
        [0, 'Notices',     'নোটিশ',        'notices',    5, 'main'],
        [0, 'Gallery',     'গ্যালারি',     'gallery',    6, 'main'],
    ];
    $pdo->exec("DELETE FROM menus");
    $sm = $pdo->prepare("INSERT INTO menus (parent_id,title_en,title_bn,page_slug,sort_order,menu_location) VALUES (?,?,?,?,?,'main')");
    $menuIds = [];
    foreach ($menus as $m) {
        $sm->execute([$m[0],$m[1],$m[2],$m[3],$m[4]]);
        $menuIds[$m[3]] = $pdo->lastInsertId();
    }

    // Sub-menus for Academic
    $acadId = $menuIds['academic'];
    $submenus = [
        [$acadId,'Curriculum','পাঠ্যক্রম','academic&sub=curriculum',0],
        [$acadId,'Departments','বিভাগসমূহ','academic&sub=departments',1],
        [$acadId,'Class Routine','ক্লাস রুটিন','academic&sub=routine',2],
        [$acadId,'Exam Schedule','পরীক্ষার সময়সূচি','academic&sub=exam',3],
        [$acadId,'Results','ফলাফল','academic&sub=results',4],
    ];
    $ss = $pdo->prepare("INSERT INTO menus (parent_id,title_en,title_bn,page_slug,sort_order,menu_location) VALUES (?,?,?,?,?,'main')");
    foreach ($submenus as $s) { $ss->execute($s); }

    // Sub-menus for Administration
    $adminId = $menuIds['administration'];
    $adminSubs = [
        [$adminId,'Governing Body','পরিচালনা পর্ষদ','administration&sub=governing_body',0],
        [$adminId,'Principal','অধ্যক্ষ','administration&sub=principal',1],
        [$adminId,'Teachers','শিক্ষকবৃন্দ','administration&sub=teachers',2],
        [$adminId,'Staff','কর্মকর্তা/কর্মচারী','administration&sub=staff',3],
    ];
    foreach ($adminSubs as $s) { $ss->execute($s); }

    // Sub-menus for Admission
    $admId = $menuIds['admission'];
    $admSubs = [
        [$admId,'Admission Rules','ভর্তির নিয়মাবলী','admission&sub=rules',0],
        [$admId,'Application Form','আবেদন ফর্ম','admission&sub=form',1],
        [$admId,'Fee Structure','ফি তালিকা','admission&sub=fees',2],
        [$admId,'Job Openings','চাকরির বিজ্ঞপ্তি','admission&sub=jobs',3],
    ];
    foreach ($admSubs as $s) { $ss->execute($s); }

    // Default pages
    $pages = [
        ['index','Home','হোম','<p>Welcome to our institution.</p>','<p>আমাদের প্রতিষ্ঠানে আপনাকে স্বাগতম।</p>','home'],
        ['about','About Us','আমাদের সম্পর্কে','<h2>History</h2><p>Founded with a vision...</p>','<h2>ইতিহাস</h2><p>একটি দূরদর্শিতা নিয়ে প্রতিষ্ঠিত...</p>','about'],
        ['academic','Academic','শিক্ষা কার্যক্রম','<p>Academic programs and resources.</p>','<p>শিক্ষামূলক কার্যক্রম ও সম্পদ।</p>','academic'],
        ['administration','Administration','প্রশাসন','<p>Our administration team.</p>','<p>আমাদের প্রশাসনিক দল।</p>','administration'],
        ['admission','Admission','ভর্তি তথ্য','<p>Admission information.</p>','<p>ভর্তি সম্পর্কিত তথ্য।</p>','admission'],
        ['notices','Notices','নোটিশ','<p>Latest notices and updates.</p>','<p>সর্বশেষ নোটিশ ও আপডেট।</p>','notices'],
        ['gallery','Gallery','গ্যালারি','<p>Photo gallery.</p>','<p>ফটো গ্যালারি।</p>','gallery'],
    ];
    $sp = $pdo->prepare("INSERT INTO pages (slug,title_en,title_bn,content_en,content_bn,template,is_active) VALUES (?,?,?,?,?,?,1) ON DUPLICATE KEY UPDATE title_en=VALUES(title_en)");
    foreach ($pages as $p) { $sp->execute($p); }

    // Sample notices
    $notices = [
        ['Annual Exam Notice 2025','বার্ষিক পরীক্ষার নোটিশ ২০২৫','Annual examination schedule is now published.','বার্ষিক পরীক্ষার সময়সূচি প্রকাশিত হয়েছে।','exam',0,1],
        ['Admission Open 2025-26','ভর্তি বিজ্ঞপ্তি ২০২৫-২৬','Admission for session 2025-26 is now open.','২০২৫-২৬ শিক্ষাবর্ষে ভর্তি কার্যক্রম শুরু হয়েছে।','admission',1,1],
        ['Teacher Recruitment Notice','শিক্ষক নিয়োগ বিজ্ঞপ্তি','Applications are invited for teaching positions.','শিক্ষক পদে আবেদন আহ্বান করা হচ্ছে।','job',0,1],
        ['Holiday Notice','ছুটির বিজ্ঞপ্তি','Institution will remain closed on national holidays.','জাতীয় ছুটির দিনে প্রতিষ্ঠান বন্ধ থাকবে।','notice',0,0],
    ];
    $sn = $pdo->prepare("INSERT INTO notices (title_en,title_bn,content_en,content_bn,type,is_pinned,is_urgent,publish_date,is_active) VALUES (?,?,?,?,?,?,?,CURDATE(),1)");
    foreach ($notices as $n) { $sn->execute($n); }

    // Sample banners
    $banners = [
        ['Welcome to Our Institution','আমাদের প্রতিষ্ঠানে আপনাকে স্বাগতম','Nurturing minds, building futures','মেধার পরিচর্যা, ভবিষ্যৎ গড়ার প্রতিষ্ঠান',1],
        ['Academic Excellence','শিক্ষার উৎকর্ষতা','Committed to quality education','মানসম্পন্ন শিক্ষার প্রতি অঙ্গীকারবদ্ধ',2],
        ['Admission Open','ভর্তি চলছে','Session 2025-26 admission is open','২০২৫-২৬ শিক্ষাবর্ষে ভর্তি চলছে',3],
    ];
    $sb = $pdo->prepare("INSERT INTO banners (title_en,title_bn,subtitle_en,subtitle_bn,sort_order,is_active) VALUES (?,?,?,?,?,1)");
    foreach ($banners as $b) { $sb->execute($b); }
}

function writeConfig(array $data): bool {
    $content = '<?php' . PHP_EOL;
    $content .= '// ============================================================' . PHP_EOL;
    $content .= '// config.php — Auto-generated by setup.php' . PHP_EOL;
    $content .= '// ============================================================' . PHP_EOL . PHP_EOL;
    $content .= "define('DB_HOST', " . var_export($data['db_host'], true) . ");" . PHP_EOL;
    $content .= "define('DB_USER', " . var_export($data['db_user'], true) . ");" . PHP_EOL;
    $content .= "define('DB_PASS', " . var_export($data['db_pass'], true) . ");" . PHP_EOL;
    $content .= "define('DB_NAME', " . var_export($data['db_name'], true) . ");" . PHP_EOL;
    $content .= "define('DB_CHARSET', 'utf8mb4');" . PHP_EOL . PHP_EOL;

    // Keep rest of original config
    $original = file_get_contents(__DIR__ . '/config/config.php');
    // Remove lines up to and including DB_CHARSET define
    $lines = explode(PHP_EOL, $original);
    $skip = true;
    $rest = [];
    foreach ($lines as $line) {
        if ($skip && strpos($line, "define('DB_CHARSET'") !== false) { $skip = false; continue; }
        if (!$skip) $rest[] = $line;
    }
    $content .= implode(PHP_EOL, array_slice($rest, 1));

    return file_put_contents(__DIR__ . '/config/config.php', $content) !== false;
}

// ── Handle POST ───────────────────────────────────────────
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'install') {
        $db_host = trim($_POST['db_host'] ?? 'localhost');
        $db_user = trim($_POST['db_user'] ?? '');
        $db_pass = $_POST['db_pass'] ?? '';
        $db_name = trim($_POST['db_name'] ?? 'school_db');

        // Test connection (create DB if not exists)
        $test = testConnection($db_host, $db_user, $db_pass);
        if (!$test['ok']) {
            $error = 'Database connection failed: ' . $test['error'];
        } else {
            $pdo = $test['pdo'];
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `$db_name`");

            try {
                runSchema($pdo);
                seedDefaultData($pdo, $_POST);
                $success = 'Installation complete! Please delete setup.php now.';
            } catch (Exception $e) {
                $error = 'Schema error: ' . $e->getMessage();
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>School Website — Setup</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',sans-serif;background:#0f4c35;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.card{background:#fff;border-radius:12px;padding:40px;max-width:680px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,.3)}
.logo{text-align:center;margin-bottom:30px}
.logo h1{color:#006a4e;font-size:26px;font-weight:700}
.logo p{color:#666;font-size:14px;margin-top:4px}
.badge{display:inline-block;background:#f42a41;color:#fff;font-size:11px;padding:2px 8px;border-radius:20px;vertical-align:middle;margin-left:6px}
h2{font-size:15px;color:#333;margin:24px 0 12px;padding-bottom:6px;border-bottom:2px solid #006a4e;text-transform:uppercase;letter-spacing:.5px}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
@media(max-width:500px){.grid{grid-template-columns:1fr}}
label{display:block;font-size:13px;color:#555;margin-bottom:4px;font-weight:600}
input,select{width:100%;padding:10px 12px;border:1.5px solid #ddd;border-radius:6px;font-size:14px;outline:none;transition:border-color .2s}
input:focus,select:focus{border-color:#006a4e}
.full{grid-column:1/-1}
.btn{display:block;width:100%;padding:14px;background:#006a4e;color:#fff;font-size:16px;font-weight:700;border:none;border-radius:8px;cursor:pointer;margin-top:24px;letter-spacing:.5px;transition:background .2s}
.btn:hover{background:#00503a}
.alert{padding:14px;border-radius:8px;margin-bottom:20px;font-size:14px}
.alert.err{background:#fee;border:1px solid #f42a41;color:#c00}
.alert.ok{background:#e6f4ea;border:1px solid #006a4e;color:#006a4e;font-weight:600}
.warn{background:#fff3cd;border:1px solid #ffc107;border-radius:8px;padding:12px;margin-bottom:20px;font-size:13px;color:#856404}
.steps{display:flex;gap:8px;margin-bottom:28px;justify-content:center}
.step{padding:6px 16px;border-radius:20px;font-size:13px;background:#f0f0f0;color:#999}
.step.active{background:#006a4e;color:#fff;font-weight:700}
</style>
</head>
<body>
<div class="card">
  <div class="logo">
    <h1>🏫 School Website Setup <span class="badge">v1.0</span></h1>
    <p>One-time installation wizard for Bangladeshi Schools & Colleges</p>
  </div>

  <div class="steps">
    <div class="step active">① Configure</div>
    <div class="step">② Install</div>
    <div class="step">③ Done</div>
  </div>

  <?php if ($error): ?>
  <div class="alert err">❌ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?php if ($success): ?>
  <div class="alert ok">✅ <?= htmlspecialchars($success) ?><br><br>
    <a href="admin/" style="color:#006a4e;font-weight:700">→ Go to Admin Panel</a> &nbsp;|&nbsp;
    <a href="?page=index" style="color:#006a4e;font-weight:700">→ View Website</a>
  </div>
  <div class="warn">⚠️ <strong>Security:</strong> Please delete <code>setup.php</code> from your server after installation!</div>
  <?php else: ?>

  <div class="warn">⚠️ This setup will create the database schema and seed default data. Running it again will reset admin credentials but preserve content.</div>

  <form method="POST">
    <input type="hidden" name="action" value="install">

    <h2>🗄️ Database</h2>
    <div class="grid">
      <div><label>DB Host</label><input name="db_host" value="localhost" required></div>
      <div><label>DB Name</label><input name="db_name" value="school_db" required></div>
      <div><label>DB Username</label><input name="db_user" value="root" required></div>
      <div><label>DB Password</label><input type="password" name="db_pass" placeholder="(empty for localhost)"></div>
    </div>

    <h2>🏫 Institute Information</h2>
    <div class="grid">
      <div><label>Institute Name (English)</label><input name="site_name_en" value="XYZ High School" required></div>
      <div><label>Institute Name (বাংলা)</label><input name="site_name_bn" value="এক্সওয়াইজেড উচ্চ বিদ্যালয়" required></div>
      <div><label>Tagline (English)</label><input name="tagline_en" value="Excellence in Education"></div>
      <div><label>Tagline (বাংলা)</label><input name="tagline_bn" value="শিক্ষায় উৎকর্ষতা"></div>
      <div><label>Established Year</label><input name="estd_year" value="1975"></div>
      <div><label>Institute Type</label>
        <select name="inst_type">
          <option value="school">School (প্রাথমিক/মাধ্যমিক)</option>
          <option value="college">College (উচ্চ মাধ্যমিক)</option>
          <option value="school_college">School & College</option>
          <option value="madrasa">Madrasa (মাদ্রাসা)</option>
          <option value="technical">Technical Institute</option>
          <option value="university">University/College</option>
        </select>
      </div>
      <div class="full"><label>Address (English)</label><input name="address_en" value="Village, Upazila, District, Bangladesh"></div>
      <div class="full"><label>Address (বাংলা)</label><input name="address_bn" value="গ্রাম, উপজেলা, জেলা, বাংলাদেশ"></div>
      <div><label>Phone</label><input name="phone" value="+880-XXX-XXXXXXX"></div>
      <div><label>Email</label><input type="email" name="email" value="info@school.edu.bd"></div>
    </div>

    <h2>🔑 Admin Account</h2>
    <div class="grid">
      <div><label>Admin Full Name</label><input name="admin_name" value="Administrator" required></div>
      <div><label>Admin Email</label><input type="email" name="admin_email" value="admin@school.edu.bd"></div>
      <div><label>Username</label><input value="admin" readonly style="background:#f9f9f9;color:#999"></div>
      <div><label>Password</label><input type="password" name="admin_pass" placeholder="Strong password" required minlength="8"></div>
    </div>

    <button type="submit" class="btn">🚀 Install Now</button>
  </form>
  <?php endif; ?>
</div>
</body>
</html>
