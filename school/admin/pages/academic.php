<?php // admin/pages/academic.php
$tab=$_GET['tab']??'routine';$db=getDB();
if($_SERVER['REQUEST_METHOD']==='POST'){
    $t=$_POST['form_tab']??'routine';
    if($t==='routine'){
        $d=['class_name_en'=>sanitize($_POST['class_name_en']??''),'class_name_bn'=>sanitize($_POST['class_name_bn']??''),'section'=>sanitize($_POST['section']??''),'session_year'=>sanitize($_POST['session_year']??''),'file_url'=>sanitize($_POST['file_url']??''),'is_active'=>isset($_POST['is_active'])?1:0];
        if(!empty($_FILES['routine_file']['name'])){$up=handleUpload('routine_file','academic');if(isset($up['filename']))$d['file_url']=UPLOAD_URL.'documents/'.$up['filename'];}
        $db->prepare("INSERT INTO class_routines (class_name_en,class_name_bn,section,session_year,file_url,is_active) VALUES (?,?,?,?,?,?)")->execute(array_values($d));
        flash('Routine added.','success');
    } elseif($t==='exam'){
        $d=['title_en'=>sanitize($_POST['title_en']??''),'title_bn'=>sanitize($_POST['title_bn']??''),'class_name'=>sanitize($_POST['class_name']??''),'session_year'=>sanitize($_POST['session_year']??''),'start_date'=>$_POST['start_date']?:null,'end_date'=>$_POST['end_date']?:null,'file_url'=>sanitize($_POST['file_url']??''),'is_active'=>isset($_POST['is_active'])?1:0];
        if(!empty($_FILES['exam_file']['name'])){$up=handleUpload('exam_file','academic');if(isset($up['filename']))$d['file_url']=UPLOAD_URL.'documents/'.$up['filename'];}
        $db->prepare("INSERT INTO exam_schedules (title_en,title_bn,class_name,session_year,start_date,end_date,file_url,is_active) VALUES (?,?,?,?,?,?,?,?)")->execute(array_values($d));
        flash('Schedule added.','success');
    } elseif($t==='results'){
        $d=['title_en'=>sanitize($_POST['title_en']??''),'title_bn'=>sanitize($_POST['title_bn']??''),'class_name'=>sanitize($_POST['class_name']??''),'session_year'=>sanitize($_POST['session_year']??''),'publish_date'=>$_POST['publish_date']?:null,'file_url'=>sanitize($_POST['file_url']??''),'ext_link'=>sanitize($_POST['ext_link']??''),'is_active'=>isset($_POST['is_active'])?1:0];
        $db->prepare("INSERT INTO results (title_en,title_bn,class_name,session_year,publish_date,file_url,ext_link,is_active) VALUES (?,?,?,?,?,?,?,?)")->execute(array_values($d));
        flash('Result added.','success');
    } elseif($t==='dept'){
        $d=['name_en'=>sanitize($_POST['name_en']??''),'name_bn'=>sanitize($_POST['name_bn']??''),'description_en'=>sanitize($_POST['description_en']??''),'is_active'=>isset($_POST['is_active'])?1:0,'sort_order'=>(int)($_POST['sort_order']??0)];
        $db->prepare("INSERT INTO departments (name_en,name_bn,description_en,is_active,sort_order) VALUES (?,?,?,?,?)")->execute(array_values($d));
        flash('Department added.','success');
    }
    redirect(ADMIN_PATH.'?section=academic&tab='.$t);
}
if(isset($_GET['delete_routine'])){$db->prepare("DELETE FROM class_routines WHERE id=?")->execute([(int)$_GET['delete_routine']]);flash('Deleted.','success');redirect(ADMIN_PATH.'?section=academic&tab=routine');}
if(isset($_GET['delete_exam'])){$db->prepare("DELETE FROM exam_schedules WHERE id=?")->execute([(int)$_GET['delete_exam']]);flash('Deleted.','success');redirect(ADMIN_PATH.'?section=academic&tab=exam');}
if(isset($_GET['delete_result'])){$db->prepare("DELETE FROM results WHERE id=?")->execute([(int)$_GET['delete_result']]);flash('Deleted.','success');redirect(ADMIN_PATH.'?section=academic&tab=results');}
if(isset($_GET['delete_dept'])){$db->prepare("DELETE FROM departments WHERE id=?")->execute([(int)$_GET['delete_dept']]);flash('Deleted.','success');redirect(ADMIN_PATH.'?section=academic&tab=dept');}
$routines=$db->query("SELECT * FROM class_routines ORDER BY class_name_en")->fetchAll();
$exams=$db->query("SELECT * FROM exam_schedules ORDER BY start_date DESC")->fetchAll();
$results=$db->query("SELECT * FROM results ORDER BY publish_date DESC")->fetchAll();
$depts=$db->query("SELECT * FROM departments ORDER BY sort_order")->fetchAll();
?>
<div class="atabs">
  <button class="atab-btn <?= $tab==='routine'?'active':''?>" onclick="location.href='?section=academic&tab=routine'" type="button">Class Routine</button>
  <button class="atab-btn <?= $tab==='exam'?'active':''?>" onclick="location.href='?section=academic&tab=exam'" type="button">Exam Schedule</button>
  <button class="atab-btn <?= $tab==='results'?'active':''?>" onclick="location.href='?section=academic&tab=results'" type="button">Results</button>
  <button class="atab-btn <?= $tab==='dept'?'active':''?>" onclick="location.href='?section=academic&tab=dept'" type="button">Departments</button>
