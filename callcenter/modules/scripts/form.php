<?php
require_once dirname(__DIR__, 2) . '/config.php';
require_once ROOT . '/includes/auth.php';
require_once ROOT . '/includes/helpers.php';
require_role('executive');

$id        = (int)($_GET['id'] ?? 0);
$view_only = isset($_GET['view']);
$script    = $id ? db_row("SELECT * FROM scripts WHERE id=?", [$id]) : null;
$is_edit   = (bool)$script;

if ($id && !$script) redirect(BASE_URL . '/modules/scripts/index.php');

$page_title  = $is_edit ? ($view_only ? 'Script Detail' : 'Edit Script') : 'New Script';
$active_page = 'scripts';

// ── POST: Save ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$view_only) {
    require_csrf();

    $name        = clean($_POST['name']        ?? '');
    $content     = $_POST['content'] ?? '';  // preserve formatting
    $campaign_id = (int)($_POST['campaign_id'] ?? 0);
    $is_default  = isset($_POST['is_default']) ? 1 : 0;
    

    $errors = [];
    if (!$name)    $errors[] = 'Script name is required.';
    if (!$content) $errors[] = 'Script content is required.';

    if (empty($errors)) {
        // Only one default per campaign/global
        if ($is_default) {
            db_exec(
                "UPDATE scripts SET is_default=0 WHERE campaign_id" . ($campaign_id ? "=?" : " IS NULL") . ($is_edit ? " AND id != ?" : ""),
                array_filter([$campaign_id ?: null, $is_edit ? $id : null])
            );
        }

        if ($is_edit) {
            db_exec(
                "UPDATE scripts SET name=?, content=?, campaign_id=?, is_default=? WHERE id=?",
                [$name, $content, $campaign_id ?: null, $is_default, $id]
            );
            audit_log('edit_script', 'scripts', $id, "Updated: $name");
            flash_success("Script updated.");
            redirect(BASE_URL . '/modules/scripts/form.php?id=' . $id . '&view=1');
        } else {
            $sid = db_exec(
                "INSERT INTO scripts (name, content, campaign_id, is_default, created_by)
                 VALUES (?, ?, ?, ?, ?)",
                [$name, $content, $campaign_id ?: null, $is_default, current_user_id()]
            );
            audit_log('create_script', 'scripts', $sid, "Created: $name");
            flash_success("Script <strong>" . h($name) . "</strong> created.");
            redirect(BASE_URL . '/modules/scripts/form.php?id=' . $sid . '&view=1');
        }
    }
    $script = array_merge($script ?? [], $_POST);
}

$campaigns = db_rows("SELECT id, name FROM campaigns WHERE status='active' ORDER BY name");

require ROOT . '/partials/header.php';
?>

<div class="page-header no-print">
  <a href="<?= BASE_URL ?>/modules/scripts/index.php" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left"></i>
  </a>
  <i class="bi bi-file-text-fill text-info ms-1"></i>
  <h5 class="ms-1"><?= $page_title ?></h5>
  <?php if ($is_edit && $view_only && can('edit', 'scripts')): ?>
  <div class="ms-auto d-flex gap-2">
    <button data-print class="btn btn-sm btn-outline-secondary no-print">
      <i class="bi bi-printer"></i>
    </button>
    <a href="?id=<?= $id ?>" class="btn btn-sm btn-outline-warning no-print">
      <i class="bi bi-pencil me-1"></i>Edit
    </a>
  </div>
  <?php endif ?>
</div>

<div class="page-body">
  <div class="row justify-content-center">
    <div class="col-12 col-md-9">

      <?php if (!empty($errors)): ?>
      <div class="alert alert-danger">
        <ul class="mb-0 ps-3"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach ?></ul>
      </div>
      <?php endif ?>

      <div class="card">
        <div class="card-header d-flex align-items-center gap-2">
          <?= $view_only ? h($script['name']) : ($is_edit ? 'Edit Script' : 'New Script') ?>
          <?php if ($view_only && $script['is_default']): ?>
          <span class="badge bg-warning text-dark ms-1">Default</span>
          <?php endif ?>
        </div>
        <div class="card-body">
          <?php if ($view_only): ?>
          <!-- Script content display -->
          <?php if ($script['campaign_id']): ?>
          <?php $camp = db_row("SELECT id, name FROM campaigns WHERE id=?", [$script['campaign_id']]); ?>
          <?php if ($camp): ?>
          <div class="mb-3 text-muted small">
            Campaign:
            <a href="<?= BASE_URL ?>/modules/campaigns/form.php?id=<?= $camp['id'] ?>&view=1">
              <?= h($camp['name']) ?>
            </a>
          </div>
          <?php endif ?>
          <?php else: ?>
          <div class="mb-3 text-muted small">General script (no specific campaign)</div>
          <?php endif ?>

          <div class="script-content p-3 bg-light rounded border" style="white-space:pre-wrap;font-size:.9rem;line-height:1.7">
<?= h($script['content']) ?>
          </div>

          <div class="mt-3 text-muted small">
            Created <?= format_datetime($script['created_at']) ?>
          </div>

          <?php else: ?>
          <!-- Edit form -->
          <form method="post">
            <?= csrf_field() ?>
            <div class="row g-3">
              <div class="col-12 col-md-8">
                <label class="form-label">Script Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="name" required
                       value="<?= h($script['name'] ?? '') ?>" placeholder="e.g. Outbound Introduction Script">
              </div>
              <div class="col-12 col-md-4">
                <label class="form-label">Campaign</label>
                <select class="form-select" name="campaign_id">
                  <option value="">General (no campaign)</option>
                  <?php foreach ($campaigns as $c): ?>
                  <option value="<?= $c['id'] ?>" <?= ($script['campaign_id']??0)==$c['id']?'selected':'' ?>>
                    <?= h($c['name']) ?>
                  </option>
                  <?php endforeach ?>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label">Script Content <span class="text-danger">*</span></label>
                <div class="mb-1">
                  <small class="text-muted">
                    Use <code>[CONTACT_NAME]</code>, <code>[PHONE]</code>, <code>[AGENT_NAME]</code>, <code>[DATE]</code> as placeholders.
                  </small>
                </div>
                <textarea class="form-control font-monospace" name="content" rows="18" required
                          placeholder="Hello [CONTACT_NAME], this is [AGENT_NAME] calling from Ovijat Group..."
                          style="font-size:.85rem;line-height:1.7"><?= h($script['content'] ?? '') ?></textarea>
              </div>
              <div class="col-12 d-flex gap-4">
                <div class="form-check">
                  <input type="checkbox" class="form-check-input" name="is_default" id="isDefault"
                         <?= ($script['is_default']??0) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="isDefault">Set as default script</label>
                </div>
                <div class="form-check">
                </div>
              </div>
            </div>

            <div class="d-flex gap-2 mt-4">
              <button type="submit" class="btn btn-primary">
                <i class="bi bi-save me-2"></i><?= $is_edit ? 'Save Changes' : 'Create Script' ?>
              </button>
              <a href="<?= BASE_URL ?>/modules/scripts/index.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
          </form>
          <?php endif ?>
        </div>
      </div>

    </div>
  </div>
</div>

<?php require ROOT . '/partials/footer.php'; ?>
