<?php
/**
 * OVIJAT GROUP - setup.php
 * Run this file ONCE to create all tables and seed demo data.
 * DELETE this file after successful setup for security.
 * Usage: php setup.php  OR  visit /setup.php in browser
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'ovijat_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ─── Connect ──────────────────────────────────────────────────────────────────
try {
    $pdo = new PDO(
        "mysql:host=".DB_HOST.";charset=".DB_CHARSET,
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `".DB_NAME."` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `".DB_NAME."`");
} catch (PDOException $e) {
    die("DB Connection Failed: ".$e->getMessage());
}

// ─── Schema ───────────────────────────────────────────────────────────────────
$schema = <<<SQL

-- Settings
CREATE TABLE IF NOT EXISTS `settings` (
  `key` VARCHAR(100) PRIMARY KEY,
  `value` TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Admin Users
CREATE TABLE IF NOT EXISTS `admins` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(80) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- News Ticker Items
CREATE TABLE IF NOT EXISTS `ticker_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `text_en` VARCHAR(500) NOT NULL,
  `text_bn` VARCHAR(500) NOT NULL,
  `active` TINYINT(1) DEFAULT 1,
  `start_date` DATE NULL,
  `end_date` DATE NULL,
  `sort_order` INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Event Popups
CREATE TABLE IF NOT EXISTS `event_popups` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title_en` VARCHAR(200) NOT NULL,
  `title_bn` VARCHAR(200) NOT NULL,
  `body_en` TEXT NOT NULL,
  `body_bn` TEXT NOT NULL,
  `image` VARCHAR(255) NULL,
  `active` TINYINT(1) DEFAULT 1,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Hero Banners
CREATE TABLE IF NOT EXISTS `banners` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title_en` VARCHAR(200) NOT NULL,
  `title_bn` VARCHAR(200) NOT NULL,
  `subtitle_en` VARCHAR(300) NULL,
  `subtitle_bn` VARCHAR(300) NULL,
  `image` VARCHAR(255) NOT NULL,
  `link` VARCHAR(255) NULL,
  `active` TINYINT(1) DEFAULT 1,
  `sort_order` INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Product Categories
CREATE TABLE IF NOT EXISTS `product_categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name_en` VARCHAR(150) NOT NULL,
  `name_bn` VARCHAR(150) NOT NULL,
  `image` VARCHAR(255) NULL,
  `parent_id` INT NULL,
  `active` TINYINT(1) DEFAULT 1,
  `sort_order` INT DEFAULT 0,
  FOREIGN KEY (`parent_id`) REFERENCES `product_categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Products
CREATE TABLE IF NOT EXISTS `products` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `category_id` INT NOT NULL,
  `name_en` VARCHAR(200) NOT NULL,
  `name_bn` VARCHAR(200) NOT NULL,
  `desc_en` TEXT NULL,
  `desc_bn` TEXT NULL,
  `weight` VARCHAR(80) NULL,
  `image` VARCHAR(255) NULL,
  `active` TINYINT(1) DEFAULT 1,
  `sort_order` INT DEFAULT 0,
  FOREIGN KEY (`category_id`) REFERENCES `product_categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sister Concerns
CREATE TABLE IF NOT EXISTS `sister_concerns` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name_en` VARCHAR(200) NOT NULL,
  `name_bn` VARCHAR(200) NOT NULL,
  `desc_en` TEXT NULL,
  `desc_bn` TEXT NULL,
  `logo` VARCHAR(255) NULL,
  `website` VARCHAR(255) NULL,
  `active` TINYINT(1) DEFAULT 1,
  `sort_order` INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Global Presence
CREATE TABLE IF NOT EXISTS `global_presence` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `country_en` VARCHAR(150) NOT NULL,
  `country_bn` VARCHAR(150) NOT NULL,
  `flag_emoji` VARCHAR(20) NULL,
  `active` TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Rice Showcase
CREATE TABLE IF NOT EXISTS `rice_products` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name_en` VARCHAR(200) NOT NULL,
  `name_bn` VARCHAR(200) NOT NULL,
  `desc_en` TEXT NULL,
  `desc_bn` TEXT NULL,
  `origin_en` VARCHAR(100) NULL,
  `origin_bn` VARCHAR(100) NULL,
  `image` VARCHAR(255) NULL,
  `active` TINYINT(1) DEFAULT 1,
  `sort_order` INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Management Profiles
CREATE TABLE IF NOT EXISTS `management` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name_en` VARCHAR(200) NOT NULL,
  `name_bn` VARCHAR(200) NOT NULL,
  `title_en` VARCHAR(200) NOT NULL,
  `title_bn` VARCHAR(200) NOT NULL,
  `message_en` TEXT NULL,
  `message_bn` TEXT NULL,
  `image` VARCHAR(255) NULL,
  `active` TINYINT(1) DEFAULT 1,
  `sort_order` INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Contact Sales Persons
CREATE TABLE IF NOT EXISTS `sales_contacts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `type` ENUM('local','export') NOT NULL DEFAULT 'local',
  `name_en` VARCHAR(200) NOT NULL,
  `name_bn` VARCHAR(200) NOT NULL,
  `title_en` VARCHAR(200) NOT NULL,
  `title_bn` VARCHAR(200) NOT NULL,
  `phone` VARCHAR(80) NOT NULL,
  `email` VARCHAR(150) NULL,
  `image` VARCHAR(255) NULL,
  `active` TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Inquiries (Contact Form)
CREATE TABLE IF NOT EXISTS `inquiries` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(200) NOT NULL,
  `email` VARCHAR(150) NULL,
  `phone` VARCHAR(80) NULL,
  `subject` VARCHAR(300) NOT NULL,
  `message` TEXT NOT NULL,
  `ip` VARCHAR(50) NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Job Listings
CREATE TABLE IF NOT EXISTS `jobs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title_en` VARCHAR(300) NOT NULL,
  `title_bn` VARCHAR(300) NOT NULL,
  `department_en` VARCHAR(200) NULL,
  `department_bn` VARCHAR(200) NULL,
  `location_en` VARCHAR(200) NULL,
  `location_bn` VARCHAR(200) NULL,
  `type_en` VARCHAR(100) NULL,
  `type_bn` VARCHAR(100) NULL,
  `desc_en` TEXT NULL,
  `desc_bn` TEXT NULL,
  `salary_range` VARCHAR(100) NULL,
  `active` TINYINT(1) DEFAULT 1,
  `expires_at` DATE NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Job Applications
CREATE TABLE IF NOT EXISTS `job_applications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `job_id` INT NOT NULL,
  `name` VARCHAR(200) NOT NULL,
  `phone` VARCHAR(80) NOT NULL,
  `email` VARCHAR(150) NULL,
  `academic_qualifications` TEXT NOT NULL,
  `work_experience` TEXT NOT NULL,
  `cover_letter` TEXT NOT NULL,
  `skills` TEXT NULL,  -- JSON array of skill keys
  `ip` VARCHAR(50) NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`job_id`) REFERENCES `jobs`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SQL;

foreach (explode(';', $schema) as $q) {
    $q = trim($q);
    if ($q) $pdo->exec($q);
}
echo "✅ Tables created.<br>";

// ─── Seed Settings ────────────────────────────────────────────────────────────
$defaults = [
    'site_name_en'        => 'Ovijat Group',
    'site_name_bn'        => 'অভিজাতগ্রুপ',
    'site_tagline_en'     => 'Nourishing Bangladesh, Reaching the World',
    'site_tagline_bn'     => 'বাংলাদেশকে পুষ্টি দিচ্ছি, বিশ্বে পৌঁছাচ্ছি',
    'logo'                => '',
    'favicon'             => '',
    'helpline'            => '09641000025',
    'email'               => 'info@ovijatfood.com',
    'address_en'          => 'Ovijat Tower, 45 Gulshan Avenue, Dhaka-1212, Bangladesh',
    'address_bn'          => 'অভিজাতটাওয়ার, ৪৫ গুলশান অ্যাভিনিউ, ঢাকা-১২১২, বাংলাদেশ',
    'footer_about_en'     => 'Ovijat Group is one of Bangladesh\'s leading food and beverage conglomerates, committed to quality, safety, and innovation since 2005.',
    'footer_about_bn'     => 'অভিজাতগ্রুপ ২০০৫ সাল থেকে মান, নিরাপত্তা এবং উদ্ভাবনে প্রতিশ্রুতিবদ্ধ বাংলাদেশের শীর্ষস্থানীয় খাদ্য ও পানীয় প্রতিষ্ঠানগুলির একটি।',
    'facebook'            => '#',
    'linkedin'            => '#',
    'youtube'             => '#',
    'default_lang'        => 'en',
    'ticker_enabled'      => '1',
    'meta_keywords'       => 'ovijat group, food bangladesh, rice, beverage',
    'meta_description'    => 'Ovijat Group - Premium Food & Beverage Conglomerate in Bangladesh',
];
$stmt = $pdo->prepare("INSERT IGNORE INTO settings (`key`, `value`) VALUES (?,?)");
foreach ($defaults as $k => $v) $stmt->execute([$k, $v]);
echo "✅ Settings seeded.<br>";

// ─── Admin Account ────────────────────────────────────────────────────────────
$adminPass = password_hash('Admin@123', PASSWORD_BCRYPT);
$pdo->exec("INSERT IGNORE INTO admins (username, password) VALUES ('admin', '$adminPass')");
echo "✅ Admin created → username: <b>admin</b> | password: <b>Admin@123</b><br>";

// ─── Demo Ticker ──────────────────────────────────────────────────────────────
$pdo->exec("INSERT IGNORE INTO ticker_items (id,text_en,text_bn,active,start_date,end_date,sort_order) VALUES
(1,'🌾 Ovijat Premium Aromatic Rice — Now available in 500+ retail stores across Bangladesh!','🌾 অভিজাতপ্রিমিয়াম সুগন্ধি চাল — এখন বাংলাদেশ জুড়ে ৫০০+ রিটেইল স্টোরে পাওয়া যাচ্ছে!',1,NULL,NULL,1),
(2,'🏆 Ovijat Group wins Best FMCG Brand Award 2024 — Thank you Bangladesh!','🏆 অভিজাতগ্রুপ শ্রেষ্ঠ এফএমসিজি ব্র্যান্ড পুরস্কার ২০২৪ জিতেছে — ধন্যবাদ বাংলাদেশ!',1,NULL,NULL,2),
(3,'📦 New Export Deal signed with UAE & Saudi Arabia — Expanding global reach!','📦 সংযুক্ত আরব আমিরাত ও সৌদি আরবের সাথে নতুন রপ্তানি চুক্তি স্বাক্ষরিত!',1,NULL,NULL,3)");
echo "✅ Ticker seeded.<br>";

// ─── Demo Popup ───────────────────────────────────────────────────────────────
$today = date('Y-m-d');
$end = date('Y-m-d', strtotime('+30 days'));
$pdo->exec("INSERT IGNORE INTO event_popups (id,title_en,title_bn,body_en,body_bn,image,active,start_date,end_date) VALUES
(1,'Eid Mubarak 2025','ঈদ মোবারক ২০২৫','Ovijat Group wishes you and your family a blessed Eid ul-Adha. May this special occasion bring joy, health, and prosperity.','অভিজাতগ্রুপ আপনাকে ও আপনার পরিবারকে ঈদ উল-আযহার শুভেচ্ছা জানায়।',NULL,1,'$today','$end')");
echo "✅ Event popup seeded.<br>";

// ─── Demo Banners ─────────────────────────────────────────────────────────────
$pdo->exec("INSERT IGNORE INTO banners (id,title_en,title_bn,subtitle_en,subtitle_bn,image,active,sort_order) VALUES
(1,'Nourishing Millions','লক্ষ পরিবারকে পুষ্টি দিচ্ছি','Premium quality food products from farm to table','ক্ষেত থেকে রান্নাঘর — প্রিমিয়াম মানের খাদ্যপণ্য','demo_banner1.jpg',1,1),
(2,'The Taste of Trust','বিশ্বাসের স্বাদ','Over 20 years of excellence in food manufacturing','খাদ্য উৎপাদনে ২০ বছরের শ্রেষ্ঠত্ব','demo_banner2.jpg',1,2),
(3,'Global Reach, Local Heart','বৈশ্বিক উপস্থিতি, স্থানীয় হৃদয়','Exporting Bangladeshi quality to 25+ countries','২৫+ দেশে বাংলাদেশের মান রপ্তানি করছি','demo_banner3.jpg',1,3)");
echo "✅ Banners seeded.<br>";

// ─── Demo Categories & Products ───────────────────────────────────────────────
$pdo->exec("INSERT IGNORE INTO product_categories (id,name_en,name_bn,parent_id,active,sort_order) VALUES
(1,'Rice & Grains','চাল ও শস্য',NULL,1,1),
(2,'Spices & Condiments','মশলা ও সস',NULL,1,2),
(3,'Beverages','পানীয়',NULL,1,3),
(4,'Snacks & Confectionery','স্ন্যাকস ও মিষ্টান্ন',NULL,1,4),
(5,'Premium Rice','প্রিমিয়াম চাল',1,1,1),
(6,'General Rice','সাধারণ চাল',1,1,2),
(7,'Ground Spices','গুঁড়া মশলা',2,1,1),
(8,'Ready Sauces','রেডি সস',2,1,2),
(9,'Fruit Juice','ফলের রস',3,1,1),
(10,'Water','পানি',3,1,2)");
echo "✅ Categories seeded.<br>";

$pdo->exec("INSERT IGNORE INTO products (id,category_id,name_en,name_bn,desc_en,desc_bn,weight,image,active,sort_order) VALUES
(1,5,'Ovijat Aromatic Kalizira','অভিজাতসুগন্ধি কালিজিরা','100% authentic kalizira rice sourced from northern Bangladesh','উত্তর বাংলাদেশ থেকে সংগ্রহ করা ১০০% আসল কালিজিরা চাল','1 kg','demo_product1.jpg',1,1),
(2,5,'Ovijat Basmati Gold','অভিজাতবাসমতি গোল্ড','Long-grain fragrant basmati rice, premium grade','লম্বা দানার সুগন্ধি বাসমতি চাল, প্রিমিয়াম গ্রেড','5 kg','demo_product2.jpg',1,2),
(3,6,'Ovijat Miniket','অভিজাতমিনিকেট','Fine quality miniket rice for everyday cooking','প্রতিদিনের রান্নার জন্য উৎকৃষ্ট মানের মিনিকেট চাল','25 kg','demo_product3.jpg',1,1),
(4,7,'Ovijat Turmeric Powder','অভিজাতহলুদ গুঁড়া','Pure sun-dried turmeric ground to perfection','বিশুদ্ধ রোদে শুকানো হলুদ নিখুঁতভাবে গুঁড়া','200 g','demo_product4.jpg',1,1),
(5,7,'Ovijat Chili Powder','অভিজাতমরিচ গুঁড়া','Fiery red chili ground from select local peppers','বাছাই করা স্থানীয় মরিচ থেকে তৈরি ঝাল লাল মরিচ গুঁড়া','200 g','demo_product5.jpg',1,2),
(6,9,'Ovijat Mango Drink','অভিজাতআমের পানীয়','Real mango pulp drink — taste the summer','আসল আমের পাল্প পানীয় — গ্রীষ্মের স্বাদ নিন','250 ml','demo_product6.jpg',1,1),
(7,10,'Ovijat Pure Water','অভিজাতবিশুদ্ধ পানি','WHO-standard purified mineral water','ডব্লিউএইচও মানের বিশুদ্ধ মিনারেল পানি','500 ml','demo_product7.jpg',1,1)");
echo "✅ Products seeded.<br>";

// ─── Demo Sister Concerns ─────────────────────────────────────────────────────
$pdo->exec("INSERT IGNORE INTO sister_concerns (id,name_en,name_bn,desc_en,desc_bn,website,active,sort_order) VALUES
(1,'Ovijat Foods Ltd.','অভিজাতফুডস লিমিটেড','Flagship food processing and manufacturing unit','প্রধান খাদ্য প্রক্রিয়াকরণ ও উৎপাদন ইউনিট','https://ovijatfood.com',1,1),
(2,'Ovijat Agro Ltd.','অভিজাতএগ্রো লিমিটেড','Upstream agricultural sourcing and contract farming','আপস্ট্রিম কৃষি সংগ্রহ ও চুক্তি চাষ',NULL,1,2),
(3,'Ovijat Beverage Co.','অভিজাতবিভারেজ কো.','Juice, water, and soft drink manufacturing division','জুস, পানি ও সফট ড্রিংক উৎপাদন বিভাগ',NULL,1,3),
(4,'Ovijat Export House','অভিজাতএক্সপোর্ট হাউস','International trade and export logistics arm','আন্তর্জাতিক বাণিজ্য ও রপ্তানি লজিস্টিক্স শাখা',NULL,1,4),
(5,'Ovijat Packaging Ind.','অভিজাতপ্যাকেজিং ইন্ড.','In-house sustainable packaging solutions','ইন-হাউস টেকসই প্যাকেজিং সমাধান',NULL,1,5)");
echo "✅ Sister concerns seeded.<br>";

// ─── Demo Global Presence ─────────────────────────────────────────────────────
$pdo->exec("INSERT IGNORE INTO global_presence (id,country_en,country_bn,flag_emoji,active) VALUES
(1,'Bangladesh','বাংলাদেশ','🇧🇩',1),(2,'Saudi Arabia','সৌদি আরব','🇸🇦',1),
(3,'UAE','সংযুক্ত আরব আমিরাত','🇦🇪',1),(4,'Qatar','কাতার','🇶🇦',1),
(5,'Kuwait','কুয়েত','🇰🇼',1),(6,'Bahrain','বাহরাইন','🇧🇭',1),
(7,'United Kingdom','যুক্তরাজ্য','🇬🇧',1),(8,'United States','যুক্তরাষ্ট্র','🇺🇸',1),
(9,'Canada','কানাডা','🇨🇦',1),(10,'Australia','অস্ট্রেলিয়া','🇦🇺',1),
(11,'Malaysia','মালয়েশিয়া','🇲🇾',1),(12,'Singapore','সিঙ্গাপুর','🇸🇬',1),
(13,'Italy','ইতালি','🇮🇹',1),(14,'Japan','জাপান','🇯🇵',1),
(15,'India','ভারত','🇮🇳',1)");
echo "✅ Global presence seeded.<br>";

// ─── Demo Rice Showcase ───────────────────────────────────────────────────────
$pdo->exec("INSERT IGNORE INTO rice_products (id,name_en,name_bn,desc_en,desc_bn,origin_en,origin_bn,image,active,sort_order) VALUES
(1,'Kalizira Aromatic','কালিজিরা সুগন্ধি','Black cumin scented heirloom rice, prized at royal feasts','কালোজিরার সুগন্ধে ভরপুর ঐতিহ্যবাহী চাল','Dinajpur, Bangladesh','দিনাজপুর, বাংলাদেশ','demo_rice1.jpg',1,1),
(2,'Chinigura Premium','চিনিগুড়া প্রিমিয়াম','Tiny pearl-like grains with a naturally sweet fragrance','স্বাভাবিকভাবে মিষ্টি সুগন্ধের ছোট মুক্তার মতো দানা','Rajshahi, Bangladesh','রাজশাহী, বাংলাদেশ','demo_rice2.jpg',1,2),
(3,'BRRI-28 High Yield','বিআরআরআই-২৮ উচ্চ ফলনশীল','High-yield modern variety for mass consumption','ব্যাপক ভোগের জন্য উচ্চ-ফলনশীল আধুনিক জাত','Cumilla, Bangladesh','কুমিল্লা, বাংলাদেশ','demo_rice3.jpg',1,3)");
echo "✅ Rice showcase seeded.<br>";

// ─── Demo Management ──────────────────────────────────────────────────────────
$pdo->exec("INSERT IGNORE INTO management (id,name_en,name_bn,title_en,title_bn,message_en,message_bn,image,active,sort_order) VALUES
(1,'Mohammad Rafiqul Islam','মোহাম্মদ রফিকুল ইসলাম','Chairman & Founder','চেয়ারম্যান ও প্রতিষ্ঠাতা','Our mission is simple: bring the finest Bangladeshi produce to every table in the nation and beyond. We believe in building trust one product at a time.','আমাদের লক্ষ্য সহজ: দেশের প্রতিটি পরিবারের কাছে এবং বিশ্বে সেরা বাংলাদেশি পণ্য পৌঁছে দেওয়া।','demo_chairman.jpg',1,1),
(2,'Farida Begum','ফরিদা বেগম','Managing Director','ব্যবস্থাপনা পরিচালক','Innovation and sustainability are at the heart of everything we do. Ovijat Group remains committed to modern, responsible food production.','উদ্ভাবন ও টেকসই উন্নয়ন আমাদের সকল কাজের কেন্দ্রে। অভিজাতগ্রুপ আধুনিক, দায়িত্বশীল খাদ্য উৎপাদনে প্রতিশ্রুতিবদ্ধ।','demo_md.jpg',1,2)");
echo "✅ Management seeded.<br>";

// ─── Demo Sales Contacts ──────────────────────────────────────────────────────
$pdo->exec("INSERT IGNORE INTO sales_contacts (id,type,name_en,name_bn,title_en,title_bn,phone,email,image,active) VALUES
(1,'local','Karim Hassan','করিম হাসান','Head of Local Sales','স্থানীয় বিক্রয় প্রধান','+880 1711-000001','karim@ovijatfood.com','demo_sales1.jpg',1),
(2,'export','Nasreen Sultana','নাসরীন সুলতানা','Export Sales Manager','রপ্তানি বিক্রয় ব্যবস্থাপক','+880 1711-000002','export@ovijatfood.com','demo_sales2.jpg',1)");
echo "✅ Sales contacts seeded.<br>";

// ─── Demo Jobs ────────────────────────────────────────────────────────────────
$expiry1 = date('Y-m-d', strtotime('+60 days'));
$expiry2 = date('Y-m-d', strtotime('+45 days'));
$expiry3 = date('Y-m-d', strtotime('+30 days'));
$pdo->exec("INSERT IGNORE INTO jobs (id,title_en,title_bn,department_en,department_bn,location_en,location_bn,type_en,type_bn,desc_en,desc_bn,salary_range,active,expires_at) VALUES
(1,'Senior Sales Executive','সিনিয়র বিক্রয় নির্বাহী','Sales & Distribution','বিক্রয় ও বিতরণ','Dhaka','ঢাকা','Full-time','পূর্ণকালীন','Drive sales in Dhaka region, manage distributor relationships, achieve monthly targets.','ঢাকা অঞ্চলে বিক্রয় পরিচালনা, ডিস্ট্রিবিউটর সম্পর্ক রক্ষা, মাসিক লক্ষ্যমাত্রা অর্জন।','BDT 35,000 - 50,000',1,'$expiry1'),
(2,'Quality Assurance Officer','মান নিশ্চিতকরণ কর্মকর্তা','Production','উৎপাদন','Narayanganj','নারায়ণগঞ্জ','Full-time','পূর্ণকালীন','Monitor food safety standards, conduct lab tests, maintain ISO documentation.','খাদ্য নিরাপত্তা মান পর্যবেক্ষণ, ল্যাব পরীক্ষা পরিচালনা, আইএসও ডকুমেন্টেশন রক্ষণাবেক্ষণ।','BDT 40,000 - 55,000',1,'$expiry2'),
(3,'Digital Marketing Specialist','ডিজিটাল মার্কেটিং বিশেষজ্ঞ','Marketing','বিপণন','Dhaka (Remote Possible)','ঢাকা (রিমোট সম্ভব)','Full-time','পূর্ণকালীন','Manage social media, SEO, and paid campaigns for Ovijat brands.','অভিজাতব্র্যান্ডের সোশ্যাল মিডিয়া, এসইও এবং পেইড ক্যাম্পেইন পরিচালনা।','BDT 30,000 - 45,000',1,'$expiry3')");
echo "✅ Jobs seeded.<br>";

echo "<hr><h2 style='color:green'>✅ SETUP COMPLETE!</h2>";
echo "<p><strong>DB:</strong> ".DB_NAME."</p>";
echo "<p><strong>Admin Login:</strong> /admin/ → username: <b>admin</b> | password: <b>Admin@123</b></p>";
echo "<p style='color:red'><strong>⚠️ DELETE THIS FILE (setup.php) IMMEDIATELY AFTER SETUP!</strong></p>";
echo "<p><a href='/'>→ Visit Website</a> | <a href='/admin/'>→ Admin Panel</a></p>";

// ─── New Tables (v2) ──────────────────────────────────────────────────────────
$newTables = <<<SQL2

CREATE TABLE IF NOT EXISTS `promotions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title_en` VARCHAR(200) NOT NULL,
  `title_bn` VARCHAR(200) NOT NULL,
  `desc_en` TEXT NULL,
  `desc_bn` TEXT NULL,
  `badge_en` VARCHAR(80) NULL,
  `badge_bn` VARCHAR(80) NULL,
  `image` VARCHAR(255) NULL,
  `link` VARCHAR(255) NULL,
  `active` TINYINT(1) DEFAULT 1,
  `start_date` DATE NULL,
  `end_date` DATE NULL,
  `sort_order` INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `testimonials` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name_en` VARCHAR(200) NOT NULL,
  `name_bn` VARCHAR(200) NOT NULL,
  `role_en` VARCHAR(200) NULL,
  `role_bn` VARCHAR(200) NULL,
  `text_en` TEXT NOT NULL,
  `text_bn` TEXT NOT NULL,
  `stars` TINYINT DEFAULT 5,
  `image` VARCHAR(255) NULL,
  `active` TINYINT(1) DEFAULT 1,
  `sort_order` INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `partners` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(200) NOT NULL,
  `logo` VARCHAR(255) NULL,
  `website` VARCHAR(255) NULL,
  `active` TINYINT(1) DEFAULT 1,
  `sort_order` INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `admin_users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(80) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('superadmin','editor') DEFAULT 'editor',
  `active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `action_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `admin_id` INT NULL,
  `admin_user` VARCHAR(80) NULL,
  `action` VARCHAR(300) NOT NULL,
  `details` TEXT NULL,
  `ip` VARCHAR(50) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX(`created_at`),
  INDEX(`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `visitor_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ip` VARCHAR(50) NOT NULL,
  `page` VARCHAR(300) NOT NULL,
  `referrer` VARCHAR(500) NULL,
  `user_agent` VARCHAR(500) NULL,
  `country` VARCHAR(100) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX(`created_at`),
  INDEX(`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SQL2;

foreach (explode(';', $newTables) as $q) { $q=trim($q); if($q) try{$pdo->exec($q);}catch(Exception $e){} }

// Migrate existing admin to admin_users
$existing = $pdo->query("SELECT * FROM admins LIMIT 1")->fetch();
if ($existing) {
    $pdo->prepare("INSERT IGNORE INTO admin_users (username,password,role) VALUES (?,?,'superadmin')")->execute([$existing['username'],$existing['password']]);
}

// Demo promotions
$pdo->exec("INSERT IGNORE INTO promotions (id,title_en,title_bn,desc_en,desc_bn,badge_en,badge_bn,active,sort_order) VALUES
(1,'Eid Special Bundle Offer','ঈদ বিশেষ বান্ডেল অফার','Get 20% off on premium rice combo packs this Eid season.','এই ঈদ মৌসুমে প্রিমিয়াম রাইস কম্বো প্যাকে ২০% ছাড় পান।','20% OFF','২০% ছাড়',1,1),
(2,'Private Label Solutions','প্রাইভেট লেবেল সমাধান','Launch your own brand with Ovijat Group manufacturing excellence.','অভিজাত গ্রুপের উৎপাদন শ্রেষ্ঠত্বে আপনার নিজস্ব ব্র্যান্ড লঞ্চ করুন।','New Service','নতুন সেবা',1,2),
(3,'Global Export Campaign','গ্লোবাল এক্সপোর্ট ক্যাম্পেইন','Now exporting to 25+ countries — Quality you can trust worldwide.','এখন ২৫+ দেশে রপ্তানি হচ্ছে — বিশ্বব্যাপী বিশ্বাসযোগ্য মান।','Expanding','সম্প্রসারণ',1,3)");

// Demo testimonials
$pdo->exec("INSERT IGNORE INTO testimonials (id,name_en,name_bn,role_en,role_bn,text_en,text_bn,stars,active,sort_order) VALUES
(1,'Ahmed Al-Rashid','আহমেদ আল-রাশিদ','Importer, Dubai UAE','আমদানিকারক, দুবাই UAE','Ovijat Group delivers consistently high quality rice. Our clients in the UAE are extremely satisfied. Highly recommended!','অভিজাত গ্রুপ ধারাবাহিকভাবে উচ্চমানের চাল সরবরাহ করে। দৃঢ়ভাবে প্রস্তাবিত!',5,1,1),
(2,'Sarah Mitchell','সারাহ মিচেল','Retail Chain Manager, UK','রিটেইল চেইন ম্যানেজার, যুক্তরাজ্য','The aromatic rice varieties from Ovijat are unique. Kalizira especially is a favorite among our South Asian customers.','অভিজাতের সুগন্ধি চালের জাত অনন্য। কালিজিরা বিশেষভাবে আমাদের প্রিয়।',5,1,2),
(3,'Mohammad Hassan','মোহাম্মদ হাসান','Restaurant Owner, Qatar','রেস্তোরাঁ মালিক, কাতার','We have been sourcing Ovijat spices and rice for 3 years. The quality never disappoints. Excellent supply chain too.','আমরা ৩ বছর ধরে অভিজাতের মশলা ও চাল নিচ্ছি। মান কখনো হতাশ করেনি।',5,1,3)");

// Demo partners
$pdo->exec("INSERT IGNORE INTO partners (id,name,active,sort_order) VALUES
(1,'Delight Distribution Inc.',1,1),(2,'Al-Madina Trading',1,2),(3,'UK Food Importers Ltd.',1,3),(4,'Tokyo Spice House',1,4),(5,'GCC Fresh Markets',1,5)");

echo '<br>✅ v2 tables and demo data seeded.<br>';

// ─── Column Migrations (idempotent) ────────────────────────────────────────────
$migrations = [
    "ALTER TABLE banners ADD COLUMN hide_buttons TINYINT(1) DEFAULT 0",
    "ALTER TABLE banners ADD COLUMN show_title   TINYINT(1) DEFAULT 1",
];
foreach ($migrations as $mig) {
    try { $pdo->exec($mig); } catch(PDOException $e) { /* column already exists, fine */ }
}
echo "✅ Column migrations applied.<br>";

