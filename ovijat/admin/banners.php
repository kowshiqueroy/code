<?php
/**
 * Admin Banners — with customizable show/hide title/buttons.
 * Newest banner listed first, last uploaded shown first on public.
 */
require_once __DIR__.'/auth.php';
requireAdmin();
$action=$_GET['action']??'list'; $id=(int)($_GET['id']??0);

if($action==='delete'&&$id){
    $r=db()->prepare("SELECT image FROM banners WHERE id=?");$r->execute([$id]);$r=$r->fetch();
    if($r&&$r['image'])@unlink(UPLOAD_DIR.'banners/'.$r['image']);
    db()->prepare("DELETE FROM banners WHERE id=?")->execute([$id]);
    logAction('Delete Banner','ID:'.$id); flash('Banner deleted.','success'); redirect(SITE_URL.'/admin/banners.php');
}

if($_SERVER['REQUEST_METHOD']==='POST'&&csrf_verify()){
    $tEn=sanitizeText($_POST['title_en']??''); $tBn=sanitizeText($_POST['title_bn']??'');
    $sEn=sanitizeText($_POST['subtitle_en']??''); $sBn=sanitizeText($_POST['subtitle_bn']??'');
    $link=sanitizeText($_POST['link']??'');
    $active=(int)($_POST['active']??1);
    $sort=(int)($_POST['sort_order']??0);
    $hideButtons=(int)($_POST['hide_buttons']??0);
    $showTitle= isset($_POST['show_title'])?1:0;
    $editId=(int)($_POST['edit_id']??0);

    $oldImg='';
    if($editId){$r=db()->prepare("SELECT image FROM banners WHERE id=?");$r->execute([$editId]);$r=$r->fetch();$oldImg=$r['image']??'';}
    $image=$oldImg;
    if(!empty($_FILES['image']['name'])){$new=processUploadedImage($_FILES['image'],'banner','banners',$oldImg);if($new)$image=$new;}

    if($editId){
        db()->prepare("UPDATE banners SET title_en=?,title_bn=?,subtitle_en=?,subtitle_bn=?,image=?,link=?,active=?,sort_order=?,hide_buttons=?,show_title=? WHERE id=?")
            ->execute([$tEn,$tBn,$sEn,$sBn,$image,$link,$active,$sort,$hideButtons,$showTitle,$editId]);
        logAction('Update Banner','ID:'.$editId); flash('Updated.','success');
    } else {
        db()->prepare("INSERT INTO banners (title_en,title_bn,subtitle_en,subtitle_bn,image,link,active,sort_order,hide_buttons,show_title) VALUES (?,?,?,?,?,?,?,?,?,?)")
            ->execute([$tEn,$tBn,$sEn,$sBn,$image,$link,$active,$sort,$hideButtons,$showTitle]);
        logAction('Add Banner'); flash('Added.','success');
    }
    redirect(SITE_URL.'/admin/banners.php');
}

// Add columns if not exist (idempotent migration)
try{db()->exec("ALTER TABLE banners ADD COLUMN hide_buttons TINYINT(1) DEFAULT 0, ADD COLUMN show_title TINYINT(1) DEFAULT 1");}catch(Exception $e){}

