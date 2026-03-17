<?php
require_once __DIR__ . '/auth.php';
requireAdmin();
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

if ($action === 'delete' && $id) {
    db()->prepare("DELETE FROM global_presence WHERE id=?")->execute([$id]);
    flash('Deleted.','success'); redirect(SITE_URL.'/admin/global.php');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $cEn=sanitizeText($_POST['country_en']??'');$cBn=sanitizeText($_POST['country_bn']??'');
    $flag=sanitizeText($_POST['flag_emoji']??'');$active=(int)($_POST['active']??1);$editId=(int)($_POST['edit_id']??0);
    if($editId){db()->prepare("UPDATE global_presence SET country_en=?,country_bn=?,flag_emoji=?,active=? WHERE id=?")->execute([$cEn,$cBn,$flag,$active,$editId]);flash('Updated.','success');}
    else{db()->prepare("INSERT INTO global_presence (country_en,country_bn,flag_emoji,active) VALUES (?,?,?,?)")->execute([$cEn,$cBn,$flag,$active]);flash('Added.','success');}
    redirect(SITE_URL.'/admin/global.php');
}
$editing=null;if($action==='edit'&&$id){$s=db()->prepare("SELECT * FROM global_presence WHERE id=?");$s->execute([$id]);$editing=$s->fetch();}
$countries=db()->query("SELECT * FROM global_presence ORDER BY id")->fetchAll();
require_once __DIR__ . '/partials/admin_header.php';
?>
<div class="admin-page-header"><h1>🌍 Global Presence</h1><p>Manage export destination countries.</p></div>
<div class="admin-panel">
  <h2 class="admin-section-title"><?= $editing?'Edit':'Add'?> Country</h2>
  <form method="POST" class="admin-form">
    <input type="hidden" name="csrf_token" value="<?= csrf_token()?>">
    <input type="hidden" name="edit_id" value="<?= $editing['id']??0?>">
    <div class="form-row">
      <div class="form-group"><label>Country (EN) *</label><input type="text" name="country_en" class="form-input" required value="<?= e($editing['country_en']??'')?>"></div>
      <div class="form-group"><label>Country (BN)</label><input type="text" name="country_bn" class="form-input" value="<?= e($editing['country_bn']??'')?>"></div>
      <div class="form-group"><label>Flag Emoji</label><input type="text" name="flag_emoji" class="form-input" maxlength="10" value="<?= e($editing['flag_emoji']??'')?>" placeholder="🇧🇩"></div>
      <div class="form-group"><label>Status</label><select name="active" class="form-input"><option value="1" <?=($editing['active']??1)==1?'selected':''?>>Active</option><option value="0" <?=($editing['active']??1)==0?'selected':''?>>Inactive</option></select></div>
    </div>
    <div class="form-actions"><button type="submit" class="btn btn-primary"><?= $editing?'💾 Update':'➕ Add'?></button><?php if($editing):?><a href="<?= SITE_URL?>/admin/global.php" class="btn btn-ghost">Cancel</a><?php endif;?></div>
  </form>
</div>
<div class="admin-panel">
  <h2 class="admin-section-title">All Countries (<?= count($countries)?>)</h2>
  <table class="admin-table"><thead><tr><th>Flag</th><th>Country (EN)</th><th>Country (BN)</th><th>Status</th><th>Actions</th></tr></thead>
  <tbody><?php foreach($countries as $c):?><tr><td><?= e($c['flag_emoji'])?></td><td><?= e($c['country_en'])?></td><td><?= e($c['country_bn'])?></td><td><span class="badge <?=$c['active']?'badge-green':'badge-gray'?>"><?=$c['active']?'Active':'Off'?></span></td><td class="table-actions"><a href="?action=edit&id=<?=$c['id']?>" class="btn-mini">Edit</a><a href="?action=delete&id=<?=$c['id']?>&csrf_token=<?= csrf_token()?>" class="btn-mini btn-danger" onclick="return confirm('Delete?')">Del</a></td></tr><?php endforeach;?></tbody></table>
</div>
<?php require_once __DIR__ . '/partials/admin_footer.php'; ?>
