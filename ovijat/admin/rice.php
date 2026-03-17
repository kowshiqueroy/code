<?php
/**
 * OVIJAT GROUP — admin/rice.php
 */
require_once __DIR__ . '/auth.php';
requireAdmin();
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

if ($action === 'delete' && $id) {
    $r=db()->prepare("SELECT image FROM rice_products WHERE id=?"); $r->execute([$id]); $r=$r->fetch();
    if ($r && $r['image']) @unlink(UPLOAD_DIR.'rice/'.$r['image']);
    db()->prepare("DELETE FROM rice_products WHERE id=?")->execute([$id]);
    flash('Rice item deleted.','success'); redirect(SITE_URL.'/admin/rice.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $nameEn = sanitizeText($_POST['name_en']??''); $nameBn = sanitizeText($_POST['name_bn']??'');
    $descEn = sanitizeText($_POST['desc_en']??''); $descBn = sanitizeText($_POST['desc_bn']??'');
    $origEn = sanitizeText($_POST['origin_en']??''); $origBn = sanitizeText($_POST['origin_bn']??'');
    $active=(int)($_POST['active']??1); $sort=(int)($_POST['sort_order']??0); $editId=(int)($_POST['edit_id']??0);
    $oldImg=''; if($editId){$r=db()->prepare("SELECT image FROM rice_products WHERE id=?");$r->execute([$editId]);$r=$r->fetch();$oldImg=$r['image']??'';}
    $image=$oldImg; if(!empty($_FILES['image']['name'])){$new=processUploadedImage($_FILES['image'],'rice','rice',$oldImg);if($new)$image=$new;}
    if($editId){db()->prepare("UPDATE rice_products SET name_en=?,name_bn=?,desc_en=?,desc_bn=?,origin_en=?,origin_bn=?,image=?,active=?,sort_order=? WHERE id=?")->execute([$nameEn,$nameBn,$descEn,$descBn,$origEn,$origBn,$image,$active,$sort,$editId]);flash('Updated.','success');}
    else{db()->prepare("INSERT INTO rice_products (name_en,name_bn,desc_en,desc_bn,origin_en,origin_bn,image,active,sort_order) VALUES (?,?,?,?,?,?,?,?,?)")->execute([$nameEn,$nameBn,$descEn,$descBn,$origEn,$origBn,$image,$active,$sort]);flash('Added.','success');}
    redirect(SITE_URL.'/admin/rice.php');
}
$editing=null; if($action==='edit'&&$id){$s=db()->prepare("SELECT * FROM rice_products WHERE id=?");$s->execute([$id]);$editing=$s->fetch();}
$items=db()->query("SELECT * FROM rice_products ORDER BY sort_order")->fetchAll();
require_once __DIR__ . '/partials/admin_header.php';
?>
<div class="admin-page-header"><h1>🌾 Rice Showcase</h1><p>Manage premium rice varieties. Images: 700×500px.</p></div>
<div class="admin-panel">
  <h2 class="admin-section-title"><?= $editing?'Edit Rice Item':'Add Rice Item'?></h2>
  <form method="POST" enctype="multipart/form-data" class="admin-form">
    <input type="hidden" name="csrf_token" value="<?= csrf_token()?>">
    <input type="hidden" name="edit_id" value="<?= $editing['id']??0?>">
    <div class="form-row">
      <div class="form-group"><label>Name (EN) *</label><input type="text" name="name_en" class="form-input" required value="<?= e($editing['name_en']??'')?>"></div>
      <div class="form-group"><label>Name (BN)</label><input type="text" name="name_bn" class="form-input" value="<?= e($editing['name_bn']??'')?>"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Origin (EN)</label><input type="text" name="origin_en" class="form-input" value="<?= e($editing['origin_en']??'')?>"></div>
      <div class="form-group"><label>Origin (BN)</label><input type="text" name="origin_bn" class="form-input" value="<?= e($editing['origin_bn']??'')?>"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Description (EN)</label><textarea name="desc_en" class="form-textarea" rows="4"><?= e($editing['desc_en']??'')?></textarea></div>
      <div class="form-group"><label>Description (BN)</label><textarea name="desc_bn" class="form-textarea" rows="4"><?= e($editing['desc_bn']??'')?></textarea></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Image (700×500px)</label><?php if($editing&&$editing['image']):?><div class="current-media"><img src="<?= imgUrl($editing['image'],'rice','rice')?>" style="max-height:60px;border-radius:4px;"><small>Current</small></div><?php endif;?><input type="file" name="image" class="form-input" accept="image/*"></div>
      <div class="form-group"><label>Status</label><select name="active" class="form-input"><option value="1" <?=($editing['active']??1)==1?'selected':''?>>Active</option><option value="0" <?=($editing['active']??1)==0?'selected':''?>>Inactive</option></select><label style="margin-top:12px">Sort</label><input type="number" name="sort_order" class="form-input" value="<?=(int)($editing['sort_order']??0)?>"></div>
    </div>
    <div class="form-actions"><button type="submit" class="btn btn-primary"><?= $editing?'💾 Update':'➕ Add'?></button><?php if($editing):?><a href="<?= SITE_URL?>/admin/rice.php" class="btn btn-ghost">Cancel</a><?php endif;?></div>
  </form>
</div>
<div class="admin-panel">
  <h2 class="admin-section-title">All Rice Items</h2>
  <table class="admin-table">
    <thead><tr><th>Img</th><th>Name</th><th>Origin</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach($items as $it):?>
        <tr><td><img src="<?= imgUrl($it['image']??'','rice','rice')?>" style="width:64px;height:44px;object-fit:cover;border-radius:4px;"></td><td><?= e($it['name_en'])?><br><small><?= e($it['name_bn'])?></small></td><td><?= e($it['origin_en'])?:'—'?></td><td><span class="badge <?=$it['active']?'badge-green':'badge-gray'?>"><?=$it['active']?'Active':'Off'?></span></td><td class="table-actions"><a href="?action=edit&id=<?=$it['id']?>" class="btn-mini">Edit</a><a href="?action=delete&id=<?=$it['id']?>&csrf_token=<?= csrf_token()?>" class="btn-mini btn-danger" onclick="return confirm('Delete?')">Del</a></td></tr>
      <?php endforeach;?>
    </tbody>
  </table>
</div>
<?php require_once __DIR__ . '/partials/admin_footer.php'; ?>
