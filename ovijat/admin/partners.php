<?php
require_once __DIR__.'/auth.php'; requireAdmin();
$action=$_GET['action']??'list'; $id=(int)($_GET['id']??0);
if($action==='delete'&&$id){
    $r=db()->prepare("SELECT logo FROM partners WHERE id=?");$r->execute([$id]);$r=$r->fetch();
  
    db()->prepare("DELETE FROM partners WHERE id=?")->execute([$id]);
      if($r&&$r['logo'])@unlink(UPLOAD_DIR.'partners/'.$r['logo']);
    logAction('Delete Partner','ID:'.$id); flash('Deleted.','success'); redirect(SITE_URL.'/admin/partners.php');
}
if($_SERVER['REQUEST_METHOD']==='POST'&&csrf_verify()){
    $name=sanitizeText($_POST['name']??'');
    $website=sanitizeText($_POST['website']??'');
    $active=(int)($_POST['active']??1);$sort=(int)($_POST['sort_order']??0);
    $editId=(int)($_POST['edit_id']??0);
    $oldImg='';if($editId){$r=db()->prepare("SELECT logo FROM partners WHERE id=?");$r->execute([$editId]);$r=$r->fetch();$oldImg=$r['logo']??'';}
    $logo=$oldImg;if(!empty($_FILES['logo']['name'])){$new=processUploadedImage($_FILES['logo'],'partner','partners',$oldImg);if($new)$logo=$new;}
    if($editId){db()->prepare("UPDATE partners SET name=?,website=?,logo=?,active=?,sort_order=? WHERE id=?")->execute([$name,$website,$logo,$active,$sort,$editId]);flash('Updated.','success');}
    else{db()->prepare("INSERT INTO partners (name,website,logo,active,sort_order) VALUES (?,?,?,?,?)")->execute([$name,$website,$logo,$active,$sort]);flash('Added.','success');}
    logAction($editId?'Update Partner':'Add Partner'); redirect(SITE_URL.'/admin/partners.php');
}
$editing=null;if($action==='edit'&&$id){$s=db()->prepare("SELECT * FROM partners WHERE id=?");$s->execute([$id]);$editing=$s->fetch();}
$items=db()->query("SELECT * FROM partners ORDER BY sort_order")->fetchAll();
require_once __DIR__.'/partials/admin_header.php';
?>
<div class="admin-page-header"><h1>🤝 Partners & Distributors</h1><p>Logos scroll on homepage. Image: 300×120px.</p></div>
<div class="admin-panel">
  <h2 class="admin-section-title"><?= $editing?'Edit':'Add'?> Partner</h2>
  <form method="POST" enctype="multipart/form-data" class="admin-form">
    <input type="hidden" name="csrf_token" value="<?= csrf_token()?>">
    <input type="hidden" name="edit_id" value="<?= $editing['id']??0?>">
    <div class="form-row">
      <div class="form-group"><label>Company Name *</label><input type="text" name="name" class="form-input" required value="<?= e($editing['name']??'')?>"></div>
      <div class="form-group"><label>Website URL</label><input type="url" name="website" class="form-input" value="<?= e($editing['website']??'')?>" placeholder="https://..."></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Logo (300×120px, auto-resized)</label><?php if($editing&&$editing['logo']):?><div class="current-media"><img src="<?= imgUrl($editing['logo'],'partners','partner')?>" style="max-height:40px;"><small>Current</small></div><?php endif;?><input type="file" name="logo" class="form-input" accept="image/*"></div>
      <div class="form-group"><label>Status</label><select name="active" class="form-input"><option value="1" <?=($editing['active']??1)==1?'selected':''?>>Active</option><option value="0" <?=($editing['active']??1)==0?'selected':''?>>Inactive</option></select><label style="margin-top:10px">Sort Order</label><input type="number" name="sort_order" class="form-input" value="<?=(int)($editing['sort_order']??0)?>"></div>
    </div>
    <div class="form-actions"><button type="submit" class="btn btn-primary"><?= $editing?'💾 Update':'➕ Add Partner'?></button><?php if($editing):?><a href="<?= SITE_URL?>/admin/partners.php" class="btn btn-ghost">Cancel</a><?php endif;?></div>
  </form>
</div>
<div class="admin-panel">
  <h2 class="admin-section-title">All Partners (<?= count($items)?>)</h2>
  <table class="admin-table"><thead><tr><th>Logo</th><th>Name</th><th>Website</th><th>Status</th><th>Actions</th></tr></thead>
  <tbody><?php foreach($items as $p):?><tr><td><?= $p['logo']?"<img src='".imgUrl($p['logo'],'partners','partner')."' style='height:32px;object-fit:contain;'>":"—"?></td><td><?= e($p['name'])?></td><td><?= $p['website']?"<a href='".e($p['website'])."' target='_blank'>↗</a>":'—'?></td><td><span class="badge <?=$p['active']?'badge-green':'badge-gray'?>"><?=$p['active']?'Active':'Off'?></span></td><td class="table-actions"><a href="?action=edit&id=<?=$p['id']?>" class="btn-mini">Edit</a><a href="?action=delete&id=<?=$p['id']?>&csrf_token=<?= csrf_token()?>" class="btn-mini btn-danger" onclick="return confirm('Delete?')">Del</a></td></tr><?php endforeach;?></tbody></table>
</div>
<?php require_once __DIR__.'/partials/admin_footer.php'; ?>
