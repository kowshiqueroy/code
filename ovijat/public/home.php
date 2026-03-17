<?php
/**
 * OVIJAT GROUP — home.php v2.0
 * Homepage: Hero, Trust Strip, Stats, Categories (with 3 USPs),
 * Promotions, Products, Rice Showcase, Concerns, Global, Testimonials,
 * Partners, Career CTA.  No Leadership section.
 */
require_once __DIR__.'/../includes/config.php';
logVisitor();
$lang      = lang();
$pageTitle = $lang==='bn'?'হোম':'Home';
$metaDesc  = setting('meta_description');

// Data
$banners   = db()->query("SELECT * FROM banners WHERE active=1 ORDER BY id DESC, sort_order ASC LIMIT 5")->fetchAll();
$featProds = db()->query("SELECT p.*,c.name_en cat_en,c.name_bn cat_bn FROM products p JOIN product_categories c ON p.category_id=c.id WHERE p.active=1 ORDER BY p.sort_order LIMIT 8")->fetchAll();
$riceItems = db()->query("SELECT * FROM rice_products WHERE active=1 ORDER BY sort_order LIMIT 3")->fetchAll();
$concerns  = db()->query("SELECT * FROM sister_concerns WHERE active=1 ORDER BY sort_order LIMIT 6")->fetchAll();
$countries = db()->query("SELECT * FROM global_presence WHERE active=1 ORDER BY id LIMIT 15")->fetchAll();
$topCats   = db()->query("SELECT * FROM product_categories WHERE parent_id IS NULL AND active=1 ORDER BY sort_order LIMIT 6")->fetchAll();
try { $promos = db()->query("SELECT * FROM promotions WHERE active=1 ORDER BY sort_order LIMIT 3")->fetchAll(); } catch(Exception $e){ $promos=[]; }
try { $testimonials = db()->query("SELECT * FROM testimonials WHERE active=1 ORDER BY sort_order LIMIT 3")->fetchAll(); } catch(Exception $e){ $testimonials=[]; }
try { $partners = db()->query("SELECT * FROM partners WHERE active=1 ORDER BY sort_order LIMIT 10")->fetchAll(); } catch(Exception $e){ $partners=[]; }

require_once __DIR__.'/partials/header.php';
?>

<!-- ═══ HERO SLIDER ═══ -->
<section class="hero-section" aria-label="Hero Banner">
  <?php if($banners): ?>
    <div class="hero-slider" id="heroSlider">
      <?php foreach($banners as $i=>$b): ?>
        <div class="hero-slide <?= $i===0?'active':'' ?>">
          <div class="hero-bg" style="background-image:url('<?= imgUrl($b['image'],'banners','banner') ?>')"></div>
          <div class="hero-overlay"></div>
          <div class="hero-content">
            <div class="container">
              <span class="hero-tag">OVIJAT GROUP</span>
              <?php if(!empty($b['show_title']) || $b['show_title']===null || !isset($b['show_title'])): ?>
                <h1 class="hero-title"><?= e($lang==='bn'?$b['title_bn']:$b['title_en']) ?></h1>
              <?php endif; ?>
              <?php $sub=$lang==='bn'?($b['subtitle_bn']??''):($b['subtitle_en']??'');
              if($sub): ?><p class="hero-subtitle"><?= e($sub) ?></p><?php endif; ?>
              <?php if(empty($b['hide_buttons'])): ?>
              <div class="hero-cta">
                <a href="<?= SITE_URL ?>/?page=products" class="btn btn-golden btn-lg">
                  <?= $lang==='bn'?'পণ্য দেখুন':'Explore Products' ?>
                </a>
                <a href="<?= SITE_URL ?>/?page=contact" class="btn btn-outline-white">
                  <?= $lang==='bn'?'যোগাযোগ করুন':'Get In Touch' ?>
                </a>
              </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
      <?php if(count($banners)>1): ?>
        <div class="hero-controls">
          <button class="hero-prev" aria-label="Previous">&#8249;</button>
          <div class="hero-dots">
            <?php foreach($banners as $i=>$b): ?><button class="hero-dot <?= $i===0?'active':'' ?>" data-target="<?= $i ?>"></button><?php endforeach; ?>
          </div>
          <button class="hero-next" aria-label="Next">&#8250;</button>
        </div>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="hero-static" style="min-height:400px;background:linear-gradient(135deg,var(--green-deep),var(--green-mid));position:relative;display:flex;align-items:center">
      <div class="hero-overlay"></div>
      <div class="hero-content"><div class="container">
        <span class="hero-tag">OVIJAT GROUP</span>
        <h1 class="hero-title"><?= e(setting('site_tagline_'.$lang,'Nourishing Bangladesh, Reaching the World')) ?></h1>
        <div class="hero-cta"><a href="<?= SITE_URL ?>/?page=products" class="btn btn-golden btn-lg"><?= $lang==='bn'?'পণ্য দেখুন':'Explore Products' ?></a></div>
      </div></div>
    </div>
  <?php endif; ?>
