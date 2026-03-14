<?php
/**
 * Administration / Teachers Page
 */
try {
  $principal = db()->query("SELECT * FROM teachers WHERE is_principal=1 AND is_active=1 LIMIT 1")->fetch();
  $all_teachers = db()->query("SELECT * FROM teachers WHERE is_principal=0 AND is_active=1 ORDER BY sort_order, name_en")->fetchAll();
  $departments = db()->query("SELECT DISTINCT department_en, department_bn FROM teachers WHERE is_active=1 AND department_en != '' ORDER BY department_en")->fetchAll();
  $governing = db()->query("SELECT * FROM governing_body WHERE is_active=1 ORDER BY sort_order")->fetchAll();
} catch(Exception $e) {
  $principal=null; $all_teachers=[]; $departments=[]; $governing=[];
}

$tab = in_array($_GET['tab']??'principal', ['principal','teachers','staff','governing']) ? ($_GET['tab']??'principal') : 'principal';
?>

<div class="breadcrumb">
  <div class="container">
    <ol>

      <li class="active"><?= t('Administration','প্রশাসন') ?></li>
    </ol>
  </div>
</div>

<section class="section">
  <div class="container">
    <h1 style="color:var(--primary);font-size:1.9rem;font-weight:800;margin-bottom:24px;padding-bottom:12px;border-bottom:3px solid var(--accent)">
      <?= t('Administration','প্রশাসন') ?>
    </h1>

    <!-- Tabs -->
    <div style="display:flex;gap:8px;margin-bottom:32px;flex-wrap:wrap;border-bottom:2px solid var(--border);padding-bottom:12px">
      <?php
      $tabs = [
        ['key'=>'principal','en'=>"Principal's Message",'bn'=>'প্রধান শিক্ষকের বার্তা'],
        ['key'=>'teachers','en'=>'Our Teachers','bn'=>'আমাদের শিক্ষকবৃন্দ'],
        ['key'=>'governing','en'=>'Governing Body','bn'=>'গভর্নিং বডি'],
      ];
      foreach ($tabs as $t_item):
      ?>
      <a href="<?= url('administration') ?>&tab=<?= $t_item['key'] ?>"
         class="btn btn-sm <?= $tab===$t_item['key']?'btn-primary':'btn-outline' ?>">
        <?= t($t_item['en'],$t_item['bn']) ?>
      </a>
      <?php endforeach; ?>
    </div>

    <!-- Principal's Message -->
    <?php if ($tab === 'principal'): ?>
    <?php if ($principal): ?>
    <div class="principal-msg" style="align-items:flex-start">
      <div style="text-align:center;flex-shrink:0">
        <?php if (!empty($principal['photo'])): ?>
        <img src="<?= upload_url($principal['photo']) ?>" alt="<?= h(field($principal,'name')) ?>"
             style="width:200px;height:200px;border-radius:var(--radius-lg);object-fit:cover;border:4px solid var(--primary-light)">
        <?php else: ?>
        <div style="width:200px;height:200px;border-radius:var(--radius-lg);background:var(--primary-light);display:flex;align-items:center;justify-content:center;font-size:4rem;color:var(--primary)">👤</div>
        <?php endif; ?>
        <div style="margin-top:16px">
          <div style="font-weight:700;font-size:1.05rem"><?= h(field($principal,'name')) ?></div>
          <div style="color:var(--primary);font-size:.88rem;margin-top:4px"><?= h(field($principal,'designation')) ?></div>
          <?php if ($principal['qualification']): ?>
          <div style="color:var(--text-muted);font-size:.82rem;margin-top:4px"><?= h($principal['qualification']) ?></div>
          <?php endif; ?>
          <?php if ($principal['email']): ?>
          <div style="margin-top:8px"><a href="mailto:<?= h($principal['email']) ?>" style="font-size:.82rem">✉️ <?= h($principal['email']) ?></a></div>
          <?php endif; ?>
        </div>
      </div>
      <div style="flex:1">
        <h2 style="color:var(--primary);font-size:1.3rem;margin-bottom:16px"><?= t("Principal's Message","প্রধান শিক্ষকের বার্তা") ?></h2>
        <?php $bio = field($principal,'bio'); ?>
        <?php if ($bio): ?>
        <div class="page-content"><?= $bio ?></div>
        <?php else: ?>
        <p style="color:var(--text-muted)"><?= t('Message coming soon.','শীঘ্রই আসছে।') ?></p>
        <?php endif; ?>
      </div>
    </div>
    <?php else: ?>
    <p style="color:var(--text-muted);text-align:center;padding:40px"><?= t('Principal information not available.','প্রধান শিক্ষকের তথ্য পাওয়া যায়নি।') ?></p>
    <?php endif; ?>
    <!-- CMS content -->
    <?php if (!empty($page_data['content_en'])): ?>
    <div class="page-content" style="margin-top:40px;padding-top:32px;border-top:1px solid var(--border)">
      <?= $page_data[LANG==='bn'?'content_bn':'content_en'] ?? $page_data['content_en'] ?? '' ?>
    </div>
    <?php endif; ?>

    <!-- Teachers -->
    <?php elseif ($tab === 'teachers'): ?>
    <?php if (!empty($departments)): ?>
    <?php foreach ($departments as $dept): ?>
    <div style="margin-bottom:48px">
      <h2 style="font-size:1.2rem;font-weight:700;color:var(--white);background:var(--primary);padding:12px 20px;border-radius:var(--radius);margin-bottom:20px">
        <?= h(LANG==='bn' ? ($dept['department_bn']?:$dept['department_en']) : $dept['department_en']) ?>
      </h2>
      <div class="grid grid-4">
        <?php
        $dept_teachers = array_filter($all_teachers, fn($t) => $t['department_en'] === $dept['department_en']);
        foreach ($dept_teachers as $teacher): ?>
        <div class="card teacher-card" style="padding:24px">
          <?php if (!empty($teacher['photo'])): ?>
          <img src="<?= upload_url($teacher['photo']) ?>" alt="<?= h(field($teacher,'name')) ?>"
               style="width:100px;height:100px;border-radius:50%;margin:0 auto 16px;border:4px solid var(--primary-light);object-fit:cover;display:block">
          <?php else: ?>
          <div style="width:100px;height:100px;border-radius:50%;margin:0 auto 16px;background:var(--primary-light);display:flex;align-items:center;justify-content:center;font-size:2.5rem;color:var(--primary)">👤</div>
          <?php endif; ?>
          <div class="teacher-name"><?= h(field($teacher,'name')) ?></div>
          <div class="teacher-desig"><?= h(field($teacher,'designation')) ?></div>
          <?php if ($teacher['qualification']): ?>
          <div style="font-size:.75rem;color:var(--text-muted);margin-top:4px"><?= h($teacher['qualification']) ?></div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>
    <?php elseif (!empty($all_teachers)): ?>
    <div class="grid grid-4">
      <?php foreach ($all_teachers as $teacher): ?>
      <div class="card teacher-card" style="padding:24px">
        <?php if (!empty($teacher['photo'])): ?>
        <img src="<?= upload_url($teacher['photo']) ?>" alt="<?= h(field($teacher,'name')) ?>"
             style="width:100px;height:100px;border-radius:50%;margin:0 auto 16px;border:4px solid var(--primary-light);object-fit:cover;display:block">
        <?php else: ?>
        <div style="width:100px;height:100px;border-radius:50%;margin:0 auto 16px;background:var(--primary-light);display:flex;align-items:center;justify-content:center;font-size:2.5rem;color:var(--primary)">👤</div>
        <?php endif; ?>
        <div class="teacher-name"><?= h(field($teacher,'name')) ?></div>
        <div class="teacher-desig"><?= h(field($teacher,'designation')) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p style="text-align:center;color:var(--text-muted);padding:40px"><?= t('No teacher information available.','কোনো শিক্ষকের তথ্য নেই।') ?></p>
    <?php endif; ?>

    <!-- Governing Body -->
    <?php elseif ($tab === 'governing'): ?>
    <?php if (!empty($governing)): ?>
    <div class="grid grid-4">
      <?php foreach ($governing as $member): ?>
      <div class="card teacher-card" style="padding:24px">
        <?php if (!empty($member['photo'])): ?>
        <img src="<?= upload_url($member['photo']) ?>" alt="<?= h(field($member,'name')) ?>"
             style="width:100px;height:100px;border-radius:50%;margin:0 auto 16px;border:4px solid var(--primary-light);object-fit:cover;display:block">
        <?php else: ?>
        <div style="width:100px;height:100px;border-radius:50%;margin:0 auto 16px;background:var(--primary-light);display:flex;align-items:center;justify-content:center;font-size:2.5rem;color:var(--primary)">👤</div>
        <?php endif; ?>
        <div class="teacher-name"><?= h(field($member,'name')) ?></div>
        <div class="teacher-desig"><?= h(field($member,'designation')) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p style="text-align:center;color:var(--text-muted);padding:40px"><?= t('No governing body information.','গভর্নিং বডির তথ্য নেই।') ?></p>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</section>
