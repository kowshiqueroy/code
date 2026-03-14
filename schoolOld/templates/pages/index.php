<?php
/**
 * Home Page Template
 * Features: Slider, Quick Stats, Notices, Events, Principal's Message, Gallery Highlights
 */

// Fetch sliders
try {
  $sliders = db()->query("SELECT * FROM sliders WHERE is_active=1 ORDER BY sort_order ASC LIMIT 5")->fetchAll();
} catch(Exception $e) { $sliders = []; }

// Fetch events
try {
  $events = db()->query("SELECT * FROM events WHERE is_published=1 AND event_date >= CURDATE() ORDER BY event_date ASC LIMIT 5")->fetchAll();
} catch(Exception $e) { $events = []; }

// Fetch principal
try {
  $principal = db()->query("SELECT * FROM teachers WHERE is_principal=1 AND is_active=1 LIMIT 1")->fetch();
} catch(Exception $e) { $principal = null; }

// Fetch gallery highlights
try {
  $gallery_items = db()->query("SELECT * FROM gallery WHERE is_published=1 AND media_type='image' ORDER BY created_at DESC LIMIT 6")->fetchAll();
} catch(Exception $e) { $gallery_items = []; }

// Fetch quick links
try {
  $quick_links = db()->query("SELECT * FROM quick_links WHERE is_active=1 ORDER BY sort_order LIMIT 8")->fetchAll();
} catch(Exception $e) { $quick_links = []; }

// Site stats
$stats = [
  ['key'=>'total_students', 'label_en'=>'Students','label_bn'=>'শিক্ষার্থী','icon'=>'👨‍🎓','default'=>'0'],
  ['key'=>'total_teachers', 'label_en'=>'Teachers','label_bn'=>'শিক্ষক','icon'=>'👩‍🏫','default'=>'0'],
  ['key'=>'pass_rate',      'label_en'=>'Pass Rate','label_bn'=>'পাসের হার','icon'=>'📊','default'=>'0%'],
  ['key'=>'established_year','label_en'=>'Est. Year','label_bn'=>'প্রতিষ্ঠাকাল','icon'=>'🏫','default'=>'—'],
];
?>

<!-- ─── Hero Slider ──────────────────────────────────────────────────────── -->
<?php if (!empty($sliders)): ?>
<div class="hero-slider" aria-label="Image slideshow">
  <div class="slider-track">
    <?php foreach ($sliders as $slide): ?>
    <div class="slide">
      <?php if (!empty($slide['image'])): ?>
      <img src="<?= upload_url($slide['image']) ?>" alt="<?= h(field($slide,'title')) ?>" loading="<?= $slide === reset($sliders) ? 'eager' : 'lazy' ?>">
      <?php else: ?>
      <div style="width:100%;height:100%;background:linear-gradient(135deg,var(--primary),var(--primary-dark))"></div>
      <?php endif; ?>
      <?php if (!empty($slide['title_en']) || !empty($slide['title_bn'])): ?>
      <div class="slide-overlay">
        <div class="container">
          <div class="slide-content">
            <h2><?= h(field($slide,'title')) ?></h2>
            <?php if (!empty($slide['subtitle_en']) || !empty($slide['subtitle_bn'])): ?>
            <p><?= h(field($slide,'subtitle')) ?></p>
            <?php endif; ?>
            <?php if (!empty($slide['link'])): ?>
            <a href="<?= h($slide['link']) ?>" class="btn btn-accent"><?= t('Learn More','আরও জানুন') ?></a>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php if (count($sliders) > 1): ?>
  <button class="slider-arrow slider-prev" aria-label="Previous slide">‹</button>
  <button class="slider-arrow slider-next" aria-label="Next slide">›</button>
  <div class="slider-nav" role="tablist" aria-label="Slide navigation">
    <?php foreach ($sliders as $i => $slide): ?>
    <button class="slider-dot <?= $i===0?'active':'' ?>" role="tab" aria-label="Slide <?= $i+1 ?>"></button>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php else: ?>
