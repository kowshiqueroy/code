<?php
// public/pages/home.php
$banners  = getBanners();
$notices  = getNotices('notice', 8);
$news     = getNotices('news', 5);
$events   = getNotices('event', 5);
$jobs     = getNotices('job', 3);
$results  = getNotices('result', 3);
$admNotices = getNotices('admission', 3);
$studHonorees  = getHonorees('student', 3);
$teachHonorees = getHonorees('teacher', 1);
$principal     = getStaff('principal');
$principalData = $principal[0] ?? null;
$showBanner    = getSetting('show_banner', '1') === '1';
$showPMsg      = getSetting('show_principal_msg', '1') === '1';
$showHonorees  = getSetting('show_honorees', '1') === '1';
$showGallery   = getSetting('show_gallery', '1') === '1';

// Recent gallery albums
$albums = [];
if ($showGallery) {
    try {
        $albums = getDB()->query("SELECT ga.*, (SELECT gi.filename FROM gallery_images gi WHERE gi.album_id=ga.id AND gi.is_active=1 LIMIT 1) AS first_img FROM gallery_albums ga WHERE ga.is_active=1 ORDER BY ga.sort_order ASC, ga.album_date DESC LIMIT 6")->fetchAll();
    } catch(Exception $e) { $albums = []; }
}
?>

<!-- ── Hero Slider ─────────────────────────────────────────── -->
<?php if ($showBanner && $banners): ?>
<section class="hero-slider" aria-label="<?= t('Banner Slideshow','ব্যানার স্লাইডশো') ?>">
  <div class="slider-track" id="sliderTrack">
    <?php foreach ($banners as $i => $banner): ?>
    <div class="slide <?= $i === 0 ? 'active' : '' ?>" style="<?= $banner['image'] ? 'background-image:url('.imgUrl($banner['image'],'large').')' : 'background: linear-gradient(135deg, var(--primary) 0%, #004d39 100%)' ?>">
      <div class="slide-overlay"></div>
      <div class="slide-content">
        <h2 class="slide-title"><?= h(field($banner, 'title')) ?></h2>
        <?php if ($banner['subtitle_' . getLang()] ?? $banner['subtitle_en']): ?>
        <p class="slide-subtitle"><?= h(field($banner, 'subtitle')) ?></p>
        <?php endif; ?>
        <?php if ($banner['link']): ?>
        <a href="<?= h($banner['link']) ?>" class="slide-btn"><?= t('Learn More','আরও জানুন') ?></a>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php if (count($banners) > 1): ?>
  <button class="slider-btn prev" id="sliderPrev" aria-label="Previous">&#8592;</button>
  <button class="slider-btn next" id="sliderNext" aria-label="Next">&#8594;</button>
  <div class="slider-dots" id="sliderDots">
    <?php foreach ($banners as $i => $_): ?>
    <button class="dot <?= $i === 0 ? 'active' : '' ?>" data-index="<?= $i ?>" aria-label="Slide <?= $i+1 ?>"></button>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>
<?php endif; ?>

