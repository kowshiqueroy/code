<?php
// public/pages/notices.php
$filter = preg_replace('/[^a-z]/', '', $_GET['type'] ?? '');
$types = ['','notice','news','event','job','result','exam','admission','circular'];
if (!in_array($filter, $types)) $filter = '';

$paged  = max(1, (int)($_GET['paged'] ?? 1));
$limit  = 15;
$offset = ($paged - 1) * $limit;

try {
    $where  = "is_active=1 AND (expire_date IS NULL OR expire_date >= CURDATE())";
    $params = [];
    if ($filter) { $where .= " AND type=?"; $params[] = $filter; }
    $total  = getDB()->prepare("SELECT COUNT(*) FROM notices WHERE $where");
    $total->execute($params);
    $count  = (int)$total->fetchColumn();
    $pages  = ceil($count / $limit);

    $stmt = getDB()->prepare("SELECT * FROM notices WHERE $where ORDER BY is_pinned DESC, created_at DESC LIMIT $limit OFFSET $offset");
    $stmt->execute($params);
    $notices = $stmt->fetchAll();
} catch(Exception $e) { $notices = []; $pages = 1; $count = 0; }

$typeLabels = [
    ''         => ['All','সকল'],
    'notice'   => ['Notice','নোটিশ'],
    'news'     => ['News','সংবাদ'],
    'event'    => ['Event','ইভেন্ট'],
    'job'      => ['Job','চাকরি'],
    'result'   => ['Result','ফলাফল'],
    'exam'     => ['Exam','পরীক্ষা'],
    'admission'=> ['Admission','ভর্তি'],
    'circular' => ['Circular','সার্কুলার'],
];
?>
<div class="page-hero" style="background:linear-gradient(135deg,#2c5282,#1a365d)">
  <div class="container">
    <h1 class="page-hero-title"><?= t('Notices & Updates','নোটিশ ও আপডেট') ?></h1>
    <nav class="breadcrumb"><a href="<?= pageUrl('index') ?>"><?= t('Home','হোম') ?></a> / <?= t('Notices','নোটিশ') ?></nav>
  </div>
</div>

<div class="container page-body">
  <!-- Filter tabs -->
  <div class="filter-tabs">
    <?php foreach ($typeLabels as $type => $labels): ?>
    <a href="<?= pageUrl('notices', $type ? ['type'=>$type] : []) ?>" class="filter-tab <?= $filter === $type ? 'active' : '' ?>">
      <?= t($labels[0], $labels[1]) ?>
    </a>
    <?php endforeach; ?>
  </div>

  <div class="content-grid">
    <div class="main-col">
      <p class="result-count"><?= t('Total','মোট') ?>: <strong><?= banglaNum((string)$count) ?></strong> <?= t('results','ফলাফল') ?></p>

      <?php if ($notices): ?>
      <ul class="notice-list-full">
        <?php foreach ($notices as $n): ?>
        <li class="notice-item-full <?= $n['is_pinned'] ? 'pinned' : '' ?> <?= $n['is_urgent'] ? 'urgent' : '' ?>">
          <div class="notice-left">
            <div class="notice-date-box">
              <span class="ndate-day"><?= banglaNum(date('d', strtotime($n['publish_date'] ?? $n['created_at']))) ?></span>
              <span class="ndate-mon"><?= formatDate($n['publish_date'] ?? $n['created_at']) ?></span>
            </div>
          </div>
          <div class="notice-right">
            <div class="notice-top-row">
              <span class="notice-tag tag-<?= h($n['type']) ?>"><?= t(ucfirst($n['type']), '') ?></span>
              <?php if ($n['is_pinned']): ?><span class="pin-badge">📌 <?= t('Pinned','পিন করা') ?></span><?php endif; ?>
              <?php if ($n['is_urgent']): ?><span class="badge-urgent"><?= t('Urgent','জরুরি') ?></span><?php endif; ?>
            </div>
            <a href="<?= pageUrl('notice_detail', ['id' => $n['id']]) ?>" class="notice-title-full"><?= h(field($n,'title')) ?></a>
            <?php $excerpt = excerpt(field($n,'content') ?: '', 20); if ($excerpt): ?>
            <p class="notice-excerpt"><?= h($excerpt) ?></p>
            <?php endif; ?>
            <div class="notice-foot">
              <?php if ($n['file_url']): ?><a href="<?= h($n['file_url']) ?>" target="_blank" class="btn-sm">📎 <?= t('Download','ডাউনলোড') ?></a><?php endif; ?>
              <?php if ($n['type'] === 'job'): ?><a href="<?= pageUrl('apply', ['notice_id' => $n['id']]) ?>" class="btn-primary-sm">📝 <?= t('Apply','আবেদন') ?></a><?php endif; ?>
              <a href="<?= pageUrl('notice_detail', ['id' => $n['id']]) ?>" class="read-more"><?= t('Read More →','আরও পড়ুন →') ?></a>
            </div>
          </div>
        </li>
        <?php endforeach; ?>
      </ul>

      <!-- Pagination -->
      <?php if ($pages > 1): ?>
      <div class="pagination">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
        <a href="<?= pageUrl('notices', array_merge($filter ? ['type' => $filter] : [], ['paged' => $i])) ?>" class="page-link <?= $paged === $i ? 'active' : '' ?>"><?= banglaNum((string)$i) ?></a>
        <?php endfor; ?>
      </div>
      <?php endif; ?>

      <?php else: ?>
      <p class="empty-msg"><?= t('No notices found.','কোনো নোটিশ পাওয়া যায়নি।') ?></p>
      <?php endif; ?>
    </div>

    <?php include __DIR__ . '/sidebar_widget.php'; ?>
  </div>
</div>
