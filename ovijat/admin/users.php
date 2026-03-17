<?php
require_once __DIR__.'/auth.php'; requireAdmin();
$action=$_GET['action']??'list'; $id=(int)($_GET['id']??0);

if($action==='delete'&&$id){
    if($id==$_SESSION['admin_id']){flash('Cannot delete yourself.','error');redirect(SITE_URL.'/admin/users.php');}
    db()->prepare("DELETE FROM admin_users WHERE id=?")->execute([$id]);
    logAction('Delete User','ID:'.$id); flash('User deleted.','success'); redirect(SITE_URL.'/admin/users.php');
}
if($action==='toggle'&&$id){
    if($id==$_SESSION['admin_id']){flash('Cannot deactivate yourself.','error');redirect(SITE_URL.'/admin/users.php');}
    db()->prepare("UPDATE admin_users SET active=1-active WHERE id=?")->execute([$id]);
    logAction('Toggle User Active','ID:'.$id); redirect(SITE_URL.'/admin/users.php');
}

$errors=[];
if($_SERVER['REQUEST_METHOD']==='POST'&&csrf_verify()){
    $username=sanitizeText($_POST['username']??'');
    $role=in_array($_POST['role']??'',['superadmin','editor'])?$_POST['role']:'editor';
    $active=(int)($_POST['active']??1);
    $editId=(int)($_POST['edit_id']??0);
    $password=$_POST['password']??'';
    $confirm =$_POST['confirm']??'';
    if(strlen($username)<3)$errors[]='Username must be at least 3 characters.';
    if(!$editId&&strlen($password)<8)$errors[]='Password must be at least 8 characters.';
    if($password&&$password!==$confirm)$errors[]='Passwords do not match.';
    if(!$errors){
        if($editId){
            if($password){
                db()->prepare("UPDATE admin_users SET username=?,role=?,active=?,password=? WHERE id=?")->execute([$username,$role,$active,password_hash($password,PASSWORD_BCRYPT),$editId]);
            } else {
                db()->prepare("UPDATE admin_users SET username=?,role=?,active=? WHERE id=?")->execute([$username,$role,$active,$editId]);
            }
            logAction('Update User','ID:'.$editId); flash('User updated.','success');
        } else {
            try {
                db()->prepare("INSERT INTO admin_users (username,password,role,active) VALUES (?,?,?,?)")->execute([$username,password_hash($password,PASSWORD_BCRYPT),$role,$active]);
                logAction('Create User','Username:'.$username); flash('User created.','success');
            } catch(Exception $e){ $errors[]='Username already exists.'; }
        }
        if(!$errors) redirect(SITE_URL.'/admin/users.php');
    }
}
$editing=null;if($action==='edit'&&$id){$s=db()->prepare("SELECT * FROM admin_users WHERE id=?");$s->execute([$id]);$editing=$s->fetch();}
$users=db()->query("SELECT * FROM admin_users ORDER BY id")->fetchAll();
require_once __DIR__.'/partials/admin_header.php';
?>
<div class="admin-page-header"><h1>👥 Admin Users</h1><p>Manage who can access this admin panel.</p></div>
<?php if($errors):?><div class="alert alert-danger"><ul><?php foreach($errors as $e):?><li><?= e($e)?></li><?php endforeach;?></ul></div><?php endif;?>
<div class="admin-panel">
  <h2 class="admin-section-title"><?= $editing?'Edit User':'Add New Admin User'?></h2>
  <form method="POST" class="admin-form">
    <input type="hidden" name="csrf_token" value="<?= csrf_token()?>">
    <input type="hidden" name="edit_id" value="<?= $editing['id']??0?>">
    <div class="form-row">
      <div class="form-group"><label>Username *</label><input type="text" name="username" class="form-input" required value="<?= e($editing['username']??$_POST['username']??'')?>"></div>
      <div class="form-group"><label>Role</label><select name="role" class="form-input"><option value="superadmin" <?=($editing['role']??'')==='superadmin'?'selected':''?>>Super Admin (full access)</option><option value="editor" <?=($editing['role']??'')==='editor'?'selected':''?>>Editor (content only)</option></select></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Password <?= $editing?'(leave blank to keep current)':' *'?></label><input type="password" name="password" class="form-input" <?= !$editing?'required':''?> placeholder="Min. 8 characters" autocomplete="new-password"></div>
      <div class="form-group"><label>Confirm Password</label><input type="password" name="confirm" class="form-input" placeholder="Repeat password" autocomplete="new-password"></div>
    </div>
    <div class="form-group"><label>Status</label><select name="active" class="form-input" style="max-width:200px"><option value="1" <?=($editing['active']??1)==1?'selected':''?>>Active</option><option value="0" <?=($editing['active']??1)==0?'selected':''?>>Inactive</option></select></div>
    <div class="form-actions"><button type="submit" class="btn btn-primary"><?= $editing?'💾 Update User':'➕ Create User'?></button><?php if($editing):?><a href="<?= SITE_URL?>/admin/users.php" class="btn btn-ghost">Cancel</a><?php endif;?></div>
  </form>
</div>
<div class="admin-panel">
  <h2 class="admin-section-title">All Users (<?= count($users)?>)</h2>
  <table class="admin-table">
    <thead><tr><th>Username</th><th>Role</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach($users as $u): $isSelf=($u['id']==$_SESSION['admin_id']); ?>
        <tr>
          <td><strong><?= e($u['username'])?></strong><?= $isSelf?' <span class="badge badge-blue">You</span>':''?></td>
          <td><span class="badge <?=$u['role']==='superadmin'?'badge-orange':'badge-gray'?>"><?= ucfirst($u['role'])?></span></td>
          <td><span class="badge <?=$u['active']?'badge-green':'badge-gray'?>"><?=$u['active']?'Active':'Inactive'?></span></td>
          <td><?= date('d M Y',strtotime($u['created_at']))?></td>
          <td class="table-actions">
            <?php if(!$isSelf):?>
              <a href="?action=edit&id=<?=$u['id']?>" class="btn-mini">Edit</a>
              <a href="?action=toggle&id=<?=$u['id']?>&csrf_token=<?= csrf_token()?>" class="btn-mini"><?=$u['active']?'Deactivate':'Activate'?></a>
              <a href="?action=delete&id=<?=$u['id']?>&csrf_token=<?= csrf_token()?>" class="btn-mini btn-danger" onclick="return confirm('Delete user?')">Del</a>
            <?php else:?><a href="?action=edit&id=<?=$u['id']?>" class="btn-mini">Edit My Password</a><?php endif;?>
          </td>
        </tr>
      <?php endforeach;?>
    </tbody>
  </table>
</div>
<?php require_once __DIR__.'/partials/admin_footer.php'; ?>
