<?php
// public/pages/notice_detail.php
$id = (int)($_GET['id'] ?? 0);
$notice = null;
if ($id) {
    try {
        $stmt = getDB()->prepare("SELECT * FROM notices WHERE id=? AND is_active=1");
        $stmt->execute([$id]);
        $notice = $stmt->fetch();
    } catch(Exception $e) {}
}
if (!$notice) { include __DIR__ . '/404.php'; return; }
?>
<div class="page-hero" style="background:linear-gradient(135deg,var(--primary),#004d39)">
  <div class="container">
    <h1 class="page-hero-title"><?= h(field($notice,'title')) ?></h1>
    <nav class="breadcrumb">
      <a href="<?= pageUrl('index') ?>"><?= t('Home','হোম') ?></a> / 
      <a href="<?= pageUrl('notices') ?>"><?= t('Notices','নোটিশ') ?></a> / 
      <?= h(mb_substr(field($notice,'title'), 0, 40)) ?>
    </nav>
  </div>
</div>
<div class="container page-body">
  <div class="content-grid">
    <div class="main-col">
      <article class="notice-article">
        <div class="notice-article-meta">
          <span class="notice-tag tag-<?= h($notice['type']) ?>"><?= t(ucfirst($notice['type']),'') ?></span>
          <span class="notice-date">📅 <?= formatDate($notice['publish_date'] ?? $notice['created_at']) ?></span>
          <?php if ($notice['is_urgent']): ?><span class="badge-urgent"><?= t('Urgent','জরুরি') ?></span><?php endif; ?>
        </div>
        <h1 class="notice-article-title"><?= h(field($notice,'title')) ?></h1>
        <div class="notice-article-body cms-content">
          <?= field($notice,'content') ?: '<p>' . h(field($notice,'title')) . '</p>' ?>
        </div>
        <?php if ($notice['file_url']): ?>
        <div class="notice-attachment">
          <a href="<?= h($notice['file_url']) ?>" target="_blank" class="btn-download-lg">
            📎 <?= t('Download Attachment','সংযুক্তি ডাউনলোড করুন') ?>
          </a>
        </div>
        <?php endif; ?>
        <?php if ($notice['type'] === 'job'): ?>
        <div class="job-apply-box">
          <h3><?= t('Interested? Apply Online','আগ্রহী? অনলাইনে আবেদন করুন') ?></h3>
          <a href="<?= pageUrl('apply', ['notice_id' => $notice['id']]) ?>" class="btn-apply">📝 <?= t('Apply Now','এখনই আবেদন করুন') ?></a>
        </div>
        <?php endif; ?>
        <div class="notice-share">
          <a href="<?= pageUrl('notices') ?>" class="btn-back">← <?= t('Back to Notices','নোটিশে ফিরুন') ?></a>
        </div>
      </article>
    </div>
    <?php include __DIR__ . '/sidebar_widget.php'; ?>
  </div>
</div>
