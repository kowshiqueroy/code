<?php
/**
 * OVIJAT GROUP — footer.php v2.0 (Refactored)
 */
$lang     = lang();
$siteName = 'OVIJAT';
$helpline = getDynamicHelpline();
$facebook = setting('facebook','');
$linkedin = setting('linkedin','');
$youtube  = setting('youtube','');
?>
</main>

<a href="tel:<?= e($helpline) ?>" class="float-helpline" title="Call Helpline">📞</a>

<?php $brochureFile = setting('brochure_pdf'); if($brochureFile): ?>
<a href="<?= SITE_URL ?>/uploads/docs/<?= $brochureFile ?>" class="float-brochure" target="_blank" title="Download Brochure">
  <span class="brochure-icon">📄</span> <span class="brochure-text"><?= L('read_more') === 'আরও পড়ুন' ? 'ব্রোশিওর' : 'Brochure' ?></span>
</a>
<?php endif; ?>

<footer class="site-footer">
  <div class="footer-top">
    <div class="container footer-grid">

      <!-- About -->
      <div class="footer-col footer-about-col">
        <h3 class="footer-heading"><?= L('footer_about_title') ?></h3>
        <p class="footer-about-text"><?= e(setting('footer_about_'.$lang,'Ovijat Group is one of Bangladesh\'s leading food and beverage conglomerates.')) ?></p>
        <div class="footer-social">
          <?php if($facebook): ?><a href="<?= e($facebook) ?>" target="_blank" rel="noopener" class="social-icon" aria-label="Facebook"><svg viewBox="0 0 24 24" fill="currentColor" width="18"><path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/></svg></a><?php endif; ?>
          <?php if($linkedin): ?><a href="<?= e($linkedin) ?>" target="_blank" rel="noopener" class="social-icon" aria-label="LinkedIn"><svg viewBox="0 0 24 24" fill="currentColor" width="18"><path d="M16 8a6 6 0 016 6v7h-4v-7a2 2 0 00-4 0v7H10v-7a6 6 0 016-6zM2 9h4v12H2z"/><circle cx="4" cy="4" r="2"/></svg></a><?php endif; ?>
          <?php if($youtube): ?><a href="<?= e($youtube) ?>" target="_blank" rel="noopener" class="social-icon" aria-label="YouTube"><svg viewBox="0 0 24 24" fill="currentColor" width="18"><path d="M22.54 6.42a2.78 2.78 0 00-1.95-1.96C18.88 4 12 4 12 4s-6.88 0-8.59.46a2.78 2.78 0 00-1.95 1.96A29 29 0 001 12a29 29 0 00.46 5.58 2.78 2.78 0 001.95 1.96C5.12 20 12 20 12 20s6.88 0 8.59-.46a2.78 2.78 0 001.95-1.96A29 29 0 0023 12a29 29 0 00-.46-5.58z"/><polygon points="9.75 15.02 15.5 12 9.75 8.98 9.75 15.02" fill="white"/></svg></a><?php endif; ?>
        </div>
      </div>

      <!-- Quick Links -->
      <div class="footer-col">
        <h3 class="footer-heading"><?= L('footer_quick_links') ?></h3>
        <ul class="footer-links">
          <li><a href="<?= SITE_URL ?>/?page=home"><?= L('nav_home') ?></a></li>
          <li><a href="<?= SITE_URL ?>/?page=products"><?= L('nav_products') ?></a></li>
          <li><a href="<?= SITE_URL ?>/?page=rice"><?= L('footer_rice_showcase') ?></a></li>
          <li><a href="<?= SITE_URL ?>/?page=concerns"><?= L('footer_sister_concerns') ?></a></li>
          <li><a href="<?= SITE_URL ?>/?page=global"><?= L('nav_global') ?></a></li>
          <li><a href="<?= SITE_URL ?>/?page=management"><?= L('footer_management') ?></a></li>
          <li><a href="<?= SITE_URL ?>/?page=careers"><?= L('nav_careers') ?></a></li>
          <li><a href="<?= SITE_URL ?>/?page=contact"><?= L('nav_contact') ?></a></li>
        </ul>
      </div>

      <!-- Products -->
      <div class="footer-col">
        <h3 class="footer-heading"><?= L('footer_product_lines') ?></h3>
        <ul class="footer-links">
          <?php
          try {
            $cats=db()->query("SELECT * FROM product_categories WHERE parent_id IS NULL AND active=1 ORDER BY sort_order LIMIT 6")->fetchAll();
            foreach($cats as $cat):
          ?><li><a href="<?= SITE_URL ?>/?page=category&id=<?= $cat['id'] ?>"><?= t($cat,'name') ?></a></li><?php
            endforeach;
          }catch(Exception $e){}
          ?>
        </ul>
      </div>

      <!-- Office Contacts -->
      <div class="footer-col">
        <h3 class="footer-heading"><?= L('footer_offices') ?></h3>
        <div class="footer-offices">
          <div class="footer-office-block">
            <div class="footer-office-title">🇺🇸 USA Office</div>
            <div class="footer-office-detail">
              <a href="tel:+19173885447">(+1) 917-388-5447</a><br>
              <a href="mailto:director@ovijatfood.com">director@ovijatfood.com</a><br>
              Delight Distribution Inc.<br>5605 Maspeth, New York
            </div>
          </div>
          <div class="footer-office-block">
            <div class="footer-office-title">🇧🇩 Dhaka Office</div>
            <div class="footer-office-detail">
              <a href="tel:+8801733390331">(+88) 01733-390331</a><br>
              <a href="mailto:info@ovijatfood.com">info@ovijatfood.com</a><br>
              Shadharan Bima Bhaban 2<br>139, Motijheel C/A, Dhaka-1000
            </div>
          </div>
          <div class="footer-office-block">
            <div class="footer-office-title">🏭 Factory</div>
            <div class="footer-office-detail">
              <a href="tel:+8801733390331">(+88) 01733-390331</a><br>
              <a href="mailto:career@ovijatfood.com">career@ovijatfood.com</a><br>
              Ramgonj, Nilphamari, Bangladesh
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>

  <div class="footer-bottom">
    <div class="container footer-bottom-inner">
      <p>Copyright &copy; 2015–<?= date('Y') ?> Ovijat Group. <?= L('footer_copyright') ?></p>
      <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap">
        <span class="footer-helpline-bottom">📞 <?= e($helpline) ?></span>
        <span class="footer-credit">
          <a href="<?= SITE_URL ?>/admin/" class="footer-it-link" title="Admin Panel">IT Team</a>
        </span>
      </div>
    </div>
  </div>
</footer>

<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
</body>
</html>
