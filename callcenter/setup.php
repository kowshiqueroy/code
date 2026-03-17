<?php
// ============================================================
// setup.php — Database schema creator + seeder
// Run ONCE. Refresh to check status.
// ============================================================

define('ROOT',       __DIR__);
define('APP_NAME',   'Ovijat Call Center');
define('BASE_URL',   'http://localhost/code/callcenter');
define('DB_HOST',    'localhost');
define('DB_NAME',    'callcenter_db');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');

$errors = [];
$steps  = [];

// ── Connect (create DB if missing) ──────────────────────────
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';charset=' . DB_CHARSET,
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `" . DB_NAME . "`");
    $steps[] = ['ok', 'Connected to MySQL and selected database <strong>' . DB_NAME . '</strong>'];
} catch (PDOException $e) {
    die('<p style="color:red">DB connection failed: ' . htmlspecialchars($e->getMessage()) . '</p>');
}

// ── Guard: already installed? ────────────────────────────────
$tables = $pdo->query("SHOW TABLES LIKE 'users'")->fetchAll();
if (!empty($tables)) {
    // Allow re-run only with ?force=1
    if (!isset($_GET['force'])) {
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Setup</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
        </head><body class="bg-light"><div class="container py-5 text-center">
        <div class="alert alert-warning">
          <h4>Already Installed</h4>
          <p>The database <strong>' . DB_NAME . '</strong> is already set up.</p>
          <a href="' . BASE_URL . '/login.php" class="btn btn-primary me-2">Go to Login</a>
          <a href="?force=1" class="btn btn-outline-danger" onclick="return confirm(\'This will DROP all tables and re-create them. All data will be lost. Continue?\')">Force Re-install</a>
        </div></div></body></html>';
        exit;
    }
    // Drop all tables
    $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
    $all = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($all as $t) { $pdo->exec("DROP TABLE IF EXISTS `$t`"); }
    $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
    $steps[] = ['warn', 'Dropped all existing tables (force re-install)'];
}

// ── Schema ───────────────────────────────────────────────────
$schema = <<<SQL

