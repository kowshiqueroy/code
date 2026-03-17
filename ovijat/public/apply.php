<?php
/**
 * OVIJAT GROUP — public/apply.php
 * Job application form.
 * SECURITY: Text fields ONLY. Absolutely NO file/CV uploads.
 */
require_once __DIR__ . '/../includes/config.php';
$lang = lang();
$jobId = (int)($_GET['job'] ?? 0);
$today = date('Y-m-d');

$job = db()->prepare("SELECT * FROM jobs WHERE id=? AND active=1 AND expires_at >= ?");
$job->execute([$jobId, $today]);
$job = $job->fetch();

if (!$job) {
    redirect(SITE_URL . '/?page=careers');
}

$pageTitle = ($lang === 'bn' ? 'আবেদন করুন: ' : 'Apply: ') . t($job, 'title');

// Skills definition
$skills = [
    'excel'         => ['en' => 'Microsoft Excel',       'bn' => 'মাইক্রোসফট এক্সেল'],
    'google_sheets' => ['en' => 'Google Sheets',         'bn' => 'গুগল শিটস'],
    'photoshop'     => ['en' => 'Adobe Photoshop',       'bn' => 'অ্যাডোবি ফটোশপ'],
    'email'         => ['en' => 'Professional Email',     'bn' => 'পেশাদার ইমেইল'],
    'tally'         => ['en' => 'Tally ERP',             'bn' => 'ট্যালি ইআরপি'],
    'word'          => ['en' => 'Microsoft Word',         'bn' => 'মাইক্রোসফট ওয়ার্ড'],
    'powerpoint'    => ['en' => 'PowerPoint/Presentation','bn' => 'পাওয়ারপয়েন্ট'],
    'internet'      => ['en' => 'Internet Browsing',     'bn' => 'ইন্টারনেট ব্রাউজিং'],
    'typing'        => ['en' => 'Bangla/English Typing',  'bn' => 'বাংলা/ইংরেজি টাইপিং'],
    'social_media'  => ['en' => 'Social Media Marketing','bn' => 'সোশ্যাল মিডিয়া মার্কেটিং'],
];

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Block any file upload attempt — belts AND suspenders
    if (!empty($_FILES)) {
        $errors[] = 'File uploads are not permitted in this application form.';
    }

    if (!csrf_verify()) {
        $errors[] = 'Security validation failed. Please refresh and try again.';
    }

    if (!$errors) {
        $name      = sanitizeText($_POST['name'] ?? '');
        $phone     = sanitizeText($_POST['phone'] ?? '');
        $email     = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL) ? trim($_POST['email']) : '';
        $academic  = sanitizeText($_POST['academic'] ?? '');
        $workexp   = sanitizeText($_POST['workexp'] ?? '');
        $cover     = sanitizeText($_POST['cover_letter'] ?? '');
        $rawSkills = $_POST['skills'] ?? [];

        // Whitelist skills
        $allowedKeys = array_keys($skills);
        $chosenSkills = array_filter($rawSkills, fn($k) => in_array($k, $allowedKeys));

        if (strlen($name) < 2)    $errors[] = $lang === 'bn' ? 'নাম প্রয়োজন।'             : 'Full name is required.';
        if (strlen($phone) < 7)   $errors[] = $lang === 'bn' ? 'ফোন নম্বর প্রয়োজন।'      : 'Phone number is required.';
        if (strlen($academic) < 5)$errors[] = $lang === 'bn' ? 'শিক্ষাগত যোগ্যতা লিখুন।'  : 'Academic qualifications required.';
        if (strlen($workexp) < 3) $errors[] = $lang === 'bn' ? 'কর্মঅভিজ্ঞতা লিখুন।'     : 'Work experience is required.';
        if (strlen($cover) < 20)  $errors[] = $lang === 'bn' ? 'কভার লেটার কমপক্ষে ২০ অক্ষরের হতে হবে।' : 'Cover letter must be at least 20 characters.';

        if (!$errors) {
            $stmt = db()->prepare("INSERT INTO job_applications (job_id,name,phone,email,academic_qualifications,work_experience,cover_letter,skills,ip) VALUES (?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                $jobId,
                $name,
                $phone,
                $email ?: null,
                $academic,
                $workexp,
                $cover,
                json_encode(array_values($chosenSkills)),
                $_SERVER['REMOTE_ADDR'],
            ]);
            $success = true;
        }
    }
}

