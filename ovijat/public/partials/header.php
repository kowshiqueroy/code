<?php
/**
 * OVIJAT GROUP — header.php v2.0
 * Fixes: logo+brand name, language toggle shows opposite, dropdown nav,
 *        ticker icon only, social links conditional, centered grids.
 */
$lang        = lang();
$siteName    = 'OVIJAT';
$siteSubName = 'FOOD & BEVERAGE INDUSTRIES LTD.';
$logo        = setting('logo');
$helpline    = setting('helpline','09647000025');
$ticker      = getActiveTicker();
$popup       = getActivePopup();
$currentPage = $_GET['page'] ?? 'home';
$logoUrl     = $logo ? imgUrl($logo,'logos','placeholder') : '';
$facebook    = setting('facebook','');
$linkedin    = setting('linkedin','');
$youtube     = setting('youtube','');

// Load all active product categories for nav dropdown
try {
    $navCats = db()->query("SELECT id,name_en,name_bn,parent_id FROM product_categories WHERE active=1 ORDER BY sort_order LIMIT 12")->fetchAll();
    $topNavCats = array_filter($navCats, fn($c) => !$c['parent_id']);
} catch(Exception $e){ $navCats=[]; $topNavCats=[]; }

$navLinks = [
    ['page'=>'home',       'en'=>'Home',      'bn'=>'হোম'],
    ['page'=>'rice',       'en'=>'Rice',       'bn'=>'চাল'],
    ['page'=>'concerns',   'en'=>'Companies',  'bn'=>'প্রতিষ্ঠান'],
    ['page'=>'global',     'en'=>'Global',     'bn'=>'বৈশ্বিক'],
    ['page'=>'management', 'en'=>'Leadership', 'bn'=>'নেতৃত্ব'],
    ['page'=>'careers',    'en'=>'Careers',    'bn'=>'ক্যারিয়ার'],
    ['page'=>'contact',    'en'=>'Contact',    'bn'=>'যোগাযোগ'],
];
?>
<!DOCTYPE html>
<html lang="<?= $lang==='bn'?'bn':'en' ?>" dir="ltr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= e($pageTitle??$siteName) ?> — <?= e($siteName) ?></title>
<meta name="description" content="<?= e($metaDesc??setting('meta_description')) ?>">
<meta name="keywords" content="<?= e(setting('meta_keywords')) ?>">
<meta property="og:title" content="<?= e($pageTitle??$siteName) ?>">
<meta property="og:type" content="website">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;900&family=DM+Sans:wght@300;400;500;600;700&family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/main.css">

<link rel="icon" href="uploads/ovijatlogo.ico" type="image/x-icon">


</head>
<body class="lang-<?= $lang ?>">

<!-- Loading Screen -->
<div id="loading-screen">
  <div class="loader-inner">
    <div class="loader-logo-wrap">
      <?php if($logoUrl): ?><img src="<?= e($logoUrl) ?>" alt="Ovijat" style="height:70px;width:auto;object-fit:contain"><?php else: ?>
      <div class="loader-text-logo">OVIJAT</div><?php endif; ?>
    </div>
    <div class="loader-brand-sub">Food &amp; Beverage Industries Ltd.</div>
      <div class="loader-brand-sub2">Ovijat Group</div>
    <div class="loader-bar"><span></span></div>
  </div>
</div>

<?php if($popup): ?>
<!-- Event Popup -->
<div id="event-popup" class="popup-overlay" aria-hidden="true">
  <div class="popup-box" role="dialog" aria-modal="true">
    <button class="popup-close" id="popupClose" aria-label="Close">&#x2715;</button>
    <?php if($popup['image']&&file_exists(UPLOAD_DIR.'popup/'.$popup['image'])): ?>
      <div class="popup-image-wrap"><img src="<?= imgUrl($popup['image'],'popup','popup') ?>" alt="Event" loading="lazy"></div>
    <?php endif; ?>
    <div class="popup-content">
      <h2 class="popup-title"><?= e($lang==='bn'?$popup['title_bn']:$popup['title_en']) ?></h2>
      <p class="popup-body"><?= nl2br(e($lang==='bn'?$popup['body_bn']:$popup['body_en'])) ?></p>
    </div>
  </div>
