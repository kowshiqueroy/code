<?php // admin/pages/admissions.php
$action=$_GET['action']??'list';$id=(int)($_GET['id']??0);$db=getDB();
if($_SERVER['REQUEST_METHOD']==='POST'){$d=['title_en'=>sanitize($_POST['title_en']??''),'title_bn'=>sanitize($_POST['title_bn']??''),'content_en'=>$_POST['content_en']??'','content_bn'=>$_POST['content_bn']??'','type'=>$_POST['type']??'info','class_name'=>sanitize($_POST['class_name']??''),'session_year'=>sanitize($_POST['session_year']??''),'file_url'=>sanitize($_POST['file_url']??''),'is_active'=>isset($_POST['is_active'])?1:0,'sort_order'=>(int)($_POST['sort_order']??0)];if(!empty($_FILES['adm_file']['name'])){$up=handleUpload('adm_file','admissions');if(isset($up['filename']))$d['file_url']=UPLOAD_URL.'documents/'.$up['filename'];}if($d['title_en']){if($id){$stmt=$db->prepare("UPDATE admissions SET title_en=?,title_bn=?,content_en=?,content_bn=?,type=?,class_name=?,session_year=?,file_url=?,is_active=?,sort_order=? WHERE id=?");$stmt->execute([...array_values($d),$id]);flash('Updated.','success');}else{$stmt=$db->prepare("INSERT INTO admissions (title_en,title_bn,content_en,content_bn,type,class_name,session_year,file_url,is_active,sort_order) VALUES (?,?,?,?,?,?,?,?,?,?)");$stmt->execute(array_values($d));flash('Added.','success');}redirect(ADMIN_PATH.'?section=admissions');}
}
if($action==='delete'&&$id){$db->prepare("DELETE FROM admissions WHERE id=?")->execute([$id]);flash('Deleted.','success');redirect(ADMIN_PATH.'?section=admissions');}
$row=null;if($id){$stmt=$db->prepare("SELECT * FROM admissions WHERE id=?");$stmt->execute([$id]);$row=$stmt->fetch();}
$list=$db->query("SELECT * FROM admissions ORDER BY sort_order,type")->fetchAll();
$types=['rules'=>'Rules','form'=>'Form','fee'=>'Fee Structure','contact'=>'Contact','info'=>'General Info'];
?>
<div class="acard"><div class="acard-header"><div class="acard-title">🏫 Admissions</div><?php if($action==='list'):?><a href="?section=admissions&action=add" class="btn btn-primary">+ Add</a><?php else:?><a href="?section=admissions" class="btn btn-light btn-sm">← Back</a><?php endif;?></div><div class="acard-body">
<?php if($action==='list'):?>
<table class="atable"><thead><tr><th>Title</th><th>Type</th><th>Session</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php foreach($list as $a):?><tr><td><?= h($a['title_en'])?></td><td><span class="badge badge-info"><?= h($types[$a['type']]??$a['type'])?></span></td><td><?= h($a['session_year'])?></td><td><?= $a['is_active']?'<span class="badge badge-success">Active</span>':'<span class="badge badge-gray">Hidden</span>'?></td><td><a href="?section=admissions&action=edit&id=<?= $a['id']?>" class="btn btn-sm btn-light">✏️</a> <a href="?section=admissions&action=delete&id=<?= $a['id']?>" class="btn btn-sm btn-danger" data-confirm="Delete?">🗑️</a></td></tr><?php endforeach;?>
<?php if(!$list):?><tr><td colspan="5" style="text-align:center;padding:30px;color:#999">No admission info.</td></tr><?php endif;?>
</tbody></table>
<?php else:?>
<form method="POST" enctype="multipart/form-data" class="aform">
<div class="form-row"><div class="form-group"><label>Title (EN) <span class="req">*</span></label><input type="text" name="title_en" value="<?= h($row['title_en']??'')?>" required></div><div class="form-group"><label>শিরোনাম (বাংলা)</label><input type="text" name="title_bn" value="<?= h($row['title_bn']??'')?>"></div><div class="form-group"><label>Type</label><select name="type"><?php foreach($types as $v=>$l):?><option value="<?= $v?>" <?= ($row['type']??'info')===$v?'selected':''?>><?= $l?></option><?php endforeach;?></select></div><div class="form-group"><label>Session Year</label><input type="text" name="session_year" value="<?= h($row['session_year']??'')?>"></div><div class="form-group"><label>For Class</label><input type="text" name="class_name" value="<?= h($row['class_name']??'')?>"></div><div class="form-group"><label>Sort Order</label><input type="number" name="sort_order" value="<?= h($row['sort_order']??0)?>"></div></div>
<div class="form-group"><label>Content (English)</label><textarea name="content_en" rows="6"><?= h($row['content_en']??'')?></textarea></div>
<div class="form-group"><label>বিষয়বস্তু (বাংলা)</label><textarea name="content_bn" rows="6"><?= h($row['content_bn']??'')?></textarea></div>
<div class="form-row"><div class="form-group"><label>File URL</label><input type="url" name="file_url" value="<?= h($row['file_url']??'')?>"></div><div class="form-group"><label>Upload File</label><input type="file" name="adm_file" accept=".pdf,.doc,.docx"></div></div>
<label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin-bottom:16px"><input type="checkbox" name="is_active" <?= ($row['is_active']??1)?'checked':''?>> Active</label>
<div style="display:flex;gap:10px"><button type="submit" class="btn btn-primary">💾 Save</button><a href="?section=admissions" class="btn btn-light">Cancel</a></div>
</form>
<?php endif;?></div></div>
