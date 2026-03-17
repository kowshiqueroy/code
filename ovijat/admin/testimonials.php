<?php
require_once __DIR__.'/auth.php'; requireAdmin();
$action=$_GET['action']??'list'; $id=(int)($_GET['id']??0);
if($action==='delete'&&$id){
    $r=db()->prepare("SELECT image FROM testimonials WHERE id=?");$r->execute([$id]);$r=$r->fetch();
    if($r&&$r['image'])@unlink(UPLOAD_DIR.'management/'.$r['image']);
    db()->prepare("DELETE FROM testimonials WHERE id=?")->execute([$id]);
    flash('Deleted.','success'); redirect(SITE_URL.'/admin/testimonials.php');
}
if($_SERVER['REQUEST_METHOD']==='POST'&&csrf_verify()){
    $nEn=sanitizeText($_POST['name_en']??'');$nBn=sanitizeText($_POST['name_bn']??'');
    $rEn=sanitizeText($_POST['role_en']??'');$rBn=sanitizeText($_POST['role_bn']??'');
    $tEn=sanitizeText($_POST['text_en']??'');$tBn=sanitizeText($_POST['text_bn']??'');
    $stars=max(1,min(5,(int)($_POST['stars']??5)));
    $active=(int)($_POST['active']??1);$sort=(int)($_POST['sort_order']??0);
    $editId=(int)($_POST['edit_id']??0);
    $oldImg='';if($editId){$r=db()->prepare("SELECT image FROM testimonials WHERE id=?");$r->execute([$editId]);$r=$r->fetch();$oldImg=$r['image']??'';}
    $image=$oldImg;if(!empty($_FILES['image']['name'])){$new=processUploadedImage($_FILES['image'],'testimonial','management',$oldImg);if($new)$image=$new;}
    if($editId){db()->prepare("UPDATE testimonials SET name_en=?,name_bn=?,role_en=?,role_bn=?,text_en=?,text_bn=?,stars=?,image=?,active=?,sort_order=? WHERE id=?")->execute([$nEn,$nBn,$rEn,$rBn,$tEn,$tBn,$stars,$image,$active,$sort,$editId]);flash('Updated.','success');}
    else{db()->prepare("INSERT INTO testimonials (name_en,name_bn,role_en,role_bn,text_en,text_bn,stars,image,active,sort_order) VALUES (?,?,?,?,?,?,?,?,?,?)")->execute([$nEn,$nBn,$rEn,$rBn,$tEn,$tBn,$stars,$image,$active,$sort]);flash('Added.','success');}
    redirect(SITE_URL.'/admin/testimonials.php');
}
$editing=null;if($action==='edit'&&$id){$s=db()->prepare("SELECT * FROM testimonials WHERE id=?");$s->execute([$id]);$editing=$s->fetch();}
$items=db()->query("SELECT * FROM testimonials ORDER BY sort_order")->fetchAll();
require_once __DIR__.'/partials/admin_header.php';
?>
<div class="admin-page-header"><h1>⭐ Testimonials</h1></div>
<div class="admin-panel">
  <h2 class="admin-section-title"><?= $editing?'Edit':'Add'?> Testimonial</h2>
  <form method="POST" enctype="multipart/form-data" class="admin-form">
    <input type="hidden" name="csrf_token" value="<?= csrf_token()?>">
    <input type="hidden" name="edit_id" value="<?= $editing['id']??0?>">
    <div class="form-row">
      <div class="form-group"><label>Name (EN) *</label><input type="text" name="name_en" class="form-input" required value="<?= e($editing['name_en']??'')?>"></div>
      <div class="form-group"><label>Name (BN)</label><input type="text" name="name_bn" class="form-input" value="<?= e($editing['name_bn']??'')?>"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Role / Company (EN)</label><input type="text" name="role_en" class="form-input" value="<?= e($editing['role_en']??'')?>"></div>
      <div class="form-group"><label>Role / Company (BN)</label><input type="text" name="role_bn" class="form-input" value="<?= e($editing['role_bn']??'')?>"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Review Text (EN) *</label><textarea name="text_en" class="form-textarea" rows="4" required><?= e($editing['text_en']??'')?></textarea></div>
      <div class="form-group"><label>Review Text (BN)</label><textarea name="text_bn" class="form-textarea" rows="4"><?= e($editing['text_bn']??'')?></textarea></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Stars (1-5)</label><select name="stars" class="form-input"><?php for($i=5;$i>=1;$i--):?><option value="<?=$i?>" <?=($editing['stars']??5)==$i?'selected':''?>><?= str_repeat('★',$i)?></option><?php endfor;?></select></div>
      <div class="form-group"><label>Photo (200×200px)</label><?php if($editing&&$editing['image']):?><div class="current-media"><img src="<?= imgUrl($editing['image'],'management','management')?>" style="width:40px;height:40px;border-radius:50%;object-fit:cover;"><small>Current</small></div><?php endif;?><input type="file" name="image" class="form-input" accept="image/*"></div>
      <div class="form-group"><label>Status</label><select name="active" class="form-input"><option value="1" <?=($editing['active']??1)==1?'selected':''?>>Active</option><option value="0" <?=($editing['active']??1)==0?'selected':''?>>Inactive</option></select></div>
      <div class="form-group"><label>Sort</label><input type="number" name="sort_order" class="form-input" value="<?=(int)($editing['sort_order']??0)?>"></div>
    </div>
    <div class="form-actions"><button type="submit" class="btn btn-primary"><?= $editing?'💾 Update':'➕ Add'?></button><?php if($editing):?><a href="<?= SITE_URL?>/admin/testimonials.php" class="btn btn-ghost">Cancel</a><?php endif;?></div>
  </form>
</div>
<div class="admin-panel">
  <h2 class="admin-section-title">All Testimonials (<?= count($items)?>)</h2>
  <table class="admin-table"><thead><tr><th>Name</th><th>Role</th><th>Stars</th><th>Status</th><th>Actions</th></tr></thead>
  <tbody><?php foreach($items as $it):?><tr><td><?= e($it['name_en'])?></td><td><?= e($it['role_en'])?:'—'?></td><td><?= str_repeat('★',$it['stars']??5)?></td><td><span class="badge <?=$it['active']?'badge-green':'badge-gray'?>"><?=$it['active']?'Active':'Off'?></span></td><td class="table-actions"><a href="?action=edit&id=<?=$it['id']?>" class="btn-mini">Edit</a><a href="?action=delete&id=<?=$it['id']?>&csrf_token=<?= csrf_token()?>" class="btn-mini btn-danger" onclick="return confirm('Delete?')">Del</a></td></tr><?php endforeach;?></tbody></table>
</div>
<?php require_once __DIR__.'/partials/admin_footer.php'; ?>
