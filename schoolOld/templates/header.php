<!DOCTYPE html>
<html lang="<?= LANG === 'bn' ? 'bn' : 'en' ?>" dir="ltr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="<?= h(get_setting('site_tagline_'.LANG, get_setting('site_tagline_en'))) ?>">
  <title><?= h($page_title ?? $site_name) ?></title>
  <link rel="icon" href="<?= h($site_favicon ?? asset('images/favicon.ico')) ?>" type="image/x-icon">
  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Bengali:wght@400;500;600;700&family=Hind+Siliguri:wght@400;500;600;700&family=Merriweather+Sans:wght@400;600;700&display=swap" rel="stylesheet">
  <!-- Styles -->
  <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
  <!-- Dynamic theme color from settings -->
  <style>
    :root {
      --primary:      <?= h(get_setting('primary_color','#006B3F')) ?>;
      --secondary:    <?= h(get_setting('secondary_color','#F42A41')) ?>;
      --accent:       <?= h(get_setting('accent_color','#F7A600')) ?>;
      --primary-dark: color-mix(in srgb, <?= h(get_setting('primary_color','#006B3F')) ?> 75%, black);
      --primary-light:color-mix(in srgb, <?= h(get_setting('primary_color','#006B3F')) ?> 10%, white);
    }
    <?php
    // Inject custom CSS if set
    $custom_css = get_setting('custom_header_code');
    if ($custom_css) echo $custom_css;
    // Page-specific custom CSS
    if (!empty($page_data['custom_css'])) echo $page_data['custom_css'];
    ?>
  </style>
  <?= get_setting('google_analytics') ?>
</head>
<body class="<?= LANG === 'bn' ? 'bn' : '' ?>">

<a class="skip-link" href="#main-content">Skip to main content</a>

<!-- ─── Topbar ──────────────────────────────────────────────────────────── -->
<div class="topbar" role="banner">
  <div class="container">
    <div class="topbar-left">
      <?php $phone = get_setting('site_phone'); if($phone): ?>
      <a href="tel:<?= h(preg_replace('/[^0-9+]/','',$phone)) ?>">📞 <?= h($phone) ?></a>
      <?php endif; ?>
      <?php $email = get_setting('site_email'); if($email): ?>
      <a href="mailto:<?= h($email) ?>">✉️ <?= h($email) ?></a>
      <?php endif; ?>
      <?php
      $eiin = get_setting('eiin_number');
      $code = get_setting('institute_code');
      if ($eiin) echo '<span>EIIN: ' . h($eiin) . '</span>';
      if ($code) echo '<span>' . t('Inst. Code','প্রতি. কোড') . ': ' . h($code) . '</span>';
      ?>
    </div>
    <div class="topbar-right">
      <div class="lang-toggle" role="navigation" aria-label="Language switcher">
        <a href="<?= lang_url('en') ?>" class="<?= LANG==='en'?'active':'' ?>" title="Switch to English">EN</a>
        <a href="<?= lang_url('bn') ?>" class="<?= LANG==='bn'?'active':'' ?>" title="Switch to Bangla">বাং</a>
      </div>
      <?php $fb = get_setting('facebook_url'); if($fb): ?>
      <a href="<?= h($fb) ?>" target="_blank" rel="noopener" aria-label="Facebook" style="color:rgba(255,255,255,.8);font-size:1.1rem">f</a>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ─── Site Header ─────────────────────────────────────────────────────── -->