<!-- Default hero when no slider -->
<div style="background:linear-gradient(135deg,var(--primary),var(--primary-dark));color:#fff;padding:80px 0;text-align:center">
  <div class="container">
    <h1 style="font-size:2.4rem;margin-bottom:16px"><?= h(t(get_setting('site_name_en'),get_setting('site_name_bn'))) ?></h1>
    <p style="font-size:1.1rem;opacity:.9;max-width:600px;margin:0 auto 28px"><?= h(t(get_setting('site_tagline_en'),get_setting('site_tagline_bn'))) ?></p>
    <a href="<?= url('about') ?>" class="btn btn-accent"><?= t('About Us','আমাদের সম্পর্কে') ?></a>
    &nbsp;
    <a href="<?= url('admissions') ?>" class="btn" style="background:rgba(255,255,255,.15);color:#fff;border-color:rgba(255,255,255,.4)"><?= t('Admissions','ভর্তি') ?></a>
  </div>
</div>
<?php endif; ?>

<!-- ─── Quick Stats Bar ──────────────────────────────────────────────────── -->
<div class="stats-bar" role="region" aria-label="<?= t('School Statistics','প্রতিষ্ঠানের পরিসংখ্যান') ?>">
  <div class="container">
    <div class="stats-grid">
      <?php foreach ($stats as $s): ?>
      <div class="stat-item">
        <div class="stat-number" data-count="<?= preg_replace('/\D/','',(get_setting($s['key'],$s['default']))) ?>">
          <?= h(get_setting($s['key'],$s['default'])) ?>
        </div>
        <div class="stat-label"><?= h(t($s['label_en'],$s['label_bn'])) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- ─── Quick Links (if any) ────────────────────────────────────────────── -->
<?php if (!empty($quick_links)): ?>
<div style="background:var(--primary-light);padding:24px 0;border-bottom:1px solid var(--border)">
  <div class="container">
    <div style="display:flex;gap:12px;flex-wrap:wrap;justify-content:center">
      <?php foreach ($quick_links as $ql): ?>
      <a href="<?= h($ql['url']) ?>" class="btn btn-outline btn-sm" target="<?= $ql['target']??'_self' ?>">
        <?= $ql['icon'] ? $ql['icon'].' ' : '' ?><?= h(field($ql,'title')) ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ─── Notices + Events ─────────────────────────────────────────────────── -->
<section class="section section-alt" aria-labelledby="notices-heading">
  <div class="container">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:40px">

      <!-- Notices -->
      <div>
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px">
          <h2 id="notices-heading" style="font-size:1.4rem;font-weight:700;color:var(--primary);padding-bottom:10px;border-bottom:3px solid var(--accent)">
            📋 <?= t('Latest Notices','সর্বশেষ বিজ্ঞপ্তি') ?>
          </h2>
          <a href="<?= url('notices') ?>" class="btn btn-sm btn-outline"><?= t('View All','সব দেখুন') ?></a>
        </div>

        <?php if (!empty($notices)): ?>
        <?php foreach (array_slice($notices, 0, 6) as $notice): ?>
        <div class="notice-card <?= $notice['is_important'] ? 'important' : '' ?>">
          <div class="notice-date">
            <div class="day"><?= date('d', strtotime($notice['created_at'])) ?></div>
            <div class="mon"><?= date('M', strtotime($notice['created_at'])) ?></div>
          </div>
          <div class="notice-body">
            <?php if ($notice['is_important']): ?>
            <span class="badge" style="background:var(--secondary);color:#fff;font-size:.68rem;margin-bottom:4px;display:inline-block"><?= t('Important','গুরুত্বপূর্ণ') ?></span>
            <?php endif; ?>
            <div class="notice-title">
              <a href="<?= url('notices') ?>&id=<?= (int)$notice['id'] ?>" style="color:var(--text)">
                <?= h(field($notice,'title')) ?>
              </a>
            </div>
            <?php if ($notice['attachment']): ?>
            <a href="<?= upload_url($notice['attachment']) ?>" class="notice-meta" download>
              📎 <?= t('Download Attachment','সংযুক্তি ডাউনলোড') ?>
            </a>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
        <?php else: ?>
        <p style="color:var(--text-muted);text-align:center;padding:32px"><?= t('No notices available.','কোনো বিজ্ঞপ্তি নেই।') ?></p>
        <?php endif; ?>
      </div>

      <!-- Events -->
      <div>
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px">
          <h2 style="font-size:1.4rem;font-weight:700;color:var(--primary);padding-bottom:10px;border-bottom:3px solid var(--accent)">
            📅 <?= t('Upcoming Events','আসন্ন অনুষ্ঠান') ?>
          </h2>
          <a href="<?= url('index') ?>&section=events" class="btn btn-sm btn-outline"><?= t('View All','সব দেখুন') ?></a>
        </div>

        <?php if (!empty($events)): ?>
        <?php foreach ($events as $event): ?>
        <div class="event-card">
          <div class="event-date-box">
            <div class="day"><?= date('d', strtotime($event['event_date'])) ?></div>
            <div class="mon"><?= date('M', strtotime($event['event_date'])) ?></div>
          </div>
          <div>
            <div style="font-weight:700;font-size:.92rem;color:var(--text);margin-bottom:4px">
              <?= h(field($event,'title')) ?>
            </div>
            <?php if (!empty($event['venue_en']) || !empty($event['venue_bn'])): ?>
            <div style="font-size:.8rem;color:var(--text-muted)">
              📍 <?= h(field($event,'venue')) ?>
            </div>
            <?php endif; ?>
            <?php if (!empty($event['event_time'])): ?>
            <div style="font-size:.8rem;color:var(--text-muted)">
              🕐 <?= h(date('h:i A', strtotime($event['event_time']))) ?>
            </div>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
        <?php else: ?>
        <p style="color:var(--text-muted);text-align:center;padding:32px"><?= t('No upcoming events.','কোনো আসন্ন অনুষ্ঠান নেই।') ?></p>
        <?php endif; ?>
      </div>

    </div>
  </div>
