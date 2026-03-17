<?php
require_once __DIR__ . '/auth.php';
requireAdmin();
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

if ($action === 'delete' && $id) {
    $r=db()->prepare("SELECT logo FROM sister_concerns WHERE id=?");$r->execute([$id]);$r=$r->fetch();
    if($r&&$r['logo'])@unlink(UPLOAD_DIR.'concerns/'.$r['logo']);
    db()->prepare("DELETE FROM sister_concerns WHERE id=?")->execute([$id]);
    flash('Deleted.','success');redirect(SITE_URL.'/admin/concerns.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $nEn=sanitizeText($_POST['name_en']??'');$nBn=sanitizeText($_POST['name_bn']??'');
    $dEn=sanitizeText($_POST['desc_en']??'');$dBn=sanitizeText($_POST['desc_bn']??'');
    $web=sanitizeText($_POST['website']??'');$active=(int)($_POST['active']??1);$sort=(int)($_POST['sort_order']??0);$editId=(int)($_POST['edit_id']??0);
    $oldImg='';if($editId){$r=db()->prepare("SELECT logo FROM sister_concerns WHERE id=?");$r->execute([$editId]);$r=$r->fetch();$oldImg=$r['logo']??'';}
    $logo=$oldImg;if(!empty($_FILES['logo']['name'])){$new=processUploadedImage($_FILES['logo'],'concern','concerns',$oldImg);if($new)$logo=$new;}
    if($editId){db()->prepare("UPDATE sister_concerns SET name_en=?,name_bn=?,desc_en=?,desc_bn=?,logo=?,website=?,active=?,sort_order=? WHERE id=?")->execute([$nEn,$nBn,$dEn,$dBn,$logo,$web,$active,$sort,$editId]);flash('Updated.','success');}
    else{db()->prepare("INSERT INTO sister_concerns (name_en,name_bn,desc_en,desc_bn,logo,website,active,sort_order) VALUES (?,?,?,?,?,?,?,?)")->execute([$nEn,$nBn,$dEn,$dBn,$logo,$web,$active,$sort]);flash('Added.','success');}
    redirect(SITE_URL.'/admin/concerns.php');
}

$editing=null;if($action==='edit'&&$id){$s=db()->prepare("SELECT * FROM sister_concerns WHERE id=?");$s->execute([$id]);$editing=$s->fetch();}
$items=db()->query("SELECT * FROM sister_concerns ORDER BY sort_order")->fetchAll();
require_once __DIR__ . '/partials/admin_header.php';
?>
<div class="admin-page-header"><h1>🏭 Sister Concerns</h1><p>Manage companies under the Ovijat Group umbrella. Logo: 300×180px.</p></div>
<div class="admin-panel">
  <h2 class="admin-section-title"><?= $editing?'Edit':'Add'?> Sister Concern</h2>
  <form method="POST" enctype="multipart/form-data" class="admin-form">
    <input type="hidden" name="csrf_token" value="<?= csrf_token()?>">
    <input type="hidden" name="edit_id" value="<?= $editing['id']??0?>">
    <div class="form-row">
      <div class="form-group"><label>Name (EN) *</label><input type="text" name="name_en" class="form-input" required value="<?= e($editing['name_en']??'')?>"></div>
      <div class="form-group"><label>Name (BN)</label><input type="text" name="name_bn" class="form-input" value="<?= e($editing['name_bn']??'')?>"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Description (EN)</label><textarea name="desc_en" class="form-textarea" rows="3"><?= e($editing['desc_en']??'')?></textarea></div>
      <div class="form-group"><label>Description (BN)</label><textarea name="desc_bn" class="form-textarea" rows="3"><?= e($editing['desc_bn']??'')?></textarea></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Logo (300×180px)</label><?php if($editing&&$editing['logo']):?><div class="current-media"><img src="<?= imgUrl($editing['logo'],'concerns','concern')?>" style="max-height:50px;"><small>Current</small></div><?php endif;?><input type="file" name="logo" class="form-input" accept="image/*"></div>
      <div class="form-group"><label>Website URL</label><input type="url" name="website" class="form-input" value="<?= e($editing['website']??'')?>" placeholder="https://..."><label style="margin-top:12px">Status</label><select name="active" class="form-input"><option value="1" <?=($editing['active']??1)==1?'selected':''?>>Active</option><option value="0" <?=($editing['active']??1)==0?'selected':''?>>Inactive</option></select></div>
    </div>
    <div class="form-group"><label>Sort Order</label><input type="number" name="sort_order" class="form-input" value="<?=(int)($editing['sort_order']??0)?>" style="max-width:120px"></div>
    <div class="form-actions"><button type="submit" class="btn btn-primary"><?= $editing?'💾 Update':'➕ Add'?></button><?php if($editing):?><a href="<?= SITE_URL?>/admin/concerns.php" class="btn btn-ghost">Cancel</a><?php endif;?></div>
  </form>
</div>
<div class="admin-panel">
  <h2 class="admin-section-title">All Concerns</h2>
  <table class="admin-table">
    <thead><tr><th>Logo</th><th>Name</th><th>Website</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach($items as $c):?>
        <tr><td><img src="<?= imgUrl($c['logo']??'','concerns','concern')?>" style="height:36px;object-fit:contain;border-radius:4px;background:#f5f5f5;padding:4px;"></td><td><?= e($c['name_en'])?><br><small><?= e($c['name_bn'])?></small></td><td><?= $c['website']?"<a href='".e($c['website'])."' target='_blank'>↗ Link</a>":'—'?></td><td><span class="badge <?=$c['active']?'badge-green':'badge-gray'?>"><?=$c['active']?'Active':'Off'?></span></td><td class="table-actions"><a href="?action=edit&id=<?=$c['id']?>" class="btn-mini">Edit</a><a href="?action=delete&id=<?=$c['id']?>&csrf_token=<?= csrf_token()?>" class="btn-mini btn-danger" onclick="return confirm('Delete?')">Del</a></td></tr>
      <?php endforeach;?>
    </tbody>
  </table>
</div>
<?php require_once __DIR__ . '/partials/admin_footer.php'; ?>
