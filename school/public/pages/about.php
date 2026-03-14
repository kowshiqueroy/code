<?php
// public/pages/about.php
$pg = getPage('about');
?>
<div class="page-hero" style="background:var(--primary)">
  <div class="container">
    <h1 class="page-hero-title"><?= t('About Us','আমাদের সম্পর্কে') ?></h1>
    <nav class="breadcrumb"><a href="<?= pageUrl('index') ?>"><?= t('Home','হোম') ?></a> / <?= t('About Us','আমাদের সম্পর্কে') ?></nav>
  </div>
</div>
<div class="container page-body">
  <div class="content-grid">
    <div class="main-col">
      <?php if ($pg): ?>
      <div class="cms-content">
        <?= field($pg, 'content') ?>
      </div>
      <?php else: ?>
      <div class="info-cards">
        <div class="info-card">
          <div class="info-icon">🏫</div>
          <h3><?= t('History','ইতিহাস') ?></h3>
          <p><?= t('Information about our institution\'s history will appear here.','এখানে আমাদের প্রতিষ্ঠানের ইতিহাস দেখা যাবে।') ?></p>
        </div>
        <div class="info-card">
          <div class="info-icon">🎯</div>
          <h3><?= t('Mission','মিশন') ?></h3>
          <p><?= t('Our mission statement will appear here.','আমাদের মিশন বিবৃতি এখানে দেখা যাবে।') ?></p>
        </div>
        <div class="info-card">
          <div class="info-icon">🔭</div>
          <h3><?= t('Vision','ভিশন') ?></h3>
          <p><?= t('Our vision for the future.','ভবিষ্যতের জন্য আমাদের দৃষ্টিভঙ্গি।') ?></p>
        </div>
      </div>
      <?php endif; ?>
    </div>
    <?php include __DIR__ . '/sidebar_widget.php'; ?>
  </div>
</div>
