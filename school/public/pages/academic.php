<?php
// public/pages/academic.php
$sub = currentSub();
$subTitles = [
    ''            => ['Academic','শিক্ষা কার্যক্রম'],
    'curriculum'  => ['Curriculum','পাঠ্যক্রম'],
    'departments' => ['Departments','বিভাগসমূহ'],
    'routine'     => ['Class Routine','ক্লাস রুটিন'],
    'exam'        => ['Exam Schedule','পরীক্ষার সময়সূচি'],
    'results'     => ['Results','ফলাফল'],
];
$title = $subTitles[$sub] ?? $subTitles[''];
?>
<div class="page-hero" style="background:linear-gradient(135deg,var(--primary),#004d39)">
  <div class="container">
    <h1 class="page-hero-title"><?= t($title[0],$title[1]) ?></h1>
    <nav class="breadcrumb">
      <a href="<?= pageUrl('index') ?>"><?= t('Home','হোম') ?></a> / 
      <?php if ($sub): ?><a href="<?= pageUrl('academic') ?>"><?= t('Academic','শিক্ষা') ?></a> / <?= t($title[0],$title[1]) ?>
      <?php else: ?><?= t('Academic','শিক্ষা কার্যক্রম') ?><?php endif; ?>
    </nav>
  </div>
</div>

<!-- Sub-nav tabs -->
<div class="sub-nav-bar">
  <div class="container sub-nav-inner">
    <?php foreach (['' => ['Academic Overview','সংক্ষিপ্ত'], 'curriculum' => ['Curriculum','পাঠ্যক্রম'], 'departments' => ['Departments','বিভাগ'], 'routine' => ['Routine','রুটিন'], 'exam' => ['Exam','পরীক্ষা'], 'results' => ['Results','ফলাফল']] as $s => $labels): ?>
    <a href="<?= pageUrl('academic', $s ? ['sub' => $s] : []) ?>" class="sub-nav-link <?= $sub === $s ? 'active' : '' ?>"><?= t($labels[0], $labels[1]) ?></a>
    <?php endforeach; ?>
  </div>
</div>

<div class="container page-body">
  <div class="content-grid">
    <div class="main-col">

      <?php if ($sub === '' || $sub === 'curriculum'): ?>
      <?php
        $pg = getPage('academic');
        if ($pg): ?>
        <div class="cms-content"><?= field($pg,'content') ?></div>
      <?php else: ?>
        <div class="info-cards">
          <div class="info-card"><div class="info-icon">📚</div><h3><?= t('Curriculum','পাঠ্যক্রম') ?></h3><p><?= t('Curriculum details will appear here.','পাঠ্যক্রমের বিস্তারিত এখানে দেখাবে।') ?></p></div>
          <div class="info-card"><div class="info-icon">📖</div><h3><?= t('Syllabus','সিলেবাস') ?></h3><p><?= t('Syllabus information.','সিলেবাসের তথ্য।') ?></p></div>
        </div>
      <?php endif; ?>

      <?php elseif ($sub === 'departments'): ?>
      <?php
        $depts = getDB()->query("SELECT * FROM departments WHERE is_active=1 ORDER BY sort_order")->fetchAll();
      ?>
      <div class="dept-grid">
        <?php foreach ($depts as $dept): ?>
        <div class="dept-card">
          <h3><?= h(field($dept,'name')) ?></h3>
          <?php if ($dept['description_' . getLang()] ?? $dept['description_en']): ?>
          <p><?= h(excerpt(field($dept,'description'), 30)) ?></p>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php if (!$depts): ?><p class="empty-msg"><?= t('No departments listed.','কোনো বিভাগ নেই।') ?></p><?php endif; ?>
      </div>

      <?php elseif ($sub === 'routine'): ?>
      <?php
        $routines = getDB()->query("SELECT * FROM class_routines WHERE is_active=1 ORDER BY class_name_en")->fetchAll();
      ?>
      <table class="data-table">
        <thead>
          <tr>
            <th><?= t('Class','শ্রেণী') ?></th>
            <th><?= t('Section','শাখা') ?></th>
            <th><?= t('Session','সেশন') ?></th>
            <th><?= t('Download','ডাউনলোড') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($routines as $r): ?>
          <tr>
            <td><?= h(field($r,'class_name')) ?></td>
            <td><?= h($r['section']) ?></td>
            <td><?= h($r['session_year']) ?></td>
            <td><?php if ($r['file_url']): ?><a href="<?= h($r['file_url']) ?>" target="_blank" class="btn-sm">📥 <?= t('Download','ডাউনলোড') ?></a><?php else: ?>—<?php endif; ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$routines): ?><tr><td colspan="4" class="empty-td"><?= t('No routines available.','কোনো রুটিন নেই।') ?></td></tr><?php endif; ?>
        </tbody>
      </table>

      <?php elseif ($sub === 'exam'): ?>
      <?php
        $exams = getDB()->query("SELECT * FROM exam_schedules WHERE is_active=1 ORDER BY start_date DESC")->fetchAll();
      ?>
      <table class="data-table">
        <thead>
          <tr>
            <th><?= t('Title','শিরোনাম') ?></th>
            <th><?= t('Class','শ্রেণী') ?></th>
            <th><?= t('Start Date','শুরুর তারিখ') ?></th>
            <th><?= t('End Date','শেষের তারিখ') ?></th>
            <th><?= t('File','ফাইল') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($exams as $e): ?>
          <tr>
            <td><?= h(field($e,'title')) ?></td>
            <td><?= h($e['class_name']) ?></td>
            <td><?= formatDate($e['start_date']) ?></td>
            <td><?= formatDate($e['end_date']) ?></td>
            <td><?php if ($e['file_url']): ?><a href="<?= h($e['file_url']) ?>" target="_blank" class="btn-sm">📥</a><?php else: ?>—<?php endif; ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$exams): ?><tr><td colspan="5" class="empty-td"><?= t('No schedules yet.','কোনো সময়সূচি নেই।') ?></td></tr><?php endif; ?>
        </tbody>
      </table>

      <?php elseif ($sub === 'results'): ?>
      <?php
        $results = getDB()->query("SELECT * FROM results WHERE is_active=1 ORDER BY publish_date DESC")->fetchAll();
      ?>
      <table class="data-table">
        <thead>
          <tr>
            <th><?= t('Title','শিরোনাম') ?></th>
            <th><?= t('Class','শ্রেণী') ?></th>
            <th><?= t('Session','সেশন') ?></th>
            <th><?= t('Published','প্রকাশের তারিখ') ?></th>
            <th><?= t('Action','দেখুন') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($results as $r): ?>
          <tr>
            <td><?= h(field($r,'title')) ?></td>
            <td><?= h($r['class_name']) ?></td>
            <td><?= h($r['session_year']) ?></td>
            <td><?= formatDate($r['publish_date']) ?></td>
            <td>
              <?php if ($r['file_url']): ?><a href="<?= h($r['file_url']) ?>" target="_blank" class="btn-sm">📥</a><?php endif; ?>
              <?php if ($r['ext_link']): ?><a href="<?= h($r['ext_link']) ?>" target="_blank" class="btn-sm">🔗</a><?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$results): ?><tr><td colspan="5" class="empty-td"><?= t('No results published.','কোনো ফলাফল প্রকাশিত হয়নি।') ?></td></tr><?php endif; ?>
        </tbody>
      </table>
      <?php endif; ?>

    </div>
    <?php include __DIR__ . '/sidebar_widget.php'; ?>
  </div>
</div>
