<?php
// admin/pages/settings.php
$db  = getDB();
$tab = $_GET['tab'] ?? 'general';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $group = $_POST['settings_group'] ?? 'general';
    foreach ($_POST as $key => $value) {
        if ($key === 'settings_group' || $key === 'csrf') continue;
        if (is_array($value)) continue;
        setSetting($key, sanitize_setting($value), $group);
    }

    // Handle logo/favicon uploads
    foreach (['logo' => 'logo', 'favicon' => 'logo'] as $field => $mode) {
        if (!empty($_FILES[$field]['name'])) {
            $up = handleUpload($field, 'site', $mode);
            if (isset($up['filename'])) {
                setSetting($field, $up['filename'], 'design');
            }
        }
    }

    flash('Settings saved successfully.', 'success');
    redirect(ADMIN_PATH . '?section=settings&tab=' . $tab);
}

function sanitize_setting(string $v): string {
    // Allow some HTML in certain fields (map embeds)
    return trim($v);
}

// Load all settings
$stmt = $db->query("SELECT `key`,`value` FROM settings");
$s = array_column($stmt->fetchAll(), 'value', 'key');
function sv(string $key, string $default = ''): string {
    global $s; return h($s[$key] ?? $default);
}
?>

<div class="atabs">
  <button class="atab-btn <?= $tab==='general'?'active':'' ?>" onclick="window.location='?section=settings&tab=general'" type="button">🏫 General</button>
  <button class="atab-btn <?= $tab==='design'?'active':'' ?>" onclick="window.location='?section=settings&tab=design'" type="button">🎨 Design</button>
  <button class="atab-btn <?= $tab==='contact'?'active':'' ?>" onclick="window.location='?section=settings&tab=contact'" type="button">📍 Contact</button>
  <button class="atab-btn <?= $tab==='display'?'active':'' ?>" onclick="window.location='?section=settings&tab=display'" type="button">👁️ Display</button>
  <button class="atab-btn <?= $tab==='social'?'active':'' ?>" onclick="window.location='?section=settings&tab=social'" type="button">🌐 Social</button>
</div>

