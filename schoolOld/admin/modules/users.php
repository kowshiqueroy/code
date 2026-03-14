<?php
/**
 * Admin Users Module
 */
$admin_title = 'User Management';
require_role('superadmin');

$msg = '';
$mode = $_GET['mode'] ?? 'list';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username  = trim($_POST['username']??'');
    $email     = trim($_POST['email']??'');
    $full_name = trim($_POST['full_name']??'');
    $role      = in_array($_POST['role']??'editor',['editor','admin','superadmin'])?$_POST['role']:'editor';
    $is_active = (int)!empty($_POST['is_active']);
    $password  = $_POST['password']??'';
    $edit_id   = (int)($_POST['edit_id']??0);

    if (!$username || !$email) {
        $msg_type = 'error'; $msg = 'Username and email are required.';
    } else {
        if ($edit_id) {
            $updates = "username=?,email=?,full_name=?,role=?,is_active=?";
            $params  = [$username,$email,$full_name,$role,$is_active];
            if ($password) { $updates .= ",password=?"; $params[] = password_hash($password, PASSWORD_BCRYPT); }
            $params[] = $edit_id;
            db()->prepare("UPDATE users SET $updates WHERE id=?")->execute($params);
            $msg = 'User updated.';
        } else {
            if (!$password) { $msg = 'Password is required for new users.'; goto done; }
            db()->prepare("INSERT INTO users (username,email,full_name,role,is_active,password) VALUES (?,?,?,?,?,?)")
               ->execute([$username,$email,$full_name,$role,$is_active,password_hash($password,PASSWORD_BCRYPT)]);
            $msg = 'User created.';
        }
        $mode = 'list';
    }
}
done:
if (isset($_GET['delete']) && (int)$_GET['delete'] !== (int)$_SESSION['admin_user_id']) {
    db()->prepare("DELETE FROM users WHERE id=?")->execute([(int)$_GET['delete']]);
    header('Location: /admin/?action=users&msg=deleted'); exit;
}
$edit_row = null;
if ($mode === 'edit') { $s=db()->prepare("SELECT * FROM users WHERE id=?"); $s->execute([(int)($_GET['id']??0)]); $edit_row=$s->fetch(); }
$users_list = db()->query("SELECT * FROM users ORDER BY role DESC, username")->fetchAll();
?>
<?php if ($msg): ?><div class="alert alert-<?= isset($msg_type)&&$msg_type==='error'?'error':'success' ?>"><?= $msg_type==='error'?'⚠️':'✅' ?> <?= h($msg) ?></div><?php endif; ?>
<?php if (isset($_GET['msg'])): ?><div class="alert alert-success">✅ Done.</div><?php endif; ?>

<?php if ($mode === 'list'): ?>
<div class="panel">
  <div class="panel-header">
    <div class="panel-title">👤 Users (<?= count($users_list) ?>)</div>
    <a href="/admin/?action=users&mode=add" class="btn btn-primary btn-sm">➕ Add User</a>
  </div>
  <table class="admin-table">
    <thead><tr><th>User</th><th>Email</th><th>Role</th><th>Last Login</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach ($users_list as $u): ?>
      <tr>
        <td>
          <div style="display:flex;align-items:center;gap:10px">
            <div style="width:36px;height:36px;border-radius:50%;background:var(--primary-light);color:var(--primary);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.9rem;flex-shrink:0"><?= mb_strtoupper(mb_substr($u['username'],0,1)) ?></div>
            <div>
              <div style="font-weight:600"><?= h($u['username']) ?></div>
              <div style="font-size:.75rem;color:var(--muted)"><?= h($u['full_name']) ?></div>
            </div>
          </div>
        </td>
        <td style="font-size:.85rem"><?= h($u['email']) ?></td>
        <td>
          <span class="status-badge <?= $u['role']==='superadmin'?'status-inactive':($u['role']==='admin'?'status-published':'status-draft') ?>">
            <?= ucfirst($u['role']) ?>
          </span>
        </td>
        <td style="font-size:.78rem;color:var(--muted)"><?= $u['last_login'] ? date('d M Y H:i', strtotime($u['last_login'])) : 'Never' ?></td>
        <td><span class="status-badge <?= $u['is_active']?'status-published':'status-inactive' ?>"><?= $u['is_active']?'Active':'Inactive' ?></span></td>
        <td>
          <div class="actions">
            <a href="/admin/?action=users&mode=edit&id=<?= $u['id'] ?>" class="btn btn-xs btn-secondary">✏️ Edit</a>
            <?php if ($u['id'] != $_SESSION['admin_user_id']): ?>
            <a href="/admin/?action=users&delete=<?= $u['id'] ?>" class="btn btn-xs btn-danger" onclick="return confirm('Delete user <?= h($u['username']) ?>?')">🗑️</a>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php else: ?>
<div class="panel" style="max-width:600px">
  <div class="panel-header"><div class="panel-title"><?= $edit_row?'✏️ Edit User':'➕ Add User' ?></div><a href="/admin/?action=users" class="btn btn-secondary btn-sm">← Back</a></div>
  <div class="panel-body">
    <form method="POST">
      <?php if ($edit_row): ?><input type="hidden" name="edit_id" value="<?= $edit_row['id'] ?>"><?php endif; ?>
      <div class="form-row col-2">
        <div class="form-group"><label class="form-label">Username *</label><input type="text" name="username" class="form-control" required value="<?= h($edit_row['username']??'') ?>"></div>
        <div class="form-group"><label class="form-label">Full Name</label><input type="text" name="full_name" class="form-control" value="<?= h($edit_row['full_name']??'') ?>"></div>
      </div>
      <div class="form-group"><label class="form-label">Email *</label><input type="email" name="email" class="form-control" required value="<?= h($edit_row['email']??'') ?>"></div>
      <div class="form-row col-2">
        <div class="form-group"><label class="form-label">Password <?= $edit_row?'(leave blank to keep)':' *' ?></label><input type="password" name="password" class="form-control" <?= $edit_row?'':'required' ?>></div>
        <div class="form-group"><label class="form-label">Role</label>
          <select name="role" class="form-control form-select">
            <option value="editor" <?= ($edit_row['role']??'')==='editor'?'selected':'' ?>>Editor</option>
            <option value="admin" <?= ($edit_row['role']??'')==='admin'?'selected':'' ?>>Admin</option>
            <option value="superadmin" <?= ($edit_row['role']??'')==='superadmin'?'selected':'' ?>>Super Admin</option>
          </select>
        </div>
      </div>
      <div style="background:var(--bg);border-radius:8px;padding:12px;margin-bottom:16px;font-size:.82rem">
        <strong>Roles:</strong> Editor = content only | Admin = full CMS | Super Admin = users + settings
      </div>
      <label class="form-check" style="margin-bottom:16px"><input type="checkbox" name="is_active" value="1" <?= ($edit_row['is_active']??1)?'checked':'' ?>> Active</label>
      <div style="display:flex;gap:12px"><button type="submit" class="btn btn-primary">💾 Save</button><a href="/admin/?action=users" class="btn btn-secondary">Cancel</a></div>
    </form>
  </div>
</div>
<?php endif; ?>
