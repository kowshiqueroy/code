<?php
// public/pages/admission.php
$sub = currentSub();
$admItems = [];
try {
    if ($sub) {
        $stmt = getDB()->prepare("SELECT * FROM admissions WHERE type=? AND is_active=1 ORDER BY sort_order");
        $stmt->execute([$sub]);
        $admItems = $stmt->fetchAll();
    } else {
        $admItems = getDB()->query("SELECT * FROM admissions WHERE is_active=1 ORDER BY sort_order")->fetchAll();
    }
} catch(Exception $e) { $admItems = []; }

$jobs = getNotices('job', 20);
?>
<div class="page-hero" style="background:linear-gradient(135deg,#7c3238,#5a1e2a)">
  <div class="container">
    <h1 class="page-hero-title"><?= t('Admission','ভর্তি তথ্য') ?></h1>
    <nav class="breadcrumb"><a href="<?= pageUrl('index') ?>"><?= t('Home','হোম') ?></a> / <?= t('Admission','ভর্তি') ?></nav>
  </div>
</div>

<div class="sub-nav-bar">
  <div class="container sub-nav-inner">
    <a href="<?= pageUrl('admission') ?>" class="sub-nav-link <?= !$sub ? 'active' : '' ?>"><?= t('Overview','সংক্ষিপ্ত') ?></a>
    <a href="<?= pageUrl('admission',['sub'=>'rules']) ?>" class="sub-nav-link <?= $sub==='rules'?'active':'' ?>"><?= t('Rules','নিয়মাবলী') ?></a>
    <a href="<?= pageUrl('admission',['sub'=>'form']) ?>" class="sub-nav-link <?= $sub==='form'?'active':'' ?>"><?= t('Forms','ফর্ম') ?></a>
    <a href="<?= pageUrl('admission',['sub'=>'fees']) ?>" class="sub-nav-link <?= $sub==='fees'?'active':'' ?>"><?= t('Fees','ফি') ?></a>
    <a href="<?= pageUrl('admission',['sub'=>'jobs']) ?>" class="sub-nav-link <?= $sub==='jobs'?'active':'' ?>">💼 <?= t('Jobs','চাকরি') ?></a>
  </div>
</div>

<div class="container page-body">
  <div class="content-grid">
    <div class="main-col">

      <?php if ($sub === 'jobs'): ?>
        <h2 class="section-title-inner">💼 <?= t('Job Openings','চাকরির বিজ্ঞপ্তি') ?></h2>
        <?php if ($jobs): ?>
        <ul class="notice-list">
          <?php foreach ($jobs as $j): ?>
          <li class="notice-item <?= $j['is_urgent'] ? 'urgent' : '' ?>">
            <div class="notice-meta">
              <span class="notice-date"><?= formatDate($j['publish_date'] ?? $j['created_at']) ?></span>
              <?php if ($j['is_urgent']): ?><span class="badge-urgent"><?= t('Urgent','জরুরি') ?></span><?php endif; ?>
            </div>
            <a href="<?= pageUrl('notice_detail', ['id' => $j['id']]) ?>" class="notice-title"><?= h(field($j,'title')) ?></a>
            <div class="notice-actions">
              <?php if ($j['file_url']): ?><a href="<?= h($j['file_url']) ?>" class="btn-sm" target="_blank">📎 <?= t('Circular','বিজ্ঞপ্তি') ?></a><?php endif; ?>
              <a href="<?= pageUrl('apply', ['notice_id' => $j['id']]) ?>" class="btn-primary-sm">📝 <?= t('Apply Online','অনলাইনে আবেদন') ?></a>
            </div>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php else: ?><p class="empty-msg"><?= t('No current openings.','বর্তমানে কোনো শূন্য পদ নেই।') ?></p><?php endif; ?>

      <?php else: ?>
        <?php foreach ($admItems as $item): ?>
        <div class="adm-item">
          <h3><?= h(field($item,'title')) ?></h3>
          <?php if (field($item,'content')): ?>
          <div class="cms-content"><?= field($item,'content') ?></div>
          <?php endif; ?>
          <?php if ($item['file_url']): ?>
          <a href="<?= h($item['file_url']) ?>" class="btn-download" target="_blank">📥 <?= t('Download','ডাউনলোড') ?></a>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php if (!$admItems): ?><p class="empty-msg"><?= t('Admission information will be updated soon.','ভর্তি তথ্য শীঘ্রই আপডেট করা হবে।') ?></p><?php endif; ?>
      <?php endif; ?>

    </div>
    <?php include __DIR__ . '/sidebar_widget.php'; ?>
  </div>
</div>
