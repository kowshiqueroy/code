<?php
/**
 * Default Page Template — Renders CMS page content with optional sidebar
 */
$page_title_display = field($page_data, 'title');
$content = $page_data[LANG === 'bn' ? 'content_bn' : 'content_en'] ?? $page_data['content_en'] ?? '';
?>

<!-- Breadcrumb -->
<div class="breadcrumb">
  <div class="container">
    <ol>
      <li class="active"><?= h($page_title_display) ?></li>
    </ol>
  </div>
</div>

<section class="section">
  <div class="container">
    <div class="main-layout">
      <!-- Main Content -->
      <div>
        <h1 style="color:var(--primary);font-size:1.9rem;font-weight:800;margin-bottom:24px;padding-bottom:12px;border-bottom:3px solid var(--accent)">
          <?= h($page_title_display) ?>
        </h1>
        <div class="page-content">
          <?= $content ?: '<p style="color:var(--text-muted)">' . t('Content coming soon.','শীঘ্রই আসছে।') . '</p>' ?>
        </div>
      </div>
      <!-- Sidebar -->
      <?php include __DIR__ . '/../partials/sidebar.php'; ?>
    </div>
  </div>
</section>