</div>
<script>
(function(){
  var key='ovijat_popup_seen_'+new Date().toISOString().slice(0,10);
  if(!localStorage.getItem(key)){
    document.getElementById('event-popup').classList.add('active');
    function closePopup(){document.getElementById('event-popup').classList.remove('active');localStorage.setItem(key,'1');}
    document.getElementById('popupClose').addEventListener('click',closePopup);
    document.getElementById('event-popup').addEventListener('click',function(e){if(e.target===this)closePopup();});
  }
})();
</script>
<?php endif; ?>

<?php if($ticker): ?>
<!-- News Ticker — icon only, no text -->
<div class="ticker-wrap" aria-label="News ticker">
  <span class="ticker-label">📢 <?= $lang==='bn'?'সংবাদ':'NEWS' ?></span>
  <div class="ticker-content" role="marquee">
    <div class="ticker-inner">
      <?php foreach($ticker as $item): ?>
        <span class="ticker-item"><?= e($lang==='bn'?$item['text_bn']:$item['text_en']) ?></span>
        <span class="ticker-sep">◆</span>
      <?php endforeach; ?>
      <?php foreach($ticker as $item): ?>
        <span class="ticker-item"><?= e($lang==='bn'?$item['text_bn']:$item['text_en']) ?></span>
        <span class="ticker-sep">◆</span>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Top Bar -->
<div class="topbar">
  <div class="container topbar-inner">
    <div class="topbar-left">
      <span class="topbar-helpline">📞 <a href="tel:<?= e($helpline) ?>"><?= e($helpline) ?></a></span>
      <?php if(setting('email')): ?><span class="topbar-sep">|</span><span>✉ <a href="mailto:<?= e(setting('email')) ?>"><?= e(setting('email')) ?></a></span><?php endif; ?>
    </div>
    <div class="topbar-right">
      <?php if($facebook): ?><a href="<?= e($facebook) ?>" class="topbar-social" target="_blank" rel="noopener" aria-label="Facebook"><svg viewBox="0 0 24 24" fill="currentColor" width="15" height="15"><path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/></svg></a><?php endif; ?>
      <?php if($linkedin): ?><a href="<?= e($linkedin) ?>" class="topbar-social" target="_blank" rel="noopener" aria-label="LinkedIn"><svg viewBox="0 0 24 24" fill="currentColor" width="15" height="15"><path d="M16 8a6 6 0 016 6v7h-4v-7a2 2 0 00-4 0v7H10v-7a6 6 0 016-6zM2 9h4v12H2z"/><circle cx="4" cy="4" r="2"/></svg></a><?php endif; ?>
      <?php if($youtube): ?><a href="<?= e($youtube) ?>" class="topbar-social" target="_blank" rel="noopener" aria-label="YouTube"><svg viewBox="0 0 24 24" fill="currentColor" width="15" height="15"><path d="M22.54 6.42a2.78 2.78 0 00-1.95-1.96C18.88 4 12 4 12 4s-6.88 0-8.59.46a2.78 2.78 0 00-1.95 1.96A29 29 0 001 12a29 29 0 00.46 5.58 2.78 2.78 0 001.95 1.96C5.12 20 12 20 12 20s6.88 0 8.59-.46a2.78 2.78 0 001.95-1.96A29 29 0 0023 12a29 29 0 00-.46-5.58z"/><polygon points="9.75 15.02 15.5 12 9.75 8.98 9.75 15.02" fill="white"/></svg></a><?php endif; ?>
      <!-- Language toggle: shows OPPOSITE language to switch to -->
      <div class="lang-toggle">
        <?php if($lang==='en'): ?>
          <a href="<?= SITE_URL ?>/?page=lang&l=bn" class="lang-btn">বাংলা</a>
        <?php else: ?>
          <a href="<?= SITE_URL ?>/?page=lang&l=en" class="lang-btn">EN</a> 
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Main Header -->
<header class="site-header" id="siteHeader">
  <div class="container header-inner">
    <!-- Logo + Brand Name (like image 4) -->
    <a href="<?= SITE_URL ?>/" class="site-logo" aria-label="Ovijat Home">
      <?php if($logoUrl): ?>
        <img src="<?= e($logoUrl) ?>" alt="Ovijat Logo" class="logo-img" loading="eager">
      <?php else: ?>
        <div style="width:48px;height:48px;background:var(--red-brand);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:900;font-size:.8rem">OVJ</div>
      <?php endif; ?>
      <div class="logo-brand-text">
        <span class="logo-brand-name"><?= e($siteName) ?></span>
        <span class="logo-brand-sub"><?= e($siteSubName) ?></span>
      </div>
    </a>

    <!-- Desktop Nav -->
    <nav class="main-nav" aria-label="Main navigation">
      <ul class="nav-list">
        <!-- Home -->
        <li class="nav-item">
          <a href="<?= SITE_URL ?>/?page=home" class="nav-link <?= $currentPage==='home'?'active':'' ?>">
            <?= $lang==='bn'?'হোম':'Home' ?>
          </a>
        </li>

        <!-- Products with dropdown -->
        <li class="nav-item">
          <a href="<?= SITE_URL ?>/?page=products" class="nav-link <?= in_array($currentPage,['products','category','product'])?'active':'' ?>">
            <?= $lang==='bn'?'পণ্যসমূহ':'Products' ?> <span class="nav-arrow">▾</span>
          </a>
          <?php if($topNavCats): ?>
          <div class="nav-dropdown">
            <div class="nav-dropdown-parent"><?= $lang==='bn'?'সব বিভাগ':'All Categories' ?></div>
            <?php foreach($topNavCats as $nc): ?>
              <a href="<?= SITE_URL ?>/?page=category&id=<?= $nc['id'] ?>" class="nav-dropdown-item">
                <?= e($lang==='bn'?$nc['name_bn']:$nc['name_en']) ?>
              </a>
            <?php endforeach; ?>
            <div class="nav-dropdown-sep"></div>
            <a href="<?= SITE_URL ?>/?page=products" class="nav-dropdown-item" style="color:var(--green-mid);font-weight:700">
              <?= $lang==='bn'?'সব পণ্য দেখুন →':'View All Products →' ?>
            </a>
          </div>
          <?php endif; ?>
        </li>

        <!-- Other links -->
        <?php foreach($navLinks as $link): if($link['page']==='home') continue; ?>
        <li class="nav-item">
          <a href="<?= SITE_URL ?>/?page=<?= $link['page'] ?>" class="nav-link <?= $currentPage===$link['page']?'active':'' ?>">
            <?= $lang==='bn'?e($link['bn']):e($link['en']) ?>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>
    </nav>

    <button class="hamburger" id="hamburger" aria-label="Toggle menu" aria-expanded="false">
      <span></span><span></span><span></span>
    </button>
  </div>