<div class="acard">
  <div class="acard-header"><div class="acard-title">⚙️ <?= ucfirst($tab) ?> Settings</div></div>
  <div class="acard-body">
    <form method="POST" enctype="multipart/form-data" class="aform">
      <input type="hidden" name="settings_group" value="<?= h($tab) ?>">

      <?php if ($tab === 'general'): ?>
      <div class="form-row">
        <div class="form-group"><label>Institute Name (English)</label><input type="text" name="site_name_en" value="<?= sv('site_name_en') ?>"></div>
        <div class="form-group"><label>প্রতিষ্ঠানের নাম (বাংলা)</label><input type="text" name="site_name_bn" value="<?= sv('site_name_bn') ?>"></div>
        <div class="form-group"><label>Tagline (English)</label><input type="text" name="site_tagline_en" value="<?= sv('site_tagline_en') ?>"></div>
        <div class="form-group"><label>ট্যাগলাইন (বাংলা)</label><input type="text" name="site_tagline_bn" value="<?= sv('site_tagline_bn') ?>"></div>
        <div class="form-group"><label>Established Year</label><input type="text" name="established_year" value="<?= sv('established_year') ?>" placeholder="1975"></div>
        <div class="form-group"><label>EIIN Number</label><input type="text" name="eiin_number" value="<?= sv('eiin_number') ?>"></div>
        <div class="form-group"><label>Institute Code</label><input type="text" name="institute_code" value="<?= sv('institute_code') ?>"></div>
        <div class="form-group"><label>Institute Type</label>
          <select name="institute_type">
            <?php foreach (['school'=>'School','college'=>'College','school_college'=>'School & College','madrasa'=>'Madrasa','technical'=>'Technical'] as $v=>$l): ?>
            <option value="<?= $v ?>" <?= sv('institute_type')===$v?'selected':'' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label>Default Language</label>
          <select name="default_lang">
            <option value="bn" <?= sv('default_lang')==='bn'?'selected':'' ?>>বাংলা (Bengali)</option>
            <option value="en" <?= sv('default_lang')==='en'?'selected':'' ?>>English</option>
          </select>
        </div>
        <div class="form-group"><label>Developer Name</label><input type="text" name="developer_name" value="<?= sv('developer_name') ?>"></div>
        <div class="form-group"><label>Developer URL</label><input type="url" name="developer_url" value="<?= sv('developer_url') ?>"></div>
      </div>

      <?php elseif ($tab === 'design'): ?>
      <div class="form-row">
        <div class="form-group">
          <label>Logo <span class="hint">(Recommended: transparent PNG, at least 200px height)</span></label>
          <input type="file" name="logo" accept="image/*" data-preview="logo_preview">
          <?php if (sv('logo')): ?><img id="logo_preview" src="<?php echo "../uploads/images/" . $s['logo'] ; ?>" style="height:60px;margin-top:8px;border-radius:6px;border:1px solid var(--border)"><?php else: ?><img id="logo_preview" src="" style="display:none;height:60px;margin-top:8px"><?php endif; ?>
        </div>
        <div class="form-group">
          <label>Favicon</label>
          <input type="file" name="favicon" accept="image/*" data-preview="favicon_preview">
          <?php if (sv('favicon')): ?><img id="favicon_preview" src="<?php echo "../uploads/images/" . $s['favicon'] ; ?>" style="width:32px;height:32px;margin-top:8px"><?php else: ?><img id="favicon_preview" src="" style="display:none;width:32px;height:32px;margin-top:8px"><?php endif; ?>
        </div>
        <div class="form-group">
          <label>Primary Color</label>
          <input type="color" name="primary_color" value="<?= sv('primary_color','#006a4e') ?>" style="width:80px;height:40px;padding:4px">
          <span class="hint">Main brand color (default: government green #006a4e)</span>
        </div>
        <div class="form-group">
          <label>Secondary Color</label>
          <input type="color" name="secondary_color" value="<?= sv('secondary_color','#f42a41') ?>" style="width:80px;height:40px;padding:4px">
          <span class="hint">Accent color (default: Bangladesh red #f42a41)</span>
        </div>
        <div class="form-group">
          <label>Base Font Size</label>
          <select name="font_size">
            <option value="small" <?= sv('font_size')==='small'?'selected':'' ?>>Small (14px)</option>
            <option value="medium" <?= sv('font_size','medium')==='medium'?'selected':'' ?>>Medium (16px)</option>
            <option value="large" <?= sv('font_size')==='large'?'selected':'' ?>>Large (18px)</option>
          </select>
        </div>
      </div>

      <?php elseif ($tab === 'contact'): ?>
      <div class="form-row">
        <div class="form-group form-full"><label>Address (English)</label><textarea name="address_en" rows="3"><?= sv('address_en') ?></textarea></div>
        <div class="form-group form-full"><label>ঠিকানা (বাংলা)</label><textarea name="address_bn" rows="3"><?= sv('address_bn') ?></textarea></div>
        <div class="form-group"><label>Phone</label><input type="tel" name="phone" value="<?= sv('phone') ?>"></div>
        <div class="form-group"><label>Email</label><input type="email" name="email" value="<?= sv('email') ?>"></div>
        <div class="form-group form-full">
          <label>Google Map Embed Code <span class="hint">(Paste full &lt;iframe&gt; code from Google Maps → Share → Embed)</span></label>
          <textarea name="google_map_embed" rows="4" placeholder='<iframe src="https://www.google.com/maps/embed?..." ...></iframe>'><?= sv('google_map_embed') ?></textarea>
        </div>
      </div>

      <?php elseif ($tab === 'display'): ?>
      <div style="display:flex;flex-direction:column;gap:16px">
        <?php $toggles = [
          'show_news_ticker' => 'Show News Ticker Bar',
          'show_banner'      => 'Show Banner Slider on Home',
          'show_principal_msg'=> "Show Principal's Message",
          'show_honorees'    => 'Show Honorees Section',
          'show_notices'     => 'Show Notices on Home',
          'show_gallery'     => 'Show Gallery on Home',
        ]; foreach ($toggles as $key => $label): ?>
        <label style="display:flex;align-items:center;gap:12px;cursor:pointer;font-size:.9rem;font-weight:600">
          <label class="toggle">
            <input type="checkbox" class="status-toggle" name="<?= $key ?>" value="1" <?= ($s[$key] ?? '1')==='1'?'checked':'' ?>>
            <span class="slider-s"></span>
          </label>
          <?= $label ?>
        </label>
        <?php endforeach; ?>
      </div>

      <?php elseif ($tab === 'social'): ?>
      <div class="form-row">
        <div class="form-group"><label>Facebook Page URL</label><input type="url" name="facebook_url" value="<?= sv('facebook_url') ?>" placeholder="https://facebook.com/..."></div>
        <div class="form-group"><label>YouTube Channel URL</label><input type="url" name="youtube_url" value="<?= sv('youtube_url') ?>" placeholder="https://youtube.com/..."></div>
      </div>
      <?php endif; ?>

      <div style="margin-top:24px;display:flex;gap:10px">
        <button type="submit" class="btn btn-primary">💾 Save Settings</button>
        <a href="?section=dashboard" class="btn btn-light">Cancel</a>
      </div>
    </form>
  </div>
</div>
