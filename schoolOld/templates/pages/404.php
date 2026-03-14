<?php
/**
 * 404 Not Found Page
 */
?>
<div style="text-align:center;padding:80px 20px">
  <div style="font-size:5rem;margin-bottom:16px">🔍</div>
  <h1 style="font-size:4rem;font-weight:800;color:var(--primary);margin-bottom:8px">404</h1>
  <h2 style="font-size:1.4rem;color:var(--text);margin-bottom:16px"><?= t('Page Not Found','পাতাটি পাওয়া যায়নি') ?></h2>
  <p style="color:var(--text-muted);max-width:400px;margin:0 auto 28px"><?= t('The page you are looking for does not exist or has been moved.','আপনি যে পাতাটি খুঁজছেন সেটি নেই বা সরানো হয়েছে।') ?></p>
  <a href="/" class="btn btn-primary">🏠 <?= t('Back to Home','হোম পেজে যান') ?></a>
  &nbsp;
  <a href="<?= url('contact') ?>" class="btn btn-outline"><?= t('Contact Us','যোগাযোগ') ?></a>
</div>
