<?php
require_once __DIR__.'/auth.php'; 
requireAdmin();

$tab = $_GET['tab'] ?? 'actions'; // actions | visitors
$search = sanitizeText($_GET['search'] ?? '');
$today = date('Y-m-d');

// Date Range Filters
$startDate = $_GET['start_date'] ?? $today;
$endDate = $_GET['end_date'] ?? $today;

// Ensure start date is not after end date
if ($startDate > $endDate) {
    $startDate = $endDate;
}

$page = max(1, (int)($_GET['p'] ?? 1)); 
$perPg = 50;

if ($tab === 'visitors') {
    // VISITOR LOGS QUERY BUILDING
    $whereClause = "WHERE DATE(created_at) BETWEEN :start_date AND :end_date";
    $params = [
        ':start_date' => $startDate,
        ':end_date' => $endDate
    ];

    if ($search) {
        $whereClause .= " AND (ip LIKE :search OR page LIKE :search)";
        $params[':search'] = "%{$search}%";
    }

    // Get Total Count
    $stmtTotal = db()->prepare("SELECT COUNT(*) FROM visitor_logs $whereClause");
    $stmtTotal->execute($params);
    $total = $stmtTotal->fetchColumn();

    $pg = paginate($total, $perPg, $page);
    
    // Get Paginated Logs
    $stmtLogs = db()->prepare("SELECT * FROM visitor_logs $whereClause ORDER BY created_at DESC LIMIT {$pg['limit']} OFFSET {$pg['offset']}");
    $stmtLogs->execute($params);
    $logs = $stmtLogs->fetchAll();

    // Stats (Filtered by Date Range only)
    $stmtIPs = db()->prepare("SELECT COUNT(DISTINCT ip) FROM visitor_logs WHERE DATE(created_at) BETWEEN :start AND :end");
    $stmtIPs->execute([':start' => $startDate, ':end' => $endDate]);
    $uniqueIPs = $stmtIPs->fetchColumn();

    $stmtTop = db()->prepare("SELECT page, COUNT(*) as cnt FROM visitor_logs WHERE DATE(created_at) BETWEEN :start AND :end GROUP BY page ORDER BY cnt DESC LIMIT 10");
    $stmtTop->execute([':start' => $startDate, ':end' => $endDate]);
    $topPages = $stmtTop->fetchAll();

} else {
    // ACTION LOGS QUERY BUILDING
    $whereClause = "WHERE DATE(created_at) BETWEEN :start_date AND :end_date";
    $params = [
        ':start_date' => $startDate,
        ':end_date' => $endDate
    ];

    if ($search) {
        $whereClause .= " AND (admin_user LIKE :search OR action LIKE :search)";
        $params[':search'] = "%{$search}%";
    }

    // Get Total Count
    $stmtTotal = db()->prepare("SELECT COUNT(*) FROM action_logs $whereClause");
    $stmtTotal->execute($params);
    $total = $stmtTotal->fetchColumn();

    $pg = paginate($total, $perPg, $page);
    
    // Get Paginated Logs
    $stmtLogs = db()->prepare("SELECT * FROM action_logs $whereClause ORDER BY created_at DESC LIMIT {$pg['limit']} OFFSET {$pg['offset']}");
    $stmtLogs->execute($params);
    $logs = $stmtLogs->fetchAll();
}

require_once __DIR__.'/partials/admin_header.php';
?>

<div class="admin-page-header">
    <h1>📊 Logs</h1>
    <p>Today: <?= date('d F Y') ?>. Showing data from: <strong><?= e($startDate) ?></strong> to <strong><?= e($endDate) ?></strong></p>
</div>

<div style="display:flex;gap:.5rem;margin-bottom:1.5rem;flex-wrap:wrap">
  <a href="?tab=actions&start_date=<?= e($startDate)?>&end_date=<?= e($endDate)?>" class="btn <?=$tab==='actions'?'btn-primary':'btn-ghost'?> btn-sm">🛠️ Action Logs</a>
  <a href="?tab=visitors&start_date=<?= e($startDate)?>&end_date=<?= e($endDate)?>" class="btn <?=$tab==='visitors'?'btn-primary':'btn-ghost'?> btn-sm">👁️ Visitor Logs</a>
</div>