</div>
<div class="acard"><div class="acard-body">
<?php if($tab==='routine'):?>
<form method="POST" enctype="multipart/form-data" class="aform" style="margin-bottom:20px;padding-bottom:20px;border-bottom:1px solid var(--border)"><input type="hidden" name="form_tab" value="routine"><div class="form-row"><div class="form-group"><label>Class Name (EN)</label><input type="text" name="class_name_en" placeholder="Class X" required></div><div class="form-group"><label>শ্রেণীর নাম (বাংলা)</label><input type="text" name="class_name_bn"></div><div class="form-group"><label>Section</label><input type="text" name="section" placeholder="A/B"></div><div class="form-group"><label>Session Year</label><input type="text" name="session_year" placeholder="2024-25"></div><div class="form-group"><label>File URL or Upload</label><input type="url" name="file_url" placeholder="https://..."></div><div class="form-group"><label>Upload File</label><input type="file" name="routine_file" accept=".pdf,.doc,.docx"></div></div><label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin-bottom:12px"><input type="checkbox" name="is_active" checked> Active</label><button type="submit" class="btn btn-primary btn-sm">+ Add Routine</button></form>
<table class="atable"><thead><tr><th>Class</th><th>Section</th><th>Session</th><th>File</th><th></th></tr></thead><tbody>
<?php foreach($routines as $r):?><tr><td><?= h($r['class_name_en'])?></td><td><?= h($r['section'])?></td><td><?= h($r['session_year'])?></td><td><?php if($r['file_url']):?><a href="<?= h($r['file_url'])?>" target="_blank" class="btn btn-xs btn-light">📥</a><?php else:?>—<?php endif;?></td><td><a href="?section=academic&tab=routine&delete_routine=<?= $r['id']?>" class="btn btn-xs btn-danger" data-confirm="Delete?">🗑️</a></td></tr><?php endforeach;?>
</tbody></table>
<?php elseif($tab==='exam'):?>
<form method="POST" enctype="multipart/form-data" class="aform" style="margin-bottom:20px;padding-bottom:20px;border-bottom:1px solid var(--border)"><input type="hidden" name="form_tab" value="exam"><div class="form-row"><div class="form-group"><label>Title (EN) <span class="req">*</span></label><input type="text" name="title_en" required></div><div class="form-group"><label>শিরোনাম (বাংলা)</label><input type="text" name="title_bn"></div><div class="form-group"><label>Class</label><input type="text" name="class_name"></div><div class="form-group"><label>Session</label><input type="text" name="session_year"></div><div class="form-group"><label>Start Date</label><input type="date" name="start_date"></div><div class="form-group"><label>End Date</label><input type="date" name="end_date"></div><div class="form-group"><label>File URL</label><input type="url" name="file_url"></div><div class="form-group"><label>Upload File</label><input type="file" name="exam_file" accept=".pdf,.doc,.docx"></div></div><button type="submit" class="btn btn-primary btn-sm">+ Add Schedule</button></form>
<table class="atable"><thead><tr><th>Title</th><th>Class</th><th>Start</th><th>End</th><th>File</th><th></th></tr></thead><tbody>
<?php foreach($exams as $e):?><tr><td><?= h($e['title_en'])?></td><td><?= h($e['class_name'])?></td><td><?= $e['start_date']?date('d M Y',strtotime($e['start_date'])):'—'?></td><td><?= $e['end_date']?date('d M Y',strtotime($e['end_date'])):'—'?></td><td><?php if($e['file_url']):?><a href="<?= h($e['file_url'])?>" target="_blank" class="btn btn-xs btn-light">📥</a><?php else:?>—<?php endif;?></td><td><a href="?section=academic&tab=exam&delete_exam=<?= $e['id']?>" class="btn btn-xs btn-danger" data-confirm="Delete?">🗑️</a></td></tr><?php endforeach;?>
</tbody></table>
<?php elseif($tab==='results'):?>
<form method="POST" class="aform" style="margin-bottom:20px;padding-bottom:20px;border-bottom:1px solid var(--border)"><input type="hidden" name="form_tab" value="results"><div class="form-row"><div class="form-group"><label>Title (EN) <span class="req">*</span></label><input type="text" name="title_en" required></div><div class="form-group"><label>শিরোনাম (বাংলা)</label><input type="text" name="title_bn"></div><div class="form-group"><label>Class</label><input type="text" name="class_name"></div><div class="form-group"><label>Session</label><input type="text" name="session_year"></div><div class="form-group"><label>Publish Date</label><input type="date" name="publish_date" value="<?= date('Y-m-d')?>"></div><div class="form-group"><label>File URL</label><input type="url" name="file_url" placeholder="https://..."></div><div class="form-group"><label>External Result Link</label><input type="url" name="ext_link" placeholder="educationboard.gov.bd/..."></div></div><button type="submit" class="btn btn-primary btn-sm">+ Add Result</button></form>
<table class="atable"><thead><tr><th>Title</th><th>Class</th><th>Session</th><th>Date</th><th>Links</th><th></th></tr></thead><tbody>
<?php foreach($results as $r):?><tr><td><?= h($r['title_en'])?></td><td><?= h($r['class_name'])?></td><td><?= h($r['session_year'])?></td><td><?= $r['publish_date']?date('d M Y',strtotime($r['publish_date'])):'—'?></td><td><?php if($r['file_url']):?><a href="<?= h($r['file_url'])?>" target="_blank" class="btn btn-xs btn-light">📥</a><?php endif;?><?php if($r['ext_link']):?><a href="<?= h($r['ext_link'])?>" target="_blank" class="btn btn-xs btn-light">🔗</a><?php endif;?></td><td><a href="?section=academic&tab=results&delete_result=<?= $r['id']?>" class="btn btn-xs btn-danger" data-confirm="Delete?">🗑️</a></td></tr><?php endforeach;?>
</tbody></table>
<?php elseif($tab==='dept'):?>
<form method="POST" class="aform" style="margin-bottom:20px;padding-bottom:20px;border-bottom:1px solid var(--border)"><input type="hidden" name="form_tab" value="dept"><div class="form-row"><div class="form-group"><label>Department Name (EN)</label><input type="text" name="name_en" required></div><div class="form-group"><label>বিভাগের নাম (বাংলা)</label><input type="text" name="name_bn"></div><div class="form-group"><label>Description</label><input type="text" name="description_en"></div><div class="form-group"><label>Sort Order</label><input type="number" name="sort_order" value="0"></div></div><button type="submit" class="btn btn-primary btn-sm">+ Add Department</button></form>
<table class="atable"><thead><tr><th>#</th><th>Name (EN)</th><th>Name (বাংলা)</th><th></th></tr></thead><tbody>
<?php foreach($depts as $i=>$d):?><tr><td><?= $i+1?></td><td><?= h($d['name_en'])?></td><td><?= h($d['name_bn'])?></td><td><a href="?section=academic&tab=dept&delete_dept=<?= $d['id']?>" class="btn btn-xs btn-danger" data-confirm="Delete?">🗑️</a></td></tr><?php endforeach;?>
</tbody></table>
<?php endif;?>
</div></div>
