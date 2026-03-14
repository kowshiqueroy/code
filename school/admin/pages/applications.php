<?php // admin/pages/applications.php
$db=getDB();$id=(int)($_GET['id']??0);
if(isset($_GET['status'])&&$id){$db->prepare("UPDATE job_applications SET status=? WHERE id=?")->execute([sanitize($_GET['status']),$id]);flash('Status updated.','success');redirect(ADMIN_PATH.'?section=applications');}
if(isset($_GET['delete'])&&$id){$db->prepare("DELETE FROM job_applications WHERE id=?")->execute([$id]);flash('Deleted.','success');redirect(ADMIN_PATH.'?section=applications');}
$filter=sanitize($_GET['filter']??'');$where='1=1';$params=[];if($filter){$where.=" AND status=?";$params[]=$filter;}
$list=$db->prepare("SELECT ja.*,n.title_en AS job_title FROM job_applications ja LEFT JOIN notices n ON ja.notice_id=n.id WHERE $where ORDER BY ja.created_at DESC");$list->execute($params);$apps=$list->fetchAll();
$statusOpts=['pending'=>'badge-warning','reviewed'=>'badge-info','shortlisted'=>'badge-success','rejected'=>'badge-danger'];
// View single app
$viewApp=null;if(isset($_GET['view'])&&$_GET['view']){$stmt=$db->prepare("SELECT * FROM job_applications WHERE id=?");$stmt->execute([(int)$_GET['view']]);$viewApp=$stmt->fetch();if($viewApp&&$viewApp['status']==='pending'){$db->prepare("UPDATE job_applications SET status='reviewed' WHERE id=?")->execute([$viewApp['id']]);}}
?>
<?php if($viewApp):?>
<div class="acard"><div class="acard-header"><div class="acard-title">📝 Application Details</div><a href="?section=applications" class="btn btn-light btn-sm">← Back</a></div><div class="acard-body">
<table class="atable" style="max-width:600px"><tbody>
<tr><th style="width:140px">Applicant</th><td><strong><?= h($viewApp['applicant_name'])?></strong></td></tr>
<tr><th>Email</th><td><?= h($viewApp['email'])?:'-'?></td></tr>
<tr><th>Phone</th><td><?= h($viewApp['phone'])?></td></tr>
<tr><th>Position</th><td><?= h($viewApp['position'])?:'-'?></td></tr>
<tr><th>Applied On</th><td><?= date('d M Y H:i',strtotime($viewApp['created_at']))?></td></tr>
<tr><th>Status</th><td><span class="badge <?= $statusOpts[$viewApp['status']]??'badge-gray'?>"><?= h($viewApp['status'])?></span></td></tr>
<?php if($viewApp['message']):?><tr><th>Message</th><td><?= nl2br(h($viewApp['message']))?></td></tr><?php endif;?>
<?php if($viewApp['cv_file']):?><tr><th>CV</th><td><a href="<?= h(UPLOAD_URL.'documents/'.$viewApp['cv_file'])?>" target="_blank" class="btn btn-sm btn-light">📥 Download CV</a></td></tr><?php endif;?>
</tbody></table>
<div style="margin-top:16px;display:flex;gap:8px;flex-wrap:wrap">
<?php foreach(array_keys($statusOpts) as $st):?><a href="?section=applications&id=<?= $viewApp['id']?>&status=<?= $st?>" class="btn btn-sm <?= $st==='rejected'?'btn-danger':($st==='shortlisted'?'btn-success':'btn-light')?>"><?= ucfirst($st)?></a><?php endforeach;?>
<a href="?section=applications&delete=1&id=<?= $viewApp['id']?>" class="btn btn-sm btn-danger" data-confirm="Delete this application?">🗑️ Delete</a>
</div>
</div></div>
<?php else:?>
<div class="acard"><div class="acard-header"><div class="acard-title">📝 Job Applications (<?= count($apps)?>)</div>
<div style="display:flex;gap:6px;flex-wrap:wrap">
<a href="?section=applications" class="btn btn-sm <?= !$filter?'btn-primary':'btn-light'?>">All</a>
<?php foreach(array_keys($statusOpts) as $st):?><a href="?section=applications&filter=<?= $st?>" class="btn btn-sm <?= $filter===$st?'btn-primary':'btn-light'?>"><?= ucfirst($st)?></a><?php endforeach;?>
</div>
</div>
<div class="atable-wrap"><table class="atable"><thead><tr><th>Applicant</th><th>Phone</th><th>Position / Job</th><th>Applied</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php foreach($apps as $a):?><tr>
<td><strong><?= h($a['applicant_name'])?></strong><?php if($a['email']):?><br><small><?= h($a['email'])?></small><?php endif;?></td>
<td><?= h($a['phone'])?></td>
<td><small><?= h(mb_substr($a['position']?:$a['job_title']?:'—',0,40))?></small></td>
<td><?= date('d M Y',strtotime($a['created_at']))?></td>
<td><span class="badge <?= $statusOpts[$a['status']]??'badge-gray'?>"><?= h($a['status'])?></span></td>
<td><a href="?section=applications&view=<?= $a['id']?>" class="btn btn-sm btn-light">👁️ View</a></td>
</tr><?php endforeach;?>
<?php if(!$apps):?><tr><td colspan="6" style="text-align:center;padding:30px;color:#999">No applications.</td></tr><?php endif;?>
</tbody></table></div></div>
<?php endif;?>
