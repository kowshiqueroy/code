<?php
/**
 * Students Page Template — Routines, Results, Exam Schedules
 */
try {
    $routines = db()->query("SELECT * FROM routines WHERE is_published=1 AND routine_type='class' ORDER BY academic_year DESC, class_name")->fetchAll();
    $exam_schedules = db()->query("SELECT * FROM routines WHERE is_published=1 AND routine_type='exam' ORDER BY academic_year DESC")->fetchAll();
    $results = db()->query("SELECT * FROM results WHERE is_published=1 ORDER BY exam_year DESC, created_at DESC")->fetchAll();
} catch(Exception $e) { $routines=[]; $exam_schedules=[]; $results=[]; }

$tab = in_array($_GET['tab']??'routines', ['routines','exams','results']) ? ($_GET['tab']??'routines') : 'routines';
?>

<div class="breadcrumb">
  <div class="container">
    <ol>
      <li class="active"><?= t('Students','শিক্ষার্থী') ?></li>
    </ol>
  </div>
</div>

<section class="section">
  <div class="container">
    <div class="main-layout">
      <div>
        <h1 style="color:var(--primary);font-size:1.9rem;font-weight:800;margin-bottom:24px;padding-bottom:12px;border-bottom:3px solid var(--accent)">
          🎓 <?= t('Student Resources','শিক্ষার্থী তথ্যসম্ভার') ?>
        </h1>

        <!-- Tabs -->
        <div style="display:flex;gap:8px;margin-bottom:28px;flex-wrap:wrap">
          <a href="<?= url('students') ?>&tab=routines" class="btn btn-sm <?= $tab==='routines'?'btn-primary':'btn-outline' ?>">📅 <?= t('Class Routines','ক্লাস রুটিন') ?></a>
          <a href="<?= url('students') ?>&tab=exams"    class="btn btn-sm <?= $tab==='exams'?'btn-primary':'btn-outline' ?>">📝 <?= t('Exam Schedule','পরীক্ষার সময়সূচি') ?></a>
          <a href="<?= url('students') ?>&tab=results"  class="btn btn-sm <?= $tab==='results'?'btn-primary':'btn-outline' ?>">📊 <?= t('Results','ফলাফল') ?></a>
        </div>

        <!-- Class Routines -->
        <?php if ($tab === 'routines'): ?>
        <?php if (!empty($routines)): ?>
        <table class="table">
          <thead><tr><th><?= t('Routine','রুটিন') ?></th><th><?= t('Class','শ্রেণী') ?></th><th><?= t('Year','বছর') ?></th><th><?= t('Download','ডাউনলোড') ?></th></tr></thead>
          <tbody>
            <?php foreach ($routines as $r): ?>
            <tr>
              <td><?= h(field($r,'title')) ?></td>
              <td><?= h($r['class_name']) ?></td>
              <td><?= h($r['academic_year']) ?></td>
              <td>
                <?php if ($r['file_path']): ?>
                <a href="<?= upload_url($r['file_path']) ?>" class="btn btn-sm btn-primary" download>⬇️ <?= t('Download','ডাউনলোড') ?></a>
                <?php else: ?>
                <span style="color:var(--text-muted)"><?= t('Not available','পাওয়া যায়নি') ?></span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php else: ?>
        <p style="color:var(--text-muted);text-align:center;padding:40px"><?= t('No routines available yet.','কোনো রুটিন পাওয়া যায়নি।') ?></p>
        <?php endif; ?>

        <!-- Exam Schedule -->
        <?php elseif ($tab === 'exams'): ?>
        <?php if (!empty($exam_schedules)): ?>
        <table class="table">
          <thead><tr><th><?= t('Exam','পরীক্ষা') ?></th><th><?= t('Class','শ্রেণী') ?></th><th><?= t('Year','বছর') ?></th><th><?= t('Download','ডাউনলোড') ?></th></tr></thead>
          <tbody>
            <?php foreach ($exam_schedules as $e): ?>
            <tr>
              <td><?= h(field($e,'title')) ?></td>
              <td><?= h($e['class_name']) ?></td>
              <td><?= h($e['academic_year']) ?></td>
              <td><?php if($e['file_path']): ?><a href="<?= upload_url($e['file_path']) ?>" class="btn btn-sm btn-primary" download>⬇️</a><?php else: ?>—<?php endif; ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php else: ?>
        <p style="color:var(--text-muted);text-align:center;padding:40px"><?= t('No exam schedules yet.','পরীক্ষার সময়সূচি পাওয়া যায়নি।') ?></p>
        <?php endif; ?>

        <!-- Results -->
        <?php elseif ($tab === 'results'): ?>
        <?php if (!empty($results)): ?>
        <table class="table">
          <thead><tr><th><?= t('Result','ফলাফল') ?></th><th><?= t('Exam','পরীক্ষা') ?></th><th><?= t('Year','বছর') ?></th><th><?= t('View','দেখুন') ?></th></tr></thead>
          <tbody>
            <?php foreach ($results as $r): ?>
            <tr>
              <td><?= h(field($r,'title')) ?></td>
              <td><?= h($r['exam_type']) ?></td>
              <td><?= h($r['exam_year']) ?></td>
              <td style="display:flex;gap:6px">
                <?php if ($r['file_path']): ?><a href="<?= upload_url($r['file_path']) ?>" class="btn btn-sm btn-primary" target="_blank">📄 <?= t('PDF','পিডিএফ') ?></a><?php endif; ?>
                <?php if ($r['external_link']): ?><a href="<?= h($r['external_link']) ?>" class="btn btn-sm btn-outline" target="_blank">🔗 <?= t('Online','অনলাইন') ?></a><?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php else: ?>
        <p style="color:var(--text-muted);text-align:center;padding:40px"><?= t('No results published yet.','কোনো ফলাফল প্রকাশিত হয়নি।') ?></p>
        <?php endif; ?>
        <?php endif; ?>

        <!-- Page content from CMS -->
        <?php if (!empty($page_data['content_en']) || !empty($page_data['content_bn'])): ?>
        <div class="page-content" style="margin-top:40px;padding-top:32px;border-top:1px solid var(--border)">
          <?= $page_data[LANG==='bn'?'content_bn':'content_en'] ?? $page_data['content_en'] ?? '' ?>
        </div>
        <?php endif; ?>
      </div>
      <?php include __DIR__ . '/../partials/sidebar.php'; ?>
    </div>
  </div>
</section>
