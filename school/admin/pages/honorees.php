<?php // admin/pages/honorees.php
$action=$_GET['action']??'list';$id=(int)($_GET['id']??0);$db=getDB();
if($_SERVER['REQUEST_METHOD']==='POST'){$d=['name_en'=>sanitize($_POST['name_en']??''),'name_bn'=>sanitize($_POST['name_bn']??''),'type'=>$_POST['type']??'student','year'=>sanitize($_POST['year']??''),'class_name'=>sanitize($_POST['class_name']??''),'achievement'=>sanitize($_POST['achievement']??''),'is_active'=>isset($_POST['is_active'])?1:0,'photo'=>sanitize($_POST['current_photo']??'')];if(!empty($_FILES['photo']['name'])){$up=handleUpload('photo','honorees','portrait');if(isset($up['filename']))$d['photo']=$up['filename'];}if($id){$stmt=$db->prepare("UPDATE honorees SET name_en=?,name_bn=?,type=?,year=?,class_name=?,achievement=?,is_active=?,photo=? WHERE id=?");$stmt->execute([...array_values($d),$id]);flash('Updated.','success');}else{$stmt=$db->prepare("INSERT INTO honorees (name_en,name_bn,type,year,class_name,achievement,is_active,photo) VALUES (?,?,?,?,?,?,?,?)");$stmt->execute(array_values($d));flash('Added.','success');}redirect(ADMIN_PATH.'?section=honorees');}
if($action==='delete'&&$id){$db->prepare("DELETE FROM honorees WHERE id=?")->execute([$id]);flash('Deleted.','success');redirect(ADMIN_PATH.'?section=honorees');}
$row=null;if($id){$stmt=$db->prepare("SELECT * FROM honorees WHERE id=?");$stmt->execute([$id]);$row=$stmt->fetch();}
$list=$db->query("SELECT * FROM honorees ORDER BY type,year DESC")->fetchAll();
?>
<div class="acard"><div class="acard-header"><div class="acard-title">⭐ Honorees</div><?php if($action==='list'):?><a href="?section=honorees&action=add" class="btn btn-primary">+ Add</a><?php else:?><a href="?section=honorees" class="btn btn-light btn-sm">← Back</a><?php endif;?></div><div class="acard-body">
<?php if($action==='list'):?>
<table class="atable"><thead><tr><th>Photo</th><th>Name</th><th>Type</th><th>Year</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php foreach($list as $h):?><tr><td><?php if($h['photo']):?><img src="<?= h(imgUrl($h['photo'],'thumb'))?>" style="width:44px;height:44px;border-radius:50%;object-fit:cover"><?php else:?>👤<?php endif;?></td><td><?= h($h['name_en'])?></td><td><span class="badge badge-<?= $h['type']==='student'?'info':'purple' ?>"><?= h($h['type'])?></span></td><td><?= h($h['year'])?></td><td><?= $h['is_active']?'<span class="badge badge-success">Active</span>':'<span class="badge badge-gray">Hidden</span>'?></td><td><a href="?section=honorees&action=edit&id=<?= $h['id']?>" class="btn btn-sm btn-light">✏️</a> <a href="?section=honorees&action=delete&id=<?= $h['id']?>" class="btn btn-sm btn-danger" data-confirm="Delete?">🗑️</a></td></tr><?php endforeach;?>
<?php if(!$list):?><tr><td colspan="6" style="text-align:center;padding:30px;color:#999">No honorees.</td></tr><?php endif;?>
</tbody></table>
<?php else:?>
<form method="POST" enctype="multipart/form-data" class="aform"><input type="hidden" name="current_photo" value="<?= h($row['photo']??'')?>">
<div class="form-row">
<div class="form-group"><label>Name (EN) <span class="req">*</span></label><input type="text" name="name_en" value="<?= h($row['name_en']??'')?>" required></div>
<div class="form-group"><label>নাম (বাংলা)</label><input type="text" name="name_bn" value="<?= h($row['name_bn']??'')?>"></div>
<div class="form-group"><label>Type</label><select name="type"><option value="student" <?= ($row['type']??'')==='student'?'selected':''?>>Student</option><option value="teacher" <?= ($row['type']??'')==='teacher'?'selected':''?>>Teacher</option></select></div>
<div class="form-group"><label>Year</label><input type="text" name="year" value="<?= h($row['year']??date('Y'))?>"></div>
<div class="form-group"><label>Class/Dept</label><input type="text" name="class_name" value="<?= h($row['class_name']??'')?>"></div>
<div class="form-group"><label>Achievement</label><input type="text" name="achievement" value="<?= h($row['achievement']??'')?>"></div>
</div>
<div class="form-group"><label>Photo <span class="hint">(1:1 ratio, auto-cropped)</span></label><input type="file" name="photo" accept="image/*" data-preview="ph_prev"><?php if(!empty($row['photo'])):?><img id="ph_prev" src="<?= h(imgUrl($row['photo'],'medium'))?>" style="width:100px;height:100px;border-radius:50%;object-fit:cover;margin-top:8px"><?php else:?><img id="ph_prev" src="" style="display:none;width:100px;height:100px;border-radius:50%;object-fit:cover;margin-top:8px"><?php endif;?></div>
<label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin-bottom:16px"><input type="checkbox" name="is_active" <?= ($row['is_active']??1)?'checked':''?>> Active</label>
<button type="submit" class="btn btn-primary">💾 Save</button></form>
<?php endif;?>
</div></div>