</section>

<!-- ═══ TRUST STRIP ═══ -->
<div class="trust-strip reveal">
  <div class="container">
    <div class="trust-grid">
      <div class="trust-item"><span class="trust-item-icon">🏷️</span><div class="trust-item-text"><span class="trust-item-label"><?= $lang==='bn'?'প্রাইভেট লেবেলিং':'Private Labeling' ?></span><span class="trust-item-sub"><?= $lang==='bn'?'আপনার ব্র্যান্ডে উৎপাদন':'Your Brand, Our Quality' ?></span></div></div>
      <div class="trust-sep"></div>
      <div class="trust-item"><span class="trust-item-icon">🌍</span><div class="trust-item-text"><span class="trust-item-label"><?= $lang==='bn'?'ওয়ার্ল্ডওয়াইড শিপিং':'Worldwide Shipping' ?></span><span class="trust-item-sub"><?= $lang==='bn'?'২৫+ দেশে রপ্তানি':'Exporting to 25+ Countries' ?></span></div></div>
      <div class="trust-sep"></div>
      <div class="trust-item"><span class="trust-item-icon">🕐</span><div class="trust-item-text"><span class="trust-item-label">24/7 Support</span><span class="trust-item-sub"><?= $lang==='bn'?'সর্বদা আপনার পাশে':'Always Here to Help' ?></span></div></div>
      <div class="trust-sep"></div>
      <div class="trust-item"><span class="trust-item-icon">✅</span><div class="trust-item-text"><span class="trust-item-label">ISO Certified</span><span class="trust-item-sub"><?= $lang==='bn'?'আন্তর্জাতিক মান':'International Standards' ?></span></div></div>
      <div class="trust-sep"></div>
      <div class="trust-item"><span class="trust-item-icon">⭐</span><div class="trust-item-text"><span class="trust-item-label"><?= $lang==='bn'?'২০+ বছর অভিজ্ঞতা':'20+ Years Experience' ?></span><span class="trust-item-sub"><?= $lang==='bn'?'বিশ্বস্ততার সাথে':'Trusted Since 2005' ?></span></div></div>
    </div>
  </div>
</div>

<!-- ═══ STATS ═══ -->
<section class="stats-bar" aria-label="Statistics">
  <div class="container stats-grid stagger-children">
    <?php $stats=$lang==='bn'?[['num'=>'২০+','label'=>'বছরের অভিজ্ঞতা'],['num'=>'৫০০+','label'=>'পণ্য ভ্যারিয়েন্ট'],['num'=>'২৫+','label'=>'রপ্তানি দেশ'],['num'=>'১,০০০+','label'=>'কর্মী']]:[['num'=>'20+','label'=>'Years of Excellence'],['num'=>'500+','label'=>'Product Variants'],['num'=>'25+','label'=>'Export Countries'],['num'=>'1,000+','label'=>'Employees']];
    foreach($stats as $s): ?><div class="stat-item"><span class="stat-num"><?= $s['num'] ?></span><span class="stat-label"><?= $s['label'] ?></span></div><?php endforeach; ?>
  </div>
</section>

<!-- ═══ PROMOTIONS / CAMPAIGNS ═══ -->
<?php if($promos): ?>
<section class="section bg-light" aria-label="Promotions">
  <div class="container">
    <div class="section-header reveal">
      <span class="section-tag red"><?= $lang==='bn'?'বিশেষ অফার':'Special Offers' ?></span>
      <h2 class="section-title"><?= $lang==='bn'?'প্রচার ও ক্যাম্পেইন':'Promotions & Campaigns' ?></h2>
    </div>
    <div class="promos-grid stagger-children">
      <?php foreach($promos as $p): ?>
        <div class="promo-card">
          <?php if($p['image']): ?><img src="<?= imgUrl($p['image'],'promotions','promo') ?>" class="promo-card-img" alt="<?= t($p,'title') ?>" loading="lazy"><?php endif; ?>
          <div class="promo-card-body">
            <?php $badge=$lang==='bn'?$p['badge_bn']:$p['badge_en']; if($badge): ?><span class="promo-badge"><?= e($badge) ?></span><?php endif; ?>
            <div class="promo-title"><?= t($p,'title') ?></div>
            <?php $desc=$lang==='bn'?$p['desc_bn']:$p['desc_en']; if($desc): ?><p class="promo-desc"><?= e($desc) ?></p><?php endif; ?>
            <?php if($p['end_date']): ?><div class="promo-date">⏱ <?= $lang==='bn'?'শেষ তারিখ:':'Ends:' ?> <?= date('d M Y',strtotime($p['end_date'])) ?></div><?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ═══ PRODUCT CATEGORIES ═══ -->
