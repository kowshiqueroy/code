<?php
/**
 * OVIJAT GROUP — public/contact.php
 * Contact page: Sales contact persons + inquiry form.
 */
require_once __DIR__.'/../includes/config.php';
logVisitor();
$lang = lang();
$pageTitle = $lang === 'bn' ? 'যোগাযোগ' : 'Contact Us';

$localContact  = db()->query("SELECT * FROM sales_contacts WHERE type='local' AND active=1 LIMIT 1")->fetch();
$exportContact = db()->query("SELECT * FROM sales_contacts WHERE type='export' AND active=1 LIMIT 1")->fetch();

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Security validation failed. Please refresh and try again.';
    } else {
        $name    = sanitizeText($_POST['name'] ?? '');
        $email   = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL) ? trim($_POST['email']) : '';
        $phone   = sanitizeText($_POST['phone'] ?? '');
        $subject = sanitizeText($_POST['subject'] ?? '');
        $message = sanitizeText($_POST['message'] ?? '');

        if (strlen($name) < 2)    $errors[] = $lang === 'bn' ? 'নাম প্রয়োজন।' : 'Name is required.';
        if (!$email && !$phone)   $errors[] = $lang === 'bn' ? 'ইমেইল বা ফোন প্রয়োজন।' : 'Email or phone is required.';
        if (strlen($subject) < 3) $errors[] = $lang === 'bn' ? 'বিষয় প্রয়োজন।' : 'Subject is required.';
        if (strlen($message) < 10)$errors[] = $lang === 'bn' ? 'বার্তা কমপক্ষে ১০ অক্ষরের হতে হবে।' : 'Message must be at least 10 characters.';

        if (!$errors) {
            $stmt = db()->prepare("INSERT INTO inquiries (name,email,phone,subject,message,ip) VALUES (?,?,?,?,?,?)");
            $stmt->execute([$name, $email ?: null, $phone ?: null, $subject, $message, $_SERVER['REMOTE_ADDR']]);
            $success = true;
        }
    }
}