</header>

<!-- Mobile Nav -->
<div class="mobile-nav" id="mobileNav" aria-hidden="true">
  <ul class="mobile-nav-list">
    <li><a href="<?= SITE_URL ?>/?page=home" class="mobile-nav-link <?= $currentPage==='home'?'active':'' ?>"><?= $lang==='bn'?'হোম':'Home' ?></a></li>
    <li>
      <a href="<?= SITE_URL ?>/?page=products" class="mobile-nav-link <?= in_array($currentPage,['products','category','product'])?'active':'' ?>"><?= $lang==='bn'?'পণ্যসমূহ':'Products' ?></a>
      <?php if($topNavCats): ?>
        <?php foreach($topNavCats as $nc): ?>
          <a href="<?= SITE_URL ?>/?page=category&id=<?= $nc['id'] ?>" class="mobile-sub-link">↳ <?= e($lang==='bn'?$nc['name_bn']:$nc['name_en']) ?></a>
        <?php endforeach; ?>
      <?php endif; ?>
    </li>
    <?php foreach($navLinks as $link): if($link['page']==='home') continue; ?>
    <li><a href="<?= SITE_URL ?>/?page=<?= $link['page'] ?>" class="mobile-nav-link <?= $currentPage===$link['page']?'active':'' ?>"><?= $lang==='bn'?e($link['bn']):e($link['en']) ?></a></li>
    <?php endforeach; ?>
    <li class="mobile-lang-row">
      <?php if($lang==='en'): ?>
        <a href="<?= SITE_URL ?>/?page=lang&l=bn" class="lang-btn">বাংলা</a>
      <?php else: ?>
        <a href="<?= SITE_URL ?>/?page=lang&l=en" class="lang-btn">English</a>
      <?php endif; ?>
    </li>
  </ul>
</div>

<main id="mainContent">
