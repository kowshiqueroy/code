<?php
/**
 * OVIJAT GROUP — admin/settings.php
 * Site-wide settings: logo, name, tagline, contact, social, defaults.
 */
require_once __DIR__ . '/auth.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $fields = [
        'site_name_en','site_name_bn','site_tagline_en','site_tagline_bn',
        'helpline','email','address_en','address_bn',
        'footer_about_en','footer_about_bn',
        'facebook','linkedin','youtube',
        'default_lang','ticker_enabled',
        'meta_keywords','meta_description',
    ];
    $stmt = db()->prepare("INSERT INTO settings (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=?");
    foreach ($fields as $f) {
        $val = sanitizeText($_POST[$f] ?? '');
        $stmt->execute([$f, $val, $val]);
    }

    // Logo upload
    if (!empty($_FILES['logo']['name'])) {
        $newLogo = processUploadedImage($_FILES['logo'], 'logo', 'logos', setting('logo'));
        if ($newLogo) {
            db()->prepare("INSERT INTO settings (`key`,`value`) VALUES ('logo',?) ON DUPLICATE KEY UPDATE `value`=?")->execute([$newLogo, $newLogo]);
        }
    }

    flash('Settings saved successfully.', 'success');
    redirect(SITE_URL . '/admin/settings.php');
}

require_once __DIR__ . '/partials/admin_header.php';
?>
<div class="admin-page-header">
  <h1>⚙️ Site Settings</h1>
  <p>Control all global site content, branding, and configuration.</p>
</div>

<form method="POST" enctype="multipart/form-data" class="admin-form">
  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

  <!-- Branding -->
  <div class="admin-panel">
    <h2 class="admin-section-title">Branding & Identity</h2>
    <div class="form-row">
      <div class="form-group">
        <label>Site Name (English)</label>
        <input type="text" name="site_name_en" class="form-input" value="<?= e(setting('site_name_en')) ?>" required>
      </div>
      <div class="form-group">
        <label>Site Name (বাংলা)</label>
        <input type="text" name="site_name_bn" class="form-input" value="<?= e(setting('site_name_bn')) ?>">
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Tagline (English)</label>
        <input type="text" name="site_tagline_en" class="form-input" value="<?= e(setting('site_tagline_en')) ?>">
      </div>
      <div class="form-group">
        <label>Tagline (বাংলা)</label>
        <input type="text" name="site_tagline_bn" class="form-input" value="<?= e(setting('site_tagline_bn')) ?>">
      </div>
    </div>
    <div class="form-group">
      <label>Logo (Recommended: 400×160px, PNG/WebP)</label>
      <?php $logo = setting('logo'); if ($logo): ?>
        <div class="current-media">
          <img src="<?= imgUrl($logo,'logos','placeholder') ?>" alt="Current Logo" style="max-height:60px;background:#f5f5f5;padding:8px;border-radius:6px;">
          <small>Current logo. Upload new to replace.</small>
        </div>
      <?php endif; ?>
      <input type="file" name="logo" class="form-input" accept="image/*">
      <small class="form-hint">Will be auto-resized to 400×160px</small>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Default Language</label>
        <select name="default_lang" class="form-input">
          <option value="en" <?= setting('default_lang') === 'en' ? 'selected' : '' ?>>English</option>
          <option value="bn" <?= setting('default_lang') === 'bn' ? 'selected' : '' ?>>বাংলা</option>
        </select>
      </div>
      <div class="form-group">
        <label>News Ticker Enabled</label>
        <select name="ticker_enabled" class="form-input">
          <option value="1" <?= setting('ticker_enabled') === '1' ? 'selected' : '' ?>>Yes — Enabled</option>
          <option value="0" <?= setting('ticker_enabled') === '0' ? 'selected' : '' ?>>No — Disabled</option>
        </select>
      </div>
    </div>
  </div>

  <!-- Contact -->
  <div class="admin-panel">
    <h2 class="admin-section-title">Contact Information</h2>
    <div class="form-row">
      <div class="form-group">
        <label>Helpline Number</label>
        <input type="text" name="helpline" class="form-input" value="<?= e(setting('helpline')) ?>">
      </div>
      <div class="form-group">
        <label>Email Address</label>
        <input type="email" name="email" class="form-input" value="<?= e(setting('email')) ?>">
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Address (English)</label>
        <textarea name="address_en" class="form-textarea" rows="3"><?= e(setting('address_en')) ?></textarea>
      </div>
      <div class="form-group">
        <label>Address (বাংলা)</label>
        <textarea name="address_bn" class="form-textarea" rows="3"><?= e(setting('address_bn')) ?></textarea>
      </div>
    </div>
  </div>

  <!-- Footer About -->
  <div class="admin-panel">
    <h2 class="admin-section-title">Footer About Text</h2>
    <div class="form-row">
      <div class="form-group">
        <label>Footer About (English)</label>
        <textarea name="footer_about_en" class="form-textarea" rows="4"><?= e(setting('footer_about_en')) ?></textarea>
      </div>
      <div class="form-group">
        <label>Footer About (বাংলা)</label>
        <textarea name="footer_about_bn" class="form-textarea" rows="4"><?= e(setting('footer_about_bn')) ?></textarea>
      </div>
    </div>
  </div>

  <!-- Social -->
  <div class="admin-panel">
    <h2 class="admin-section-title">Social Media Links</h2>
    <div class="form-row">
      <div class="form-group">
        <label>Facebook URL</label>
        <input type="url" name="facebook" class="form-input" value="<?= e(setting('facebook')) ?>">
      </div>
      <div class="form-group">
        <label>LinkedIn URL</label>
        <input type="url" name="linkedin" class="form-input" value="<?= e(setting('linkedin')) ?>">
      </div>
      <div class="form-group">
        <label>YouTube URL</label>
        <input type="url" name="youtube" class="form-input" value="<?= e(setting('youtube')) ?>">
      </div>
    </div>
  </div>

  <!-- SEO -->
  <div class="admin-panel">
    <h2 class="admin-section-title">SEO / Meta Tags</h2>
    <div class="form-group">
      <label>Meta Keywords</label>
      <input type="text" name="meta_keywords" class="form-input" value="<?= e(setting('meta_keywords')) ?>">
    </div>
    <div class="form-group">
      <label>Meta Description</label>
      <textarea name="meta_description" class="form-textarea" rows="3"><?= e(setting('meta_description')) ?></textarea>
    </div>
  </div>

  <div class="form-actions sticky-actions">
    <button type="submit" class="btn btn-primary btn-lg">💾 Save All Settings</button>
  </div>
</form>

<?php require_once __DIR__ . '/partials/admin_footer.php'; ?>
