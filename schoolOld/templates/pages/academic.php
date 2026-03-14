<?php
/**
 * Academic Page Template
 */
try {
    $departments = db()->query("SELECT d.*, t.name_en as head_name_en, t.name_bn as head_name_bn, t.photo as head_photo FROM departments d LEFT JOIN teachers t ON d.head_teacher_id=t.id WHERE d.is_active=1 ORDER BY d.sort_order")->fetchAll();
} catch(Exception $e) { $departments = []; }

$tab = in_array($_GET['tab']??'overview', ['overview','departments','curriculum']) ? ($_GET['tab']??'overview') : 'overview';
?>
<div class="breadcrumb">
  <div class="container">
    <ol>
   
      <li class="active"><?= t('Academic','একাডেমিক') ?></li>
    </ol>
  </div>
</div>
<section class="section">
  <div class="container">
    <div class="main-layout">
      <div>
        <h1 style="color:var(--primary);font-size:1.9rem;font-weight:800;margin-bottom:24px;padding-bottom:12px;border-bottom:3px solid var(--accent)">
          📚 <?= t('Academic Programs','একাডেমিক কার্যক্রম') ?>
        </h1>
        <div style="display:flex;gap:8px;margin-bottom:28px;flex-wrap:wrap">
          <a href="<?= url('academic') ?>&tab=overview"     class="btn btn-sm <?= $tab==='overview'?'btn-primary':'btn-outline' ?>">📋 <?= t('Overview','সংক্ষিপ্ত') ?></a>
          <a href="<?= url('academic') ?>&tab=departments"  class="btn btn-sm <?= $tab==='departments'?'btn-primary':'btn-outline' ?>">🏛️ <?= t('Departments','বিভাগ') ?></a>
          <a href="<?= url('academic') ?>&tab=curriculum"   class="btn btn-sm <?= $tab==='curriculum'?'btn-primary':'btn-outline' ?>">📖 <?= t('Curriculum','পাঠ্যক্রম') ?></a>
        </div>
        <?php if ($tab === 'overview' || $tab === 'curriculum'): ?>
        <div class="page-content">
          <?= $page_data[LANG==='bn'?'content_bn':'content_en'] ?? $page_data['content_en'] ?? '<p style="color:var(--text-muted)">'.t('Academic content coming soon.','একাডেমিক তথ্য শীঘ্রই আসছে।').'</p>' ?>
        </div>
        <?php elseif ($tab === 'departments'): ?>
        <?php if (!empty($departments)): ?>
        <div class="grid grid-3" style="gap:20px">
          <?php foreach ($departments as $dept): ?>
          <div class="card" style="padding:24px;text-align:center">
            <div style="font-size:2.5rem;margin-bottom:12px">🏛️</div>
            <h3 style="color:var(--primary);font-size:1rem;margin-bottom:8px"><?= h(field($dept,'name')) ?></h3>
            <?php if ($dept['description_en'] || $dept['description_bn']): ?>
            <p style="font-size:.85rem;color:var(--text-muted)"><?= h(mb_substr(field($dept,'description'),0,100)) ?></p>
            <?php endif; ?>
            <?php if ($dept['head_name_en']): ?>
            <div style="margin-top:12px;padding-top:12px;border-top:1px solid var(--border)">
              <div style="font-size:.75rem;color:var(--text-muted)"><?= t('Head','বিভাগীয় প্রধান') ?></div>
              <div style="font-size:.88rem;font-weight:600"><?= h(LANG==='bn'?($dept['head_name_bn']?:$dept['head_name_en']):$dept['head_name_en']) ?></div>
            </div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p style="color:var(--text-muted);text-align:center;padding:40px"><?= t('Department information coming soon.','বিভাগের তথ্য শীঘ্রই আসছে।') ?></p>
        <?php endif; ?>
        <?php endif; ?>
      </div>
      <?php include __DIR__ . '/../partials/sidebar.php'; ?>
    </div>
  </div>
</section>
