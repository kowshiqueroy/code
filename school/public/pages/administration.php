<?php
// public/pages/administration.php
$sub = currentSub();
$subMap = [
    ''               => ['Administration','প্রশাসন'],
    'governing_body' => ['Governing Body','পরিচালনা পর্ষদ'],
    'principal'      => ['Principal','অধ্যক্ষ'],
    'teachers'       => ['Teachers','শিক্ষকবৃন্দ'],
    'staff'          => ['Staff','কর্মকর্তা/কর্মচারী'],
];
$title = $subMap[$sub] ?? $subMap[''];
?>
<div class="page-hero" style="background:linear-gradient(135deg,#1a3a6b,#0d2244)">
  <div class="container">
    <h1 class="page-hero-title"><?= t($title[0],$title[1]) ?></h1>
    <nav class="breadcrumb">
      <a href="<?= pageUrl('index') ?>"><?= t('Home','হোম') ?></a> / <?= t($title[0],$title[1]) ?>
    </nav>
  </div>
</div>

<div class="sub-nav-bar">
  <div class="container sub-nav-inner">
    <?php foreach ($subMap as $s => $labels): ?>
    <a href="<?= pageUrl('administration', $s ? ['sub' => $s] : []) ?>" class="sub-nav-link <?= $sub === $s ? 'active' : '' ?>"><?= t($labels[0],$labels[1]) ?></a>
    <?php endforeach; ?>
  </div>
</div>

<div class="container page-body">
  <div class="content-grid">
    <div class="main-col">

      <?php if ($sub === '' || $sub === 'governing_body'): ?>
      <?php $govBody = getStaff('governing_body'); ?>
      <?php if ($govBody): ?>
      <div class="staff-grid">
        <?php foreach ($govBody as $s): ?>
        <div class="staff-card">
          <div class="staff-photo">
            <?php if ($s['photo']): ?><img src="<?= h(imgUrl($s['photo'],'medium')) ?>" alt="<?= h(field($s,'name')) ?>"><?php else: ?><div class="photo-ph">👤</div><?php endif; ?>
          </div>
          <div class="staff-info">
            <h3><?= h(field($s,'name')) ?></h3>
            <p class="staff-desig"><?= h(field($s,'designation') ?: '') ?></p>
            <?php if ($s['phone']): ?><p class="staff-contact">📞 <?= h($s['phone']) ?></p><?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?><p class="empty-msg"><?= t('No members listed.','কোনো সদস্য নেই।') ?></p><?php endif; ?>

      <?php elseif ($sub === 'principal'): ?>
      <?php
        $principals = array_merge(getStaff('principal'), getStaff('vice_principal'));
      ?>
      <div class="principal-profiles">
        <?php foreach ($principals as $p): ?>
        <div class="principal-profile-card">
          <div class="profile-photo">
            <?php if ($p['photo']): ?><img src="<?= h(imgUrl($p['photo'],'medium')) ?>" alt="<?= h(field($p,'name')) ?>"><?php else: ?><div class="photo-ph lg">👤</div><?php endif; ?>
          </div>
          <div class="profile-info">
            <h2><?= h(field($p,'name')) ?></h2>
            <p class="profile-desig"><?= h(field($p,'designation') ?: t('Principal','অধ্যক্ষ')) ?></p>
            <?php if ($p['qualification']): ?><p><strong><?= t('Qualification','যোগ্যতা') ?>:</strong> <?= h($p['qualification']) ?></p><?php endif; ?>
            <?php if ($p['joining_date']): ?><p><strong><?= t('Joining Date','যোগদানের তারিখ') ?>:</strong> <?= formatDate($p['joining_date']) ?></p><?php endif; ?>
            <?php if ($p['phone']): ?><p>📞 <?= h($p['phone']) ?></p><?php endif; ?>
            <?php if ($p['email']): ?><p>✉️ <?= h($p['email']) ?></p><?php endif; ?>
            <?php if (field($p,'bio')): ?><div class="profile-bio"><?= nl2br(h(field($p,'bio'))) ?></div><?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if (!$principals): ?><p class="empty-msg"><?= t('Information not available.','তথ্য পাওয়া যায়নি।') ?></p><?php endif; ?>
      </div>

      <?php elseif ($sub === 'teachers'): ?>
      <?php $teachers = getStaff('teacher'); ?>
      <table class="data-table staff-table">
        <thead>
          <tr>
            <th>#</th>
            <th><?= t('Photo','ছবি') ?></th>
            <th><?= t('Name','নাম') ?></th>
            <th><?= t('Designation','পদবি') ?></th>
            <th><?= t('Subject','বিষয়') ?></th>
            <th><?= t('Qualification','যোগ্যতা') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($teachers as $i => $t_item): ?>
          <tr>
            <td><?= banglaNum((string)($i+1)) ?></td>
            <td>
              <?php if ($t_item['photo']): ?>
              <img src="<?= h(imgUrl($t_item['photo'],'thumb')) ?>" class="table-photo" alt="">
              <?php else: ?><div class="table-photo-ph">👤</div><?php endif; ?>
            </td>
            <td><strong><?= h(field($t_item,'name')) ?></strong></td>
            <td><?= h(field($t_item,'designation') ?: '') ?></td>
            <td><?= h(field($t_item,'subject') ?: '') ?></td>
            <td><?= h($t_item['qualification'] ?: '') ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$teachers): ?><tr><td colspan="6" class="empty-td"><?= t('No teachers listed.','কোনো শিক্ষক নেই।') ?></td></tr><?php endif; ?>
        </tbody>
      </table>

      <?php elseif ($sub === 'staff'): ?>
      <?php $staffList = getStaff('staff'); ?>
      <table class="data-table">
        <thead>
          <tr>
            <th>#</th>
            <th><?= t('Name','নাম') ?></th>
            <th><?= t('Designation','পদবি') ?></th>
            <th><?= t('Department','বিভাগ') ?></th>
            <th><?= t('Contact','যোগাযোগ') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($staffList as $i => $s): ?>
          <tr>
            <td><?= banglaNum((string)($i+1)) ?></td>
            <td><?= h(field($s,'name')) ?></td>
            <td><?= h(field($s,'designation') ?: '') ?></td>
            <td><?= h(field($s,'department') ?: '') ?></td>
            <td><?= h($s['phone'] ?: '') ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$staffList): ?><tr><td colspan="5" class="empty-td"><?= t('No staff listed.','কোনো কর্মী নেই।') ?></td></tr><?php endif; ?>
        </tbody>
      </table>
      <?php endif; ?>

    </div>
    <?php include __DIR__ . '/sidebar_widget.php'; ?>
  </div>
</div>