require_once __DIR__ . '/partials/header.php';
?>
<section class="section page-section">
  <div class="container">
    <nav class="breadcrumb">
      <a href="<?= SITE_URL ?>/"><?= $lang === 'bn' ? 'হোম' : 'Home' ?></a>
      <span>›</span>
      <a href="<?= SITE_URL ?>/?page=careers"><?= $lang === 'bn' ? 'ক্যারিয়ার' : 'Careers' ?></a>
      <span>›</span>
      <span><?= t($job,'title') ?></span>
    </nav>

    <div class="page-hero-header">
      <h1 class="page-hero-title"><?= $lang === 'bn' ? 'চাকরিতে আবেদন' : 'Job Application' ?></h1>
    </div>

    <!-- Job Summary Card -->
    <div class="apply-job-summary">
      <h2><?= t($job,'title') ?></h2>
      <div class="job-meta">
        <?php if ($job['department_en']): ?><span class="job-meta-tag">🏢 <?= t($job,'department') ?></span><?php endif; ?>
        <?php if ($job['location_en']): ?><span class="job-meta-tag">📍 <?= t($job,'location') ?></span><?php endif; ?>
        <?php if ($job['type_en']): ?><span class="job-meta-tag">⏱ <?= t($job,'type') ?></span><?php endif; ?>
        <?php if ($job['salary_range']): ?><span class="job-meta-tag salary-tag">💰 <?= e($job['salary_range']) ?></span><?php endif; ?>
      </div>
      <?php $desc = $lang === 'bn' ? $job['desc_bn'] : $job['desc_en'];
      if ($desc): ?><p class="job-desc"><?= nl2br(e($desc)) ?></p><?php endif; ?>
    </div>

    <?php if ($success): ?>
      <div class="alert alert-success apply-success">
        <div class="success-icon">🎉</div>
        <h3><?= $lang === 'bn' ? 'আবেদন সফল!' : 'Application Submitted!' ?></h3>
        <p><?= $lang === 'bn' ? 'আপনার আবেদন সফলভাবে গ্রহণ করা হয়েছে। আমরা শীঘ্রই আপনার সাথে যোগাযোগ করব।' : 'Your application has been received. Our HR team will contact you soon.' ?></p>
        <a href="<?= SITE_URL ?>/?page=careers" class="btn btn-primary"><?= $lang === 'bn' ? 'অন্য পদ দেখুন' : 'View Other Positions' ?></a>
      </div>
    <?php else: ?>

      <?php if ($errors): ?>
        <div class="alert alert-danger">
          <ul><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul>
        </div>
      <?php endif; ?>

      <!-- ⚠️ enctype is intentionally NOT set to multipart — prevents file upload -->
      <form method="POST" action="" class="apply-form" novalidate>
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="job_id" value="<?= $jobId ?>">

        <div class="form-section-block">
          <h3 class="form-block-title"><?= $lang === 'bn' ? 'ব্যক্তিগত তথ্য' : 'Personal Information' ?></h3>
          <div class="form-row">
            <div class="form-group">
              <label for="a_name"><?= $lang === 'bn' ? 'পূর্ণ নাম *' : 'Full Name *' ?></label>
              <input type="text" id="a_name" name="name" class="form-input" required maxlength="200"
                     value="<?= e($_POST['name'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label for="a_phone"><?= $lang === 'bn' ? 'মোবাইল নম্বর *' : 'Mobile Number *' ?></label>
              <input type="tel" id="a_phone" name="phone" class="form-input" required maxlength="80"
                     value="<?= e($_POST['phone'] ?? '') ?>">
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label for="a_email"><?= $lang === 'bn' ? 'ই-মেইল' : 'Email Address' ?></label>
              <input type="email" id="a_email" name="email" class="form-input" maxlength="150"
                     value="<?= e($_POST['email'] ?? '') ?>">
            </div>
          </div>
        </div>

        <div class="form-section-block">
          <h3 class="form-block-title"><?= $lang === 'bn' ? 'শিক্ষাগত যোগ্যতা *' : 'Academic Qualifications *' ?></h3>
          <p class="form-hint"><?= $lang === 'bn' ? 'আপনার সর্বোচ্চ শিক্ষাগত যোগ্যতা থেকে শুরু করে বিস্তারিত লিখুন।' : 'List your qualifications from highest to lowest, including institution names and years.' ?></p>
          <div class="form-group">
            <textarea id="a_academic" name="academic" class="form-textarea" rows="5" required><?= e($_POST['academic'] ?? '') ?></textarea>
          </div>
        </div>

        <div class="form-section-block">
          <h3 class="form-block-title"><?= $lang === 'bn' ? 'কর্ম অভিজ্ঞতা *' : 'Work Experience *' ?></h3>
          <p class="form-hint"><?= $lang === 'bn' ? 'যদি ফ্রেশার হন, তাহলে "ফ্রেশার" লিখুন অথবা ইন্টার্নশিপ / স্বেচ্ছাকর্মের বিবরণ দিন।' : 'If fresher, write "Fresher" or describe any internship/volunteer experience.' ?></p>
          <div class="form-group">
            <textarea id="a_workexp" name="workexp" class="form-textarea" rows="5" required><?= e($_POST['workexp'] ?? '') ?></textarea>
          </div>
        </div>

        <div class="form-section-block">
          <h3 class="form-block-title"><?= $lang === 'bn' ? 'কম্পিউটার দক্ষতা' : 'Computer Skills' ?></h3>
          <p class="form-hint"><?= $lang === 'bn' ? 'প্রযোজ্য সব দক্ষতায় টিক দিন।' : 'Check all that apply.' ?></p>
          <div class="skills-grid">
            <?php foreach ($skills as $key => $labels): ?>
              <label class="skill-checkbox-label">
                <input type="checkbox" name="skills[]" value="<?= e($key) ?>"
                  <?= in_array($key, $_POST['skills'] ?? []) ? 'checked' : '' ?>>
                <span class="skill-check-custom"></span>
                <?= $lang === 'bn' ? e($labels['bn']) : e($labels['en']) ?>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="form-section-block">
          <h3 class="form-block-title"><?= $lang === 'bn' ? 'কভার লেটার *' : 'Cover Letter *' ?></h3>
          <p class="form-hint"><?= $lang === 'bn' ? 'কেন আপনি এই পদের জন্য উপযুক্ত তা নিজের ভাষায় বলুন।' : 'Tell us in your own words why you are the right fit for this role.' ?></p>
          <div class="form-group">
            <textarea id="a_cover" name="cover_letter" class="form-textarea" rows="8" required><?= e($_POST['cover_letter'] ?? '') ?></textarea>
          </div>
        </div>

        <div class="form-notice">
          ℹ️ <?= $lang === 'bn' ? 'দ্রষ্টব্য: এই ফর্মে কোনো ফাইল বা সিভি আপলোড গ্রহণ করা হয় না।' : 'Note: This form does not accept any file or CV uploads. Text-based application only.' ?>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn btn-primary btn-lg">
            <?= $lang === 'bn' ? 'আবেদন জমা দিন ✓' : 'Submit Application ✓' ?>
          </button>
          <a href="<?= SITE_URL ?>/?page=careers" class="btn btn-ghost">
            <?= $lang === 'bn' ? '← ফিরে যান' : '← Back to Careers' ?>
          </a>
        </div>
      </form>
    <?php endif; ?>
  </div>
</section>
<?php require_once __DIR__ . '/partials/footer.php'; ?>
