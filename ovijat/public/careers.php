<?php
/**
 * OVIJAT GROUP — public/careers.php
 * Job listings — only shows non-expired active jobs.
 */
require_once __DIR__.'/../includes/config.php';
logVisitor();
$lang = lang();
$pageTitle = $lang === 'bn' ? 'ক্যারিয়ার' : 'Careers';
$today = date('Y-m-d');
$jobs = db()->query("SELECT * FROM jobs WHERE active=1 AND expires_at >= '$today' ORDER BY created_at DESC")->fetchAll();
require_once __DIR__ . '/partials/header.php';
?>
<section class="section page-section">
  <div class="container">
    <div class="page-hero-header">
      <h1 class="page-hero-title"><?= $lang === 'bn' ? 'আমাদের দলে যোগ দিন' : 'Join Our Team' ?></h1>
      <p><?= $lang === 'bn' ? 'আপনার ক্যারিয়ার গড়ুন বাংলাদেশের অন্যতম শীর্ষ খাদ্য প্রতিষ্ঠানে।' : 'Build your career at one of Bangladesh\'s leading food conglomerates.' ?></p>
    </div>

    <?php if ($jobs): ?>
      <div class="jobs-grid">
        <?php foreach ($jobs as $job):
          $daysLeft = (int)ceil((strtotime($job['expires_at']) - time()) / 86400);
          $urgency  = $daysLeft <= 7 ? 'urgent' : ($daysLeft <= 15 ? 'soon' : 'normal');
        ?>
          <div class="job-card">
            <div class="job-card-top">
              <div>
                <h2 class="job-title"><?= t($job,'title') ?></h2>
                <div class="job-meta">
                  <?php if ($job['department_en']): ?>
                    <span class="job-meta-tag">🏢 <?= t($job,'department') ?></span>
                  <?php endif; ?>
                  <?php if ($job['location_en']): ?>
                    <span class="job-meta-tag">📍 <?= t($job,'location') ?></span>
                  <?php endif; ?>
                  <?php if ($job['type_en']): ?>
                    <span class="job-meta-tag">⏱ <?= t($job,'type') ?></span>
                  <?php endif; ?>
                  <?php if ($job['salary_range']): ?>
                    <span class="job-meta-tag salary-tag">💰 <?= e($job['salary_range']) ?></span>
                  <?php endif; ?>
                </div>
              </div>
              <div class="job-deadline <?= $urgency ?>">
                <?php if ($urgency === 'urgent'): ?>
                  🔴 <?= $lang === 'bn' ? 'শেষ '.$daysLeft.' দিন!' : $daysLeft.' day(s) left!' ?>
                <?php else: ?>
                  📅 <?= $lang === 'bn' ? 'আবেদনের শেষ তারিখ' : 'Apply by' ?>:
                  <?= date('d M Y', strtotime($job['expires_at'])) ?>
                <?php endif; ?>
              </div>
            </div>
            <?php $desc = $lang === 'bn' ? $job['desc_bn'] : $job['desc_en'];
            if ($desc): ?>
              <p class="job-desc"><?= nl2br(e(mb_substr($desc, 0, 200))) ?>...</p>
            <?php endif; ?>
            <div class="job-card-actions">
              <a href="<?= SITE_URL ?>/?page=apply&job=<?= $job['id'] ?>" class="btn btn-primary">
                <?= $lang === 'bn' ? 'আবেদন করুন →' : 'Apply Now →' ?>
              </a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="empty-state-box">
        <div class="empty-icon">📋</div>
        <h3><?= $lang === 'bn' ? 'বর্তমানে কোনো খালি পদ নেই।' : 'No open positions at the moment.' ?></h3>
        <p><?= $lang === 'bn' ? 'পরে আবার দেখুন অথবা আমাদের সাথে যোগাযোগ করুন।' : 'Check back later or reach out to us directly.' ?></p>
        <a href="<?= SITE_URL ?>/?page=contact" class="btn btn-secondary">
          <?= $lang === 'bn' ? 'যোগাযোগ করুন' : 'Contact Us' ?>
        </a>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php require_once __DIR__ . '/partials/footer.php'; ?>
