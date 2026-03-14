<?php
// public/pages/sidebar_widget.php — Reusable sidebar for inner pages
$sidebarNotices = getNotices('', 6);
$sidebarJobs    = getNotices('job', 3);
?>
<aside class="inner-sidebar">

  <!-- Latest Notices -->
  <div class="sidebar-card">
    <div class="sidebar-card-header">📌 <?= t('Latest Notices','সর্বশেষ নোটিশ') ?></div>
    <ul class="sidebar-notice-list">
      <?php foreach ($sidebarNotices as $sn): ?>
      <li>
        <a href="<?= pageUrl('notice_detail', ['id' => $sn['id']]) ?>"><?= h(mb_substr(field($sn,'title'), 0, 55)) ?>…</a>
        <span class="item-date"><?= formatDate($sn['publish_date'] ?? $sn['created_at']) ?></span>
      </li>
      <?php endforeach; ?>
      <?php if (!$sidebarNotices): ?><li><?= t('No notices.','কোনো নোটিশ নেই।') ?></li><?php endif; ?>
    </ul>
    <a href="<?= pageUrl('notices') ?>" class="sidebar-link"><?= t('All Notices →','সব নোটিশ →') ?></a>
  </div>

  <!-- Job Openings -->
  <?php if ($sidebarJobs): ?>
  <div class="sidebar-card jobs-card">
    <div class="sidebar-card-header jobs-header">💼 <?= t('Job Openings','চাকরির বিজ্ঞপ্তি') ?></div>
    <ul class="sidebar-notice-list">
      <?php foreach ($sidebarJobs as $j): ?>
      <li>
        <a href="<?= pageUrl('notice_detail', ['id' => $j['id']]) ?>"><?= h(mb_substr(field($j,'title'), 0, 50)) ?></a>
        <a href="<?= pageUrl('apply', ['notice_id' => $j['id']]) ?>" class="apply-mini-btn"><?= t('Apply','আবেদন') ?></a>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>

  <!-- Quick Links -->
  <div class="sidebar-card quick-links-card">
    <div class="sidebar-card-header">⚡ <?= t('Quick Links','দ্রুত লিংক') ?></div>
    <ul class="quick-links">
      <li><a href="<?= pageUrl('academic',['sub'=>'routine']) ?>">📅 <?= t('Class Routine','ক্লাস রুটিন') ?></a></li>
      <li><a href="<?= pageUrl('academic',['sub'=>'exam']) ?>">📝 <?= t('Exam Schedule','পরীক্ষার সময়সূচি') ?></a></li>
      <li><a href="<?= pageUrl('academic',['sub'=>'results']) ?>">🏆 <?= t('Results','ফলাফল') ?></a></li>
      <li><a href="<?= pageUrl('admission') ?>">🎓 <?= t('Admission','ভর্তি') ?></a></li>
      <li><a href="<?= pageUrl('gallery') ?>">📷 <?= t('Gallery','গ্যালারি') ?></a></li>
    </ul>
  </div>

</aside>
