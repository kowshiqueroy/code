<?php // public/pages/404.php ?>
<div class="container" style="text-align:center;padding:80px 20px">
  <div style="font-size:80px">🔍</div>
  <h1 style="color:var(--primary);margin:20px 0"><?= t('Page Not Found','পৃষ্ঠা পাওয়া যায়নি') ?></h1>
  <p><?= t('The page you are looking for does not exist.','আপনি যে পৃষ্ঠা খুঁজছেন তা পাওয়া যায়নি।') ?></p>
  <a href="<?= pageUrl('index') ?>" style="display:inline-block;margin-top:20px;padding:12px 28px;background:var(--primary);color:#fff;border-radius:6px;text-decoration:none"><?= t('← Go Home','← হোমে যান') ?></a>
</div>
