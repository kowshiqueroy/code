<?php
require_once __DIR__.'/../includes/config.php';
logVisitor();
$lang=lang();
$pageTitle=$lang==='bn'?'রাইস শোকেস':'Rice Showcase';
$riceItems=db()->query("SELECT * FROM rice_products WHERE active=1 ORDER BY sort_order")->fetchAll();
// Show first sister concern as featured before products
$featuredConcern=db()->query("SELECT * FROM sister_concerns WHERE active=1 ORDER BY sort_order LIMIT 1")->fetch();
require_once __DIR__.'/partials/header.php';
?>
<section class="page-section">
  <div class="rice-page-hero">
    <div class="rice-page-overlay"></div>
    <div class="container">
      <span style="display:inline-block;background:var(--gold);color:var(--green-deep);padding:.3rem 1rem;border-radius:50px;font-size:.75rem;font-weight:800;text-transform:uppercase;letter-spacing:.1em;margin-bottom:1rem">OVIJAT GROUP</span>
      <h1 class="rice-page-title"><?= $lang==='bn'?'প্রিমিয়াম চালের সংগ্রহ':'Premium Rice Showcase' ?></h1>
      <p><?= $lang==='bn'?'বাংলাদেশের সেরা মাঠ থেকে আনা বিশেষ সুগন্ধি চালের বিস্তারিত পরিচয়।':'A detailed showcase of Bangladesh\'s finest aromatic rice, sourced from the best paddies.' ?></p>
    </div>
  </div>

  <div class="container">
    <!-- Sister Concern Feature (before products) -->
    <?php if($featuredConcern): ?>
    <div class="reveal" style="background:linear-gradient(135deg,var(--green-pale),var(--white));border-radius:16px;padding:2.5rem;margin-bottom:4rem;display:flex;gap:2rem;align-items:center;flex-wrap:wrap;border-left:4px solid var(--green-mid)">
      <div style="flex:0 0 auto">
        <?php if($featuredConcern['logo']): ?>
          <img src="<?= imgUrl($featuredConcern['logo'],'concerns','concern')?>" alt="<?= t($featuredConcern,'name')?>" style="height:70px;object-fit:contain">
        <?php else: ?><div style="font-size:3rem">🏭</div><?php endif; ?>
      </div>
      <div style="flex:1;min-width:200px">
        <div style="font-size:.75rem;font-weight:800;color:var(--green-light);text-transform:uppercase;letter-spacing:.1em;margin-bottom:.4rem"><?= $lang==='bn'?'প্রস্তুতকারক':'Our Manufacturing Partner' ?></div>
        <h2 style="font-family:'Playfair Display',serif;font-size:1.6rem;color:var(--green-deep);margin-bottom:.5rem"><?= t($featuredConcern,'name') ?></h2>
        <p style="color:var(--text-light);font-size:.92rem;line-height:1.7"><?= t($featuredConcern,'desc') ?></p>
        <?php if($featuredConcern['website']): ?><a href="<?= e($featuredConcern['website'])?>" target="_blank" class="btn btn-secondary btn-sm" style="margin-top:1rem"><?= $lang==='bn'?'আরও জানুন':'Learn More ↗' ?></a><?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <h2 class="sub-section-title reveal"><?= $lang==='bn'?'আমাদের পণ্য বিভাগ':'Our Rice Varieties' ?></h2>

    <div class="rice-showcase-grid">
      <?php foreach($riceItems as $i=>$r): ?>
        <div class="rice-detail-card <?= $i%2!==0?'reverse':'' ?> reveal">
          <div class="rice-detail-img"><img src="<?= imgUrl($r['image']??'','rice','rice')?>" alt="<?= t($r,'name')?>" loading="lazy"></div>
          <div class="rice-detail-content">
            <span class="rice-number"><?= str_pad($i+1,2,'0',STR_PAD_LEFT)?></span>
            <h2 class="rice-detail-name"><?= t($r,'name')?></h2>
            <?php if($r['origin_en']):?><div class="rice-origin">📍 <?= e($lang==='bn'?$r['origin_bn']:$r['origin_en'])?></div><?php endif;?>
            <?php $desc=$lang==='bn'?$r['desc_bn']:$r['desc_en']; if($desc):?><p class="rice-desc"><?= nl2br(e($desc))?></p><?php endif;?>
            <a href="<?= SITE_URL?>/?page=contact" class="btn btn-golden"><?= $lang==='bn'?'অর্ডার করুন':'Order Now'?></a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php require_once __DIR__.'/partials/footer.php'; ?>
