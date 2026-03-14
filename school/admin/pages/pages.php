<?php // admin/pages/pages.php
$action=$_GET['action']??'list';$id=(int)($_GET['id']??0);$db=getDB();
if($_SERVER['REQUEST_METHOD']==='POST'){
    $d=['slug'=>preg_replace('/[^a-z0-9_\-]/','',strtolower($_POST['slug']??'')),'title_en'=>sanitize($_POST['title_en']??''),'title_bn'=>sanitize($_POST['title_bn']??''),'content_en'=>$_POST['content_en']??'','content_bn'=>$_POST['content_bn']??'','template'=>sanitize($_POST['template']??'default'),'is_active'=>isset($_POST['is_active'])?1:0,'sort_order'=>(int)($_POST['sort_order']??0)];
    if($d['title_en']&&$d['slug']){if($id){$stmt=$db->prepare("UPDATE pages SET slug=?,title_en=?,title_bn=?,content_en=?,content_bn=?,template=?,is_active=?,sort_order=? WHERE id=?");$stmt->execute([...array_values($d),$id]);flash('Page updated.','success');}else{$stmt=$db->prepare("INSERT INTO pages (slug,title_en,title_bn,content_en,content_bn,template,is_active,sort_order,created_by) VALUES (?,?,?,?,?,?,?,?,?)");$stmt->execute([...array_values($d),$_SESSION['user_id']]);flash('Page created.','success');}redirect(ADMIN_PATH.'?section=pages');}
}
if($action==='delete'&&$id){$db->prepare("DELETE FROM pages WHERE id=?")->execute([$id]);flash('Deleted.','success');redirect(ADMIN_PATH.'?section=pages');}
$row=null;if($id){$stmt=$db->prepare("SELECT * FROM pages WHERE id=?");$stmt->execute([$id]);$row=$stmt->fetch();}
$list=$db->query("SELECT * FROM pages ORDER BY sort_order,title_en")->fetchAll();
?>
<div class="acard"><div class="acard-header"><div class="acard-title">📄 CMS Pages</div><?php if($action==='list'):?><a href="?section=pages&action=add" class="btn btn-primary">+ New Page</a><?php else:?><a href="?section=pages" class="btn btn-light btn-sm">← Back</a><?php endif;?></div><div class="acard-body">
<?php if($action==='list'):?>
<table class="atable"><thead><tr><th>Slug</th><th>Title (EN)</th><th>Template</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php foreach($list as $p):?><tr><td><code><?= h($p['slug'])?></code></td><td><?= h($p['title_en'])?></td><td><?= h($p['template'])?></td><td><?= $p['is_active']?'<span class="badge badge-success">Active</span>':'<span class="badge badge-gray">Hidden</span>'?></td><td><a href="<?= BASE_URL?>/?page=<?= h($p['slug'])?>" class="btn btn-xs btn-light" target="_blank">🌐</a> <a href="?section=pages&action=edit&id=<?= $p['id']?>" class="btn btn-sm btn-light">✏️</a> <a href="?section=pages&action=delete&id=<?= $p['id']?>" class="btn btn-sm btn-danger" data-confirm="Delete page?">🗑️</a></td></tr><?php endforeach;?>
</tbody></table>
<?php else:?>
<form method="POST" class="aform">
<div class="form-row">
<div class="form-group"><label>Slug (URL key) <span class="req">*</span></label><input type="text" name="slug" value="<?= h($row['slug']??'')?>" placeholder="about-us" pattern="[a-z0-9_\-]+" <?= $row?'readonly':''?> required><span class="hint">Used in URL: ?page=your-slug</span></div>
<div class="form-group"><label>Template</label><select name="template"><option value="default" <?= ($row['template']??'default')==='default'?'selected':''?>>Default</option><option value="about" <?= ($row['template']??'')==='about'?'selected':''?>>About</option><option value="contact" <?= ($row['template']??'')==='contact'?'selected':''?>>Contact</option></select></div>
<div class="form-group"><label>Title (English) <span class="req">*</span></label><input type="text" name="title_en" value="<?= h($row['title_en']??'')?>" required></div>
<div class="form-group"><label>শিরোনাম (বাংলা)</label><input type="text" name="title_bn" value="<?= h($row['title_bn']??'')?>"></div>
</div>
<div class="atabs"><button class="atab-btn active" data-tab="pg_en" type="button">🇬🇧 Content (EN)</button><button class="atab-btn" data-tab="pg_bn" type="button">🇧🇩 বিষয়বস্তু</button></div>
<div class="atab-content active" id="atab-pg_en">
<div class="richtext-toolbar"><button data-cmd="bold" type="button"><b>B</b></button><button data-cmd="italic" type="button"><i>I</i></button><button data-cmd="underline" type="button"><u>U</u></button><button data-cmd="insertUnorderedList" type="button">• List</button><button data-cmd="insertOrderedList" type="button">1. List</button><button data-cmd="formatBlock" data-val="h2" type="button">H2</button><button data-cmd="formatBlock" data-val="h3" type="button">H3</button><button data-cmd="createLink" data-val="https://" type="button">🔗</button></div>
<div class="richtext-area" id="pg_content_en" contenteditable="true" data-richtext><?= $row['content_en']??''?></div>
<input type="hidden" name="content_en" id="pg_content_en_hidden" value="<?= h($row['content_en']??'')?>">
</div>
<div class="atab-content" id="atab-pg_bn">
<div class="richtext-toolbar"><button data-cmd="bold" type="button"><b>B</b></button><button data-cmd="italic" type="button"><i>I</i></button><button data-cmd="insertUnorderedList" type="button">• তালিকা</button></div>
<div class="richtext-area" id="pg_content_bn" contenteditable="true" data-richtext><?= $row['content_bn']??''?></div>
<input type="hidden" name="content_bn" id="pg_content_bn_hidden" value="<?= h($row['content_bn']??'')?>">
</div>
<div style="margin-top:16px;display:flex;align-items:center;gap:20px">
<label style="display:flex;align-items:center;gap:8px;cursor:pointer"><input type="checkbox" name="is_active" <?= ($row['is_active']??1)?'checked':''?>> Active</label>
</div>
<div style="margin-top:20px;display:flex;gap:10px"><button type="submit" class="btn btn-primary">💾 Save Page</button><a href="?section=pages" class="btn btn-light">Cancel</a></div>
</form>
<script>document.querySelector('form').addEventListener('submit',function(){['pg_content_en','pg_content_bn'].forEach(id=>{const el=document.getElementById(id);if(el)document.getElementById(id+'_hidden').value=el.innerHTML;});});</script>
<?php endif;?></div></div>
