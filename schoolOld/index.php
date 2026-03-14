<?php
/**
 * BanglaEdu CMS — Public Front Controller
 * All public pages route through this file
 * URLs: /?page=index | /?page=about | /?page=gallery etc.
 */

require_once __DIR__ . '/includes/bootstrap.php';

$page = current_page();

// ─── Fetch current page data from DB ──────────────────────────────────────────
try {
    $stmt = db()->prepare("SELECT * FROM pages WHERE slug=? AND is_published=1");
    $stmt->execute([$page]);
    $page_data = $stmt->fetch();
} catch (Exception $e) {
    $page_data = null;
}

if (!$page_data && $page !== 'index') {
    // Try index as fallback
    http_response_code(404);
    $page_data = ['title_en'=>'Page Not Found','title_bn'=>'পাতাটি পাওয়া যায়নি','content_en'=>'<p>The requested page does not exist.</p>','content_bn'=>'<p>অনুরোধকৃত পাতাটি বিদ্যমান নেই।</p>','template'=>'default'];
    $page = '404';
}

// ─── Fetch menus ──────────────────────────────────────────────────────────────
try {
    $menu_stmt = db()->query("SELECT * FROM menus WHERE is_active=1 AND menu_location='main' ORDER BY sort_order ASC");
    $menus_raw = $menu_stmt->fetchAll();
    // Build tree
    $menus = [];
    $menu_children = [];
    foreach ($menus_raw as $m) {
        if ($m['parent_id'] == 0) $menus[] = $m;
        else $menu_children[$m['parent_id']][] = $m;
    }
} catch (Exception $e) {
    $menus = [];
    $menu_children = [];
}

// ─── Fetch notices (sidebar/ticker) ───────────────────────────────────────────
try {
    $notices = db()->query("SELECT * FROM notices WHERE is_published=1 AND (expire_date IS NULL OR expire_date >= CURDATE()) ORDER BY is_important DESC, created_at DESC LIMIT 10")->fetchAll();
} catch (Exception $e) { $notices = []; }

// ─── Fetch site settings ──────────────────────────────────────────────────────
$site_name    = t(get_setting('site_name_en'), get_setting('site_name_bn'));
$site_tagline = t(get_setting('site_tagline_en'), get_setting('site_tagline_bn'));
$site_logo    = get_setting('site_logo');
$primary_color= get_setting('primary_color','#006B3F');
$secondary_color = get_setting('secondary_color','#F42A41');
$accent_color = get_setting('accent_color','#F7A600');

// ─── Page title ───────────────────────────────────────────────────────────────
$page_title = ($page_data ? field($page_data, 'title') : 'Home') . ' — ' . $site_name;

// ─── Output page ──────────────────────────────────────────────────────────────
include __DIR__ . '/templates/header.php';

// Template routing
$template = $page_data['template'] ?? 'default';
$tpl_file = __DIR__ . '/templates/pages/' . $page . '.php';
$generic  = __DIR__ . '/templates/pages/' . $template . '.php';

if (file_exists($tpl_file)) {
    include $tpl_file;
} elseif ($page === '404') {
    include __DIR__ . '/templates/pages/404.php';
} elseif (file_exists($generic)) {
    include $generic;
} else {
    include __DIR__ . '/templates/pages/default.php';
}

include __DIR__ . '/templates/footer.php';
