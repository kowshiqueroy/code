<?php
require_once __DIR__ . '/auth.php';
requireAdmin();
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

if ($action === 'delete' && $id) {
    $r=db()->prepare("SELECT image FROM sales_contacts WHERE id=?");$r->execute([$id]);$r=$r->fetch();
    if($r&&$r['image'])@unlink(UPLOAD_DIR.'management/'.$r['image']);
    db()->prepare("DELETE FROM sales_contacts WHERE id=?")->execute([$id]);
    flash('Deleted.','success');redirect(SITE_URL.'/admin/contacts.php');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $type=in_array($_POST['type']??'',['local','export'])?$_POST['type']:'local';
    $nEn=sanitizeText($_POST['name_en']??'');$nBn=sanitizeText($_POST['name_bn']??'');
    $tEn=sanitizeText($_POST['title_en']??'');$tBn=sanitizeText($_POST['title_bn']??'');
    $phone=sanitizeText($_POST['phone']??'');$email=sanitizeText($_POST['email']??'');
    $active=(int)($_POST['active']??1);$editId=(int)($_POST['edit_id']??0);
    $oldImg='';if($editId){$r=db()->prepare("SELECT image FROM sales_contacts WHERE id=?");$r->execute([$editId]);$r=$r->fetch();$oldImg=$r['image']??'';}
    $image=$oldImg;if(!empty($_FILES['image']['name'])){$new=processUploadedImage($_FILES['image'],'sales','management',$oldImg);if($new)$image=$new;}
    if($editId){db()->prepare("UPDATE sales_contacts SET type=?,name_en=?,name_bn=?,title_en=?,title_bn=?,phone=?,email=?,image=?,active=? WHERE id=?")->execute([$type,$nEn,$nBn,$tEn,$tBn,$phone,$email,$image,$active,$editId]);flash('Updated.','success');}
    else{db()->prepare("INSERT INTO sales_contacts (type,name_en,name_bn,title_en,title_bn,phone,email,image,active) VALUES (?,?,?,?,?,?,?,?,?)")->execute([$type,$nEn,$nBn,$tEn,$tBn,$phone,$email,$image,$active]);flash('Added.','success');}
    redirect(SITE_URL.'/admin/contacts.php');
}
$editing=null;if($action==='edit'&&$id){$s=db()->prepare("SELECT * FROM sales_contacts WHERE id=?");$s->execute([$id]);$editing=$s->fetch();}
$items=db()->query("SELECT * FROM sales_contacts ORDER BY type,id")->fetchAll();
require_once __DIR__ . '/partials/admin_header.php';
?>
<div class="admin-page-header"><h1>📞 Sales Contact Persons</h1><p>Manage Local and Export sales contact profiles displayed on the Contact page.</p></div>
<div class="admin-panel">
  <h2 class="admin-section-title"><?= $editing?'Edit Contact':'Add Contact'?></h2>
  <form method="POST" enctype="multipart/form-data" class="admin-form">
    <input type="hidden" name="csrf_token" value="<?= csrf_token()?>">
    <input type="hidden" name="edit_id" value="<?= $editing['id']??0?>">
    <div class="form-row">
      <div class="form-group"><label>Contact Type *</label><select name="type" class="form-input"><option value="local" <?=($editing['type']??'')==='local'?'selected':''?>>🇧🇩 Local Sales</option><option value="export" <?=($editing['type']??'')==='export'?'selected':''?>>🌍 Export Sales</option></select></div>
      <div class="form-group"><label>Status</label><select name="active" class="form-input"><option value="1" <?=($editing['active']??1)==1?'selected':''?>>Active</option><option value="0" <?=($editing['active']??1)==0?'selected':''?>>Inactive</option></select></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Name (EN) *</label><input type="text" name="name_en" class="form-input" required value="<?= e($editing['name_en']??'')?>"></div>
      <div class="form-group"><label>Name (BN)</label><input type="text" name="name_bn" class="form-input" value="<?= e($editing['name_bn']??'')?>"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Title (EN) *</label><input type="text" name="title_en" class="form-input" required value="<?= e($editing['title_en']??'')?>"></div>
      <div class="form-group"><label>Title (BN)</label><input type="text" name="title_bn" class="form-input" value="<?= e($editing['title_bn']??'')?>"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Phone *</label><input type="text" name="phone" class="form-input" required value="<?= e($editing['phone']??'')?>"></div>
      <div class="form-group"><label>Email</label><input type="email" name="email" class="form-input" value="<?= e($editing['email']??'')?>"></div>
    </div>
    <div class="form-group"><label>Photo (300×360px)</label><?php if($editing&&$editing['image']):?><div class="current-media"><img src="<?= imgUrl($editing['image'],'management','sales')?>" style="height:70px;border-radius:4px;"><small>Current</small></div><?php endif;?><input type="file" name="image" class="form-input" accept="image/*"></div>
    <div class="form-actions"><button type="submit" class="btn btn-primary"><?= $editing?'💾 Update':'➕ Add'?></button><?php if($editing):?><a href="<?= SITE_URL?>/admin/contacts.php" class="btn btn-ghost">Cancel</a><?php endif;?></div>
  </form>
</div>
<div class="admin-panel">
  <h2 class="admin-section-title">All Contacts</h2>
  <table class="admin-table"><thead><tr><th>Photo</th><th>Name</th><th>Type</th><th>Phone</th><th>Status</th><th>Actions</th></tr></thead>
  <tbody><?php foreach($items as $c):?><tr><td><img src="<?= imgUrl($c['image']??'','management','sales')?>" style="width:44px;height:52px;object-fit:cover;border-radius:4px;"></td><td><?= e($c['name_en'])?><br><small><?= e($c['title_en'])?></small></td><td><span class="badge <?=$c['type']==='export'?'badge-blue':'badge-green'?>"><?= ucfirst($c['type'])?></span></td><td><?= e($c['phone'])?></td><td><span class="badge <?=$c['active']?'badge-green':'badge-gray'?>"><?=$c['active']?'Active':'Off'?></span></td><td class="table-actions"><a href="?action=edit&id=<?=$c['id']?>" class="btn-mini">Edit</a><a href="?action=delete&id=<?=$c['id']?>&csrf_token=<?= csrf_token()?>" class="btn-mini btn-danger" onclick="return confirm('Delete?')">Del</a></td></tr><?php endforeach;?></tbody></table>
</div>
<?php require_once __DIR__ . '/partials/admin_footer.php'; ?>