<section class="section" aria-label="Product Categories">
  <div class="container">
    <div class="section-header reveal">
      <span class="section-tag"><?= $lang==='bn'?'আমাদের পণ্য':'Our Products' ?></span>
      <h2 class="section-title"><?= $lang==='bn'?'পণ্য বিভাগ':'Product Categories' ?></h2>
    </div>
    <div class="categories-grid stagger-children">
      <?php foreach($topCats as $cat): ?>
        <a href="<?= SITE_URL ?>/?page=category&id=<?= $cat['id'] ?>" class="category-card">
          <div class="category-card-img"><img src="<?= imgUrl($cat['image']??'','products','product') ?>" alt="<?= t($cat,'name') ?>" loading="lazy"></div>
          <div class="category-card-label"><?= t($cat,'name') ?></div>
        </a>
      <?php endforeach; ?>
    </div>
    <div class="section-cta"><a href="<?= SITE_URL ?>/?page=products" class="btn btn-primary"><?= $lang==='bn'?'সব পণ্য দেখুন':'View All Products' ?></a></div>
  </div>
</section>

<!-- ═══ FEATURED PRODUCTS ═══ -->
<section class="section bg-light" aria-label="Featured Products">
  <div class="container">
    <div class="section-header reveal">
      <span class="section-tag"><?= $lang==='bn'?'বৈশিষ্ট্যযুক্ত':'Featured' ?></span>
      <h2 class="section-title"><?= $lang==='bn'?'জনপ্রিয় পণ্য':'Popular Products' ?></h2>
    </div>
    <div class="products-grid stagger-children">
      <?php foreach($featProds as $prod): ?>
        <a href="<?= SITE_URL ?>/?page=product&id=<?= $prod['id'] ?>" class="product-card">
          <div class="product-card-img"><img src="<?= imgUrl($prod['image']??'','products','product') ?>" alt="<?= t($prod,'name') ?>" loading="lazy"></div>
          <div class="product-card-body">
            <span class="product-cat-tag"><?= $lang==='bn'?$prod['cat_bn']:$prod['cat_en'] ?></span>
            <h3 class="product-name"><?= t($prod,'name') ?></h3>
            <?php if($prod['weight']): ?><span class="product-weight"><?= e($prod['weight']) ?></span><?php endif; ?>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ═══ RICE SHOWCASE ═══ -->
<?php if($riceItems): ?>
<section class="rice-showcase-banner reveal" aria-label="Rice Showcase">
  <div class="container rice-banner-inner">
    <div class="rice-banner-text reveal-left">
      <span class="section-tag light"><?= $lang==='bn'?'বিশেষ সংগ্রহ':'Special Collection' ?></span>
      <h2 class="rice-banner-title"><?= $lang==='bn'?'প্রিমিয়াম চালের সংগ্রহ':'Premium Rice Collection' ?></h2>
      <p><?= $lang==='bn'?'বাংলাদেশের সেরা মাঠ থেকে সংগ্রহ করা অনন্য সুগন্ধি চালের পরিচয় নিন।':'Discover our curated selection of the finest aromatic rice from Bangladesh\'s best paddies.' ?></p>
      <a href="<?= SITE_URL ?>/?page=rice" class="btn btn-golden"><?= $lang==='bn'?'রাইস শোকেস দেখুন':'Explore Rice Showcase' ?></a>
    </div>
    <div class="rice-banner-cards reveal-right">
      <?php foreach($riceItems as $r): ?>
        <div class="rice-mini-card">
          <img src="<?= imgUrl($r['image']??'','rice','rice') ?>" alt="<?= t($r,'name') ?>" loading="lazy">
          <div class="rice-mini-name"><?= t($r,'name') ?></div>
          <?php if($r['origin_en']): ?><div class="rice-mini-origin">📍 <?= e($lang==='bn'?$r['origin_bn']:$r['origin_en']) ?></div><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ═══ SISTER CONCERNS ═══ -->
<?php if($concerns): ?>
<section class="section bg-green" aria-label="Sister Concerns">
  <div class="container">
    <div class="section-header light reveal">
      <span class="section-tag light"><?= $lang==='bn'?'আমাদের পরিবার':'Our Family' ?></span>
      <h2 class="section-title light"><?= $lang==='bn'?'সিস্টার কনসার্ন':'Sister Concerns' ?></h2>
    </div>
    <div class="concerns-grid-home stagger-children">
      <?php foreach($concerns as $c): ?>
        <div class="concern-card">
          <?php if($c['logo']): ?><div class="concern-logo-wrap"><img src="<?= imgUrl($c['logo'],'concerns','concern') ?>" alt="<?= t($c,'name') ?>" loading="lazy"></div><?php else: ?><div class="concern-icon">🏭</div><?php endif; ?>
          <h3 class="concern-name"><?= t($c,'name') ?></h3>
          <p class="concern-desc"><?= t($c,'desc') ?></p>
          <?php if($c['website']): ?><a href="<?= e($c['website']) ?>" target="_blank" rel="noopener" class="concern-link"><?= $lang==='bn'?'ওয়েবসাইট →':'Website →' ?></a><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ═══ GLOBAL PRESENCE ═══ -->
