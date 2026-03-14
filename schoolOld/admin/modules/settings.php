<?php
/**
 * Admin Settings Module — Site-wide configuration
 */
$admin_title = 'Settings';
require_role('admin');

$msg    = '';
$errors = [];
$tab    = preg_replace('/[^a-z]/', '', $_GET['tab'] ?? 'general');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings_to_save = [];

    // General
    if (isset($_POST['site_name_en'])) {
        $fields = ['site_name_en','site_name_bn','site_tagline_en','site_tagline_bn','site_email','site_phone','site_address_en','site_address_bn','established_year','eiin_number','institute_code','institute_type','default_language','maintenance_mode','total_students','total_teachers','pass_rate','facebook_url','google_map_embed','google_analytics','custom_header_code','custom_footer_code','primary_color','secondary_color','accent_color'];
        foreach ($fields as $f) {
            if (array_key_exists($f, $_POST)) {
                $settings_to_save[$f] = $_POST[$f];
            }
        }
    }

    // Logo upload
    if (!empty($_FILES['site_logo']['name'])) {
        $upload_dir = APP_ROOT . '/assets/uploads/logos/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $ext = strtolower(pathinfo($_FILES['site_logo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','gif','svg','webp'])) {
            $fname = 'logo_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['site_logo']['tmp_name'], $upload_dir.$fname)) {
                $settings_to_save['site_logo'] = 'logos/' . $fname;
            }
        } else {
            $errors[] = 'Invalid logo file type.';
        }
    }

    if (empty($errors)) {
        $stmt = db()->prepare("INSERT INTO settings (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=?");
        foreach ($settings_to_save as $k => $v) {
            $stmt->execute([$k, $v, $v]);
        }
        $msg = 'Settings saved successfully.';
    }
}

// Load all settings
$all_settings = [];
try {
    $rows = db()->query("SELECT `key`,`value` FROM settings")->fetchAll();
    foreach ($rows as $r) $all_settings[$r['key']] = $r['value'];
} catch(Exception $e) {}

function sv(string $key, string $default = ''): string {
    global $all_settings;
    return $all_settings[$key] ?? $default;
}
?>

<?php if ($msg): ?><div class="alert alert-success">✅ <?= h($msg) ?></div><?php endif; ?>
<?php foreach ($errors as $e): ?><div class="alert alert-error">⚠️ <?= h($e) ?></div><?php endforeach; ?>

<!-- Settings Tabs -->
<div style="display:flex;gap:4px;flex-wrap:wrap;margin-bottom:20px;border-bottom:2px solid var(--border);padding-bottom:12px">
  <?php foreach(['general'=>'🏫 General','contact'=>'📞 Contact','social'=>'🔗 Social','theme'=>'🎨 Theme','advanced'=>'⚙️ Advanced'] as $t=>$label): ?>
  <a href="/admin/?action=settings&tab=<?= $t ?>"
     class="btn btn-sm <?= $tab===$t?'btn-primary':'btn-secondary' ?>"><?= $label ?></a>
  <?php endforeach; ?>
</div>

