<?php
require_once __DIR__.'/auth.php';
requireAdmin();
$action=$_GET['action']??'list'; $id=(int)($_GET['id']??0);
if($action==='delete'&&$id){
    $r=db()->prepare("SELECT image FROM promotions WHERE id=?");$r->execute([$id]);$r=$r->fetch();
    if($r&&$r['image'])@unlink(UPLOAD_DIR.'promotions/'.$r['image']);
    db()->prepare("DELETE FROM promotions WHERE id=?")->execute([$id]);
    logAction('Delete Promotion','ID:'.$id); flash('Deleted.','success'); redirect(SITE_URL.'/admin/promotions.php');
}
if($_SERVER['REQUEST_METHOD']==='POST'&&csrf_verify()){
    $tEn=sanitizeText($_POST['title_en']??'');$tBn=sanitizeText($_POST['title_bn']??'');
    $dEn=sanitizeText($_POST['desc_en']??'');$dBn=sanitizeText($_POST['desc_bn']??'');
    $bEn=sanitizeText($_POST['badge_en']??'');$bBn=sanitizeText($_POST['badge_bn']??'');
    $link=sanitizeText($_POST['link']??'');
    $active=(int)($_POST['active']??1);$sort=(int)($_POST['sort_order']??0);
    $start=$_POST['start_date']??null;$end=$_POST['end_date']??null;
    $editId=(int)($_POST['edit_id']??0);
    $oldImg='';if($editId){$r=db()->prepare("SELECT image FROM promotions WHERE id=?");$r->execute([$editId]);$r=$r->fetch();$oldImg=$r['image']??'';}
    $image=$oldImg;if(!empty($_FILES['image']['name'])){$new=processUploadedImage($_FILES['image'],'promo','promotions',$oldImg);if($new)$image=$new;}
    if($editId){
        db()->prepare("UPDATE promotions SET title_en=?,title_bn=?,desc_en=?,desc_bn=?,badge_en=?,badge_bn=?,image=?,link=?,active=?,sort_order=?,start_date=?,end_date=? WHERE id=?")
            ->execute([$tEn,$tBn,$dEn,$dBn,$bEn,$bBn,$image,$link,$active,$sort,$start?:null,$end?:null,$editId]);
        flash('Updated.','success');
    } else {
        db()->prepare("INSERT INTO promotions (title_en,title_bn,desc_en,desc_bn,badge_en,badge_bn,image,link,active,sort_order,start_date,end_date) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$tEn,$tBn,$dEn,$dBn,$bEn,$bBn,$image,$link,$active,$sort,$start?:null,$end?:null]);
        flash('Added.','success');
    }
    logAction($editId?'Update Promotion':'Add Promotion'); redirect(SITE_URL.'/admin/promotions.php');
}
$editing=null;if($action==='edit'&&$id){$s=db()->prepare("SELECT * FROM promotions WHERE id=?");$s->execute([$id]);$editing=$s->fetch();}
$items=db()->query("SELECT * FROM promotions ORDER BY sort_order")->fetchAll();
require_once __DIR__.'/partials/admin_header.php';
?>
<div class="admin-page-header"><h1>🎯 Promotions & Campaigns</h1></div>
<div class="admin-panel">
  <h2 class="admin-section-title"><?= $editing?'Edit':'Add'?> Promotion</h2>
  <form method="POST" enctype="multipart/form-data" class="admin-form">
    <input type="hidden" name="csrf_token" value="<?= csrf_token()?>">
    <input type="hidden" name="edit_id" value="<?= $editing['id']??0?>">
    <div class="form-row">
      <div class="form-group"><label>Title (EN) *</label><input type="text" name="title_en" class="form-input" required value="<?= e($editing['title_en']??'')?>"></div>
      <div class="form-group"><label>Title (BN)</label><input type="text" name="title_bn" class="form-input" value="<?= e($editing['title_bn']??'')?>"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Badge Label (EN, e.g. "20% OFF")</label><input type="text" name="badge_en" class="form-input" value="<?= e($editing['badge_en']??'')?>"></div>
      <div class="form-group"><label>Badge Label (BN)</label><input type="text" name="badge_bn" class="form-input" value="<?= e($editing['badge_bn']??'')?>"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Description (EN)</label><textarea name="desc_en" class="form-textarea" rows="3"><?= e($editing['desc_en']??'')?></textarea></div>
      <div class="form-group"><label>Description (BN)</label><textarea name="desc_bn" class="form-textarea" rows="3"><?= e($editing['desc_bn']??'')?></textarea></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Image (800×480px)</label><?php if($editing&&$editing['image']):?><div class="current-media"><img src="<?= imgUrl($editing['image'],'promotions','promo')?>" style="max-height:50px;border-radius:4px;"><small>Current</small></div><?php endif;?><input type="file" name="image" class="form-input" accept="image/*"></div>
      <div class="form-group"><label>Link URL (optional)</label><input type="url" name="link" class="form-input" value="<?= e($editing['link']??'')?>" placeholder="https://..."></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Start Date</label><input type="date" name="start_date" class="form-input" value="<?= e($editing['start_date']??'')?>"></div>
      <div class="form-group"><label>End Date</label><input type="date" name="end_date" class="form-input" value="<?= e($editing['end_date']??'')?>"></div>
      <div class="form-group"><label>Status</label><select name="active" class="form-input"><option value="1" <?=($editing['active']??1)==1?'selected':''?>>Active</option><option value="0" <?=($editing['active']??1)==0?'selected':''?>>Inactive</option></select></div>
      <div class="form-group"><label>Sort</label><input type="number" name="sort_order" class="form-input" value="<?=(int)($editing['sort_order']??0)?>"></div>
    </div>
    <div class="form-actions"><button type="submit" class="btn btn-primary"><?= $editing?'💾 Update':'➕ Add'?></button><?php if($editing):?><a href="<?= SITE_URL?>/admin/promotions.php" class="btn btn-ghost">Cancel</a><?php endif;?></div>
  </form>
</div>
<div class="admin-panel">
  <h2 class="admin-section-title">All Promotions (<?= count($items)?>)</h2>
  <table class="admin-table"><thead><tr><th>Title</th><th>Badge</th><th>Dates</th><th>Status</th><th>Actions</th></tr></thead>
  <tbody><?php foreach($items as $it):?><tr><td><?= e($it['title_en'])?></td><td><?= e($it['badge_en'])?:'—'?></td><td><?= $it['start_date']?date('d M y',strtotime($it['start_date'])):'∞'?> → <?= $it['end_date']?date('d M y',strtotime($it['end_date'])):'∞'?></td><td><span class="badge <?=$it['active']?'badge-green':'badge-gray'?>"><?=$it['active']?'Active':'Off'?></span></td><td class="table-actions"><a href="?action=edit&id=<?=$it['id']?>" class="btn-mini">Edit</a><a href="?action=delete&id=<?=$it['id']?>&csrf_token=<?= csrf_token()?>" class="btn-mini btn-danger" onclick="return confirm('Delete?')">Del</a></td></tr><?php endforeach;?></tbody></table>
</div>
<?php require_once __DIR__.'/partials/admin_footer.php'; ?>
