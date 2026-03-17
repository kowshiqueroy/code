<?php
require_once __DIR__.'/../includes/config.php';
logVisitor();
$lang = lang();
$pageTitle = $lang === 'bn' ? 'বৈশ্বিক উপস্থিতি' : 'Global Presence';
$countries = db()->query("SELECT * FROM global_presence WHERE active=1 ORDER BY id")->fetchAll();
require_once __DIR__ . '/partials/header.php';
?>
<section class="section page-section">
  <div class="container">
    <div class="page-hero-header">
      <h1 class="page-hero-title"><?= $lang === 'bn' ? 'বৈশ্বিক উপস্থিতি' : 'Our Global Presence' ?></h1>
      <p><?= $lang === 'bn' ? 'আমরা বিশ্বের ২৫টিরও বেশি দেশে বাংলাদেশের স্বাদ ও মান পৌঁছে দিচ্ছি।' : 'We export Bangladeshi quality to 25+ countries, bringing pride to the nation.' ?></p>
    </div>

    <div class="global-world-section">
      <div class="global-map-placeholder">
        <div class="globe-icon">🌍</div>
        <p><?= $lang === 'bn' ? 'বিশ্বের ২৫+ দেশে রপ্তানি' : 'Exporting to 25+ Countries Worldwide' ?></p>
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
      <h2><?= $lang === 'bn' ? 'আপনার দেশে রপ্তানি করতে চান?' : 'Interested in Importing?' ?></h2>
      <p><?= $lang === 'bn' ? 'আমাদের রপ্তানি বিক্রয় দলের সাথে যোগাযোগ করুন।' : 'Get in touch with our export sales team today.' ?></p>
      <a href="<?= SITE_URL ?>/?page=contact" class="btn btn-primary btn-lg">
        <?= $lang === 'bn' ? 'যোগাযোগ করুন' : 'Contact Export Team' ?>
      </a>
    </div>
  </div>
</section>
<?php require_once __DIR__ . '/partials/footer.php'; ?>