<form method="POST" enctype="multipart/form-data">
  <input type="hidden" name="site_name_en" value="placeholder_trigger">

  <!-- ── General Tab ── -->
  <?php if ($tab === 'general'): ?>
  <div class="panel">
    <div class="panel-header"><div class="panel-title">🏫 General Settings</div></div>
    <div class="panel-body">
      <div class="form-row col-2">
        <div class="form-group">
          <label class="form-label">Institution Name (English) *</label>
          <input type="text" name="site_name_en" class="form-control" value="<?= h(sv('site_name_en')) ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Institution Name (Bangla)</label>
          <input type="text" name="site_name_bn" class="form-control" value="<?= h(sv('site_name_bn')) ?>">
        </div>
      </div>
      <div class="form-row col-2">
        <div class="form-group">
          <label class="form-label">Tagline (English)</label>
          <input type="text" name="site_tagline_en" class="form-control" value="<?= h(sv('site_tagline_en')) ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Tagline (Bangla)</label>
          <input type="text" name="site_tagline_bn" class="form-control" value="<?= h(sv('site_tagline_bn')) ?>">
        </div>
      </div>
      <div class="form-row col-3">
        <div class="form-group">
          <label class="form-label">Institute Type</label>
          <select name="institute_type" class="form-control form-select">
            <?php foreach(['school'=>'School','college'=>'College','school_college'=>'School & College','madrasha'=>'Madrasha','university'=>'University'] as $val=>$lbl): ?>
            <option value="<?= $val ?>" <?= sv('institute_type')===$val?'selected':'' ?>><?= $lbl ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Established Year</label>
          <input type="text" name="established_year" class="form-control" value="<?= h(sv('established_year')) ?>" placeholder="e.g. 1985">
        </div>
        <div class="form-group">
          <label class="form-label">Default Language</label>
          <select name="default_language" class="form-control form-select">
            <option value="en" <?= sv('default_language','en')==='en'?'selected':'' ?>>English</option>
            <option value="bn" <?= sv('default_language','en')==='bn'?'selected':'' ?>>Bangla (বাংলা)</option>
          </select>
        </div>
      </div>
      <div class="form-row col-3">
        <div class="form-group">
          <label class="form-label">EIIN Number</label>
          <input type="text" name="eiin_number" class="form-control" value="<?= h(sv('eiin_number')) ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Institute Code</label>
          <input type="text" name="institute_code" class="form-control" value="<?= h(sv('institute_code')) ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Maintenance Mode</label>
          <select name="maintenance_mode" class="form-control form-select">
            <option value="0" <?= sv('maintenance_mode')==='0'?'selected':'' ?>>Off (Site Live)</option>
            <option value="1" <?= sv('maintenance_mode')==='1'?'selected':'' ?>>On (Maintenance)</option>
          </select>
        </div>
      </div>
      <!-- Logo -->
      <div class="form-group">
        <label class="form-label">Site Logo</label>
        <?php if (sv('site_logo')): ?>
        <div style="margin-bottom:8px">
          <img src="<?= upload_url(sv('site_logo')) ?>" alt="Current Logo" style="height:60px;border:1px solid var(--border);border-radius:8px;padding:4px;background:#fff">
        </div>
        <?php endif; ?>
        <input type="file" name="site_logo" class="form-control" accept="image/*">
        <div class="form-hint">Recommended: PNG with transparent background, at least 200×200px</div>
      </div>
      <!-- Stats for homepage -->
      <div style="padding-top:16px;border-top:1px solid var(--border)">
        <h3 style="font-size:.9rem;font-weight:700;color:var(--muted);margin-bottom:12px">Homepage Statistics</h3>
        <div class="form-row" style="grid-template-columns:repeat(3,1fr)">
          <div class="form-group">
            <label class="form-label">Total Students</label>
            <input type="text" name="total_students" class="form-control" value="<?= h(sv('total_students','0')) ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Total Teachers</label>
            <input type="text" name="total_teachers" class="form-control" value="<?= h(sv('total_teachers','0')) ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Pass Rate</label>
            <input type="text" name="pass_rate" class="form-control" value="<?= h(sv('pass_rate','0%')) ?>" placeholder="e.g. 98%">
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ── Contact Tab ── -->
  <?php elseif ($tab === 'contact'): ?>
  <div class="panel">
    <div class="panel-header"><div class="panel-title">📞 Contact Information</div></div>
    <div class="panel-body">
      <div class="form-row col-2">
        <div class="form-group">
          <label class="form-label">Email Address</label>
          <input type="email" name="site_email" class="form-control" value="<?= h(sv('site_email')) ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Phone Number</label>
          <input type="text" name="site_phone" class="form-control" value="<?= h(sv('site_phone')) ?>">
        </div>
      </div>
      <div class="form-row col-2">
        <div class="form-group">
          <label class="form-label">Address (English)</label>
          <textarea name="site_address_en" class="form-control" rows="3"><?= h(sv('site_address_en')) ?></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Address (Bangla)</label>
          <textarea name="site_address_bn" class="form-control" rows="3" style="font-family:'Hind Siliguri',sans-serif"><?= h(sv('site_address_bn')) ?></textarea>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Google Map Embed Code</label>
        <textarea name="google_map_embed" class="form-control" rows="4" placeholder='Paste Google Maps &lt;iframe&gt; embed code here'><?= h(sv('google_map_embed')) ?></textarea>
        <div class="form-hint">Go to Google Maps → Share → Embed a map → Copy HTML</div>
      </div>
    </div>
  </div>

  <!-- ── Social Tab ── -->
  <?php elseif ($tab === 'social'): ?>
  <div class="panel">
    <div class="panel-header"><div class="panel-title">🔗 Social Media Links</div></div>
    <div class="panel-body">
      <div class="form-group">
        <label class="form-label">Facebook Page URL</label>
        <input type="url" name="facebook_url" class="form-control" value="<?= h(sv('facebook_url')) ?>" placeholder="https://facebook.com/yourschool">
      </div>
    </div>
  </div>

  <!-- ── Theme Tab ── -->
  <?php elseif ($tab === 'theme'): ?>
  <div class="panel">
    <div class="panel-header"><div class="panel-title">🎨 Theme Colors</div></div>
    <div class="panel-body">
      <div class="form-row col-3">
        <div class="form-group">
          <label class="form-label">Primary Color</label>
          <div style="display:flex;gap:8px;align-items:center">
            <input type="color" name="primary_color" value="<?= h(sv('primary_color','#006B3F')) ?>" style="height:42px;width:60px;cursor:pointer;border:none;padding:0">
            <input type="text" id="pc" class="form-control" value="<?= h(sv('primary_color','#006B3F')) ?>" style="font-family:monospace" oninput="document.querySelector('[name=primary_color]').value=this.value">
          </div>
          <div class="form-hint">Main brand color (recommended: Bangladesh green)</div>
        </div>
        <div class="form-group">
          <label class="form-label">Secondary Color</label>
          <div style="display:flex;gap:8px;align-items:center">
            <input type="color" name="secondary_color" value="<?= h(sv('secondary_color','#F42A41')) ?>" style="height:42px;width:60px;cursor:pointer;border:none;padding:0">
            <input type="text" class="form-control" value="<?= h(sv('secondary_color','#F42A41')) ?>" style="font-family:monospace">
          </div>
          <div class="form-hint">Accent for alerts (recommended: Bangladesh red)</div>
        </div>
        <div class="form-group">
          <label class="form-label">Accent Color</label>
          <div style="display:flex;gap:8px;align-items:center">
            <input type="color" name="accent_color" value="<?= h(sv('accent_color','#F7A600')) ?>" style="height:42px;width:60px;cursor:pointer;border:none;padding:0">
            <input type="text" class="form-control" value="<?= h(sv('accent_color','#F7A600')) ?>" style="font-family:monospace">
          </div>
          <div class="form-hint">Highlights and call-to-action buttons</div>
        </div>
      </div>
      <div style="padding:20px;background:var(--primary);border-radius:var(--radius);color:#fff;margin-top:8px">
        <strong>Preview:</strong> This is how your primary color looks on text.
        <span style="background:var(--accent);color:var(--primary);padding:4px 12px;border-radius:4px;margin-left:8px;font-weight:700">Accent Button</span>
        <span style="background:var(--secondary);color:#fff;padding:4px 12px;border-radius:4px;margin-left:8px;font-weight:700">Secondary</span>
      </div>
    </div>
  </div>

  <!-- ── Advanced Tab ── -->
  <?php elseif ($tab === 'advanced'): ?>
  <div class="panel">
    <div class="panel-header"><div class="panel-title">⚙️ Advanced / Code Injection</div></div>
    <div class="panel-body">
      <div class="alert alert-warning">⚠️ These settings are for advanced users. Incorrect code can break the site.</div>
      <div class="form-group">
        <label class="form-label">Google Analytics / Tracking Code</label>
        <textarea name="google_analytics" class="form-control" rows="4" style="font-family:monospace;font-size:.82rem" placeholder="Paste your Google Analytics &lt;script&gt; tag or gtag() code"><?= h(sv('google_analytics')) ?></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">Custom Header Code (injected in &lt;head&gt;)</label>
        <textarea name="custom_header_code" class="form-control" rows="6" style="font-family:monospace;font-size:.82rem" placeholder="/* Custom CSS */ or meta tags or additional stylesheets"><?= h(sv('custom_header_code')) ?></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">Custom Footer Code (injected before &lt;/body&gt;)</label>
        <textarea name="custom_footer_code" class="form-control" rows="6" style="font-family:monospace;font-size:.82rem" placeholder="// Custom JavaScript or third-party widget embeds"><?= h(sv('custom_footer_code')) ?></textarea>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <div style="display:flex;gap:12px;margin-top:8px">
    <button type="submit" class="btn btn-primary">💾 Save Settings</button>
    <a href="/" target="_blank" class="btn btn-secondary">🌐 Preview Site</a>
  </div>
</form>
