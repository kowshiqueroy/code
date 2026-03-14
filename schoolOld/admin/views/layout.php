<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($admin_title ?? 'Admin') ?> — BanglaEdu CMS</title>
<style>
/* ── Admin CSS ── */
*{box-sizing:border-box;margin:0;padding:0}
:root{
  --sidebar-w:260px;
  --topbar-h:60px;
  --primary:#006B3F;
  --primary-dark:#004d2e;
  --primary-light:#e8f5ef;
  --accent:#F7A600;
  --secondary:#F42A41;
  --text:#1a1a2e;
  --muted:#6b7280;
  --border:#e5e7eb;
  --bg:#f0f4f8;
  --white:#fff;
  --shadow:0 2px 10px rgba(0,0,0,.08);
  --radius:10px;
}
body{font-family:'Segoe UI',system-ui,sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;flex-direction:column}
a{color:var(--primary);text-decoration:none}
h1,h2,h3{font-weight:700}

/* ── Sidebar ── */
.admin-sidebar{
  position:fixed;top:0;left:0;width:var(--sidebar-w);height:100vh;
  background:var(--primary-dark);
  overflow-y:auto;z-index:200;
  display:flex;flex-direction:column;
  transition:transform .3s;
}
.sidebar-brand{
  padding:20px 20px 16px;
  border-bottom:1px solid rgba(255,255,255,.1);
  flex-shrink:0;
}
.sidebar-brand .brand-name{color:#fff;font-size:1.1rem;font-weight:800;line-height:1.2}
.sidebar-brand .brand-sub{color:rgba(255,255,255,.6);font-size:.72rem;margin-top:2px}
.sidebar-nav{flex:1;padding:12px 0}
.nav-section{padding:16px 16px 4px;font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:rgba(255,255,255,.4)}
.nav-item a{
  display:flex;align-items:center;gap:10px;
  padding:10px 20px;
  color:rgba(255,255,255,.75);
  font-size:.88rem;
  font-weight:500;
  transition:.2s;
  border-left:3px solid transparent;
}
.nav-item a:hover,.nav-item.active a{
  color:#fff;background:rgba(255,255,255,.08);
  border-left-color:var(--accent);
}
.nav-item .icon{font-size:1rem;width:20px;text-align:center;flex-shrink:0}
.nav-badge{background:var(--secondary);color:#fff;font-size:.65rem;padding:2px 7px;border-radius:10px;margin-left:auto;font-weight:700}
.sidebar-footer{padding:16px 20px;border-top:1px solid rgba(255,255,255,.1);flex-shrink:0}
.sidebar-user{display:flex;align-items:center;gap:10px}
.user-avatar{width:36px;height:36px;border-radius:50%;background:var(--accent);color:var(--primary-dark);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.9rem;flex-shrink:0}
.user-info .user-name{color:#fff;font-size:.85rem;font-weight:600}
.user-info .user-role{color:rgba(255,255,255,.5);font-size:.72rem;text-transform:capitalize}

/* ── Main ── */
.admin-main{margin-left:var(--sidebar-w);min-height:100vh;display:flex;flex-direction:column}
.admin-topbar{
  position:sticky;top:0;z-index:100;
  background:var(--white);
  border-bottom:1px solid var(--border);
  height:var(--topbar-h);
  display:flex;align-items:center;justify-content:space-between;
  padding:0 24px;
  box-shadow:var(--shadow);
}
.topbar-title{font-size:1.1rem;font-weight:700;color:var(--text)}
.topbar-actions{display:flex;align-items:center;gap:12px}
.topbar-btn{
  display:flex;align-items:center;gap:6px;
  padding:7px 14px;
  border-radius:8px;font-size:.82rem;font-weight:600;
  cursor:pointer;border:none;transition:.2s;
}
.btn-view-site{background:var(--primary-light);color:var(--primary)}
.btn-view-site:hover{background:var(--primary);color:#fff}
.btn-logout{background:#fde8ea;color:var(--secondary)}
.btn-logout:hover{background:var(--secondary);color:#fff}
.admin-content{flex:1;padding:28px 24px}

/* ── Cards / Panels ── */
.panel{background:var(--white);border:1px solid var(--border);border-radius:var(--radius);margin-bottom:24px;overflow:hidden}
.panel-header{padding:18px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px}
.panel-title{font-size:1rem;font-weight:700;color:var(--text)}
.panel-body{padding:22px}
.stat-cards{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px}
.stat-card{background:var(--white);border:1px solid var(--border);border-radius:var(--radius);padding:20px;display:flex;align-items:center;gap:16px}
.stat-card .sc-icon{width:52px;height:52px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.6rem;flex-shrink:0}
.stat-card .sc-num{font-size:1.9rem;font-weight:800;line-height:1;color:var(--text)}
.stat-card .sc-label{font-size:.78rem;color:var(--muted);margin-top:4px}

/* ── Forms ── */
.form-row{display:grid;gap:18px;margin-bottom:18px}
.form-row.col-2{grid-template-columns:1fr 1fr}
.form-row.col-3{grid-template-columns:1fr 1fr 1fr}
.form-group{margin-bottom:18px}
label.form-label{display:block;font-size:.85rem;font-weight:600;color:#374151;margin-bottom:6px}
.form-control{
  width:100%;padding:10px 14px;
  border:2px solid var(--border);border-radius:8px;
  font-size:.9rem;font-family:inherit;
  outline:none;transition:.2s;background:var(--white);
}
.form-control:focus{border-color:var(--primary)}
textarea.form-control{resize:vertical;min-height:120px}
.form-select{appearance:none;background-image:url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");background-repeat:no-repeat;background-position:right 10px center;background-size:16px;padding-right:36px}
.form-hint{font-size:.76rem;color:var(--muted);margin-top:4px}
.form-check{display:flex;align-items:center;gap:8px;cursor:pointer}
.form-check input{width:16px;height:16px;cursor:pointer;accent-color:var(--primary)}

/* ── Buttons ── */
.btn{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:8px;font-size:.88rem;font-weight:600;cursor:pointer;border:2px solid transparent;transition:.2s;white-space:nowrap}
.btn-primary{background:var(--primary);color:#fff;border-color:var(--primary)}
.btn-primary:hover{background:var(--primary-dark)}
.btn-secondary{background:var(--bg);color:var(--text);border-color:var(--border)}
.btn-secondary:hover{background:var(--border)}
.btn-danger{background:#fde8ea;color:var(--secondary);border-color:#fecaca}
.btn-danger:hover{background:var(--secondary);color:#fff}
.btn-success{background:#d1fae5;color:#065f46;border-color:#a7f3d0}
.btn-success:hover{background:#059669;color:#fff}
.btn-sm{padding:5px 12px;font-size:.8rem}
.btn-xs{padding:3px 8px;font-size:.75rem}

/* ── Tables ── */
.table-wrap{overflow-x:auto}
.admin-table{width:100%;border-collapse:collapse;font-size:.88rem}
.admin-table th{background:var(--bg);border-bottom:2px solid var(--border);padding:12px 16px;text-align:left;font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);white-space:nowrap}
.admin-table td{padding:12px 16px;border-bottom:1px solid var(--border);vertical-align:middle}
.admin-table tr:last-child td{border-bottom:none}
.admin-table tr:hover td{background:var(--primary-light)}
.admin-table .actions{display:flex;gap:6px;flex-wrap:wrap}
.status-badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:700}
.status-published{background:#d1fae5;color:#065f46}
.status-draft{background:#fef3c7;color:#92400e}
.status-inactive{background:#fee2e2;color:#991b1b}

/* ── Alerts ── */
.alert{padding:12px 18px;border-radius:8px;margin-bottom:18px;font-size:.9rem;display:flex;align-items:flex-start;gap:8px}
.alert-success{background:#d1fae5;border:1px solid #a7f3d0;color:#065f46}
.alert-error{background:#fee2e2;border:1px solid #fecaca;color:#991b1b}
.alert-warning{background:#fef3c7;border:1px solid #fcd34d;color:#92400e}
.alert-info{background:var(--primary-light);border:1px solid #a7f3d0;color:var(--primary-dark)}

/* ── Editor placeholder ── */
#contentEditor{min-height:300px;border:2px solid var(--border);border-radius:8px;padding:14px;outline:none;font-family:inherit;font-size:.9rem;line-height:1.7}
#contentEditor:focus{border-color:var(--primary)}
.editor-toolbar{display:flex;gap:4px;flex-wrap:wrap;padding:10px;background:var(--bg);border:2px solid var(--border);border-bottom:none;border-radius:8px 8px 0 0}
.editor-toolbar .tb-btn{background:var(--white);border:1px solid var(--border);border-radius:6px;padding:5px 10px;cursor:pointer;font-size:.82rem;font-weight:600;transition:.2s}
.editor-toolbar .tb-btn:hover{background:var(--primary);color:#fff;border-color:var(--primary)}

/* ── Upload preview ── */
.upload-zone{border:2px dashed var(--border);border-radius:var(--radius);padding:40px;text-align:center;cursor:pointer;transition:.2s;background:var(--bg)}
.upload-zone:hover{border-color:var(--primary);background:var(--primary-light)}
.upload-zone.drag-over{border-color:var(--primary);background:var(--primary-light)}
.img-preview{width:80px;height:80px;object-fit:cover;border-radius:8px;border:2px solid var(--border)}

/* ── Pagination ── */
.pagination{display:flex;gap:6px;flex-wrap:wrap;margin-top:20px}
.page-btn{padding:6px 12px;border:1px solid var(--border);border-radius:6px;font-size:.82rem;color:var(--text);background:var(--white);transition:.2s;cursor:pointer}
.page-btn:hover,.page-btn.active{background:var(--primary);color:#fff;border-color:var(--primary)}

/* ── Responsive ── */
@media(max-width:900px){
  .admin-sidebar{transform:translateX(-100%)}
  .admin-sidebar.open{transform:translateX(0)}
  .admin-main{margin-left:0}
  .stat-cards{grid-template-columns:repeat(2,1fr)}
  .form-row.col-2,.form-row.col-3{grid-template-columns:1fr}
}
@media(max-width:640px){
  .stat-cards{grid-template-columns:1fr 1fr}
  .admin-content{padding:16px}
}
</style>
</head>
<body>

<!-- ── Sidebar ─────────────────────────────────────────────────────────────── -->
<aside class="admin-sidebar" id="adminSidebar">
  <div class="sidebar-brand">
    <a href="/admin/" style="text-decoration:none">
      <div class="brand-name">🏫 BanglaEdu CMS</div>
      <div class="brand-sub">Admin Panel</div>
    </a>
  </div>

  <nav class="sidebar-nav">
    <?php
    $nav = [
      ['section'=>'Dashboard'],
      ['icon'=>'📊','label'=>'Dashboard','action'=>'dashboard'],
      ['section'=>'Content'],
      ['icon'=>'📄','label'=>'Pages','action'=>'pages'],
      ['icon'=>'🗂️','label'=>'Menus','action'=>'menus'],
      ['icon'=>'📋','label'=>'Notices','action'=>'notices'],
      ['icon'=>'📅','label'=>'Events','action'=>'events'],
      ['icon'=>'🖼️','label'=>'Gallery / Media','action'=>'gallery'],
      ['section'=>'School'],
      ['icon'=>'👩‍🏫','label'=>'Teachers','action'=>'teachers'],
      ['icon'=>'🏛️','label'=>'Governing Body','action'=>'governing'],
      ['icon'=>'📚','label'=>'Departments','action'=>'departments'],
      ['icon'=>'📝','label'=>'Admissions','action'=>'admissions_admin'],
      ['icon'=>'📊','label'=>'Results','action'=>'results_admin'],
      ['icon'=>'📅','label'=>'Routines','action'=>'routines'],
      ['icon'=>'🎠','label'=>'Sliders','action'=>'sliders'],
      ['icon'=>'🔗','label'=>'Quick Links','action'=>'quick_links'],
      ['section'=>'Enquiries'],
      ['icon'=>'✉️','label'=>'Contact Messages','action'=>'messages'],
      ['section'=>'System'],
      ['icon'=>'🖼️','label'=>'Media Library','action'=>'media'],
      ['icon'=>'⚙️','label'=>'Settings','action'=>'settings'],
      ['icon'=>'👤','label'=>'Users','action'=>'users'],
      ['icon'=>'🤖','label'=>'AI Assistant','action'=>'ai_assistant'],
    ];
    foreach ($nav as $item):
      if (isset($item['section'])): ?>
      <div class="nav-section"><?= htmlspecialchars($item['section']) ?></div>
    <?php else:
      $is_active = ($action === $item['action']); ?>
      <div class="nav-item <?= $is_active?'active':'' ?>">
        <a href="/admin/?action=<?= $item['action'] ?>">
          <span class="icon"><?= $item['icon'] ?></span>
          <?= htmlspecialchars($item['label']) ?>
        </a>
      </div>
    <?php endif; endforeach; ?>
  </nav>

  <div class="sidebar-footer">
    <div class="sidebar-user">
      <div class="user-avatar"><?= mb_strtoupper(mb_substr($_SESSION['admin_username']??'A',0,1)) ?></div>
      <div class="user-info">
        <div class="user-name"><?= htmlspecialchars($_SESSION['admin_full_name'] ?? $_SESSION['admin_username'] ?? 'Admin') ?></div>
        <div class="user-role"><?= htmlspecialchars($_SESSION['admin_role'] ?? 'admin') ?></div>
      </div>
    </div>
  </div>
</aside>

<!-- ── Main ─────────────────────────────────────────────────────────────────── -->
<div class="admin-main">
  <header class="admin-topbar">
    <div style="display:flex;align-items:center;gap:12px">
      <button onclick="document.getElementById('adminSidebar').classList.toggle('open')"
              style="display:none;background:none;border:none;font-size:1.4rem;cursor:pointer;padding:4px" id="sidebarToggle">☰</button>
      <div class="topbar-title"><?= htmlspecialchars($admin_title ?? 'Dashboard') ?></div>
    </div>
    <div class="topbar-actions">
      <a href="/" target="_blank" class="topbar-btn btn-view-site">🌐 View Site</a>
      <a href="/admin/?action=logout" class="topbar-btn btn-logout"
         onclick="return confirm('Log out?')">🚪 Logout</a>
    </div>
  </header>

  <div class="admin-content">
    <?php echo $page_content; ?>
  </div>
</div>

<script>
// Show toggle on mobile
if (window.innerWidth <= 900) {
  document.getElementById('sidebarToggle').style.display = 'block';
}
window.addEventListener('resize', () => {
  document.getElementById('sidebarToggle').style.display = window.innerWidth <= 900 ? 'block' : 'none';
});
document.addEventListener('click', e => {
  const sb = document.getElementById('adminSidebar');
  if (sb.classList.contains('open') && !sb.contains(e.target) && e.target.id !== 'sidebarToggle') {
    sb.classList.remove('open');
  }
});
</script>
</body>
</html>
