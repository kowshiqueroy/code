<?php
/**
 * OVIJAT GROUP — public/category.php
 * Shows sub-categories OR products within a category.
 */
require_once __DIR__ . '/../includes/config.php';
$lang = lang();
$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect(SITE_URL . '/?page=products');

$cat = db()->prepare("SELECT * FROM product_categories WHERE id=? AND active=1");
$cat->execute([$id]);
$cat = $cat->fetch();
if (!$cat) redirect(SITE_URL . '/?page=products');

// Parent breadcrumb
$parent = null;
if ($cat['parent_id']) {
    $p = db()->prepare("SELECT * FROM product_categories WHERE id=?");
    $p->execute([$cat['parent_id']]);
    $parent = $p->fetch();
}

// Sub-categories
$subCats = db()->prepare("SELECT * FROM product_categories WHERE parent_id=? AND active=1 ORDER BY sort_order");
$subCats->execute([$id]);
$subCats = $subCats->fetchAll();

// Products in this category
$prods = db()->prepare("SELECT * FROM products WHERE category_id=? AND active=1 ORDER BY sort_order");
$prods->execute([$id]);
$prods = $prods->fetchAll();

$pageTitle = t($cat, 'name');
require_once __DIR__ . '/partials/header.php';
?>
<section class="section page-section">
  <div class="container">
    <!-- Breadcrumb -->
    <nav class="breadcrumb" aria-label="Breadcrumb">
      <a href="<?= SITE_URL ?>/"><?= $lang === 'bn' ? 'হোম' : 'Home' ?></a>
      <span>›</span>
      <a href="<?= SITE_URL ?>/?page=products"><?= $lang === 'bn' ? 'পণ্যসমূহ' : 'Products' ?></a>
      <?php if ($parent): ?>
        <span>›</span>
        <a href="<?= SITE_URL ?>/?page=category&id=<?= $parent['id'] ?>"><?= t($parent,'name') ?></a>
      <?php endif; ?>
      <span>›</span>
      <span><?= t($cat,'name') ?></span>
    </nav>

    <div class="page-hero-header">
      <h1 class="page-hero-title"><?= t($cat,'name') ?></h1>
    </div>

    <?php if ($subCats): ?>
      <h2 class="sub-section-title"><?= $lang === 'bn' ? 'উপ-বিভাগ' : 'Sub-categories' ?></h2>
      <div class="categories-grid">
        <?php foreach ($subCats as $sc): ?>
          <a href="<?= SITE_URL ?>/?page=category&id=<?= $sc['id'] ?>" class="category-card">
            <div class="category-card-img">
              <img src="<?= imgUrl($sc['image'] ?? '', 'products', 'product') ?>" alt="<?= t($sc,'name') ?>" loading="lazy">
            </div>
            <div class="category-card-label"><?= t($sc,'name') ?></div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($prods): ?>
      <?php if ($subCats): ?><h2 class="sub-section-title" style="margin-top:2.5rem"><?= $lang === 'bn' ? 'পণ্যসমূহ' : 'Products' ?></h2><?php endif; ?>
      <div class="products-grid">
        <?php foreach ($prods as $prod): ?>
          <a href="<?= SITE_URL ?>/?page=product&id=<?= $prod['id'] ?>" class="product-card">
            <div class="product-card-img">
              <img src="<?= imgUrl($prod['image'] ?? '', 'products', 'product') ?>" alt="<?= t($prod,'name') ?>" loading="lazy">
            </div>
            <div class="product-card-body">
              <h3 class="product-name"><?= t($prod,'name') ?></h3>
              <?php if ($prod['weight']): ?><span class="product-weight"><?= e($prod['weight']) ?></span><?php endif; ?>
              <?php $desc = $lang === 'bn' ? $prod['desc_bn'] : $prod['desc_en'];
              if ($desc): ?><p class="product-desc-short"><?= e(mb_substr($desc, 0, 80)) ?>...</p><?php endif; ?>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if (!$subCats && !$prods): ?>
      <p class="empty-state"><?= $lang === 'bn' ? 'এই বিভাগে এখনো কোনো পণ্য নেই।' : 'No products in this category yet.' ?></p>
    <?php endif; ?>
  </div>
</section>
<?php require_once __DIR__ . '/partials/footer.php'; ?>
