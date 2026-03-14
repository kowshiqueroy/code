<?php
// public/includes/header.php
$lang       = getLang();
$siteName   = getSetting('site_name_' . $lang, getSetting('site_name_en', 'School Name'));
$siteNameOther = getLang() === 'bn' ? getSetting('site_name_en') : getSetting('site_name_bn');
$tagline    = getSetting('site_tagline_' . $lang, '');
$logo       = getSetting('logo');
$primaryColor   = getSetting('primary_color', '#006a4e');
$secondaryColor = getSetting('secondary_color', '#f42a41');
$fontSize   = getSetting('font_size', 'medium');
$currentPage = currentPage();
$menus       = getMenus('main');
$fontSizeMap = ['small' => '14px', 'medium' => '16px', 'large' => '18px'];
$fSize       = $fontSizeMap[$fontSize] ?? '16px';
$estdYear    = getSetting('established_year', '');
$phone       = getSetting('phone', '');
$email       = getSetting('email', '');
$fbUrl       = getSetting('facebook_url', '');
$ytUrl       = getSetting('youtube_url', '');
?>
<!DOCTYPE html>
<html lang="<?= $lang === 'bn' ? 'bn' : 'en' ?>" dir="ltr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="<?= h(getSetting('site_tagline_en', $siteName)) ?>">
<title><?= h($siteName) ?> — <?= h($tagline) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@300;400;500;600;700&family=Noto+Serif+Bengali:wght@400;600;700&family=Crimson+Pro:ital,wght@0,400;0,600;1,400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/public.css">
<style>
:root {
  --primary: <?= h($primaryColor) ?>;
  --secondary: <?= h($secondaryColor) ?>;
  --base-font: <?= $fSize ?>;
}
</style>
<?php if ($logo): ?>
<link rel="icon" href="<?= h(imgUrl($logo, 'thumb')) ?>">
<?php endif; ?>
</head>
<body class="lang-<?= $lang ?>">

<!-- ── Top Bar ────────────────────────────────────────────── -->
<div class="topbar">
  <div class="container topbar-inner">
    <div class="topbar-left">
      <?php if ($phone): ?><span>📞 <?= h($phone) ?></span><?php endif; ?>
      <?php if ($email): ?><span>✉️ <?= h($email) ?></span><?php endif; ?>
      <?php if ($estdYear): ?><span><?= t('Est.','প্রতিষ্ঠা') ?> <?= banglaNum($estdYear) ?></span><?php endif; ?>
    </div>
    <div class="topbar-right">
      <?php if ($fbUrl): ?><a href="<?= h($fbUrl) ?>" target="_blank" class="social-link" aria-label="Facebook">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
      </a><?php endif; ?>
      <?php if ($ytUrl): ?><a href="<?= h($ytUrl) ?>" target="_blank" class="social-link" aria-label="YouTube">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M22.54 6.42a2.78 2.78 0 0 0-1.95-1.96C18.88 4 12 4 12 4s-6.88 0-8.59.46a2.78 2.78 0 0 0-1.95 1.96A29 29 0 0 0 1 12a29 29 0 0 0 .46 5.58A2.78 2.78 0 0 0 3.41 19.6C5.12 20 12 20 12 20s6.88 0 8.59-.46a2.78 2.78 0 0 0 1.95-1.95A29 29 0 0 0 23 12a29 29 0 0 0-.46-5.58zM9.75 15.02V8.98L15.5 12l-5.75 3.02z"/></svg>
      </a><?php endif; ?>
      <!-- Language Toggle -->
      <div class="lang-toggle">
        <a href="?setlang=bn" class="<?= $lang === 'bn' ? 'active' : '' ?>">বাং</a>
        <span>|</span>
        <a href="?setlang=en" class="<?= $lang === 'en' ? 'active' : '' ?>">EN</a>
      </div>
      <a href="<?= ADMIN_PATH ?>" class="admin-link" target="_blank"><?= t('Admin','অ্যাডমিন') ?></a>
    </div>
  </div>
</div>

