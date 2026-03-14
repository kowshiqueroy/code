<?php
/**
 * Notices / News Page
 */
$per_page = 15;
$curr_page_num = max(1,(int)($_GET['p']??1));
$category = preg_replace('/[^a-z_-]/','',strtolower($_GET['cat']??''));
$notice_id = (int)($_GET['id']??0);

// Single notice view
if ($notice_id) {
  try {
    $notice = db()->prepare("SELECT * FROM notices WHERE id=? AND is_published=1");
    $notice->execute([$notice_id]);
    $notice = $notice->fetch();
  } catch(Exception $e) { $notice = null; }
}

// List view
try {
  $where = "is_published=1 AND (expire_date IS NULL OR expire_date >= CURDATE())";
  if ($category) $where .= " AND category='" . db()->quote($category) . "'";
  $total = db()->query("SELECT COUNT(*) FROM notices WHERE $where")->fetchColumn();
  $pag = paginate($total, $per_page, $curr_page_num);
  $all_notices = db()->query("SELECT * FROM notices WHERE $where ORDER BY is_important DESC, created_at DESC LIMIT {$pag['per_page']} OFFSET {$pag['offset']}")->fetchAll();
} catch(Exception $e) { $all_notices = []; $pag = ['pages'=>1,'current'=>1]; }
?>

<div class="breadcrumb">
  <div class="container">
    <ol>
      <li class="active"><?= t('News & Notices','সংবাদ ও বিজ্ঞপ্তি') ?></li>
    </ol>
  </div>
</div>

<section class="section">
  <div class="container">
    <div class="main-layout">
      <div>
        <h1 style="color:var(--primary);font-size:1.9rem;font-weight:800;margin-bottom:24px;padding-bottom:12px;border-bottom:3px solid var(--accent)">
          📋 <?= t('News & Notices','সংবাদ ও বিজ্ঞপ্তি') ?>
        </h1>

        <?php if ($notice_id && $notice): ?>
        <!-- Single notice detail -->
        <div style="background:var(--white);border:1px solid var(--border);border-radius:var(--radius-lg);padding:32px">
          <?php if ($notice['is_important']): ?>
          <span class="badge badge-secondary" style="margin-bottom:12px;display:inline-block"><?= t('Important Notice','জরুরি বিজ্ঞপ্তি') ?></span>
          <?php endif; ?>
          <h2 style="font-size:1.4rem;color:var(--primary);margin-bottom:12px"><?= h(field($notice,'title')) ?></h2>
          <div style="color:var(--text-muted);font-size:.85rem;margin-bottom:24px">
            📅 <?= date('d M Y', strtotime($notice['created_at'])) ?>
            | 📁 <?= h(ucfirst($notice['category'])) ?>
          </div>
          <div class="page-content">
            <?= field($notice,'content') ?: '<p style="color:var(--text-muted)">' . t('No content.','বিষয়বস্তু নেই।') . '</p>' ?>
          </div>
          <?php if ($notice['attachment']): ?>
          <div style="margin-top:24px;padding-top:20px;border-top:1px solid var(--border)">
            <a href="<?= upload_url($notice['attachment']) ?>" class="btn btn-primary" download>
              📎 <?= t('Download Attachment','সংযুক্তি ডাউনলোড করুন') ?>
            </a>
          </div>
          <?php endif; ?>
          <div style="margin-top:20px">
            <a href="<?= url('notices') ?>" class="btn btn-outline btn-sm">← <?= t('Back to Notices','বিজ্ঞপ্তি তালিকায় ফিরুন') ?></a>
          </div>
        </div>

        <?php else: ?>
        <!-- Notice listing -->
        <!-- Filter tabs -->
        <div style="display:flex;gap:8px;margin-bottom:24px;flex-wrap:wrap">
          <a href="<?= url('notices') ?>" class="btn btn-sm <?= !$category?'btn-primary':'btn-outline' ?>"><?= t('All','সব') ?></a>
          <?php foreach(['general','academic','exam','admission','event'] as $cat): ?>
          <a href="<?= url('notices') ?>&cat=<?= $cat ?>" class="btn btn-sm <?= $category===$cat?'btn-primary':'btn-outline' ?>">
            <?= t(ucfirst($cat), ['general'=>'সাধারণ','academic'=>'একাডেমিক','exam'=>'পরীক্ষা','admission'=>'ভর্তি','event'=>'অনুষ্ঠান'][$cat]??ucfirst($cat)) ?>
          </a>
          <?php endforeach; ?>
        </div>

        <?php if (!empty($all_notices)): ?>
        <?php foreach ($all_notices as $n): ?>
        <div class="notice-card <?= $n['is_important']?'important':'' ?>">
          <div class="notice-date">
            <div class="day"><?= date('d', strtotime($n['created_at'])) ?></div>
            <div class="mon"><?= date('M', strtotime($n['created_at'])) ?></div>
          </div>
          <div class="notice-body" style="flex:1">
            <div style="display:flex;align-items:flex-start;gap:8px;flex-wrap:wrap">
              <?php if($n['is_important']): ?>
              <span class="badge badge-secondary"><?= t('Important','জরুরি') ?></span>
              <?php endif; ?>
              <span class="badge badge-primary"><?= h(ucfirst($n['category'])) ?></span>
            </div>
            <div class="notice-title" style="margin-top:6px">
              <a href="<?= url('notices') ?>&id=<?= (int)$n['id'] ?>" style="color:var(--text);font-weight:600">
                <?= h(field($n,'title')) ?>
              </a>
            </div>
            <?php if ($n['attachment']): ?>
            <a href="<?= upload_url($n['attachment']) ?>" class="notice-meta" download style="margin-top:4px;display:inline-flex;align-items:center;gap:4px;color:var(--primary);font-size:.8rem">
              📎 <?= t('Attachment','সংযুক্তি') ?>
            </a>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>

        <!-- Pagination -->
        <?php if ($pag['pages'] > 1): ?>
        <div style="display:flex;justify-content:center;gap:8px;margin-top:28px;flex-wrap:wrap">
          <?php for($i=1;$i<=$pag['pages'];$i++): ?>
          <a href="<?= url('notices') ?>&p=<?= $i ?><?= $category?"&cat=$category":'' ?>"
             class="btn btn-sm <?= $i===$pag['current']?'btn-primary':'btn-outline' ?>"><?= $i ?></a>
          <?php endfor; ?>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <p style="color:var(--text-muted);text-align:center;padding:40px"><?= t('No notices found.','কোনো বিজ্ঞপ্তি পাওয়া যায়নি।') ?></p>
        <?php endif; ?>
        <?php endif; ?>
      </div>

      <?php include __DIR__ . '/../partials/sidebar.php'; ?>
    </div>
  </div>
</section>
