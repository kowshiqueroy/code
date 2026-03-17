<?php
require_once __DIR__.'/../includes/config.php';
logVisitor();
$lang = lang();
$pageTitle = L('nav_global');
$countries = db()->query("SELECT * FROM global_presence WHERE active=1 ORDER BY id")->fetchAll();
require_once __DIR__ . '/partials/header.php';
?>
<section class="section page-section">
  <div class="container">
    <div class="page-hero-header">
      <h1 class="page-hero-title"><?= L('global_title') ?></h1>
      <p><?= L('global_subtitle') ?></p>
    </div>

    <div class="global-world-section">
      <div class="global-map-placeholder">
        <div class="globe-icon">🌍</div>
        <p><?= L('global_map_caption') ?></p>
      </div>
      <div class="global-countries-full">
        <?php foreach ($countries as $c): ?>
          <div class="global-country-pill">
            <span class="flag-lg"><?= e($c['flag_emoji']) ?></span>
            <span><?= e($lang === 'bn' ? $c['country_bn'] : $c['country_en']) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="global-cta-box">
      <h2><?= L('global_cta_title') ?></h2>
      <p><?= L('global_cta_desc') ?></p>
      <a href="<?= SITE_URL ?>/?page=contact" class="btn btn-primary btn-lg">
        <?= L('global_cta_btn') ?>
      </a>
    </div>
  </div>
</section>
<?php require_once __DIR__ . '/partials/footer.php'; ?>
