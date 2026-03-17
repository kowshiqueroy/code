<?php
require_once __DIR__ . '/auth.php';
requireAdmin();
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

if ($action === 'delete' && $id) {
    db()->prepare("DELETE FROM jobs WHERE id=?")->execute([$id]);
    flash('Job deleted.','success'); redirect(SITE_URL.'/admin/jobs.php');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $tEn=sanitizeText($_POST['title_en']??'');$tBn=sanitizeText($_POST['title_bn']??'');
    $dEn=sanitizeText($_POST['department_en']??'');$dBn=sanitizeText($_POST['department_bn']??'');
    $lEn=sanitizeText($_POST['location_en']??'');$lBn=sanitizeText($_POST['location_bn']??'');
    $typeEn=sanitizeText($_POST['type_en']??'');$typeBn=sanitizeText($_POST['type_bn']??'');
    $descEn=sanitizeText($_POST['desc_en']??'');$descBn=sanitizeText($_POST['desc_bn']??'');
    $salary=sanitizeText($_POST['salary_range']??'');
    $expires=$_POST['expires_at']??date('Y-m-d',strtotime('+30 days'));
    $active=(int)($_POST['active']??1);$editId=(int)($_POST['edit_id']??0);

    if($editId){
        db()->prepare("UPDATE jobs SET title_en=?,title_bn=?,department_en=?,department_bn=?,location_en=?,location_bn=?,type_en=?,type_bn=?,desc_en=?,desc_bn=?,salary_range=?,expires_at=?,active=? WHERE id=?")
            ->execute([$tEn,$tBn,$dEn,$dBn,$lEn,$lBn,$typeEn,$typeBn,$descEn,$descBn,$salary,$expires,$active,$editId]);
        flash('Job updated.','success');
    } else {
        db()->prepare("INSERT INTO jobs (title_en,title_bn,department_en,department_bn,location_en,location_bn,type_en,type_bn,desc_en,desc_bn,salary_range,expires_at,active) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$tEn,$tBn,$dEn,$dBn,$lEn,$lBn,$typeEn,$typeBn,$descEn,$descBn,$salary,$expires,$active]);
        flash('Job added.','success');
    }
    redirect(SITE_URL.'/admin/jobs.php');
}
$editing=null;if($action==='edit'&&$id){$s=db()->prepare("SELECT * FROM jobs WHERE id=?");$s->execute([$id]);$editing=$s->fetch();}
$today=date('Y-m-d');
$jobs=db()->query("SELECT *, (expires_at >= '$today') as is_live FROM jobs ORDER BY created_at DESC")->fetchAll();
require_once __DIR__ . '/partials/admin_header.php';
?>
<div class="admin-page-header"><h1>💼 Job Listings</h1><p>Manage job openings. Jobs past their expiry date are automatically hidden from the public.</p></div>
<div class="admin-panel">
  <h2 class="admin-section-title"><?= $editing?'Edit Job':'Add New Job'?></h2>
  <form method="POST" class="admin-form">
    <input type="hidden" name="csrf_token" value="<?= csrf_token()?>">
    <input type="hidden" name="edit_id" value="<?= $editing['id']??0?>">
    <div class="form-row">
      <div class="form-group"><label>Job Title (EN) *</label><input type="text" name="title_en" class="form-input" required value="<?= e($editing['title_en']??'')?>"></div>
      <div class="form-group"><label>Job Title (BN)</label><input type="text" name="title_bn" class="form-input" value="<?= e($editing['title_bn']??'')?>"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Department (EN)</label><input type="text" name="department_en" class="form-input" value="<?= e($editing['department_en']??'')?>"></div>
      <div class="form-group"><label>Department (BN)</label><input type="text" name="department_bn" class="form-input" value="<?= e($editing['department_bn']??'')?>"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Location (EN)</label><input type="text" name="location_en" class="form-input" value="<?= e($editing['location_en']??'')?>"></div>
      <div class="form-group"><label>Location (BN)</label><input type="text" name="location_bn" class="form-input" value="<?= e($editing['location_bn']??'')?>"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Job Type EN (e.g. Full-time)</label><input type="text" name="type_en" class="form-input" value="<?= e($editing['type_en']??'')?>"></div>
      <div class="form-group"><label>Job Type BN</label><input type="text" name="type_bn" class="form-input" value="<?= e($editing['type_bn']??'')?>"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Description (EN)</label><textarea name="desc_en" class="form-textarea" rows="5"><?= e($editing['desc_en']??'')?></textarea></div>
      <div class="form-group"><label>Description (BN)</label><textarea name="desc_bn" class="form-textarea" rows="5"><?= e($editing['desc_bn']??'')?></textarea></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Salary Range (e.g. BDT 30,000–50,000)</label><input type="text" name="salary_range" class="form-input" value="<?= e($editing['salary_range']??'')?>"></div>
      <div class="form-group"><label>Application Deadline *</label><input type="date" name="expires_at" class="form-input" required value="<?= e($editing['expires_at'] ?? date('Y-m-d', strtotime('+30 days')))?>"></div>
      <div class="form-group"><label>Status</label><select name="active" class="form-input"><option value="1" <?=($editing['active']??1)==1?'selected':''?>>Active</option><option value="0" <?=($editing['active']??1)==0?'selected':''?>>Inactive</option></select></div>
    </div>
    <div class="form-actions"><button type="submit" class="btn btn-primary"><?= $editing?'💾 Update Job':'➕ Add Job'?></button><?php if($editing):?><a href="<?= SITE_URL?>/admin/jobs.php" class="btn btn-ghost">Cancel</a><?php endif;?></div>
  </form>
</div>
<div class="admin-panel">
  <h2 class="admin-section-title">All Jobs (<?= count($jobs) ?>)</h2>
  <table class="admin-table">
    <thead>
      <tr>
        <th>Title</th>
        <th>Dept</th>
        <th>Deadline</th>
        <th>Status</th>
        <th>Apps</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($jobs as $j): 
        // Best practice: Use a distinct variable for the statement vs the count result
        $stmt = db()->prepare("SELECT COUNT(*) FROM job_applications WHERE job_id = ?");
        $stmt->execute([$j['id']]);
        $appCount = $stmt->fetchColumn();
      ?>
        <tr class="<?= !$j['is_live'] ? 'row-expired' : '' ?>">
          <td><?= e($j['title_en']) ?></td>
          <td><?= e($j['department_en']) ?: '—' ?></td>
          <td class="<?= !$j['is_live'] ? 'text-danger' : '' ?>">
            <?= date('d M Y', strtotime($j['expires_at'])) ?>
            <?= !$j['is_live'] ? ' (Expired)' : '' ?>
          </td>
          <td>
            <span class="badge <?= $j['active'] && $j['is_live'] ? 'badge-green' : ($j['active'] ? 'badge-orange' : 'badge-gray') ?>">
              <?= $j['active'] && $j['is_live'] ? 'Live' : ($j['active'] ? 'Expired' : 'Off') ?>
            </span>
          </td>
          <td>
            <?= $appCount ?> 
            <a href="applications.php?job=<?= $j['id'] ?>" class="btn-mini btn-sm">View</a>
          </td>
          <td class="table-actions">
            <a href="?action=edit&id=<?= $j['id'] ?>" class="btn-mini">Edit</a>
            <a href="?action=delete&id=<?= $j['id'] ?>&csrf_token=<?= csrf_token() ?>" class="btn-mini btn-danger" onclick="return confirm('Delete job and all its applications?')">Del</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require_once __DIR__ . '/partials/admin_footer.php'; ?>