<!-- ── Main Content Area ───────────────────────────────────── -->
<div class="container home-main">
  <div class="home-grid">

    <!-- LEFT: Main content -->
    <div class="home-content">

      <!-- Principal's Message -->
      <?php if ($showPMsg && $principalData): ?>
      <section class="section principal-section">
        <div class="section-header">
          <h2 class="section-title"><?= t("Principal's Message","অধ্যক্ষের বাণী") ?></h2>
          <div class="title-underline"></div>
        </div>
        <div class="principal-card">
          <div class="principal-photo">
            <?php if ($principalData['photo']): ?>
            <img src="<?= h(imgUrl($principalData['photo'], 'medium')) ?>" alt="<?= h(field($principalData,'name')) ?>">
            <?php else: ?>
            <div class="photo-placeholder">👤</div>
            <?php endif; ?>
          </div>
          <div class="principal-text">
            <blockquote><?= nl2br(h(excerpt(field($principalData, 'bio'), 60))) ?></blockquote>
            <div class="principal-info">
              <strong><?= h(field($principalData,'name')) ?></strong><br>
              <span><?= h(field($principalData,'designation') ?: t('Principal','অধ্যক্ষ')) ?></span>
            </div>
          </div>
        </div>
      </section>
      <?php endif; ?>

      <!-- Notices & Events Tabs -->
      <section class="section tabs-section">
        <div class="tabs-header">
          <button class="tab-btn active" data-tab="notices"><?= t('Notices','নোটিশ') ?></button>
          <button class="tab-btn" data-tab="events"><?= t('Events','ইভেন্ট') ?></button>
          <button class="tab-btn" data-tab="news"><?= t('News','সংবাদ') ?></button>
          <a href="<?= pageUrl('notices') ?>" class="tab-all"><?= t('See All →','সব দেখুন →') ?></a>
        </div>

        <div class="tab-content active" id="tab-notices">
          <?php if ($notices): ?>
          <ul class="notice-list">
            <?php foreach ($notices as $n): ?>
            <li class="notice-item <?= $n['is_pinned'] ? 'pinned' : '' ?> <?= $n['is_urgent'] ? 'urgent' : '' ?>">
              <div class="notice-meta">
                <span class="notice-tag tag-<?= h($n['type']) ?>"><?= t(ucfirst($n['type']), '') ?></span>
                <span class="notice-date"><?= formatDate($n['publish_date'] ?? $n['created_at']) ?></span>
              </div>
              <a href="<?= pageUrl('notice_detail', ['id' => $n['id']]) ?>" class="notice-title">
                <?= h(field($n, 'title')) ?>
                <?php if ($n['is_urgent']): ?><span class="badge-urgent"><?= t('Urgent','জরুরি') ?></span><?php endif; ?>
              </a>
              <?php if ($n['file_url']): ?>
              <a href="<?= h($n['file_url']) ?>" class="notice-download" target="_blank">📎 <?= t('Download','ডাউনলোড') ?></a>
              <?php endif; ?>
            </li>
            <?php endforeach; ?>
          </ul>
          <?php else: ?><p class="empty-msg"><?= t('No notices available.','কোনো নোটিশ নেই।') ?></p><?php endif; ?>
        </div>

        <div class="tab-content" id="tab-events">
          <?php if ($events): ?>
          <ul class="notice-list">
            <?php foreach ($events as $n): ?>
            <li class="notice-item">
              <div class="notice-meta">
                <span class="notice-date"><?= formatDate($n['publish_date'] ?? $n['created_at']) ?></span>
              </div>
              <a href="<?= pageUrl('notice_detail', ['id' => $n['id']]) ?>" class="notice-title"><?= h(field($n, 'title')) ?></a>
            </li>
            <?php endforeach; ?>
          </ul>
          <?php else: ?><p class="empty-msg"><?= t('No events.','কোনো ইভেন্ট নেই।') ?></p><?php endif; ?>
        </div>

        <div class="tab-content" id="tab-news">
          <?php if ($news): ?>
          <ul class="notice-list">
            <?php foreach ($news as $n): ?>
            <li class="notice-item">
              <div class="notice-meta"><span class="notice-date"><?= formatDate($n['publish_date'] ?? $n['created_at']) ?></span></div>
              <a href="<?= pageUrl('notice_detail', ['id' => $n['id']]) ?>" class="notice-title"><?= h(field($n, 'title')) ?></a>
            </li>
            <?php endforeach; ?>
          </ul>
          <?php else: ?><p class="empty-msg"><?= t('No news.','কোনো সংবাদ নেই।') ?></p><?php endif; ?>
        </div>
      </section>

      <!-- Honorees -->
      <?php if ($showHonorees && ($studHonorees || $teachHonorees)): ?>
      <section class="section honorees-section">
        <div class="section-header">
          <h2 class="section-title"><?= t('Stars of the Year','বর্ষসেরা') ?></h2>
          <div class="title-underline"></div>
        </div>
        <div class="honorees-grid">
          <?php foreach ($studHonorees as $h_item): ?>
          <div class="honoree-card student-card">
            <div class="honoree-badge">⭐</div>
            <div class="honoree-photo">
              <?php if ($h_item['photo']): ?>
              <img src="<?= h(imgUrl($h_item['photo'], 'medium')) ?>" alt="<?= h(field($h_item,'name')) ?>">
              <?php else: ?><div class="photo-ph">🎓</div><?php endif; ?>
            </div>
            <div class="honoree-info">
              <span class="honoree-type"><?= t('Student of the Year','বর্ষসেরা শিক্ষার্থী') ?></span>
              <h3><?= h(field($h_item,'name')) ?></h3>
              <?php if ($h_item['class_name']): ?><p><?= h($h_item['class_name']) ?></p><?php endif; ?>
              <?php if ($h_item['year']): ?><span class="honoree-year"><?= banglaNum($h_item['year']) ?></span><?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
          <?php foreach ($teachHonorees as $h_item): ?>
          <div class="honoree-card teacher-card">
            <div class="honoree-badge">🏆</div>
            <div class="honoree-photo">
              <?php if ($h_item['photo']): ?>
              <img src="<?= h(imgUrl($h_item['photo'], 'medium')) ?>" alt="<?= h(field($h_item,'name')) ?>">
              <?php else: ?><div class="photo-ph">👩‍🏫</div><?php endif; ?>
            </div>
            <div class="honoree-info">
              <span class="honoree-type"><?= t('Teacher of the Year','বর্ষসেরা শিক্ষক') ?></span>
              <h3><?= h(field($h_item,'name')) ?></h3>
              <?php if ($h_item['year']): ?><span class="honoree-year"><?= banglaNum($h_item['year']) ?></span><?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>

      <!-- Gallery Preview -->
      <?php if ($showGallery && $albums): ?>
      <section class="section gallery-section">
        <div class="section-header">
          <h2 class="section-title"><?= t('Photo Gallery','ফটো গ্যালারি') ?></h2>
          <a href="<?= pageUrl('gallery') ?>" class="section-link"><?= t('View All →','সব দেখুন →') ?></a>
        </div>
        <div class="gallery-grid">
          <?php foreach ($albums as $alb): 
            $coverImg = $alb['cover_image'] ?: $alb['first_img'];
          ?>
          <a href="<?= pageUrl('gallery', ['album' => $alb['id']]) ?>" class="gallery-item">
            <?php if ($coverImg): ?>
            <img src="<?= h(imgUrl($coverImg, 'medium')) ?>" alt="<?= h(field($alb,'title')) ?>" loading="lazy">
            <?php else: ?>
            <div class="gallery-placeholder">📷</div>
            <?php endif; ?>
            <div class="gallery-overlay">
              <span><?= h(field($alb,'title')) ?></span>
            </div>
          </a>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>

    </div><!-- /.home-content -->

    <!-- RIGHT: Sidebar -->
    <aside class="home-sidebar">

      <!-- Jobs Marquee -->
      <?php if ($jobs): ?>
      <div class="sidebar-card jobs-card">
        <div class="sidebar-card-header jobs-header">
          <span>💼</span> <?= t('Job Openings','চাকরির বিজ্ঞপ্তি') ?>
        </div>
        <ul class="sidebar-notice-list">
          <?php foreach ($jobs as $j): ?>
          <li>
            <a href="<?= pageUrl('notice_detail', ['id' => $j['id']]) ?>">
              <?= h(field($j,'title')) ?>
            </a>
            <span class="item-date"><?= formatDate($j['publish_date'] ?? $j['created_at']) ?></span>
          </li>
          <?php endforeach; ?>
        </ul>
        <a href="<?= pageUrl('admission', ['sub' => 'jobs']) ?>" class="sidebar-link"><?= t('Apply Online →','অনলাইনে আবেদন →') ?></a>
      </div>
      <?php endif; ?>

      <!-- Results -->
      <?php if ($results): ?>
      <div class="sidebar-card">
        <div class="sidebar-card-header">
          📋 <?= t('Results','ফলাফল') ?>
        </div>
        <ul class="sidebar-notice-list">
          <?php foreach ($results as $r): ?>
          <li>
            <a href="<?= pageUrl('notice_detail', ['id' => $r['id']]) ?>"><?= h(field($r,'title')) ?></a>
            <span class="item-date"><?= formatDate($r['publish_date'] ?? $r['created_at']) ?></span>
          </li>
          <?php endforeach; ?>
        </ul>
        <a href="<?= pageUrl('academic', ['sub' => 'results']) ?>" class="sidebar-link"><?= t('All Results →','সব ফলাফল →') ?></a>
      </div>
      <?php endif; ?>

      <!-- Admission Notices -->
      <?php if ($admNotices): ?>
      <div class="sidebar-card admission-card">
        <div class="sidebar-card-header adm-header">
          🎓 <?= t('Admission Info','ভর্তি তথ্য') ?>
        </div>
        <ul class="sidebar-notice-list">
          <?php foreach ($admNotices as $a): ?>
          <li>
            <a href="<?= pageUrl('notice_detail', ['id' => $a['id']]) ?>"><?= h(field($a,'title')) ?></a>
            <span class="item-date"><?= formatDate($a['publish_date'] ?? $a['created_at']) ?></span>
          </li>
          <?php endforeach; ?>
        </ul>
        <a href="<?= pageUrl('admission') ?>" class="sidebar-link"><?= t('Admission Details →','ভর্তির বিস্তারিত →') ?></a>
      </div>
      <?php endif; ?>

      <!-- Quick Links Sidebar -->
      <div class="sidebar-card quick-links-card">
        <div class="sidebar-card-header">⚡ <?= t('Quick Access','দ্রুত অ্যাক্সেস') ?></div>
        <ul class="quick-links">
          <li><a href="<?= pageUrl('academic', ['sub' => 'routine']) ?>">📅 <?= t('Class Routine','ক্লাস রুটিন') ?></a></li>
          <li><a href="<?= pageUrl('academic', ['sub' => 'exam']) ?>">📝 <?= t('Exam Schedule','পরীক্ষার সময়সূচি') ?></a></li>
          <li><a href="<?= pageUrl('academic', ['sub' => 'results']) ?>">🏆 <?= t('Results','ফলাফল') ?></a></li>
          <li><a href="<?= pageUrl('administration', ['sub' => 'teachers']) ?>">👩‍🏫 <?= t('Teachers','শিক্ষকবৃন্দ') ?></a></li>
          <li><a href="<?= pageUrl('admission', ['sub' => 'form']) ?>">📄 <?= t('Application Form','আবেদন ফর্ম') ?></a></li>
          <li><a href="<?= pageUrl('admission', ['sub' => 'fees']) ?>">💰 <?= t('Fee Structure','ফি তালিকা') ?></a></li>
        </ul>
      </div>

    </aside><!-- /.home-sidebar -->
  </div><!-- /.home-grid -->
</div><!-- /.container -->
