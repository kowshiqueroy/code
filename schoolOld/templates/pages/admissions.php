<?php
/**
 * Admissions Page Template
 */
try {
    $admission_items = db()->query("SELECT * FROM admission_info WHERE is_active=1 ORDER BY created_at DESC")->fetchAll();
} catch(Exception $e) { $admission_items = []; }

$tab = in_array($_GET['tab']??'info', ['info','form','fees']) ? ($_GET['tab']??'info') : 'info';
?>

<div class="breadcrumb">
  <div class="container">
    <ol>
      <li class="active"><?= t('Admissions','ভর্তি') ?></li>
    </ol>
  </div>
</div>

<section class="section">
  <div class="container">
    <div class="main-layout">
      <div>
        <h1 style="color:var(--primary);font-size:1.9rem;font-weight:800;margin-bottom:24px;padding-bottom:12px;border-bottom:3px solid var(--accent)">
          📝 <?= t('Admissions','ভর্তি তথ্য') ?>
        </h1>

        <!-- Tabs -->
        <div style="display:flex;gap:8px;margin-bottom:28px;flex-wrap:wrap">
          <a href="<?= url('admissions') ?>&tab=info"  class="btn btn-sm <?= $tab==='info'?'btn-primary':'btn-outline' ?>">📋 <?= t('Admission Info','ভর্তি তথ্য') ?></a>
          <a href="<?= url('admissions') ?>&tab=form"  class="btn btn-sm <?= $tab==='form'?'btn-primary':'btn-outline' ?>">📄 <?= t('Forms','ফর্ম') ?></a>
          <a href="<?= url('admissions') ?>&tab=fees"  class="btn btn-sm <?= $tab==='fees'?'btn-primary':'btn-outline' ?>">💰 <?= t('Fee Structure','বেতন তালিকা') ?></a>
        </div>

        <?php if ($tab === 'info'): ?>
        <!-- Admission Info from CMS -->
        <?php if (!empty($admission_items)): ?>
        <?php foreach ($admission_items as $item): ?>
        <div style="background:var(--white);border:1px solid var(--border);border-left:4px solid var(--primary);border-radius:var(--radius);padding:24px;margin-bottom:20px">
          <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap">
            <div style="flex:1">
              <h2 style="font-size:1.1rem;font-weight:700;color:var(--primary);margin-bottom:8px"><?= h(field($item,'title')) ?></h2>
              <?php if ($item['class_name']): ?>
              <div style="margin-bottom:8px">
                <span class="badge badge-primary"><?= t('Class','শ্রেণী') ?>: <?= h($item['class_name']) ?></span>
                <?php if ($item['academic_year']): ?>
                <span class="badge badge-accent" style="margin-left:6px"><?= h($item['academic_year']) ?></span>
                <?php endif; ?>
              </div>
              <?php endif; ?>
              <div class="page-content" style="margin-top:12px"><?= field($item,'content') ?: '<p style="color:var(--text-muted)">'.t('Details coming soon.','বিস্তারিত শীঘ্রই আসছে।').'</p>' ?></div>
            </div>
            <div style="flex-shrink:0;text-align:right">
              <?php if ($item['last_date']): ?>
              <div style="background:var(--secondary);color:#fff;padding:8px 14px;border-radius:var(--radius);font-size:.82rem;text-align:center">
                <div style="font-weight:700"><?= t('Last Date','শেষ তারিখ') ?></div>
                <div style="font-size:1rem;font-weight:800"><?= date('d M Y', strtotime($item['last_date'])) ?></div>
              </div>
              <?php endif; ?>
              <?php if ($item['form_file']): ?>
              <a href="<?= upload_url($item['form_file']) ?>" class="btn btn-primary btn-sm" style="margin-top:8px;display:block" download>
                ⬇️ <?= t('Download Form','ফর্ম ডাউনলোড') ?>
              </a>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php else: ?>
        <!-- Default admission content -->
        <div class="page-content">
          <?= $page_data[LANG==='bn'?'content_bn':'content_en'] ?? $page_data['content_en'] ?? '<p style="color:var(--text-muted)">'.t('Admission information will be published here.','ভর্তির তথ্য এখানে প্রকাশিত হবে।').'</p>' ?>
        </div>
        <?php endif; ?>

        <?php elseif ($tab === 'form'): ?>
        <!-- Admission Forms -->
        <div class="page-content">
          <h2><?= t('Admission Forms','ভর্তি ফর্ম') ?></h2>
          <?php if (!empty($admission_items)): ?>
          <table class="table" style="margin-top:20px">
            <thead><tr><th><?= t('Title','শিরোনাম') ?></th><th><?= t('Class','শ্রেণী') ?></th><th><?= t('Year','বছর') ?></th><th><?= t('Download','ডাউনলোড') ?></th></tr></thead>
            <tbody>
              <?php foreach ($admission_items as $item): ?>
              <?php if ($item['form_file']): ?>
              <tr>
                <td><?= h(field($item,'title')) ?></td>
                <td><?= h($item['class_name']) ?></td>
                <td><?= h($item['academic_year']) ?></td>
                <td><a href="<?= upload_url($item['form_file']) ?>" class="btn btn-sm btn-primary" download>⬇️ <?= t('Download','ডাউনলোড') ?></a></td>
              </tr>
              <?php endif; ?>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php else: ?>
          <p style="color:var(--text-muted)"><?= t('No forms available yet.','কোনো ফর্ম পাওয়া যায়নি।') ?></p>
          <?php endif; ?>
        </div>

        <?php elseif ($tab === 'fees'): ?>
        <!-- Fee Structure from CMS -->
        <div class="page-content">
          <?= $page_data[LANG==='bn'?'content_bn':'content_en'] ?? $page_data['content_en'] ?? '<p style="color:var(--text-muted)">'.t('Fee structure will be published here.','বেতন তালিকা এখানে প্রকাশিত হবে।').'</p>' ?>
        </div>
        <?php endif; ?>
      </div>
      <?php include __DIR__ . '/../partials/sidebar.php'; ?>
    </div>
  </div>
</section>