<header class="site-header" role="banner">
  <div class="container">
    <div class="header-inner">
      <!-- Logo -->
      <a href="/" class="site-logo" aria-label="<?= h($site_name) ?> - Home">
        <?php if (!empty($site_logo)): ?>
        <img src="<?= upload_url($site_logo) ?>" alt="<?= h($site_name) ?> Logo" class="logo-img" style="width:56px;height:56px;border-radius:50%;object-fit:cover">
        <?php else: ?>
        <div class="logo-img"><?= mb_substr($site_name, 0, 1) ?></div>
        <?php endif; ?>
        <div class="logo-text">
          <div class="site-name"><?= h($site_name) ?></div>
          <?php if ($site_tagline): ?>
          <div class="site-tagline"><?= h($site_tagline) ?></div>
          <?php endif; ?>
        </div>
      </a>

      <!-- Desktop Navigation -->
      <nav role="navigation" aria-label="Main Navigation">
        <ul class="main-nav">
          <?php foreach ($menus as $menu): ?>
          <?php
          $slug = $menu['slug'] ?: ($menu['url'] ?: '#');
          $href = $menu['url'] ?: url($slug);
          $has_children = !empty($menu_children[$menu['id']]);
          $is_active = (current_page() === $menu['slug']);
          $menu_title = field($menu, 'title');
          ?>
          <li class="<?= $has_children ? 'has-dropdown' : '' ?> <?= $is_active ? 'active' : '' ?>">
            <a href="<?= h($href) ?>" <?= $menu['target']==='_blank'?'target="_blank" rel="noopener"':'' ?>>
              <?= h($menu_title) ?>
              <?= $has_children ? '<span class="arrow" aria-hidden="true">▾</span>' : '' ?>
            </a>
            <?php if ($has_children): ?>
            <ul class="dropdown">
              <?php foreach ($menu_children[$menu['id']] as $child): ?>
              <li>
                <a href="<?= h($child['url'] ?: url($child['slug'])) ?>">
                  <?= h(field($child, 'title')) ?>
                </a>
              </li>
              <?php endforeach; ?>
            </ul>
            <?php endif; ?>
          </li>
          <?php endforeach; ?>
        </ul>
      </nav>

      <!-- Mobile Hamburger -->
      <button class="nav-hamburger" id="menuToggle" aria-label="Open menu" aria-expanded="false" aria-controls="mobileMenu">
        <span class="hamburger-line"></span>
        <span class="hamburger-line"></span>
        <span class="hamburger-line"></span>
      </button>
    </div>
  </div>
</header>

<!-- ─── Mobile Menu ──────────────────────────────────────────────────────── -->
<div class="mobile-menu-overlay" id="mobileOverlay"></div>
<nav class="mobile-menu" id="mobileMenu" role="navigation" aria-label="Mobile Navigation">
  <button class="mobile-close" id="menuClose" aria-label="Close menu">✕</button>
  <ul class="mobile-nav">
    <?php foreach ($menus as $menu): ?>
    <?php
    $href = $menu['url'] ?: url($menu['slug'] ?: '#');
    $menu_title = field($menu, 'title');
    ?>
    <li>
      <a href="<?= h($href) ?>"><?= h($menu_title) ?></a>
    </li>
    <?php if (!empty($menu_children[$menu['id']])): ?>
    <ul class="mobile-submenu">
      <?php foreach ($menu_children[$menu['id']] as $child): ?>
      <li>
        <a href="<?= h($child['url'] ?: url($child['slug'])) ?>">
          <?= h(field($child, 'title')) ?>
        </a>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
    <?php endforeach; ?>
    <li>
      <a href="<?= lang_url('en') ?>" style="<?= LANG==='en'?'color:var(--primary);font-weight:700':'' ?>">🇬🇧 English</a>
    </li>
    <li>
      <a href="<?= lang_url('bn') ?>" style="<?= LANG==='bn'?'color:var(--primary);font-weight:700':'' ?>">🇧🇩 বাংলা</a>
    </li>
  </ul>
</nav>

<!-- ─── Notice Ticker ────────────────────────────────────────────────────── -->
<?php if (!empty($notices)): ?>
<div class="notice-ticker" role="marquee" aria-label="Latest notices">
  <span class="ticker-label"><?= t('Latest','সর্বশেষ') ?></span>
  <div class="ticker-content">
    <div class="ticker-scroll" id="noticeTicker">
      <?php foreach ($notices as $n): ?>
      <a href="<?= url('notices') ?>&id=<?= (int)$n['id'] ?>" class="<?= $n['is_important']?'important':'' ?>">
        <?= h(field($n, 'title')) ?>
      </a>
      <?php endforeach; ?>
      <!-- Duplicate for seamless loop -->
      <?php foreach ($notices as $n): ?>
      <a href="<?= url('notices') ?>&id=<?= (int)$n['id'] ?>">
        <?= h(field($n, 'title')) ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ─── Main Content ─────────────────────────────────────────────────────── -->
<main id="main-content" role="main">
