<?php
require_once __DIR__.'/../includes/config.php';
logVisitor();
$lang = lang();
$pageTitle = $lang === 'bn' ? 'ব্যবস্থাপনা' : 'Management';
$mgmt = db()->query("SELECT * FROM management WHERE active=1 ORDER BY sort_order")->fetchAll();
require_once __DIR__ . '/partials/header.php';
?>
<section class="section page-section">
  <div class="container">
    <div class="page-hero-header">
      <h1 class="page-hero-title"><?= $lang === 'bn' ? 'আমাদের নেতৃত্ব' : 'Our Leadership' ?></h1>
      <p><?= $lang === 'bn' ? 'অভিজ্ঞ ও দূরদর্শী ব্যক্তিত্বদের নেতৃত্বে অভিজাতগ্রুপ এগিয়ে চলেছে।' : 'Ovijat Group is guided by visionary leaders with decades of industry experience.' ?></p>
    </div>
    <div class="mgmt-detailed-grid">
      <?php foreach ($mgmt as $m): ?>
        <div class="mgmt-profile-card">
          <div class="mgmt-profile-top">
            <div class="mgmt-profile-photo">
              <img src="<?= imgUrl($m['image'] ?? '', 'management', 'management') ?>" alt="<?= t($m,'name') ?>" loading="lazy">
            </div>
            <div class="mgmt-profile-id">
              <h2 class="mgmt-profile-name"><?= t($m,'name') ?></h2>
              <span class="mgmt-profile-title"><?= t($m,'title') ?></span>
            </div>
          </div>
          <?php $msg = $lang === 'bn' ? $m['message_bn'] : $m['message_en'];
          if ($msg): ?>
            <blockquote class="mgmt-message-full">
              <p><?= nl2br(e($msg)) ?></p>
            </blockquote>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php require_once __DIR__ . '/partials/footer.php'; ?>
