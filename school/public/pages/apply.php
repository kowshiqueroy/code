<?php
// public/pages/apply.php
$noticeId = (int)($_GET['notice_id'] ?? 0);
$notice   = null;
if ($noticeId) {
    try {
        $stmt = getDB()->prepare("SELECT * FROM notices WHERE id=? AND type='job' AND is_active=1");
        $stmt->execute([$noticeId]);
        $notice = $stmt->fetch();
    } catch(Exception $e) {}
}

$submitted = false;
$errors    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = sanitize($_POST['name'] ?? '');
    $email    = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $phone    = sanitize($_POST['phone'] ?? '');
    $position = sanitize($_POST['position'] ?? '');
    $message  = sanitize($_POST['message'] ?? '');

    if (!$name)  $errors[] = t('Name required.','নাম আবশ্যক।');
    if (!$phone) $errors[] = t('Phone required.','ফোন আবশ্যক।');

    $cvFile = '';
    if (!empty($_FILES['cv']['name'])) {
        $cvMime    = mime_content_type($_FILES['cv']['tmp_name']);
        $allowedCv = ['application/pdf','application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        if (!in_array($cvMime, $allowedCv)) {
            $errors[] = t('CV must be PDF or Word file.','সিভি PDF বা Word ফাইল হতে হবে।');
        } elseif ($_FILES['cv']['size'] > 5*1024*1024) {
            $errors[] = t('CV too large (max 5MB).','সিভি ৫ এমবির বেশি হবে না।');
        } else {
            $cvDest = UPLOAD_PATH . 'documents/';
            @mkdir($cvDest, 0755, true);
            $cvName = 'cv_' . time() . '_' . rand(100,999) . '.pdf';
            move_uploaded_file($_FILES['cv']['tmp_name'], $cvDest . $cvName);
            $cvFile = $cvName;
        }
    }

    if (empty($errors)) {
        try {
            $stmt = getDB()->prepare("INSERT INTO job_applications (notice_id,applicant_name,email,phone,position,message,cv_file) VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([$noticeId ?: null, $name, $email, $phone, $position, $message, $cvFile]);
            $submitted = true;
        } catch(Exception $e) {
            $errors[] = t('Submission failed. Please try again.','জমা দেওয়া ব্যর্থ হয়েছে। আবার চেষ্টা করুন।');
        }
    }
}
?>
<div class="page-hero" style="background:linear-gradient(135deg,#276749,#1a4532)">
  <div class="container">
    <h1 class="page-hero-title">📝 <?= t('Online Application','অনলাইন আবেদন') ?></h1>
    <nav class="breadcrumb">
      <a href="<?= pageUrl('index') ?>"><?= t('Home','হোম') ?></a> / <?= t('Apply','আবেদন') ?>
    </nav>
  </div>
</div>
<div class="container page-body">
  <div class="form-wrap">
    <?php if ($notice): ?>
    <div class="job-notice-info">
      <h3>📋 <?= h(field($notice,'title')) ?></h3>
      <p><?= formatDate($notice['publish_date'] ?? $notice['created_at']) ?></p>
    </div>
    <?php endif; ?>

    <?php if ($submitted): ?>
    <div class="success-box">
      <div class="success-icon">✅</div>
      <h2><?= t('Application Submitted!','আবেদন সফলভাবে জমা হয়েছে!') ?></h2>
      <p><?= t('We have received your application. We will contact you shortly.','আমরা আপনার আবেদন পেয়েছি। শীঘ্রই যোগাযোগ করা হবে।') ?></p>
      <a href="<?= pageUrl('notices', ['type' => 'job']) ?>" class="btn-back">← <?= t('Back to Jobs','চাকরিতে ফিরুন') ?></a>
    </div>
    <?php else: ?>

    <?php if ($errors): ?>
    <div class="alert-box error-box">
      <?php foreach ($errors as $err): ?><p>⚠️ <?= h($err) ?></p><?php endforeach; ?>
    </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="apply-form" novalidate>
      <div class="form-row">
        <div class="form-group">
          <label><?= t('Full Name *','পূর্ণ নাম *') ?></label>
          <input type="text" name="name" value="<?= h($_POST['name'] ?? '') ?>" required placeholder="<?= t('Your full name','আপনার পূর্ণ নাম') ?>">
        </div>
        <div class="form-group">
          <label><?= t('Phone *','ফোন *') ?></label>
          <input type="tel" name="phone" value="<?= h($_POST['phone'] ?? '') ?>" required placeholder="01XXXXXXXXX">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label><?= t('Email','ইমেইল') ?></label>
          <input type="email" name="email" value="<?= h($_POST['email'] ?? '') ?>" placeholder="email@example.com">
        </div>
        <div class="form-group">
          <label><?= t('Applied Position','আবেদনকৃত পদ') ?></label>
          <input type="text" name="position" value="<?= h($_POST['position'] ?? '') ?>" placeholder="<?= t('Position name','পদের নাম') ?>">
        </div>
      </div>
      <div class="form-group">
        <label><?= t('Message / Cover Letter','বার্তা / কভার লেটার') ?></label>
        <textarea name="message" rows="5" placeholder="<?= t('Write about yourself and why you are applying...','নিজের সম্পর্কে ও আবেদনের কারণ লিখুন...') ?>"><?= h($_POST['message'] ?? '') ?></textarea>
      </div>
      <div class="form-group">
        <label><?= t('Upload CV (PDF/Word, max 5MB)','সিভি আপলোড করুন (PDF/Word, সর্বোচ্চ ৫ এমবি)') ?></label>
        <input type="file" name="cv" accept=".pdf,.doc,.docx">
      </div>
      <input type="hidden" name="notice_id" value="<?= $noticeId ?>">
      <button type="submit" class="btn-submit">📤 <?= t('Submit Application','আবেদন জমা দিন') ?></button>
    </form>
    <?php endif; ?>
  </div>
</div>