// Demo promotions image dimension addition
$pdo->exec("INSERT IGNORE INTO promotions (id,title_en,title_bn,badge_en,badge_bn,desc_en,desc_bn,active,sort_order) VALUES
(1,'Eid Special Bundle','ঈদ বিশেষ বান্ডেল','20% OFF','২০% ছাড়','Get 20% off premium rice combo packs this Eid season!','এই ঈদে প্রিমিয়াম রাইস কম্বোতে ২০% ছাড় পান!',1,1),
(2,'Private Label Solutions','প্রাইভেট লেবেল সমাধান','New Service','নতুন সেবা','Launch your own brand with Ovijat Group manufacturing.','অভিজাতের উৎপাদন সুবিধায় আপনার ব্র্যান্ড চালু করুন।',1,2),
(3,'Global Export Campaign','গ্লোবাল এক্সপোর্ট ক্যাম্পেইন','Expanding','সম্প্রসারণ','Now exporting to 25+ countries worldwide.','এখন বিশ্বের ২৫+ দেশে রপ্তানি হচ্ছে।',1,3)
") ;

echo "<hr><h2 style='color:green'>✅ v2 SETUP COMPLETE!</h2>";
echo "<p><a href='/'>→ Visit Website</a> | <a href='/admin/'>→ Admin Panel</a></p>";