$editing=null; if($action==='edit'&&$id){$s=db()->prepare("SELECT * FROM banners WHERE id=?");$s->execute([$id]);$editing=$s->fetch();}
$banners=db()->query("SELECT * FROM banners ORDER BY id DESC")->fetchAll();
require_once __DIR__.'/partials/admin_header.php';
?>
<div class="admin-page-header"><h1>🖼️ Hero Banners</h1><p>Newest banner shows first on homepage. Only active banners appear. Image: 1600×640px (auto-cropped).</p></div>
<div class="admin-panel">
  <h2 class="admin-section-title"><?= $editing?'Edit Banner':'Add New Banner' ?></h2>
  <form method="POST" enctype="multipart/form-data" class="admin-form">
    <input type="hidden" name="csrf_token" value="<?= csrf_token()?>">
    <input type="hidden" name="edit_id" value="<?= $editing['id']??0?>">
    <div class="form-row">
      <div class="form-group"><label>Title (EN) *</label><input type="text" name="title_en" class="form-input" required value="<?= e($editing['title_en']??'')?>"></div>
      <div class="form-group"><label>Title (BN)</label><input type="text" name="title_bn" class="form-input" value="<?= e($editing['title_bn']??'')?>"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Subtitle (EN)</label><input type="text" name="subtitle_en" class="form-input" value="<?= e($editing['subtitle_en']??'')?>"></div>
      <div class="form-group"><label>Subtitle (BN)</label><input type="text" name="subtitle_bn" class="form-input" value="<?= e($editing['subtitle_bn']??'')?>"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Banner Image (1600×640px, auto-cropped)</label><?php if($editing&&$editing['image']):?><div class="current-media"><img src="<?= imgUrl($editing['image'],'banners','banner')?>" style="max-height:55px;border-radius:4px;"><small>Current</small></div><?php endif;?><input type="file" name="image" class="form-input" accept="image/*" <?= !$editing?'required':''?>></div>
      <div class="form-group"><label>Link URL (optional)</label><input type="url" name="link" class="form-input" value="<?= e($editing['link']??'')?>" placeholder="https://..."></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Status</label><select name="active" class="form-input"><option value="1" <?=($editing['active']??1)==1?'selected':''?>>Active (shows on site)</option><option value="0" <?=($editing['active']??1)==0?'selected':''?>>Inactive (hidden)</option></select></div>
      <div class="form-group"><label>Sort Order</label><input type="number" name="sort_order" class="form-input" value="<?=(int)($editing['sort_order']??0)?>"></div>
    </div>
    <div class="form-row">
      <div class="form-group" style="flex-direction:row;align-items:center;gap:1rem;flex-wrap:wrap">
        <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;font-weight:600">
          <input type="checkbox" name="show_title" value="1" <?= ($editing['show_title']??1)?'checked':'' ?>> Show Title &amp; Subtitle on Slide
        </label>
        <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;font-weight:600">
          <input type="checkbox" name="hide_buttons" value="1" <?= ($editing['hide_buttons']??0)?'checked':'' ?>> Hide CTA Buttons on this Slide
        </label>
      </div>
    </div>
    <div class="form-actions"><button type="submit" class="btn btn-primary"><?= $editing?'💾 Update':'➕ Add Banner'?></button><?php if($editing):?><a href="<?= SITE_URL?>/admin/banners.php" class="btn btn-ghost">Cancel</a><?php endif;?></div>
  </form>
</div>
<div class="admin-panel">
  <h2 class="admin-section-title">All Banners — newest first (<?= count($banners)?>)</h2>
  <?php if($banners): ?>
    <table class="admin-table">
      <thead><tr><th>Preview</th><th>Title</th><th>Title/Btns Visible</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach($banners as $b): ?>
          <tr>
            <td><img src="<?= imgUrl($b['image'],'banners','banner')?>" style="height:46px;width:90px;object-fit:cover;border-radius:4px;"></td>
            <td><strong><?= e($b['title_en'])?></strong><br><small><?= e($b['title_bn'])?></small></td>
            <td>
              <span class="badge <?=($b['show_title']??1)?'badge-green':'badge-gray'?>"><?=($b['show_title']??1)?'Title ✓':'Title ✗'?></span>
              <span class="badge <?=($b['hide_buttons']??0)?'badge-gray':'badge-green'?>"><?=($b['hide_buttons']??0)?'Btns ✗':'Btns ✓'?></span>
            </td>
            <td><span class="badge <?=$b['active']?'badge-green':'badge-gray'?>"><?=$b['active']?'Active':'Off'?></span></td>
            <td class="table-actions">
              <a href="?action=edit&id=<?=$b['id']?>" class="btn-mini">Edit</a>
              <a href="?action=delete&id=<?=$b['id']?>&csrf_token=<?= csrf_token()?>" class="btn-mini btn-danger" onclick="return confirm('Delete?')">Del</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?><p class="empty-msg">No banners yet.</p><?php endif; ?>
</div>
<?php require_once __DIR__.'/partials/admin_footer.php'; ?>