<div class="admin-panel" style="padding:1rem 1.5rem; margin-bottom:1.5rem;">
  <form method="GET" style="display:flex;gap:1rem;flex-wrap:wrap;align-items:flex-end">
    <input type="hidden" name="tab" value="<?= e($tab)?>">
    
    <div class="form-group" style="flex:1;min-width:140px">
        <label>Start Date</label>
        <input type="date" name="start_date" class="form-input" value="<?= e($startDate)?>" max="<?= $today?>">
    </div>
    
    <div class="form-group" style="flex:1;min-width:140px">
        <label>End Date</label>
        <input type="date" name="end_date" class="form-input" value="<?= e($endDate)?>" max="<?= $today?>">
    </div>
    
    <div class="form-group" style="flex:2;min-width:200px">
        <label>Search</label>
        <input type="text" name="search" class="form-input" value="<?= e($search)?>" placeholder="<?= $tab==='visitors'?'IP address or page URL':'Username or action name'?>">
    </div>
    
    <div class="form-group">
        <label>&nbsp;</label>
        <button type="submit" class="btn btn-primary">🔍 Filter</button>
    </div>
    
    <div class="form-group">
        <label>&nbsp;</label>
        <a href="?tab=<?= e($tab)?>&start_date=<?= $today?>&end_date=<?= $today?>" class="btn btn-ghost">Today</a>
    </div>
  </form>
</div>

<?php if($tab === 'visitors'): ?>
    <div class="stat-cards" style="margin-bottom:1.5rem">
      <div class="stat-card teal"><div class="sc-icon">👁️</div><div class="sc-num"><?= $total?></div><div class="sc-label">Page Views</div></div>
      <div class="stat-card blue"><div class="sc-icon">🌐</div><div class="sc-num"><?= $uniqueIPs?></div><div class="sc-label">Unique IPs</div></div>
    </div>
    
    <?php if($topPages): ?>
    <div class="admin-panel" style="margin-bottom:1.5rem">
      <h3 class="admin-section-title" style="font-size:.95rem">Top Pages in Range</h3>
      <div style="display:flex;flex-wrap:wrap;gap:.5rem">
        <?php foreach($topPages as $tp):?>
            <span style="background:var(--admin-bg);border:1px solid var(--gray-200);border-radius:6px;padding:.3rem .8rem;font-size:.8rem">
                <code><?= e($tp['page'])?></code> <strong>(<?= $tp['cnt']?>)</strong>
            </span>
        <?php endforeach;?>
      </div>
    </div>
    <?php endif; ?>
    
    <div class="admin-panel">
      <h2 class="admin-section-title">Visitor Log (<?= $total?> entries)</h2>
      <?php if($logs):?>
        <table class="admin-table">
          <thead><tr><th>Date & Time</th><th>IP</th><th>Page</th><th>Referrer</th></tr></thead>
          <tbody>
            <?php foreach($logs as $l):?>
            <tr>
                <td><?= date('d M Y, H:i:s', strtotime($l['created_at']))?></td>
                <td><?= e($l['ip'])?></td>
                <td><?= e(mb_substr($l['page'],0,60))?></td>
                <td><?= $l['referrer'] ? e(parse_url($l['referrer'],PHP_URL_HOST) ?: 'Direct') : 'Direct'?></td>
            </tr>
            <?php endforeach;?>
          </tbody>
        </table>
        
        <?php if($pg['pages']>1):?>
        <div class="pagination">
            <?php for($i=1;$i<=$pg['pages'];$i++):?>
                <a href="?tab=visitors&start_date=<?= e($startDate)?>&end_date=<?= e($endDate)?>&search=<?= urlencode($search)?>&p=<?=$i?>" class="page-btn <?=$i===$page?'active':''?>"><?=$i?></a>
            <?php endfor;?>
        </div>
        <?php endif;?>
        
      <?php else:?>
        <p class="empty-msg">No visitor data found for this range and search.</p>
      <?php endif;?>
    </div>

<?php else: ?>
    <div class="admin-panel">
      <h2 class="admin-section-title">Action Log (<?= $total?> entries)</h2>
      <?php if($logs):?>
        <table class="admin-table">
          <thead><tr><th>Date & Time</th><th>Admin</th><th>Action</th><th>Details</th><th>IP</th></tr></thead>
          <tbody>
            <?php foreach($logs as $l):?>
            <tr>
                <td><?= date('d M Y, H:i:s', strtotime($l['created_at']))?></td>
                <td><?= e($l['admin_user'] ?? '—')?></td>
                <td><?= e($l['action'])?></td>
                <td><?= e(mb_substr($l['details'] ?? '', 0, 60))?></td>
                <td><?= e($l['ip'] ?? '—')?></td>
            </tr>
            <?php endforeach;?>
          </tbody>
        </table>
        
        <?php if($pg['pages']>1):?>
        <div class="pagination">
            <?php for($i=1;$i<=$pg['pages'];$i++):?>
                <a href="?tab=actions&start_date=<?= e($startDate)?>&end_date=<?= e($endDate)?>&search=<?= urlencode($search)?>&p=<?=$i?>" class="page-btn <?=$i===$page?'active':''?>"><?=$i?></a>
            <?php endfor;?>
        </div>
        <?php endif;?>
        
      <?php else:?>
        <p class="empty-msg">No actions logged for this range and search.</p>
      <?php endif;?>
    </div>
<?php endif;?>

<?php require_once __DIR__.'/partials/admin_footer.php'; ?>