<?php
// ============================================================
// index.php — Public Router
// ============================================================
// URLs: ?page=index | ?page=about | ?page=academic&sub=routine
// Localhost: localhost/code/school/?page=index
// ============================================================

session_name('school_public');
session_start();

require_once __DIR__ . '/includes/functions.php';

// Language toggle via GET
if (isset($_GET['setlang']) && in_array($_GET['setlang'], ['en','bn'])) {
    setcookie('lang', $_GET['setlang'], time() + (365 * 86400), '/');
    $_COOKIE['lang'] = $_GET['setlang'];
    $redir = $_SERVER['HTTP_REFERER'] ?? '?page=index';
    header('Location: ' . $redir);
    exit;
}

$page = currentPage();
$sub  = currentSub();

// Allowed pages map
$pageMap = [
    'index'          => 'public/pages/home.php',
    'about'          => 'public/pages/about.php',
    'academic'       => 'public/pages/academic.php',
    'administration' => 'public/pages/administration.php',
    'admission'      => 'public/pages/admission.php',
    'notices'        => 'public/pages/notices.php',
    'gallery'        => 'public/pages/gallery.php',
    'notice_detail'  => 'public/pages/notice_detail.php',
    'apply'          => 'public/pages/apply.php',
];

$pageFile = $pageMap[$page] ?? null;

// Check if it's a custom CMS page
$cmsPage = null;
if (!$pageFile) {
    $cmsPage = getPage($page);
    if ($cmsPage) {
        $pageFile = 'public/pages/cms_page.php';
    } else {
        $pageFile = 'public/pages/404.php';
    }
}

// Load layout
require_once __DIR__ . '/public/includes/header.php';
require_once __DIR__ . '/' . $pageFile;
require_once __DIR__ . '/public/includes/footer.php';
