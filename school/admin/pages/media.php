<?php // admin/pages/media.php
$db=getDB();$action=$_GET['action']??'list';
// Upload
if($_SERVER['REQUEST_METHOD']==='POST'&&!empty($_FILES['media_file']['name'])){
    $up=handleUpload('media_file',sanitize($_POST['folder']??'general'));
    if(isset($up['error'])){flash($up['error'],'error');}else{flash('File uploaded.','success');}
    redirect(ADMIN_PATH.'?section=media');
}
// Delete
if($action==='delete'&&isset($_GET['id'])){$db->prepare("DELETE FROM media WHERE id=?")->execute([(int)$_GET['id']]);flash('Deleted.','success');redirect(ADMIN_PATH.'?section=media');}
$paged=max(1,(int)($_GET['paged']??1));$limit=36;$offset=($paged-1)*$limit;
$total=(int)$db->query("SELECT COUNT(*) FROM media")->fetchColumn();$pages=ceil($total/$limit);
$stmt=$db->prepare("SELECT * FROM media ORDER BY created_at DESC LIMIT $limit OFFSET $offset");$stmt->execute();$media=$stmt->fetchAll();
?>
<div class="acard"><div class="acard-header"><div class="acard-title">📁 Media Library (<?= $total?> files)</div>
<form method="POST" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:center">
<select name="folder" style="padding:6px 10px;border:1.5px solid var(--border);border-radius:6px;font-family:inherit;font-size:.85rem"><option value="general">General</option><option value="events">Events</option><option value="notices">Notices</option></select>
<input type="file" name="media_file" accept="image/*,.pdf,.doc,.docx" required style="font-size:.82rem">
<button type="submit" class="btn btn-primary btn-sm">📤 Upload</button>
</form>
</div><div class="acard-body">
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:10px">
<?php foreach($media as $m):?>
<div style="background:#f8f8f8;border-radius:8px;overflow:hidden;border:1.5px solid var(--border);position:relative">
<?php if(str_contains($m['mime_type']??'','image')):?>
<img src="<?= h(imgUrl($m['filename'],'thumb'))?>" style="width:100%;aspect-ratio:1;object-fit:cover">
<?php else:?>
<div style="width:100%;aspect-ratio:1;display:flex;align-items:center;justify-content:center;font-size:2.5rem;background:#e8f5f0">📄</div>
<?php endif;?>
<div style="padding:6px 8px"><p style="font-size:.72rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#666" title="<?= h($m['original_name']??$m['filename'])?>"><?= h(mb_substr($m['original_name']??$m['filename'],0,20))?></p>
<div style="display:flex;gap:4px;margin-top:4px">
<button class="btn btn-xs btn-light" onclick="copyUrl('<?= h(UPLOAD_URL.'images/'.$m['filename'])?>',this)" title="Copy URL">📋</button>
<a href="?section=media&action=delete&id=<?= $m['id']?>" class="btn btn-xs btn-danger" data-confirm="Delete?">🗑️</a>
</div></div>
</div>
<?php endforeach;?>
<?php if(!$media):?><p style="grid-column:1/-1;text-align:center;color:#999;padding:40px">No media files yet.</p><?php endif;?>
</div>
<?php if($pages>1):?><div class="apagination" style="margin-top:20px"><?php for($i=1;$i<=$pages;$i++):?><a href="?section=media&paged=<?= $i?>" class="apage-link <?= $paged===$i?'active':''?>"><?= $i?></a><?php endfor;?></div><?php endif;?>
</div></div>
<script>function copyUrl(url,btn){navigator.clipboard.writeText(url).then(()=>{const orig=btn.textContent;btn.textContent='✅';setTimeout(()=>btn.textContent=orig,1500);});}</script>
