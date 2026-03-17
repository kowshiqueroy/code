<?php
require_once __DIR__.'/../includes/config.php';
logVisitor();
$lang = lang();
$pageTitle = $lang === 'bn' ? 'সিস্টার কনসার্ন' : 'Sister Concerns';
$concerns = db()->query("SELECT * FROM sister_concerns WHERE active=1 ORDER BY sort_order")->fetchAll();
require_once __DIR__ . '/partials/header.php';
?>
<section class="section page-section">
  <div class="container">
    <div class="page-hero-header">
      <h1 class="page-hero-title"><?= $lang === 'bn' ? 'আমাদের সিস্টার কনসার্ন' : 'Our Sister Concerns' ?></h1>
      <p><?= $lang === 'bn' ? 'অভিজাতগ্রুপের অন্তর্ভুক্ত বিভিন্ন প্রতিষ্ঠানের সাথে পরিচিত হন।' : 'Get acquainted with the diverse companies under the Ovijat Group umbrella.' ?></p>
    </div>
    <div class="concerns-detailed-grid">
      <?php foreach ($concerns as $c): ?>
        <div class="concern-detailed-card">
          <div class="concern-logo-big">
            <?php if ($c['logo']): ?>
              <img src="<?= imgUrl($c['logo'], 'concerns', 'concern') ?>" alt="<?= t($c,'name') ?>" loading="lazy">
            <?php else: ?>
              <div class="concern-icon-lg">🏭</div>
            <?php endif; ?>
          </div>
          <div class="concern-detailed-body">
            <h2 class="concern-name-lg"><?= t($c,'name') ?></h2>
            <?php $desc = $lang === 'bn' ? $c['desc_bn'] : $c['desc_en'];
            if ($desc): ?><p class="concern-desc-full"><?= nl2br(e($desc)) ?></p><?php endif; ?>
            <?php if ($c['website']): ?>
              <a href="<?= e($c['website']) ?>" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm">
                <?= $lang === 'bn' ? 'ওয়েবসাইট ভিজিট করুন ↗' : 'Visit Website ↗' ?>
              </a>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php require_once __DIR__ . '/partials/footer.php'; ?>
