<?php
/**
 * OVIJAT GROUP — public/products.php
 * Top-level product categories listing.
 */
require_once __DIR__.'/../includes/config.php';
logVisitor();
$lang = lang();
$pageTitle = $lang === 'bn' ? 'পণ্যসমূহ' : 'Products';
$cats = db()->query("SELECT * FROM product_categories WHERE parent_id IS NULL AND active=1 ORDER BY sort_order")->fetchAll();
require_once __DIR__ . '/partials/header.php';
?>
<section class="section page-section">
  <div class="container">
    <div class="page-hero-header">
      <h1 class="page-hero-title"><?= $lang === 'bn' ? 'আমাদের পণ্যসমূহ' : 'Our Products' ?></h1>
      <p><?= $lang === 'bn' ? 'আমাদের বিস্তৃত পণ্য পরিসর আবিষ্কার করুন।' : 'Discover our extensive range of quality food products.' ?></p>
    </div>
    <div class="categories-grid-lg">
      <?php foreach ($cats as $cat):
        // Count products in this category (incl sub-cats)
        $stmt = db()->prepare("SELECT COUNT(*) FROM products p JOIN product_categories c ON p.category_id=c.id WHERE (c.id=? OR c.parent_id=?) AND p.active=1");
        $stmt->execute([$cat['id'], $cat['id']]);
        $count = $stmt->fetchColumn();
        // Sub-cats
        $subs = db()->prepare("SELECT * FROM product_categories WHERE parent_id=? AND active=1 ORDER BY sort_order LIMIT 4");
        $subs->execute([$cat['id']]);
        $subs = $subs->fetchAll();
      ?>
        <a href="<?= SITE_URL ?>/?page=category&id=<?= $cat['id'] ?>" class="cat-card-lg">
          <div class="cat-card-img-wrap">
            <img src="<?= imgUrl($cat['image'] ?? '', 'products', 'product') ?>"
                 alt="<?= t($cat,'name') ?>" loading="lazy">
          </div>
          <div class="cat-card-body">
            <h2 class="cat-card-title"><?= t($cat,'name') ?></h2>
            <span class="cat-card-count"><?= $count ?> <?= $lang === 'bn' ? 'টি পণ্য' : 'products' ?></span>
            <?php if ($subs): ?>
              <div class="cat-subs">
                <?php foreach ($subs as $s): ?>
                  <span class="cat-sub-tag"><?= t($s,'name') ?></span>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php require_once __DIR__ . '/partials/footer.php'; ?>
