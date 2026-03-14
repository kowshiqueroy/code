<?php // admin/pages/users.php
if(!can('superadmin')){echo '<div class="alert alert-danger">Access denied.</div>';return;}
$db=getDB();$action=$_GET['action']??'list';$id=(int)($_GET['id']??0);
if($_SERVER['REQUEST_METHOD']==='POST'){
    $un=sanitize($_POST['username']??'');$fn=sanitize($_POST['full_name']??'');$em=sanitize($_POST['email']??'');$role=$_POST['role']??'editor';$pw=$_POST['password']??'';$active=isset($_POST['status'])?1:0;
    if($id){$sql="UPDATE users SET username=?,full_name=?,email=?,role=?,status=?";$params=[$un,$fn,$em,$role,$active];if($pw){$sql.=",password=?";$params[]=password_hash($pw,PASSWORD_DEFAULT);}$sql.=" WHERE id=?";$params[]=$id;$db->prepare($sql)->execute($params);flash('User updated.','success');}
    else{if(!$pw){flash('Password required.','error');}else{$db->prepare("INSERT INTO users (username,full_name,email,role,status,password) VALUES (?,?,?,?,?,?)")->execute([$un,$fn,$em,$role,$active,password_hash($pw,PASSWORD_DEFAULT)]);flash('User created.','success');}}
    redirect(ADMIN_PATH.'?section=users');
}
if($action==='delete'&&$id&&$id!==$_SESSION['user_id']){$db->prepare("DELETE FROM users WHERE id=?")->execute([$id]);flash('Deleted.','success');redirect(ADMIN_PATH.'?section=users');}
$row=null;if($id){$stmt=$db->prepare("SELECT * FROM users WHERE id=?");$stmt->execute([$id]);$row=$stmt->fetch();}
$users=$db->query("SELECT * FROM users ORDER BY role DESC,created_at")->fetchAll();
?>
<div class="acard"><div class="acard-header"><div class="acard-title">👤 Admin Users</div><?php if($action==='list'):?><a href="?section=users&action=add" class="btn btn-primary">+ Add User</a><?php else:?><a href="?section=users" class="btn btn-light btn-sm">← Back</a><?php endif;?></div><div class="acard-body">
<?php if($action==='list'):?>
<table class="atable"><thead><tr><th>Name</th><th>Username</th><th>Role</th><th>Last Login</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php foreach($users as $u):?><tr>
<td><?= h($u['full_name'])?></td><td><?= h($u['username'])?></td>
<td><span class="badge <?= $u['role']==='superadmin'?'badge-danger':($u['role']==='admin'?'badge-warning':'badge-info')?>"><?= h($u['role'])?></span></td>
<td><?= $u['last_login']?date('d M Y',strtotime($u['last_login'])):'Never'?></td>
<td><?= $u['status']?'<span class="badge badge-success">Active</span>':'<span class="badge badge-gray">Disabled</span>'?></td>
<td><a href="?section=users&action=edit&id=<?= $u['id']?>" class="btn btn-sm btn-light">✏️</a><?php if($u['id']!==$_SESSION['user_id']):?> <a href="?section=users&action=delete&id=<?= $u['id']?>" class="btn btn-sm btn-danger" data-confirm="Delete user?">🗑️</a><?php endif;?></td>
</tr><?php endforeach;?>
</tbody></table>
<?php else:?>
<form method="POST" class="aform">
<div class="form-row">
<div class="form-group"><label>Full Name</label><input type="text" name="full_name" value="<?= h($row['full_name']??'')?>" required></div>
<div class="form-group"><label>Username</label><input type="text" name="username" value="<?= h($row['username']??'')?>" required></div>
<div class="form-group"><label>Email</label><input type="email" name="email" value="<?= h($row['email']??'')?>"></div>
<div class="form-group"><label>Role</label><select name="role"><option value="editor" <?= ($row['role']??'')==='editor'?'selected':''?>>Editor</option><option value="admin" <?= ($row['role']??'')==='admin'?'selected':''?>>Admin</option><option value="superadmin" <?= ($row['role']??'')==='superadmin'?'selected':''?>>Super Admin</option></select></div>
<div class="form-group"><label>Password <?= $row?'(leave blank to keep)':'' ?></label><input type="password" name="password" <?= $row?'':'required'?> autocomplete="new-password"></div>
</div>
<label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin-bottom:16px"><input type="checkbox" name="status" <?= ($row['status']??1)?'checked':''?>> Active</label>
<div style="display:flex;gap:10px"><button type="submit" class="btn btn-primary">💾 Save</button><a href="?section=users" class="btn btn-light">Cancel</a></div>
</form>
<?php endif;?></div></div>
