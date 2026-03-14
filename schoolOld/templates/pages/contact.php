<?php
/**
 * Contact Us Page
 */
$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_submit'])) {
  $name    = sanitize($_POST['name'] ?? '');
  $email   = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
  $phone   = sanitize($_POST['phone'] ?? '');
  $subject = sanitize($_POST['subject'] ?? '');
  $message = sanitize($_POST['message'] ?? '');

  if (!$name || !$message) {
    $error_msg = t('Please fill in your name and message.','নাম এবং বার্তা পূরণ করুন।');
  } elseif ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error_msg = t('Invalid email address.','অবৈধ ইমেইল ঠিকানা।');
  } else {
    try {
      db()->prepare("INSERT INTO contact_messages (name,email,phone,subject,message) VALUES (?,?,?,?,?)")
         ->execute([$name,$email,$phone,$subject,$message]);
      $success_msg = t('Your message has been sent. We will respond soon.','আপনার বার্তা পাঠানো হয়েছে। আমরা শীঘ্রই জবাব দেব।');
    } catch(Exception $e) {
      $error_msg = t('Failed to send message. Please try again.','বার্তা পাঠাতে ব্যর্থ হয়েছে।');
    }
  }
}
?>

<div class="breadcrumb">
  <div class="container">
    <ol>
      <li class="active"><?= t('Contact Us','যোগাযোগ') ?></li>
    </ol>
  </div>
</div>

<section class="section">
  <div class="container">
    <div class="section-header">
      <h1 style="font-size:1.9rem;font-weight:800;color:var(--primary);padding-bottom:12px;position:relative;display:inline-block">
        <?= t('Contact Us','যোগাযোগ করুন') ?>
        <span style="position:absolute;bottom:-3px;left:0;width:60px;height:3px;background:var(--accent);border-radius:2px"></span>
      </h1>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:48px">
      <!-- Contact Info -->
      <div>
        <h2 style="font-size:1.2rem;font-weight:700;color:var(--primary);margin-bottom:24px"><?= t('Get in Touch','যোগাযোগের তথ্য') ?></h2>

        <?php
        $contact_items = [
          ['icon'=>'📍','label'=>t('Address','ঠিকানা'),'value'=>t(get_setting('site_address_en'),get_setting('site_address_bn')),'link'=>null],
          ['icon'=>'📞','label'=>t('Phone','ফোন'),'value'=>get_setting('site_phone'),'link'=>'tel:'.preg_replace('/[^0-9+]/', '', get_setting('site_phone'))],
          ['icon'=>'✉️','label'=>t('Email','ইমেইল'),'value'=>get_setting('site_email'),'link'=>'mailto:'.get_setting('site_email')],
        ];
        ?>

        <div style="display:flex;flex-direction:column;gap:20px">
          <?php foreach ($contact_items as $item): ?>
          <?php if (!$item['value']) continue; ?>
          <div style="display:flex;gap:16px;align-items:flex-start">
            <div style="width:48px;height:48px;background:var(--primary-light);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0">
              <?= $item['icon'] ?>
            </div>
            <div>
              <div style="font-size:.8rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px"><?= h($item['label']) ?></div>
              <?php if ($item['link']): ?>
              <a href="<?= h($item['link']) ?>" style="color:var(--primary);font-weight:600"><?= h($item['value']) ?></a>
              <?php else: ?>
              <div style="font-weight:500"><?= h($item['value']) ?></div>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- Map -->
        <?php $map = get_setting('google_map_embed'); if ($map): ?>
        <div style="margin-top:32px;border-radius:var(--radius-lg);overflow:hidden;border:2px solid var(--border)">
          <?= $map ?>
        </div>
        <?php else: ?>
        <div style="margin-top:32px;background:var(--primary-light);border-radius:var(--radius-lg);padding:40px;text-align:center;color:var(--primary)">
          <div style="font-size:2.5rem;margin-bottom:8px">🗺️</div>
          <div style="font-weight:600"><?= t('Map coming soon','ম্যাপ শীঘ্রই আসছে') ?></div>
          <div style="font-size:.82rem;color:var(--text-muted);margin-top:4px"><?= t('Admin can add Google Map embed code in Settings','অ্যাডমিন সেটিংসে গুগল ম্যাপ এম্বেড যোগ করতে পারেন') ?></div>
        </div>
        <?php endif; ?>
      </div>

      <!-- Contact Form -->
      <div>
        <h2 style="font-size:1.2rem;font-weight:700;color:var(--primary);margin-bottom:24px"><?= t('Send a Message','বার্তা পাঠান') ?></h2>

        <?php if ($success_msg): ?>
        <div class="alert alert-success">✅ <?= h($success_msg) ?></div>
        <?php endif; ?>
        <?php if ($error_msg): ?>
        <div class="alert alert-error">⚠️ <?= h($error_msg) ?></div>
        <?php endif; ?>

        <form method="POST" class="contact-form" id="contactForm">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
            <div class="form-group">
              <label for="name"><?= t('Your Name','আপনার নাম') ?> *</label>
              <input type="text" id="name" name="name" required placeholder="<?= t('Full name','পূর্ণ নাম') ?>">
            </div>
            <div class="form-group">
              <label for="phone"><?= t('Phone','ফোন') ?></label>
              <input type="tel" id="phone" name="phone" placeholder="01XXXXXXXXX">
            </div>
          </div>
          <div class="form-group">
            <label for="email"><?= t('Email Address','ইমেইল ঠিকানা') ?></label>
            <input type="email" id="email" name="email" placeholder="your@email.com">
          </div>
          <div class="form-group">
            <label for="subject"><?= t('Subject','বিষয়') ?></label>
            <input type="text" id="subject" name="subject" placeholder="<?= t('Message subject','বার্তার বিষয়') ?>">
          </div>
          <div class="form-group">
            <label for="message"><?= t('Message','বার্তা') ?> *</label>
            <textarea id="message" name="message" required placeholder="<?= t('Write your message here...','এখানে আপনার বার্তা লিখুন...') ?>"></textarea>
          </div>
          <button type="submit" name="contact_submit" class="btn btn-primary btn-block">
            ✉️ <?= t('Send Message','বার্তা পাঠান') ?>
          </button>
        </form>
      </div>
    </div>

    <!-- Page content from CMS -->
    <?php if (!empty($page_data['content_en']) || !empty($page_data['content_bn'])): ?>
    <div class="page-content" style="margin-top:48px;padding-top:40px;border-top:1px solid var(--border)">
      <?= $page_data[LANG==='bn'?'content_bn':'content_en'] ?? $page_data['content_en'] ?? '' ?>
    </div>
    <?php endif; ?>
  </div>
</section>
