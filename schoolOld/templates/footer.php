</main>

<!-- ─── Footer ──────────────────────────────────────────────────────────── -->
<footer class="site-footer" role="contentinfo">
  <div class="container">
    <div class="footer-grid">
      <!-- About Column -->
      <div class="footer-col">
        <h4><?= h($site_name) ?></h4>
        <p><?= h(t(get_setting('site_tagline_en'), get_setting('site_tagline_bn'))) ?></p>
        <?php $est = get_setting('established_year'); if($est): ?>
        <p style="margin-top:10px;font-size:.82rem"><?= t('Established','প্রতিষ্ঠিত') ?>: <?= h($est) ?></p>
        <?php endif; ?>
        <div style="margin-top:16px;display:flex;gap:8px;flex-wrap:wrap">
          <?php if (get_setting('eiin_number')): ?>
          <span style="background:rgba(255,255,255,.1);padding:4px 10px;border-radius:4px;font-size:.75rem">EIIN: <?= h(get_setting('eiin_number')) ?></span>
          <?php endif; ?>
        </div>
      </div>

      <!-- Quick Links -->
      <div class="footer-col">
        <h4><?= t('Quick Links','দ্রুত লিঙ্ক') ?></h4>
        <div class="footer-links">
          <?php foreach (array_slice($menus, 0, 8) as $m): ?>
          <a href="<?= h($m['url'] ?: url($m['slug'])) ?>"><?= h(field($m,'title')) ?></a>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Useful Links -->
      <div class="footer-col">
        <h4><?= t('Govt. Links','সরকারি লিঙ্ক') ?></h4>
        <div class="footer-links">
          <a href="https://www.bangladesh.gov.bd" target="_blank" rel="noopener">Bangladesh National Portal</a>
          <a href="https://www.moedu.gov.bd" target="_blank" rel="noopener">Ministry of Education</a>
          <a href="https://www.educationboard.gov.bd" target="_blank" rel="noopener">Education Board</a>
          <a href="https://www.nctb.gov.bd" target="_blank" rel="noopener">NCTB</a>
          <a href="https://www.eshikhon.gov.bd" target="_blank" rel="noopener">eShikhon</a>
          <a href="https://banbeis.gov.bd" target="_blank" rel="noopener">BANBEIS</a>
        </div>
      </div>

      <!-- Contact -->
      <div class="footer-col">
        <h4><?= t('Contact Us','যোগাযোগ') ?></h4>
        <?php $addr = t(get_setting('site_address_en'), get_setting('site_address_bn')); if($addr): ?>
        <div class="footer-contact-item">
          <span class="icon">📍</span>
          <span><?= h($addr) ?></span>
        </div>
        <?php endif; ?>
        <?php $phone = get_setting('site_phone'); if($phone): ?>
        <div class="footer-contact-item">
          <span class="icon">📞</span>
          <a href="tel:<?= h(preg_replace('/[^0-9+]/','',$phone)) ?>" style="color:rgba(255,255,255,.8)"><?= h($phone) ?></a>
        </div>
        <?php endif; ?>
        <?php $email = get_setting('site_email'); if($email): ?>
        <div class="footer-contact-item">
          <span class="icon">✉️</span>
          <a href="mailto:<?= h($email) ?>" style="color:rgba(255,255,255,.8)"><?= h($email) ?></a>
        </div>
        <?php endif; ?>
        <!-- Language Toggle -->
        <div style="margin-top:20px;padding-top:16px;border-top:1px solid rgba(255,255,255,.15)">
          <span style="font-size:.8rem;opacity:.7;display:block;margin-bottom:8px"><?= t('Language / ভাষা','Language / ভাষা') ?></span>
          <div style="display:flex;gap:8px">
            <a href="<?= lang_url('en') ?>" style="padding:5px 14px;border:1px solid rgba(255,255,255,.3);border-radius:6px;font-size:.8rem;color:<?= LANG==='en'?'var(--accent)':'rgba(255,255,255,.7)' ?>;font-weight:<?= LANG==='en'?'700':'400' ?>">English</a>
            <a href="<?= lang_url('bn') ?>" style="padding:5px 14px;border:1px solid rgba(255,255,255,.3);border-radius:6px;font-size:.8rem;color:<?= LANG==='bn'?'var(--accent)':'rgba(255,255,255,.7)' ?>;font-weight:<?= LANG==='bn'?'700':'400' ?>">বাংলা</a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Footer Bottom -->
  <div class="footer-bottom">
    <div class="container" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;width:100%">
      <span>© <?= date('Y') ?> <?= h($site_name) ?>. <?= t('All Rights Reserved.','সকল স্বত্ব সংরক্ষিত।') ?></span>
      <div class="footer-badges">
        <span class="govt-badge">🇧🇩 Govt. Approved</span>
        <span style="font-size:.72rem">Powered by BanglaEdu CMS</span>
      </div>
    </div>
  </div>
</footer>

<!-- ─── Scroll to Top ────────────────────────────────────────────────────── -->
<button class="scroll-top" id="scrollTop" aria-label="Scroll to top" title="Scroll to top">↑</button>

<!-- ─── Lightbox ─────────────────────────────────────────────────────────── -->
<div class="lightbox-overlay" id="lightbox" role="dialog" aria-modal="true" aria-label="Image preview">
  <button class="lightbox-close" id="lightboxClose" aria-label="Close lightbox">✕</button>
  <img src="" alt="" class="lightbox-img" id="lightboxImg">
</div>

<!-- ─── Main JS ──────────────────────────────────────────────────────────── -->
<script src="<?= asset('js/app.js') ?>"></script>

<?php
// Custom footer code from settings
echo get_setting('custom_footer_code');
// Page-specific custom JS
if (!empty($page_data['custom_js'])): ?>
<script><?= $page_data['custom_js'] ?></script>
<?php endif; ?>

</body>
</html>
