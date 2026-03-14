<?php
/**
 * Gallery Page Template
 */
try {
  $albums = db()->query("SELECT * FROM albums WHERE is_published=1 ORDER BY created_at DESC")->fetchAll();
  $album_id = (int)($_GET['album'] ?? 0);
  if ($album_id) {
    $current_album = db()->prepare("SELECT * FROM albums WHERE id=?");
    $current_album->execute([$album_id]);
    $current_album = $current_album->fetch();
    $gallery_imgs = db()->prepare("SELECT * FROM gallery WHERE album_id=? AND is_published=1 AND media_type='image' ORDER BY sort_order");
    $gallery_imgs->execute([$album_id]);
    $gallery_imgs = $gallery_imgs->fetchAll();
    $gallery_videos = db()->prepare("SELECT * FROM gallery WHERE album_id=? AND is_published=1 AND media_type='video' ORDER BY sort_order");
    $gallery_videos->execute([$album_id]);
    $gallery_videos = $gallery_videos->fetchAll();
  } else {
    $gallery_imgs = db()->query("SELECT * FROM gallery WHERE is_published=1 AND media_type='image' ORDER BY created_at DESC LIMIT 24")->fetchAll();
    $gallery_videos = db()->query("SELECT * FROM gallery WHERE is_published=1 AND media_type='video' ORDER BY created_at DESC LIMIT 12")->fetchAll();
  }
} catch(Exception $e) { $albums=[]; $gallery_imgs=[]; $gallery_videos=[]; $album_id=0; $current_album=null; }

// Determine active tab
$tab = in_array($_GET['tab']??'photos', ['photos','videos']) ? ($_GET['tab']??'photos') : 'photos';
?>

<div class="breadcrumb">
  <div class="container">
    <ol>
      <?php if ($album_id && $current_album): ?>
      <li><a href="<?= url('gallery') ?>"><?= t('Gallery','গ্যালারি') ?></a></li>
      <li class="active"><?= h(field($current_album,'title')) ?></li>
      <?php else: ?>
      <li class="active"><?= t('Gallery','গ্যালারি') ?></li>
      <?php endif; ?>
    </ol>
  </div>
</div>

<section class="section">
  <div class="container">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:32px;flex-wrap:wrap;gap:16px">
      <h1 style="color:var(--primary);font-size:1.9rem;font-weight:800">
        🖼️ <?= $album_id && $current_album ? h(field($current_album,'title')) : t('Gallery','গ্যালারি') ?>
      </h1>
      <div style="display:flex;gap:8px">
        <a href="<?= url('gallery') ?>&tab=photos<?= $album_id?"&album=$album_id":'' ?>" class="btn btn-sm <?= $tab==='photos'?'btn-primary':'btn-outline' ?>">
          📷 <?= t('Photos','ছবি') ?>
        </a>
        <a href="<?= url('gallery') ?>&tab=videos<?= $album_id?"&album=$album_id":'' ?>" class="btn btn-sm <?= $tab==='videos'?'btn-primary':'btn-outline' ?>">
          🎬 <?= t('Videos','ভিডিও') ?>
        </a>
      </div>
    </div>

    <!-- Albums -->
    <?php if (!$album_id && !empty($albums)): ?>
    <div style="margin-bottom:40px">
      <h2 style="font-size:1.1rem;font-weight:700;color:var(--text-muted);margin-bottom:16px;text-transform:uppercase;letter-spacing:.05em"><?= t('Albums','অ্যালবাম') ?></h2>
      <div class="grid grid-4">
        <?php foreach ($albums as $alb): ?>
        <a href="<?= url('gallery') ?>&album=<?= (int)$alb['id'] ?>" class="card" style="text-decoration:none">
          <?php if ($alb['cover_image']): ?>
          <img src="<?= upload_url($alb['cover_image']) ?>" alt="<?= h(field($alb,'title')) ?>" class="card-img" loading="lazy">
          <?php else: ?>
          <div class="card-img" style="background:var(--primary-light);display:flex;align-items:center;justify-content:center;font-size:3rem">📁</div>
          <?php endif; ?>
          <div class="card-body">
            <div class="card-title"><?= h(field($alb,'title')) ?></div>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Photos -->
    <?php if ($tab === 'photos'): ?>
    <?php if (!empty($gallery_imgs)): ?>
    <div class="gallery-grid" style="grid-template-columns:repeat(auto-fill,minmax(200px,1fr))">
      <?php foreach ($gallery_imgs as $item): ?>
      <div class="gallery-item">
        <a href="<?= upload_url($item['file_path']) ?>"
           data-lightbox="<?= upload_url($item['file_path']) ?>"
           data-alt="<?= h(field($item,'title')) ?>"
           aria-label="<?= h(field($item,'title') ?: t('Gallery image','গ্যালারি ছবি')) ?>">
          <img src="<?= upload_url($item['thumbnail'] ?: $item['file_path']) ?>"
               alt="<?= h(field($item,'title')) ?>"
               loading="lazy"
               style="width:100%;height:180px;object-fit:cover">
          <div class="gallery-overlay">🔍</div>
        </a>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p style="text-align:center;color:var(--text-muted);padding:60px"><?= t('No photos available.','কোনো ছবি নেই।') ?></p>
    <?php endif; ?>

    <!-- Videos -->
    <?php elseif ($tab === 'videos'): ?>
    <?php if (!empty($gallery_videos)): ?>
    <div class="grid grid-3">
      <?php foreach ($gallery_videos as $vid): ?>
      <div class="card">
        <?php if (!empty($vid['video_url'])): ?>
        <?php
        // Convert YouTube URL to embed
        $yt_id = '';
        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&\s]+)/', $vid['video_url'], $m)) {
          $yt_id = $m[1];
        }
        ?>
        <?php if ($yt_id): ?>
        <div style="position:relative;padding-bottom:56.25%;overflow:hidden">
          <iframe src="https://www.youtube.com/embed/<?= h($yt_id) ?>"
                  style="position:absolute;inset:0;width:100%;height:100%"
                  frameborder="0" allowfullscreen loading="lazy"
                  title="<?= h(field($vid,'title')) ?>"></iframe>
        </div>
        <?php else: ?>
        <video controls style="width:100%;display:block" loading="lazy">
          <source src="<?= upload_url($vid['file_path']) ?>">
        </video>
        <?php endif; ?>
        <?php elseif ($vid['file_path']): ?>
        <video controls style="width:100%;display:block">
          <source src="<?= upload_url($vid['file_path']) ?>">
        </video>
        <?php endif; ?>
        <div class="card-body">
          <div class="card-title"><?= h(field($vid,'title') ?: t('Video','ভিডিও')) ?></div>
          <?php if (field($vid,'description')): ?>
          <div class="card-text"><?= h(mb_substr(field($vid,'description'),0,100)) ?></div>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p style="text-align:center;color:var(--text-muted);padding:60px"><?= t('No videos available.','কোনো ভিডিও নেই।') ?></p>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</section>
