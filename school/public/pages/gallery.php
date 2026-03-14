<?php
// public/pages/gallery.php
$albumId = (int)($_GET['album'] ?? 0);
if ($albumId) {
    $album = null;
    try {
        $stmt = getDB()->prepare("SELECT * FROM gallery_albums WHERE id=? AND is_active=1");
        $stmt->execute([$albumId]);
        $album = $stmt->fetch();
    } catch(Exception $e) {}
    $images = $album ? getAlbumImages($albumId) : [];
} else {
    $albums = [];
    try { $albums = getDB()->query("SELECT ga.*, (SELECT gi.filename FROM gallery_images gi WHERE gi.album_id=ga.id AND gi.is_active=1 ORDER BY gi.sort_order LIMIT 1) AS first_img, (SELECT COUNT(*) FROM gallery_images gi2 WHERE gi2.album_id=ga.id AND gi2.is_active=1) AS img_count FROM gallery_albums ga WHERE ga.is_active=1 ORDER BY ga.sort_order,ga.album_date DESC")->fetchAll(); } catch(Exception $e) {}
}
?>
<div class="page-hero" style="background:linear-gradient(135deg,#553c9a,#3d2b7a)">
  <div class="container">
    <h1 class="page-hero-title"><?= t('Photo Gallery','ফটো গ্যালারি') ?></h1>
    <nav class="breadcrumb">
      <a href="<?= pageUrl('index') ?>"><?= t('Home','হোম') ?></a> / 
      <?php if ($albumId && $album): ?><a href="<?= pageUrl('gallery') ?>"><?= t('Gallery','গ্যালারি') ?></a> / <?= h(field($album,'title')) ?>
      <?php else: ?><?= t('Gallery','গ্যালারি') ?><?php endif; ?>
    </nav>
  </div>
</div>
<div class="container page-body">
  <?php if ($albumId && isset($album) && $album): ?>
    <h2 class="section-title-inner"><?= h(field($album,'title')) ?></h2>
    <div class="gallery-lightbox-grid" id="galleryGrid">
      <?php foreach ($images as $img): ?>
      <a href="<?= h(imgUrl($img['filename'],'large')) ?>" class="gallery-lb-item" data-title="<?= h(field($img,'title')) ?>">
        <img src="<?= h(imgUrl($img['filename'],'thumb')) ?>" alt="<?= h(field($img,'title')) ?>" loading="lazy">
        <div class="gallery-lb-overlay">🔍</div>
      </a>
      <?php endforeach; ?>
      <?php if (!$images): ?><p class="empty-msg"><?= t('No images in this album.','এই অ্যালবামে কোনো ছবি নেই।') ?></p><?php endif; ?>
    </div>
    <!-- Lightbox -->
    <div class="lightbox" id="lightbox">
      <button class="lb-close" id="lbClose">✕</button>
      <button class="lb-prev" id="lbPrev">‹</button>
      <button class="lb-next" id="lbNext">›</button>
      <div class="lb-img-wrap"><img src="" alt="" id="lbImg"><p id="lbCaption"></p></div>
    </div>
  <?php else: ?>
    <div class="gallery-albums-grid">
      <?php foreach ($albums as $alb): 
        $cover = $alb['cover_image'] ?: $alb['first_img'];
      ?>
      <a href="<?= pageUrl('gallery', ['album' => $alb['id']]) ?>" class="album-card">
        <div class="album-thumb">
          <?php if ($cover): ?>
          <img src="<?= h(imgUrl($cover,'medium')) ?>" alt="<?= h(field($alb,'title')) ?>" loading="lazy">
          <?php else: ?><div class="album-ph">📷</div><?php endif; ?>
          <span class="album-count"><?= banglaNum($alb['img_count'] ?? '0') ?> <?= t('Photos','ছবি') ?></span>
        </div>
        <div class="album-info">
          <h3><?= h(field($alb,'title')) ?></h3>
          <?php if ($alb['album_date']): ?><span><?= formatDate($alb['album_date']) ?></span><?php endif; ?>
        </div>
      </a>
      <?php endforeach; ?>
      <?php if (!$albums): ?><p class="empty-msg"><?= t('No albums yet.','কোনো অ্যালবাম নেই।') ?></p><?php endif; ?>
    </div>
  <?php endif; ?>
</div>