<!-- ── Header ─────────────────────────────────────────────── -->
<header class="site-header">
  <div class="container header-inner">
    <div class="logo-area">
      <?php if ($logo): ?>
        <img src="<?= h(imgUrl($logo, 'medium')) ?>" alt="Logo" class="site-logo">
      <?php else: ?>
        <div class="logo-icon">🏫</div>
      <?php endif; ?>
      <div class="site-title">
        <h1><?= h($siteName) ?></h1>
        <?php if ($tagline): ?><p><?= h($tagline) ?></p><?php endif; ?>
      </div>
    </div>
    <div class="header-right">
      <div class="govt-logos">
        <img src="<?= BASE_URL ?>/assets/img/bd-logo.png" alt="Bangladesh" class="govt-logo" onerror="this.style.display='none'">
        <img src="<?= BASE_URL ?>/assets/img/moe-logo.png" alt="MoE" class="govt-logo" onerror="this.style.display='none'">
      </div>
    </div>
  </div>
</header>

<!-- ── Navigation ─────────────────────────────────────────── -->
<nav class="main-nav" id="mainNav">
  <div class="container nav-inner">
    <button class="hamburger" id="hamburger" aria-label="Menu" aria-expanded="false">
      <span></span><span></span><span></span>
    </button>
    <ul class="nav-list" id="navList">
      <?php foreach ($menus as $menu):
        $slug     = $menu['page_slug'] ?? '';
        $hasChild = !empty($menu['children']);
        $isActive = ($currentPage === explode('&', $slug)[0]);
        $menuTitle = field($menu, 'title');
        $menuUrl   = $menu['url'] ?: pageUrl(explode('&', $slug)[0]);
      ?>
      <li class="nav-item <?= $hasChild ? 'has-dropdown' : '' ?> <?= $isActive ? 'active' : '' ?>">
        <a href="<?= h($menuUrl) ?>" <?= $hasChild ? 'aria-haspopup="true"' : '' ?>>
          <?= h($menuTitle) ?>
          <?php if ($hasChild): ?><svg class="arrow" width="10" height="10" viewBox="0 0 10 10"><path d="M1 3l4 4 4-4" stroke="currentColor" fill="none" stroke-width="1.5"/></svg><?php endif; ?>
        </a>
        <?php if ($hasChild): ?>
        <ul class="dropdown">
          <?php foreach ($menu['children'] as $child):
            $cSlug  = $child['page_slug'] ?? '';
            $cTitle = field($child, 'title');
            $parts  = explode('&', $cSlug);
            $cPage  = $parts[0];
            $cExtra = [];
            foreach (array_slice($parts, 1) as $p) {
                [$k,$v] = explode('=', $p, 2) + ['',''];
                $cExtra[$k] = $v;
            }
            $cUrl = $child['url'] ?: pageUrl($cPage, $cExtra);
          ?>
          <li><a href="<?= h($cUrl) ?>"><?= h($cTitle) ?></a></li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
</nav>

<!-- ── News Ticker ─────────────────────────────────────────── -->
<?php if (getSetting('show_news_ticker', '1') === '1'):
  $tickerNotices = getNotices('', 15);
  if ($tickerNotices): ?>
<div class="news-ticker">
  <div class="container ticker-wrap">
    <span class="ticker-label"><?= t('Notice','নোটিশ') ?></span>
    <div class="ticker-track">
      <div class="ticker-inner">
        <?php foreach ($tickerNotices as $tn): ?>
        <a href="<?= pageUrl('notice_detail', ['id' => $tn['id']]) ?>" class="ticker-item">
          <?php if ($tn['is_urgent']): ?><span class="badge-urgent"><?= t('Urgent','জরুরি') ?></span><?php endif; ?>
          <?= h(field($tn, 'title')) ?>
        </a>
        <?php endforeach; ?>
        <?php foreach ($tickerNotices as $tn): // duplicate for seamless loop ?>
        <a href="<?= pageUrl('notice_detail', ['id' => $tn['id']]) ?>" class="ticker-item" aria-hidden="true">
          <?= h(field($tn, 'title')) ?>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>
<?php endif; endif; ?>

<!-- ── Page wrap start ─────────────────────────────────────── -->
<main id="main-content">