</section>

<!-- ─── Principal's Message ──────────────────────────────────────────────── -->
<?php if ($principal): ?>
<section class="section" aria-labelledby="principal-heading">
  <div class="container">
    <div class="section-header">
      <h2 id="principal-heading"><?= t("Principal's Message","প্রধান শিক্ষকের বার্তা") ?></h2>
    </div>
    <div class="principal-msg">
      <div>
        <?php if (!empty($principal['photo'])): ?>
        <img src="<?= upload_url($principal['photo']) ?>" alt="<?= h(field($principal,'name')) ?>" class="principal-photo">
        <?php else: ?>
        <div class="principal-photo" style="background:var(--primary-light);display:flex;align-items:center;justify-content:center;font-size:3rem">👤</div>
        <?php endif; ?>
        <div style="text-align:center;margin-top:16px">
          <div style="font-weight:700;font-size:1rem"><?= h(field($principal,'name')) ?></div>
          <div style="color:var(--primary);font-size:.85rem"><?= h(field($principal,'designation')) ?></div>
        </div>
      </div>
      <div style="flex:1">
        <?php $bio = field($principal,'bio'); ?>
        <?php if ($bio): ?>
        <blockquote class="principal-quote">
          <?= nl2br(h(mb_substr(strip_tags($bio),0,500))) ?>…
        </blockquote>
        <?php endif; ?>
        <a href="<?= url('administration') ?>" class="btn btn-outline"><?= t('Read Full Message','পুরো বার্তা পড়ুন') ?></a>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ─── Home Page Content from CMS ─────────────────────────────────────── -->
<?php if (!empty($page_data) && !empty($page_data['content_'.LANG])): ?>
<section class="section section-alt">
  <div class="container page-content">
    <?= $page_data['content_'.LANG] ?? $page_data['content_en'] ?? '' ?>
  </div>
</section>
<?php endif; ?>

<!-- ─── Gallery Highlights ───────────────────────────────────────────────── -->
<?php if (!empty($gallery_items)): ?>
<section class="section" aria-labelledby="gallery-heading">
  <div class="container">
    <div class="section-header">
      <h2 id="gallery-heading"><?= t('Photo Gallery','ছবির গ্যালারি') ?></h2>
      <p><?= t('Moments from our institution','আমাদের প্রতিষ্ঠানের মুহূর্তগুলো') ?></p>
    </div>
    <div class="gallery-grid">
      <?php foreach ($gallery_items as $item): ?>
      <div class="gallery-item">
        <a href="<?= upload_url($item['file_path']) ?>"
           data-lightbox="<?= upload_url($item['file_path']) ?>"
           data-alt="<?= h(field($item,'title')) ?>"
           aria-label="<?= h(field($item,'title')) ?>">
          <img src="<?= upload_url($item['thumbnail'] ?: $item['file_path']) ?>"
               alt="<?= h(field($item,'title')) ?>"
               loading="lazy">
          <div class="gallery-overlay">🔍</div>
        </a>
      </div>
      <?php endforeach; ?>
    </div>
    <div style="text-align:center;margin-top:32px">
      <a href="<?= url('gallery') ?>" class="btn btn-primary"><?= t('View Full Gallery','সম্পূর্ণ গ্যালারি দেখুন') ?></a>
    </div>
  </div>
</section>
<?php endif; ?>