-- ─── USERS ───────────────────────────────────────────────────
CREATE TABLE users (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(120) NOT NULL,
  email      VARCHAR(150) NOT NULL UNIQUE,
  phone      VARCHAR(20),
  password   VARCHAR(255) NOT NULL,
  role       ENUM('super_admin','senior_executive','executive','viewer') NOT NULL DEFAULT 'executive',
  status     ENUM('active','inactive') NOT NULL DEFAULT 'active',
  last_login DATETIME,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ─── SETTINGS ────────────────────────────────────────────────
CREATE TABLE settings (
  `key`      VARCHAR(80) PRIMARY KEY,
  value      TEXT,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ─── CONTACTS ────────────────────────────────────────────────
CREATE TABLE contacts (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name         VARCHAR(150) NOT NULL,
  phone        VARCHAR(25) NOT NULL,
  alt_phone    VARCHAR(25),
  email        VARCHAR(150),
  company      VARCHAR(150),
  contact_type ENUM('internal_staff','sr','asm','dsm','tsm','dealer',
                    'distributor','shop_owner','customer','other') NOT NULL DEFAULT 'customer',
  status       ENUM('active','inactive','blocked','former') NOT NULL DEFAULT 'active',
  notes        TEXT,
  assigned_to  INT UNSIGNED,
  created_by   INT UNSIGNED,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_phone (phone),
  INDEX idx_name  (name),
  INDEX idx_type  (contact_type),
  INDEX idx_status (status)
) ENGINE=InnoDB;

CREATE TABLE contact_tags (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  contact_id INT UNSIGNED NOT NULL,
  tag        VARCHAR(60) NOT NULL,
  INDEX idx_contact (contact_id),
  FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─── STAFF PROFILES ──────────────────────────────────────────
CREATE TABLE staff_profiles (
  id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  contact_id           INT UNSIGNED NOT NULL UNIQUE,
  employee_id          VARCHAR(30),
  department           VARCHAR(100),
  position             VARCHAR(100),
  join_date            DATE,
  exit_date            DATE,
  is_active            TINYINT(1) NOT NULL DEFAULT 1,
  successor_contact_id INT UNSIGNED,
  FOREIGN KEY (contact_id)           REFERENCES contacts(id) ON DELETE CASCADE,
  FOREIGN KEY (successor_contact_id) REFERENCES contacts(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE contact_position_history (
  id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  contact_id            INT UNSIGNED NOT NULL,
  position              VARCHAR(100),
  department            VARCHAR(100),
  effective_from        DATE,
  effective_to          DATE,
  replaced_by_contact_id INT UNSIGNED,
  notes                 TEXT,
  created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─── SALES NETWORK ───────────────────────────────────────────
CREATE TABLE sales_levels (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(60) NOT NULL,
  rank_order TINYINT UNSIGNED NOT NULL DEFAULT 1,
  color      VARCHAR(7) NOT NULL DEFAULT '#6c757d',
  is_active  TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE sales_groups (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name            VARCHAR(150) NOT NULL,
  level_id        INT UNSIGNED NOT NULL,
  parent_group_id INT UNSIGNED,
  region          VARCHAR(100),
  description     TEXT,
  is_active       TINYINT(1) NOT NULL DEFAULT 1,
  created_by      INT UNSIGNED,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (level_id)        REFERENCES sales_levels(id),
  FOREIGN KEY (parent_group_id) REFERENCES sales_groups(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE sales_group_members (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  group_id      INT UNSIGNED NOT NULL,
  contact_id    INT UNSIGNED NOT NULL,
  role_in_group VARCHAR(80),
  joined_date   DATE,
  left_date     DATE,
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (group_id)   REFERENCES sales_groups(id) ON DELETE CASCADE,
  FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE executive_group_assignments (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  group_id   INT UNSIGNED NOT NULL,
  assigned_by INT UNSIGNED,
  assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  is_active  TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (user_id)  REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (group_id) REFERENCES sales_groups(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─── CALL CENTER CORE ────────────────────────────────────────
CREATE TABLE call_outcomes (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name              VARCHAR(60) NOT NULL,
  color             VARCHAR(7) NOT NULL DEFAULT '#6c757d',
  requires_callback TINYINT(1) NOT NULL DEFAULT 0,
  sort_order        TINYINT UNSIGNED NOT NULL DEFAULT 0,
  is_active         TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE campaigns (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(150) NOT NULL,
  description TEXT,
  type        ENUM('inbound','outbound','mixed') NOT NULL DEFAULT 'outbound',
  status      ENUM('draft','active','paused','completed') NOT NULL DEFAULT 'draft',
  start_date  DATE,
  end_date    DATE,
  script_id   INT UNSIGNED,
  created_by  INT UNSIGNED,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE scripts (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(150) NOT NULL,
  content     TEXT NOT NULL,
  campaign_id INT UNSIGNED,
  is_default  TINYINT(1) NOT NULL DEFAULT 0,
  created_by  INT UNSIGNED,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE campaign_contacts (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  campaign_id   INT UNSIGNED NOT NULL,
  contact_id    INT UNSIGNED NOT NULL,
  assigned_to   INT UNSIGNED,
  status        ENUM('pending','called','callback','completed','skipped') NOT NULL DEFAULT 'pending',
  last_called_at DATETIME,
  FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
  FOREIGN KEY (contact_id)  REFERENCES contacts(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE calls (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  contact_id       INT UNSIGNED,
  campaign_id      INT UNSIGNED,
  agent_id         INT UNSIGNED NOT NULL,
  direction        ENUM('inbound','outbound') NOT NULL DEFAULT 'outbound',
  phone_dialed     VARCHAR(25),
  started_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ended_at         DATETIME,
  duration_seconds INT UNSIGNED DEFAULT 0,
  outcome_id       INT UNSIGNED,
  notes            TEXT,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_agent   (agent_id),
  INDEX idx_contact (contact_id),
  INDEX idx_date    (started_at),
  FOREIGN KEY (contact_id)  REFERENCES contacts(id) ON DELETE SET NULL,
  FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE SET NULL,
  FOREIGN KEY (agent_id)    REFERENCES users(id),
  FOREIGN KEY (outcome_id)  REFERENCES call_outcomes(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE call_summary (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  call_id            INT UNSIGNED NOT NULL UNIQUE,
  key_points         TEXT,
  follow_up_required TINYINT(1) NOT NULL DEFAULT 0,
  follow_up_date     DATE,
  sentiment          ENUM('positive','neutral','negative') NOT NULL DEFAULT 'neutral',
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (call_id) REFERENCES calls(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE callbacks (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  call_id      INT UNSIGNED,
  contact_id   INT UNSIGNED,
  assigned_to  INT UNSIGNED NOT NULL,
  scheduled_at DATETIME NOT NULL,
  notes        TEXT,
  status       ENUM('pending','completed','missed','cancelled') NOT NULL DEFAULT 'pending',
  created_by   INT UNSIGNED,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_assigned (assigned_to),
  INDEX idx_scheduled (scheduled_at),
  FOREIGN KEY (call_id)    REFERENCES calls(id) ON DELETE SET NULL,
  FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE sms_log (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  contact_id  INT UNSIGNED,
  agent_id    INT UNSIGNED NOT NULL,
  phone_to    VARCHAR(25) NOT NULL,
  message     TEXT NOT NULL,
  status      ENUM('queued','sent','failed','delivered') NOT NULL DEFAULT 'queued',
  gateway_ref VARCHAR(100),
  sent_at     DATETIME,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_agent   (agent_id),
  INDEX idx_contact (contact_id),
  FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE SET NULL,
  FOREIGN KEY (agent_id)   REFERENCES users(id)
) ENGINE=InnoDB;

-- ─── FEEDBACK / TICKETS ──────────────────────────────────────
CREATE TABLE feedback_threads (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  contact_id          INT UNSIGNED NOT NULL,
  title               VARCHAR(200) NOT NULL,
  problem_description TEXT,
  status              ENUM('open','in_progress','resolved','closed') NOT NULL DEFAULT 'open',
  priority            ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
  assigned_to         INT UNSIGNED,
  created_by          INT UNSIGNED NOT NULL,
  resolved_at         DATETIME,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_contact (contact_id),
  INDEX idx_status  (status),
  FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE feedback_entries (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  thread_id  INT UNSIGNED NOT NULL,
  agent_id   INT UNSIGNED NOT NULL,
  entry_type ENUM('feedback','update','solution','follow_up','note') NOT NULL DEFAULT 'note',
  content    TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (thread_id) REFERENCES feedback_threads(id) ON DELETE CASCADE,
  FOREIGN KEY (agent_id)  REFERENCES users(id)
) ENGINE=InnoDB;

-- ─── TASKS & WORK ────────────────────────────────────────────
CREATE TABLE task_types (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name              VARCHAR(80) NOT NULL,
  icon              VARCHAR(40) NOT NULL DEFAULT 'bi-check-square',
  color             VARCHAR(7) NOT NULL DEFAULT '#0d6efd',
  is_self_assignable TINYINT(1) NOT NULL DEFAULT 1,
  sort_order        TINYINT UNSIGNED NOT NULL DEFAULT 0,
  is_active         TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE tasks (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title       VARCHAR(200) NOT NULL,
  description TEXT,
  type_id     INT UNSIGNED,
  assigned_to INT UNSIGNED NOT NULL,
  assigned_by INT UNSIGNED,
  priority    ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
  status      ENUM('pending','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending',
  due_date    DATETIME,
  completed_at DATETIME,
  contact_id  INT UNSIGNED,
  group_id    INT UNSIGNED,
  notes       TEXT,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_assigned (assigned_to),
  INDEX idx_status   (status),
  FOREIGN KEY (type_id)    REFERENCES task_types(id) ON DELETE SET NULL,
  FOREIGN KEY (assigned_to) REFERENCES users(id),
  FOREIGN KEY (contact_id)  REFERENCES contacts(id) ON DELETE SET NULL,
  FOREIGN KEY (group_id)    REFERENCES sales_groups(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ─── ATTENDANCE & HR ─────────────────────────────────────────
CREATE TABLE attendance (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  date        DATE NOT NULL,
  check_in    DATETIME,
  check_out   DATETIME,
  work_mode   ENUM('office','wfh','field','half_day') NOT NULL DEFAULT 'office',
  total_hours DECIMAL(4,2) DEFAULT 0.00,
  notes       TEXT,
  status      ENUM('present','absent','leave','holiday') NOT NULL DEFAULT 'present',
  approved_by INT UNSIGNED,
  UNIQUE KEY uq_user_date (user_id, date),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE leave_types (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name           VARCHAR(80) NOT NULL,
  days_per_year  TINYINT UNSIGNED NOT NULL DEFAULT 10,
  color          VARCHAR(7) NOT NULL DEFAULT '#198754',
  is_active      TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE leaves (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id        INT UNSIGNED NOT NULL,
  leave_type_id  INT UNSIGNED NOT NULL,
  start_date     DATE NOT NULL,
  end_date       DATE NOT NULL,
  days           TINYINT UNSIGNED NOT NULL DEFAULT 1,
  reason         TEXT,
  status         ENUM('pending','referred','approved','rejected') NOT NULL DEFAULT 'pending',
  referred_by    INT UNSIGNED,
  referred_at    DATETIME,
  approved_by    INT UNSIGNED,
  approved_at    DATETIME,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id)       REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (leave_type_id) REFERENCES leave_types(id),
  FOREIGN KEY (referred_by)   REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (approved_by)   REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE screen_activity (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id        INT UNSIGNED NOT NULL,
  module         VARCHAR(60),
  session_start  DATETIME NOT NULL,
  session_end    DATETIME,
  duration_seconds INT UNSIGNED DEFAULT 0,
  date           DATE NOT NULL,
  INDEX idx_user_date (user_id, date),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─── REPORTS ─────────────────────────────────────────────────
CREATE TABLE report_templates (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name            VARCHAR(150) NOT NULL,
  description     TEXT,
  source_modules  JSON,
  columns         JSON,
  filters         JSON,
  input_fields    JSON,
  grouping        VARCHAR(60),
  sort_by         VARCHAR(60),
  visibility      ENUM('private','shared','public') NOT NULL DEFAULT 'private',
  created_by      INT UNSIGNED NOT NULL,
  is_active       TINYINT(1) NOT NULL DEFAULT 1,
  current_version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE report_template_permissions (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  template_id INT UNSIGNED NOT NULL,
  user_id     INT UNSIGNED NOT NULL,
  permission  ENUM('view','edit') NOT NULL DEFAULT 'view',
  granted_by  INT UNSIGNED,
  granted_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tpl_user (template_id, user_id),
  FOREIGN KEY (template_id) REFERENCES report_templates(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id)     REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE report_template_versions (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  template_id    INT UNSIGNED NOT NULL,
  version_number SMALLINT UNSIGNED NOT NULL,
  snapshot       JSON,
  changed_by     INT UNSIGNED,
  change_note    VARCHAR(255),
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (template_id) REFERENCES report_templates(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE report_runs (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  template_id      INT UNSIGNED,
  run_by           INT UNSIGNED NOT NULL,
  filter_overrides JSON,
  input_data       JSON,
  result_snapshot  JSON,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (template_id) REFERENCES report_templates(id) ON DELETE SET NULL,
  FOREIGN KEY (run_by)      REFERENCES users(id)
) ENGINE=InnoDB;

-- ─── OFFLINE SYNC ────────────────────────────────────────────
CREATE TABLE offline_sync_log (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  data_type  ENUM('call','sms','task','attendance','contact') NOT NULL,
  local_id   VARCHAR(60),
  data_json  JSON NOT NULL,
  action     ENUM('insert','update') NOT NULL DEFAULT 'insert',
  status     ENUM('pending','synced','skipped','conflict') NOT NULL DEFAULT 'synced',
  synced_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- ─── AUDIT LOGS ──────────────────────────────────────────────
CREATE TABLE audit_logs (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED,
  action      VARCHAR(60) NOT NULL,
  module      VARCHAR(60),
  record_id   INT UNSIGNED,
  description TEXT,
  ip_address  VARCHAR(45),
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user   (user_id),
  INDEX idx_module (module),
  INDEX idx_date   (created_at)
) ENGINE=InnoDB;

SQL;

// Execute schema
try {
    $pdo->exec($schema);
    $steps[] = ['ok', 'Created all database tables'];
} catch (PDOException $e) {
    $errors[] = 'Schema error: ' . $e->getMessage();
}

// ── Seed Data ────────────────────────────────────────────────
if (empty($errors)) {

    // Super admin
    $hash = password_hash('admin123', PASSWORD_BCRYPT);
    $pdo->prepare("INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, 'super_admin')")
        ->execute(['Admin', 'admin@callcenter.com', '01700000000', $hash]);
    $adminId = $pdo->lastInsertId();
    $steps[] = ['ok', 'Created super_admin user: <strong>admin@callcenter.com</strong> / admin123'];

    // Demo users
    $demoUsers = [
        ['Rahim Senior',  'senior@callcenter.com',  '01711111111', 'senior_executive'],
        ['Karim Exec',    'exec@callcenter.com',    '01722222222', 'executive'],
        ['Manager View',  'viewer@callcenter.com',  '01733333333', 'viewer'],
    ];
    $uStmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, ?)");
    foreach ($demoUsers as $u) {
        $uStmt->execute([$u[0], $u[1], $u[2], password_hash('demo123', PASSWORD_BCRYPT), $u[3]]);
    }
    $steps[] = ['ok', 'Created demo users (password: demo123): senior, exec, viewer'];

    // Call outcomes
    $outcomes = [
        ['Answered',         '#198754', 0, 1],
        ['Missed',           '#dc3545', 0, 2],
        ['Voicemail',        '#6c757d', 0, 3],
        ['Callback Requested','#fd7e14', 1, 4],
        ['Wrong Number',     '#adb5bd', 0, 5],
        ['Busy',             '#ffc107', 0, 6],
        ['Do Not Call',      '#343a40', 0, 7],
        ['Interested',       '#0dcaf0', 0, 8],
        ['Not Interested',   '#6c757d', 0, 9],
    ];
    $oStmt = $pdo->prepare("INSERT INTO call_outcomes (name, color, requires_callback, sort_order) VALUES (?, ?, ?, ?)");
    foreach ($outcomes as $o) $oStmt->execute($o);
    $steps[] = ['ok', 'Created ' . count($outcomes) . ' default call outcomes'];

    // Task types
    $taskTypes = [
        ['Phone Call',      'bi-telephone-fill',   '#0d6efd', 1, 1],
        ['SMS',             'bi-chat-fill',         '#198754', 1, 2],
        ['Facebook Message','bi-facebook',          '#0866FF', 1, 3],
        ['FB Comment',      'bi-chat-square-text', '#0866FF', 1, 4],
        ['WhatsApp',        'bi-whatsapp',          '#25D366', 1, 5],
        ['Meeting',         'bi-people-fill',       '#6f42c1', 1, 6],
        ['Site Visit',      'bi-geo-alt-fill',      '#fd7e14', 1, 7],
        ['Email Follow-up', 'bi-envelope-fill',     '#0dcaf0', 1, 8],
        ['Requisition',     'bi-file-earmark-text', '#dc3545', 0, 9],
        ['Other',           'bi-three-dots',        '#6c757d', 1, 10],
    ];
    $tStmt = $pdo->prepare("INSERT INTO task_types (name, icon, color, is_self_assignable, sort_order) VALUES (?, ?, ?, ?, ?)");
    foreach ($taskTypes as $t) $tStmt->execute($t);
    $steps[] = ['ok', 'Created ' . count($taskTypes) . ' default task types'];

    // Leave types
    $leaveTypes = [
        ['Sick Leave',    10, '#dc3545'],
        ['Casual Leave',  12, '#fd7e14'],
        ['Annual Leave',  15, '#198754'],
        ['Half Day',      0,  '#0dcaf0'],
        ['Emergency',     3,  '#6f42c1'],
    ];
    $lStmt = $pdo->prepare("INSERT INTO leave_types (name, days_per_year, color) VALUES (?, ?, ?)");
    foreach ($leaveTypes as $l) $lStmt->execute($l);
    $steps[] = ['ok', 'Created ' . count($leaveTypes) . ' default leave types'];

    // Sales levels
    $levels = [
        ['TSM (Territory Sales Manager)', 1, '#dc3545'],
        ['DSM (District Sales Manager)',  2, '#fd7e14'],
        ['ASM (Area Sales Manager)',      3, '#ffc107'],
        ['SR (Sales Representative)',     4, '#0d6efd'],
        ['Dealer',                        5, '#198754'],
        ['Distributor',                   6, '#0dcaf0'],
        ['Shop Owner',                    7, '#6c757d'],
    ];
    $slStmt = $pdo->prepare("INSERT INTO sales_levels (name, rank_order, color) VALUES (?, ?, ?)");
    foreach ($levels as $sl) $slStmt->execute($sl);
    $steps[] = ['ok', 'Created ' . count($levels) . ' default sales levels (can be renamed/reordered)'];

    // Settings
    $settingsData = [
        ['company_name',      'Ovijat Group'],
        ['company_address',   'Dhaka, Bangladesh'],
        ['company_phone',     '01700000000'],
        ['company_email',     'info@ovijat.com'],
        ['sms_gateway',       ''],
        ['sms_api_key',       ''],
        ['sms_sender_id',     'OvijatCC'],
        ['session_timeout',   '300'],
        ['theme_color',       '#1e2330'],
        ['app_name',          'Ovijat Call Center'],
    ];
    $stStmt = $pdo->prepare("INSERT INTO settings (`key`, value) VALUES (?, ?)");
    foreach ($settingsData as $s) $stStmt->execute($s);
    $steps[] = ['ok', 'Inserted default settings'];

    // Demo script
    $pdo->prepare("INSERT INTO scripts (name, content, is_default, created_by) VALUES (?, ?, 1, ?)")
        ->execute([
            'Standard Outbound Script',
            "Hello, may I speak with [Name]?\n\nGood [morning/afternoon/evening], this is [Your Name] calling from Ovijat Group.\n\nI'm calling to [purpose of call].\n\n[Key talking points]\n1. ...\n2. ...\n\nIs now a good time to talk?\n\n[Handle objections]\n\nThank you for your time. Have a great day!",
            $adminId
        ]);
    $steps[] = ['ok', 'Created default call script'];
}

// ── Output ───────────────────────────────────────────────────
$allOk = empty($errors);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Setup — Ovijat Call Center</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
<div class="container py-5" style="max-width:680px">
  <div class="text-center mb-4">
    <h2 class="fw-bold">Ovijat Call Center</h2>
    <p class="text-muted">Database Setup</p>
  </div>

  <?php if ($allOk): ?>
  <div class="alert alert-success d-flex align-items-center">
    <i class="bi bi-check-circle-fill fs-4 me-3"></i>
    <div><strong>Setup complete!</strong> Database <code><?= DB_NAME ?></code> is ready.</div>
  </div>
  <?php else: ?>
  <div class="alert alert-danger">
    <strong>Errors occurred:</strong>
    <ul class="mb-0 mt-1"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach ?></ul>
  </div>
  <?php endif ?>

  <div class="card mb-4">
    <div class="card-header fw-semibold">Setup Log</div>
    <ul class="list-group list-group-flush">
      <?php foreach ($steps as [$type, $msg]): ?>
      <li class="list-group-item d-flex align-items-start gap-2 py-2">
        <?php if ($type === 'ok'): ?>
          <i class="bi bi-check-lg text-success mt-1"></i>
        <?php elseif ($type === 'warn'): ?>
          <i class="bi bi-exclamation-triangle text-warning mt-1"></i>
        <?php else: ?>
          <i class="bi bi-x-lg text-danger mt-1"></i>
        <?php endif ?>
        <span><?= $msg ?></span>
      </li>
      <?php endforeach ?>
    </ul>
  </div>

  <?php if ($allOk): ?>
  <div class="card mb-4">
    <div class="card-header fw-semibold">Login Credentials</div>
    <div class="card-body">
      <table class="table table-sm mb-0">
        <thead><tr><th>Role</th><th>Email</th><th>Password</th></tr></thead>
        <tbody>
          <tr><td><span class="badge bg-danger">super_admin</span></td><td>admin@callcenter.com</td><td>admin123</td></tr>
          <tr><td><span class="badge bg-warning text-dark">senior_exec</span></td><td>senior@callcenter.com</td><td>demo123</td></tr>
          <tr><td><span class="badge bg-primary">executive</span></td><td>exec@callcenter.com</td><td>demo123</td></tr>
          <tr><td><span class="badge bg-secondary">viewer</span></td><td>viewer@callcenter.com</td><td>demo123</td></tr>
        </tbody>
      </table>
      <div class="alert alert-warning mt-3 mb-0 py-2">
        <i class="bi bi-shield-exclamation me-1"></i>
        Change default passwords immediately after first login in Settings.
      </div>
    </div>
  </div>

  <div class="d-grid gap-2">
    <a href="<?= BASE_URL ?>/login.php" class="btn btn-primary btn-lg">
      <i class="bi bi-box-arrow-in-right me-2"></i>Go to Login
    </a>
  </div>
  <?php endif ?>
</div>
</body>
</html>
