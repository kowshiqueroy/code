<?php
// public/includes/footer.php
$lang      = getLang();
$siteName  = getSetting('site_name_' . $lang, getSetting('site_name_en', 'School'));
$address   = getSetting('address_' . $lang, getSetting('address_en', ''));
$phone     = getSetting('phone', '');
$emailAddr = getSetting('email', '');
$mapEmbed  = getSetting('google_map_embed', '');
$devName   = getSetting('developer_name', 'Developer');
$devUrl    = getSetting('developer_url', '#');
$fbUrl     = getSetting('facebook_url', '');
$ytUrl     = getSetting('youtube_url', '');
$currentYear = date('Y');
?>
</main><!-- /#main-content -->

<!-- ── Footer ─────────────────────────────────────────────── -->
<footer class="site-footer" id="contact">
  <div class="footer-top">
    <div class="container footer-grid">

      <!-- About -->
      <div class="footer-col">
        <h3 class="footer-heading"><?= h($siteName) ?></h3>
        <?php if ($address): ?><p class="footer-address">📍 <?= h($address) ?></p><?php endif; ?>
        <?php if ($phone): ?><p>📞 <a href="tel:<?= h($phone) ?>"><?= h($phone) ?></a></p><?php endif; ?>
        <?php if ($emailAddr): ?><p>✉️ <a href="mailto:<?= h($emailAddr) ?>"><?= h($emailAddr) ?></a></p><?php endif; ?>
        <div class="footer-social">
          <?php if ($fbUrl): ?><a href="<?= h($fbUrl) ?>" target="_blank" aria-label="Facebook" class="social-btn">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
          </a><?php endif; ?>
          <?php if ($ytUrl): ?><a href="<?= h($ytUrl) ?>" target="_blank" aria-label="YouTube" class="social-btn">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M22.54 6.42a2.78 2.78 0 0 0-1.95-1.96C18.88 4 12 4 12 4s-6.88 0-8.59.46a2.78 2.78 0 0 0-1.95 1.96A29 29 0 0 0 1 12a29 29 0 0 0 .46 5.58A2.78 2.78 0 0 0 3.41 19.6C5.12 20 12 20 12 20s6.88 0 8.59-.46a2.78 2.78 0 0 0 1.95-1.95A29 29 0 0 0 23 12a29 29 0 0 0-.46-5.58zM9.75 15.02V8.98L15.5 12l-5.75 3.02z"/></svg>
          </a><?php endif; ?>
        </div>
      </div>

      <!-- Quick Links -->
      <div class="footer-col">
        <h3 class="footer-heading"><?= t('Quick Links','দ্রুত লিংক') ?></h3>
        <ul class="footer-links">
          <li><a href="<?= pageUrl('index') ?>"><?= t('Home','হোম') ?></a></li>
          <li><a href="<?= pageUrl('about') ?>"><?= t('About Us','আমাদের সম্পর্কে') ?></a></li>
          <li><a href="<?= pageUrl('academic') ?>"><?= t('Academic','শিক্ষা কার্যক্রম') ?></a></li>
          <li><a href="<?= pageUrl('administration') ?>"><?= t('Administration','প্রশাসন') ?></a></li>
          <li><a href="<?= pageUrl('admission') ?>"><?= t('Admission','ভর্তি') ?></a></li>
          <li><a href="<?= pageUrl('notices') ?>"><?= t('Notices','নোটিশ') ?></a></li>
          <li><a href="<?= pageUrl('gallery') ?>"><?= t('Gallery','গ্যালারি') ?></a></li>
        </ul>
      </div>

      <!-- Recent Notices -->
      <div class="footer-col">
        <h3 class="footer-heading"><?= t('Recent Notices','সাম্প্রতিক নোটিশ') ?></h3>
        <ul class="footer-notices">
          <?php foreach (getNotices('', 5) as $fn): ?>
          <li>
            <a href="<?= pageUrl('notice_detail', ['id' => $fn['id']]) ?>">
              <span class="notice-date"><?= formatDate($fn['publish_date'] ?? $fn['created_at']) ?></span>
              <?= h(mb_substr(field($fn, 'title'), 0, 60)) ?>…
            </a>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>

      <!-- Map / Contact -->
      <div class="footer-col">
        <h3 class="footer-heading"><?= t('Location','অবস্থান') ?></h3>
        <?php if ($mapEmbed): ?>
        <div class="footer-map">
          <?= $mapEmbed // Admin-set iframe embed, trusted content ?>
        </div>
        <?php else: ?>
        <div class="footer-map-placeholder">
          <div class="map-icon">🗺️</div>
          <p><?= h($address) ?></p>
        </div>
        <?php endif; ?>
      </div>

    </div>
  </div>

  <!-- Footer Bottom -->
  <div class="footer-bottom">
    <div class="container footer-bottom-inner">
      <div class="footer-copy">
        &copy; <?= $currentYear ?> <?= h($siteName) ?>. 
        <?= t('All rights reserved.','সর্বস্বত্ব সংরক্ষিত।') ?>
      </div>
      <div class="footer-meta">
        <!-- Language Toggle -->
        <div class="lang-switch-footer">
          <a href="?setlang=bn" class="<?= getLang() === 'bn' ? 'active' : '' ?>">বাংলা</a>
          <span>|</span>
          <a href="?setlang=en" class="<?= getLang() === 'en' ? 'active' : '' ?>">English</a>
        </div>
        <span class="footer-sep">|</span>
        <span class="footer-dev">
          <?= t('Developed by','ডেভেলপার') ?>: <a href="<?= h($devUrl) ?>" target="_blank"><?= h($devName) ?></a>
        </span>
        <span class="footer-sep">|</span>
        <a href="<?= ADMIN_PATH ?>" class="admin-panel-link" target="_blank"><?= t('Admin Panel','অ্যাডমিন প্যানেল') ?></a>
      </div>
    </div>
  </div>
</footer>

<!-- ── Scroll to Top ───────────────────────────────────────── -->
<button class="scroll-top" id="scrollTop" aria-label="Scroll to top">↑</button>

<script src="<?= BASE_URL ?>/assets/js/public.js"></script>
</body>
</html>
