<?php
require_once __DIR__ . '/auth.php';
requireAdmin();
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

if ($action === 'delete' && $id) {
    $r=db()->prepare("SELECT image FROM management WHERE id=?");$r->execute([$id]);$r=$r->fetch();
    if($r&&$r['image'])@unlink(UPLOAD_DIR.'management/'.$r['image']);
    db()->prepare("DELETE FROM management WHERE id=?")->execute([$id]);
    flash('Deleted.','success');redirect(SITE_URL.'/admin/management.php');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $nEn=sanitizeText($_POST['name_en']??'');$nBn=sanitizeText($_POST['name_bn']??'');
    $tEn=sanitizeText($_POST['title_en']??'');$tBn=sanitizeText($_POST['title_bn']??'');
    $mEn=sanitizeText($_POST['message_en']??'');$mBn=sanitizeText($_POST['message_bn']??'');
    $active=(int)($_POST['active']??1);$sort=(int)($_POST['sort_order']??0);$editId=(int)($_POST['edit_id']??0);
    $oldImg='';if($editId){$r=db()->prepare("SELECT image FROM management WHERE id=?");$r->execute([$editId]);$r=$r->fetch();$oldImg=$r['image']??'';}
    $image=$oldImg;if(!empty($_FILES['image']['name'])){$new=processUploadedImage($_FILES['image'],'management','management',$oldImg);if($new)$image=$new;}
    if($editId){db()->prepare("UPDATE management SET name_en=?,name_bn=?,title_en=?,title_bn=?,message_en=?,message_bn=?,image=?,active=?,sort_order=? WHERE id=?")->execute([$nEn,$nBn,$tEn,$tBn,$mEn,$mBn,$image,$active,$sort,$editId]);flash('Updated.','success');}
    else{db()->prepare("INSERT INTO management (name_en,name_bn,title_en,title_bn,message_en,message_bn,image,active,sort_order) VALUES (?,?,?,?,?,?,?,?,?)")->execute([$nEn,$nBn,$tEn,$tBn,$mEn,$mBn,$image,$active,$sort]);flash('Added.','success');}
    redirect(SITE_URL.'/admin/management.php');
}
$editing=null;if($action==='edit'&&$id){$s=db()->prepare("SELECT * FROM management WHERE id=?");$s->execute([$id]);$editing=$s->fetch();}
$items=db()->query("SELECT * FROM management ORDER BY sort_order")->fetchAll();
require_once __DIR__ . '/partials/admin_header.php';
?>
<div class="admin-page-header"><h1>👤 Management Profiles</h1><p>Photo auto-resized to 400×480px (portrait crop).</p></div>
<div class="admin-panel">
  <h2 class="admin-section-title"><?= $editing?'Edit Profile':'Add Profile'?></h2>
  <form method="POST" enctype="multipart/form-data" class="admin-form">
    <input type="hidden" name="csrf_token" value="<?= csrf_token()?>">
    <input type="hidden" name="edit_id" value="<?= $editing['id']??0?>">
    <div class="form-row">
      <div class="form-group"><label>Full Name (EN) *</label><input type="text" name="name_en" class="form-input" required value="<?= e($editing['name_en']??'')?>"></div>
      <div class="form-group"><label>Full Name (BN)</label><input type="text" name="name_bn" class="form-input" value="<?= e($editing['name_bn']??'')?>"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Title / Designation (EN) *</label><input type="text" name="title_en" class="form-input" required value="<?= e($editing['title_en']??'')?>"></div>
      <div class="form-group"><label>Title / Designation (BN)</label><input type="text" name="title_bn" class="form-input" value="<?= e($editing['title_bn']??'')?>"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Message / Quote (EN)</label><textarea name="message_en" class="form-textarea" rows="5"><?= e($editing['message_en']??'')?></textarea></div>
      <div class="form-group"><label>Message / Quote (BN)</label><textarea name="message_bn" class="form-textarea" rows="5"><?= e($editing['message_bn']??'')?></textarea></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Photo (400×480px portrait)</label><?php if($editing&&$editing['image']):?><div class="current-media"><img src="<?= imgUrl($editing['image'],'management','management')?>" style="height:80px;border-radius:6px;"><small>Current</small></div><?php endif;?><input type="file" name="image" class="form-input" accept="image/*"></div>
      <div class="form-group"><label>Status</label><select name="active" class="form-input"><option value="1" <?=($editing['active']??1)==1?'selected':''?>>Active</option><option value="0" <?=($editing['active']??1)==0?'selected':''?>>Inactive</option></select><label style="margin-top:12px">Sort Order</label><input type="number" name="sort_order" class="form-input" value="<?=(int)($editing['sort_order']??0)?>"></div>
    </div>
    <div class="form-actions"><button type="submit" class="btn btn-primary"><?= $editing?'💾 Update':'➕ Add'?></button><?php if($editing):?><a href="<?= SITE_URL?>/admin/management.php" class="btn btn-ghost">Cancel</a><?php endif;?></div>
  </form>
</div>
<div class="admin-panel">
  <h2 class="admin-section-title">All Profiles</h2>
  <table class="admin-table"><thead><tr><th>Photo</th><th>Name</th><th>Title</th><th>Status</th><th>Sort</th><th>Actions</th></tr></thead>
  <tbody><?php foreach($items as $m):?><tr><td><img src="<?= imgUrl($m['image']??'','management','management')?>" style="width:48px;height:58px;object-fit:cover;border-radius:6px;"></td><td><?= e($m['name_en'])?></td><td><?= e($m['title_en'])?></td><td><span class="badge <?=$m['active']?'badge-green':'badge-gray'?>"><?=$m['active']?'Active':'Off'?></span></td><td><?=$m['sort_order']?></td><td class="table-actions"><a href="?action=edit&id=<?=$m['id']?>" class="btn-mini">Edit</a><a href="?action=delete&id=<?=$m['id']?>&csrf_token=<?= csrf_token()?>" class="btn-mini btn-danger" onclick="return confirm('Delete?')">Del</a></td></tr><?php endforeach;?></tbody></table>
</div>
<?php require_once __DIR__ . '/partials/admin_footer.php'; ?>
