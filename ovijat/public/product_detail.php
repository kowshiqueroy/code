<?php
require_once __DIR__ . '/../includes/config.php';
$lang = lang();
$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect(SITE_URL . '/?page=products');
$prod = db()->prepare("SELECT p.*, c.name_en cat_en, c.name_bn cat_bn, c.parent_id cat_parent FROM products p JOIN product_categories c ON p.category_id=c.id WHERE p.id=? AND p.active=1");
$prod->execute([$id]);
$prod = $prod->fetch();
if (!$prod) redirect(SITE_URL . '/?page=products');

// Related products
$related = db()->prepare("SELECT * FROM products WHERE category_id=? AND id!=? AND active=1 LIMIT 4");
$related->execute([$prod['category_id'], $id]);
$related = $related->fetchAll();

$pageTitle = t($prod, 'name');
require_once __DIR__ . '/partials/header.php';
?>
<section class="section page-section">
  <div class="container">
    <nav class="breadcrumb">
      <a href="<?= SITE_URL ?>/"><?= $lang === 'bn' ? 'হোম' : 'Home' ?></a>
      <span>›</span>
      <a href="<?= SITE_URL ?>/?page=products"><?= $lang === 'bn' ? 'পণ্যসমূহ' : 'Products' ?></a>
      <span>›</span>
      <a href="<?= SITE_URL ?>/?page=category&id=<?= $prod['category_id'] ?>"><?= $lang === 'bn' ? $prod['cat_bn'] : $prod['cat_en'] ?></a>
      <span>›</span>
      <span><?= t($prod,'name') ?></span>
    </nav>

    <div class="product-detail-grid">
      <div class="product-detail-img-col">
        <div class="product-detail-img">
          <img src="<?= imgUrl($prod['image'] ?? '', 'products', 'product') ?>" alt="<?= t($prod,'name') ?>" loading="lazy">
        </div>
      </div>
      <div class="product-detail-info-col">
        <span class="product-cat-tag"><?= $lang === 'bn' ? $prod['cat_bn'] : $prod['cat_en'] ?></span>
        <h1 class="product-detail-title"><?= t($prod,'name') ?></h1>
        <?php if ($prod['weight']): ?>
          <div class="product-detail-weight"><strong><?= $lang === 'bn' ? 'পরিমাণ' : 'Weight / Size' ?>:</strong> <?= e($prod['weight']) ?></div>
        <?php endif; ?>
        <?php $desc = $lang === 'bn' ? $prod['desc_bn'] : $prod['desc_en'];
        if ($desc): ?>
          <div class="product-detail-desc">
            <h3><?= $lang === 'bn' ? 'বিবরণ' : 'Description' ?></h3>
            <p><?= nl2br(e($desc)) ?></p>
          </div>
        <?php endif; ?>
        <div class="product-detail-cta">
          <a href="<?= SITE_URL ?>/?page=contact" class="btn btn-primary">
            <?= $lang === 'bn' ? 'অর্ডার করুন / যোগাযোগ করুন' : 'Inquire / Order' ?>
          </a>
        </div>
      </div>
    </div>

    <?php if ($related): ?>
      <div class="related-products">
        <h2 class="sub-section-title"><?= $lang === 'bn' ? 'সম্পর্কিত পণ্য' : 'Related Products' ?></h2>
        <div class="products-grid">
          <?php foreach ($related as $r): ?>
            <a href="<?= SITE_URL ?>/?page=product&id=<?= $r['id'] ?>" class="product-card">
              <div class="product-card-img">
                <img src="<?= imgUrl($r['image'] ?? '', 'products', 'product') ?>" alt="<?= t($r,'name') ?>" loading="lazy">
              </div>
              <div class="product-card-body">
                <h3 class="product-name"><?= t($r,'name') ?></h3>
                <?php if ($r['weight']): ?><span class="product-weight"><?= e($r['weight']) ?></span><?php endif; ?>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php require_once __DIR__ . '/partials/footer.php'; ?>