require_once __DIR__ . '/partials/header.php';
?>
<section class="section page-section">
  <div class="container">
    <div class="page-hero-header">
      <h1 class="page-hero-title"><?= $lang === 'bn' ? 'যোগাযোগ করুন' : 'Get In Touch' ?></h1>
      <p><?= $lang === 'bn' ? 'আমরা আপনার সাথে কথা বলতে সর্বদা প্রস্তুত।' : 'We\'re always ready to hear from you.' ?></p>
    </div>

    <!-- Sales Contact Persons -->
    <div class="sales-contacts-grid">
      <?php if ($localContact): ?>
        <div class="sales-contact-card">
          <div class="sales-contact-photo">
            <img src="<?= imgUrl($localContact['image'] ?? '', 'management', 'sales') ?>"
                 alt="<?= t($localContact,'name') ?>" loading="lazy">
          </div>
          <div class="sales-contact-info">
            <span class="sales-contact-type"><?= $lang === 'bn' ? '🇧🇩 স্থানীয় বিক্রয়' : '🇧🇩 Local Sales' ?></span>
            <h3><?= t($localContact,'name') ?></h3>
            <p class="sales-title"><?= t($localContact,'title') ?></p>
            <a href="tel:<?= e($localContact['phone']) ?>" class="sales-phone">📞 <?= e($localContact['phone']) ?></a>
            <?php if ($localContact['email']): ?>
              <a href="mailto:<?= e($localContact['email']) ?>" class="sales-email"><?= e($localContact['email']) ?></a>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($exportContact): ?>
        <div class="sales-contact-card export-card">
          <div class="sales-contact-photo">
            <img src="<?= imgUrl($exportContact['image'] ?? '', 'management', 'sales') ?>"
                 alt="<?= t($exportContact,'name') ?>" loading="lazy">
          </div>
          <div class="sales-contact-info">
            <span class="sales-contact-type"><?= $lang === 'bn' ? '🌍 রপ্তানি বিক্রয়' : '🌍 Export Sales' ?></span>
            <h3><?= t($exportContact,'name') ?></h3>
            <p class="sales-title"><?= t($exportContact,'title') ?></p>
            <a href="tel:<?= e($exportContact['phone']) ?>" class="sales-phone">📞 <?= e($exportContact['phone']) ?></a>
            <?php if ($exportContact['email']): ?>
              <a href="mailto:<?= e($exportContact['email']) ?>" class="sales-email"><?= e($exportContact['email']) ?></a>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <!-- Contact Info Row -->
    <div class="contact-info-row">
      <div class="contact-info-block">
        <div class="contact-info-icon">📍</div>
        <div>
          <h4><?= $lang === 'bn' ? 'ঠিকানা' : 'Address' ?></h4>
          <p><?= e(setting('address_'.$lang)) ?></p>
        </div>
      </div>
      <div class="contact-info-block">
        <div class="contact-info-icon">📞</div>
        <div>
          <h4><?= $lang === 'bn' ? 'হেল্পলাইন' : 'Helpline' ?></h4>
          <p><a href="tel:<?= e(setting('helpline')) ?>"><?= e(setting('helpline')) ?></a></p>
        </div>
      </div>
      <div class="contact-info-block">
        <div class="contact-info-icon">✉️</div>
        <div>
          <h4><?= $lang === 'bn' ? 'ই-মেইল' : 'Email' ?></h4>
          <p><a href="mailto:<?= e(setting('email')) ?>"><?= e(setting('email')) ?></a></p>
        </div>
      </div>
    </div>

    <!-- Inquiry Form -->
    <div class="contact-form-section">
      <h2 class="form-section-title"><?= $lang === 'bn' ? 'বার্তা পাঠান' : 'Send Us a Message' ?></h2>

      <?php if ($success): ?>
        <div class="alert alert-success">
          <?= $lang === 'bn' ? '✅ আপনার বার্তা সফলভাবে পাঠানো হয়েছে। আমরা শীঘ্রই যোগাযোগ করব।' : '✅ Your message has been sent successfully. We will get back to you soon.' ?>
        </div>
      <?php endif; ?>

      <?php if ($errors): ?>
        <div class="alert alert-danger">
          <ul><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul>
        </div>
      <?php endif; ?>

      <?php if (!$success): ?>
      <form method="POST" action="" class="contact-form" novalidate>
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <div class="form-row">
          <div class="form-group">
            <label for="cf_name"><?= $lang === 'bn' ? 'আপনার নাম *' : 'Your Name *' ?></label>
            <input type="text" id="cf_name" name="name" class="form-input"
                   value="<?= e($_POST['name'] ?? '') ?>" maxlength="200" required>
          </div>
          <div class="form-group">
            <label for="cf_phone"><?= $lang === 'bn' ? 'ফোন নম্বর' : 'Phone Number' ?></label>
            <input type="tel" id="cf_phone" name="phone" class="form-input"
                   value="<?= e($_POST['phone'] ?? '') ?>" maxlength="80">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label for="cf_email"><?= $lang === 'bn' ? 'ই-মেইল' : 'Email Address' ?></label>
            <input type="email" id="cf_email" name="email" class="form-input"
                   value="<?= e($_POST['email'] ?? '') ?>" maxlength="150">
          </div>
          <div class="form-group">
            <label for="cf_subject"><?= $lang === 'bn' ? 'বিষয় *' : 'Subject *' ?></label>
            <input type="text" id="cf_subject" name="subject" class="form-input"
                   value="<?= e($_POST['subject'] ?? '') ?>" maxlength="300" required>
          </div>
        </div>
        <div class="form-group">
          <label for="cf_message"><?= $lang === 'bn' ? 'আপনার বার্তা *' : 'Your Message *' ?></label>
          <textarea id="cf_message" name="message" class="form-textarea" rows="6" required><?= e($_POST['message'] ?? '') ?></textarea>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn btn-primary btn-lg">
            <?= $lang === 'bn' ? 'বার্তা পাঠান' : 'Send Message' ?>
          </button>
        </div>
      </form>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php require_once __DIR__ . '/partials/footer.php'; ?>
