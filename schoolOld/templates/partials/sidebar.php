<?php
/**
 * Sidebar Partial
 * Included in most page layouts
 */
?>
<aside style="min-width:0">
  <!-- Notice Widget -->
  <?php if (!empty($notices)): ?>
  <div class="sidebar-widget">
    <div class="widget-title">📋 <?= t('Latest Notices','সর্বশেষ বিজ্ঞপ্তি') ?></div>
    <div class="widget-body" style="padding:0">
      <?php foreach (array_slice($notices,0,8) as $n): ?>
      <a href="<?= url('notices') ?>&id=<?= (int)$n['id'] ?>" class="widget-link">
        <span><?= h(mb_substr(field($n,'title'),0,55)) ?><?= mb_strlen(field($n,'title'))>55?'…':'' ?></span>
        <?php if($n['is_important']): ?><span class="badge badge-secondary"><?= t('Important','জরুরি') ?></span><?php endif; ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Quick Downloads -->
  <?php
  try {
    $downloads = db()->query("SELECT * FROM routines WHERE is_published=1 ORDER BY created_at DESC LIMIT 5")->fetchAll();
  } catch(Exception $e) { $downloads = []; }
  ?>
  <?php if (!empty($downloads)): ?>
  <div class="sidebar-widget">
    <div class="widget-title">📄 <?= t('Downloads','ডাউনলোড') ?></div>
    <div class="widget-body" style="padding:0">
      <?php foreach ($downloads as $d): ?>
      <?php if($d['file_path']): ?>
      <a href="<?= upload_url($d['file_path']) ?>" class="widget-link" download>
        <?= h(mb_substr(field($d,'title'),0,50)) ?>
      </a>
      <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Important Links -->
  <div class="sidebar-widget">
    <div class="widget-title">🔗 <?= t('Important Links','গুরুত্বপূর্ণ লিঙ্ক') ?></div>
    <div class="widget-body" style="padding:0">
      <a href="https://www.educationboard.gov.bd" target="_blank" rel="noopener" class="widget-link">Education Board</a>
      <a href="https://www.moedu.gov.bd" target="_blank" rel="noopener" class="widget-link">Ministry of Education</a>
      <a href="https://www.nctb.gov.bd" target="_blank" rel="noopener" class="widget-link">NCTB</a>
      <a href="<?= url('results') ?>" class="widget-link"><?= t('Exam Results','পরীক্ষার ফলাফল') ?></a>
      <a href="<?= url('admissions') ?>" class="widget-link"><?= t('Admissions','ভর্তি') ?></a>
    </div>
  </div>
</aside>