<?php if($countries): ?>
<section class="section bg-light" aria-label="Global Presence">
  <div class="container">
    <div class="section-header reveal">
      <span class="section-tag"><?= $lang==='bn'?'আন্তর্জাতিক':'International' ?></span>
      <h2 class="section-title"><?= $lang==='bn'?'বৈশ্বিক উপস্থিতি':'Global Presence' ?></h2>
      <p class="section-subtitle"><?= $lang==='bn'?'আমরা ২৫+ দেশে বাংলাদেশের গর্ব পৌঁছে দিচ্ছি।':'Carrying the pride of Bangladesh to 25+ countries worldwide.' ?></p>
    </div>
    <div class="global-grid stagger-children">
      <?php foreach($countries as $ctry): ?><div class="global-tag"><span class="flag"><?= e($ctry['flag_emoji']) ?></span><span><?= e($lang==='bn'?$ctry['country_bn']:$ctry['country_en']) ?></span></div><?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ═══ TESTIMONIALS ═══ -->
<?php if($testimonials): ?>
<section class="section" aria-label="Testimonials">
  <div class="container">
    <div class="section-header reveal">
      <span class="section-tag"><?= $lang==='bn'?'গ্রাহক মতামত':'Customer Feedback' ?></span>
      <h2 class="section-title"><?= $lang==='bn'?'আমাদের গ্রাহকরা বলছেন':'What Our Clients Say' ?></h2>
    </div>
    <div class="testimonials-grid stagger-children">
      <?php foreach($testimonials as $t): ?>
        <div class="testimonial-card">
          <div class="testimonial-stars"><?= str_repeat('★',$t['stars']??5) ?></div>
          <div class="testimonial-quote">"</div>
          <p class="testimonial-text"><?= e($lang==='bn'?$t['text_bn']:$t['text_en']) ?></p>
          <div class="testimonial-author">
            <?php if($t['image']): ?><div class="testimonial-avatar"><img src="<?= imgUrl($t['image'],'management','management') ?>" alt="<?= t($t,'name') ?>" loading="lazy"></div><?php else: ?><div class="testimonial-avatar" style="display:flex;align-items:center;justify-content:center;font-size:1.3rem">👤</div><?php endif; ?>
            <div><div class="testimonial-name"><?= t($t,'name') ?></div><div class="testimonial-role"><?= t($t,'role') ?></div></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ═══ PARTNERS ═══ -->
<?php if($partners): ?>
<div class="partners-strip">
  <div class="container">
    <p class="partners-title"><?= $lang==='bn'?'আমাদের পার্টনার ও বিতরণকারী':'Our Partners & Distributors' ?></p>
    <div style="overflow:hidden">
      <div class="partners-track">
        <?php foreach($partners as $p): ?>
          <?php if($p['logo']): ?><img src="<?= imgUrl($p['logo'],'partners','partner') ?>" alt="<?= e($p['name']) ?>" class="partner-logo" loading="lazy"><?php else: ?><span style="font-weight:700;color:var(--green-mid);font-size:.9rem;white-space:nowrap;opacity:.65"><?= e($p['name']) ?></span><?php endif; ?>
        <?php endforeach; ?>
        <!-- Duplicate for seamless loop -->
        <?php foreach($partners as $p): ?>
          <?php if($p['logo']): ?><img src="<?= imgUrl($p['logo'],'partners','partner') ?>" alt="<?= e($p['name']) ?>" class="partner-logo" loading="lazy"><?php else: ?><span style="font-weight:700;color:var(--green-mid);font-size:.9rem;white-space:nowrap;opacity:.65"><?= e($p['name']) ?></span><?php endif; ?>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ═══ CAREER CTA ═══ -->
<section class="cta-strip reveal" aria-label="Career CTA">
  <div class="container cta-strip-inner">
    <div>
      <h2><?= $lang==='bn'?'আমাদের দলে যোগ দিন':'Join Our Growing Team' ?></h2>
      <p><?= $lang==='bn'?'আমরা প্রতিভাবান ও উৎসাহী মানুষ খুঁজছি।':'We are looking for talented and motivated individuals.' ?></p>
    </div>
    <a href="<?= SITE_URL ?>/?page=careers" class="btn btn-primary btn-lg"><?= $lang==='bn'?'খোলা পদ দেখুন':'See Open Positions' ?></a>
  </div>
</section>

<?php require_once __DIR__.'/partials/footer.php'; ?>
