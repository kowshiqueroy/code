<?php
// public/pages/cms_page.php
// $cmsPage is set by index.php router
?>
<div class="page-hero" style="background:var(--primary)">
  <div class="container">
    <h1 class="page-hero-title"><?= h(field($cmsPage,'title')) ?></h1>
    <nav class="breadcrumb">
      <a href="<?= pageUrl('index') ?>"><?= t('Home','হোম') ?></a> / <?= h(field($cmsPage,'title')) ?>
    </nav>
  </div>
</div>
<div class="container page-body">
  <div class="content-grid">
    <div class="main-col">
      <div class="cms-content">
        <?= field($cmsPage,'content') ?>
      </div>
    </div>
    <?php include __DIR__ . '/sidebar_widget.php'; ?>
  </div>
</div>
