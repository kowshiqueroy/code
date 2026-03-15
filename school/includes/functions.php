<?php
// ============================================================
// includes/functions.php — Core Helper Functions
// ============================================================

require_once __DIR__ . '/../config/config.php';

// ── Settings ──────────────────────────────────────────────
function getSetting(string $key, string $default = ''): string {
    static $cache = null;
    if ($cache === null) {
        try {
            $rows = getDB()->query("SELECT `key`, `value` FROM settings")->fetchAll();
            $cache = array_column($rows, 'value', 'key');
        } catch (Exception $e) { $cache = []; }
    }
    return $cache[$key] ?? $default;
}

function setSetting(string $key, string $value, string $group = 'general'): void {
    $stmt = getDB()->prepare("INSERT INTO settings (`key`,`value`,`group`) VALUES (?,?,?) ON DUPLICATE KEY UPDATE `value`=?,`group`=?");
    $stmt->execute([$key, $value, $group, $value, $group]);
}

// ── Language ──────────────────────────────────────────────
function getLang(): string {
    if (isset($_COOKIE['lang']) && in_array($_COOKIE['lang'], ['en','bn'])) {
        return $_COOKIE['lang'];
    }
    return getSetting('default_lang', 'bn');
}

function t(string $key_en, string $key_bn): string {
    return getLang() === 'bn' ? $key_bn : $key_en;
}

function field(array $row, string $field): string {
    $lang = getLang();
    $langField = $field . '_' . $lang;
    $fallback  = $field . '_' . ($lang === 'bn' ? 'en' : 'bn');
    return $row[$langField] ?? $row[$fallback] ?? $row[$field] ?? '';
}

// ── URL & Routing ─────────────────────────────────────────
function pageUrl(string $page, array $extra = []): string {
    $params = array_merge(['page' => $page], $extra);
    return '?' . http_build_query($params);
}

function currentPage(): string {
    return preg_replace('/[^a-z0-9_\-]/', '', strtolower($_GET['page'] ?? 'index'));
}

function currentSub(): string {
    return preg_replace('/[^a-z0-9_\-]/', '', strtolower($_GET['sub'] ?? ''));
}

function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

// ── Security ──────────────────────────────────────────────
function h(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function csrfToken(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrfCheck(): void {
    if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) {
        http_response_code(403);
        die('Invalid CSRF token.');
    }
}

function sanitize(string $input): string {
    return trim(strip_tags($input));
}

// ── Image Processing ──────────────────────────────────────
function processImage(string $sourcePath, string $destDir, string $filename, string $mode = 'general'): array {
    $info = getimagesize($sourcePath);
    if (!$info) return [];

    $mime = $info['mime'];
    $srcW = $info[0];
    $srcH = $info[1];

    $src = match($mime) {
        'image/jpeg' => imagecreatefromjpeg($sourcePath),
        'image/png'  => imagecreatefrompng($sourcePath),
        'image/gif'  => imagecreatefromgif($sourcePath),
        'image/webp' => imagecreatefromwebp($sourcePath),
        default      => null
    };
    if (!$src) return [];

    $sizes = match($mode) {
        'portrait','staff','teacher' => [
            'thumb'  => [150, 150, true],
            'medium' => [300, 300, true],
        ],
        'banner' => [
            'thumb'  => [400, 200, true],
            'medium' => [800, 400, true],
            'large'  => [1200, 600, true],
        ],
        default => [
            'thumb'  => [150, 150, true],
            'medium' => [600, 400, false],
            'large'  => [1200, 800, false],
        ]
    };

    $result = [];
    $base   = pathinfo($filename, PATHINFO_FILENAME);

    foreach ($sizes as $size => [$tw, $th, $crop]) {
        $destFile = $destDir . $base . '_' . $size . '.webp';
        $relPath  = str_replace(UPLOAD_PATH, '', $destFile);

        if ($crop) {
            // Smart crop
            $scale = max($tw / $srcW, $th / $srcH);
            $nw    = (int)round($srcW * $scale);
            $nh    = (int)round($srcH * $scale);
            $ox    = (int)floor(($nw - $tw) / 2);
            $oy    = (int)floor(($nh - $th) / 2);
            $tmp   = imagecreatetruecolor($nw, $nh);
            imagecopyresampled($tmp, $src, 0, 0, 0, 0, $nw, $nh, $srcW, $srcH);
            $dst   = imagecreatetruecolor($tw, $th);
            imagecopy($dst, $tmp, 0, 0, $ox, $oy, $tw, $th);
            imagedestroy($tmp);
        } else {
            // Proportional resize
            $scale = min($tw / $srcW, $th / $srcH, 1.0);
            $nw    = (int)round($srcW * $scale);
            $nh    = (int)round($srcH * $scale);
            $dst   = imagecreatetruecolor($nw, $nh);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $srcW, $srcH);
        }

        // Preserve transparency for PNG
        if (in_array($mime, ['image/png','image/gif'])) {
            imagepalettetotruecolor($dst);
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
        }

        imagewebp($dst, $destFile, 85);
        imagedestroy($dst);
        $result[$size] = $relPath;
    }

    imagedestroy($src);
    return $result;
}

function handleUpload(string $fieldName, string $folder = 'general', string $mode = 'general'): array {
    if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
        return ['error' => 'No file uploaded or upload error.'];
    }

    $file = $_FILES[$fieldName];
    $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
    $allowedDocs = ['application/pdf','application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document'];

    $mime = mime_content_type($file['tmp_name']);
    $isImage = in_array($mime, $allowed);
    $isDoc   = in_array($mime, $allowedDocs);

    if (!$isImage && !$isDoc) {
        return ['error' => 'Invalid file type.'];
    }

    if ($file['size'] > 10 * 1024 * 1024) {
        return ['error' => 'File too large (max 10MB).'];
    }

    $destDir = UPLOAD_PATH . ($isImage ? 'images/' : 'documents/');
    @mkdir($destDir, 0755, true);

    $ext      = $isImage ? 'webp' : pathinfo($file['name'], PATHINFO_EXTENSION);
    $base     = preg_replace('/[^a-z0-9_\-]/', '_', strtolower(pathinfo($file['name'], PATHINFO_FILENAME)));
    $filename = $base . '_' . time() . '_' . rand(100,999) . '.' . $ext;

    if ($isImage) {
        $sizes = processImage($file['tmp_name'], $destDir, $filename, $mode);
        // Save original as webp too
        $origPath = $destDir . $filename;
        $info = getimagesize($file['tmp_name']);
        $src = match($info['mime']) {
            'image/jpeg' => imagecreatefromjpeg($file['tmp_name']),
            'image/png'  => imagecreatefrompng($file['tmp_name']),
            'image/gif'  => imagecreatefromgif($file['tmp_name']),
            'image/webp' => imagecreatefromwebp($file['tmp_name']),
            default => null
        };
        if ($src) { imagewebp($src, $origPath, 90); imagedestroy($src); }

        // Save to media library
        $pdo = getDB();
        $stmt = $pdo->prepare("INSERT INTO media (filename,original_name,mime_type,file_size,thumb,medium,large,folder,uploaded_by) VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $filename, $file['name'], 'image/webp', $file['size'],
            $sizes['thumb'] ?? '', $sizes['medium'] ?? '', $sizes['large'] ?? '',
            $folder, $_SESSION['user_id'] ?? 0
        ]);

        return ['success' => true, 'filename' => $filename, 'sizes' => $sizes, 'media_id' => $pdo->lastInsertId()];
} else {
    $docDest = $destDir . $filename;
    move_uploaded_file($file['tmp_name'], $docDest);

    // Save to media library
    $pdo = getDB();
    $stmt = $pdo->prepare("INSERT INTO media 
        (filename, original_name, mime_type, file_size, folder, uploaded_by) 
        VALUES (?,?,?,?,?,?)");
    $stmt->execute([
        $filename,
        $file['name'],
        $mime,
        $file['size'],
        $folder,
        $_SESSION['user_id'] ?? 0
    ]);

    return [
        'success' => true,
        'filename' => $filename,
        'path' => 'documents/' . $filename,
        'media_id' => $pdo->lastInsertId()
    ];
}
}

function imgUrl(string $path, string $size = 'medium'): string {
    if (empty($path)) return UPLOAD_URL . 'images/placeholder.png';
    if (str_starts_with($path, 'http')) return $path;
    // Check if it already has size suffix
    if (str_contains($path, '_thumb') || str_contains($path, '_medium') || str_contains($path, '_large')) {
        return UPLOAD_URL . 'images/' . $path;
    }
    // Try size variant
    $base    = pathinfo($path, PATHINFO_FILENAME);
    $variant = $base . '_' . $size . '.webp';
    $full    = UPLOAD_PATH . 'images/' . $variant;
    if (file_exists($full)) return UPLOAD_URL . 'images/' . $variant;
    return UPLOAD_URL . 'images/' . $path;
}

// ── Notices / Content Fetching ─────────────────────────────
function getNotices(string $type = '', int $limit = 10, bool $activeOnly = true): array {
    $where = $activeOnly ? "is_active=1 AND (expire_date IS NULL OR expire_date >= CURDATE())" : "1=1";
    $params = [];
    if ($type) { $where .= " AND type=?"; $params[] = $type; }
    $stmt = getDB()->prepare("SELECT * FROM notices WHERE $where ORDER BY is_pinned DESC, created_at DESC LIMIT $limit");
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getStaff(string $category = '', bool $activeOnly = true): array {
    $where = $activeOnly ? "is_active=1" : "1=1";
    $params = [];
    if ($category) { $where .= " AND category=?"; $params[] = $category; }
    $stmt = getDB()->prepare("SELECT * FROM staff WHERE $where ORDER BY sort_order ASC, name_en ASC");
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getMenus(string $location = 'main'): array {
    $stmt = getDB()->prepare("SELECT * FROM menus WHERE menu_location=? AND is_active=1 ORDER BY sort_order ASC");
    $stmt->execute([$location]);
    $all = $stmt->fetchAll();
    // Build tree
    $tree = [];
    $map  = [];
    foreach ($all as &$item) { $map[$item['id']] = &$item; $item['children'] = []; }
    foreach ($all as &$item) {
        if ($item['parent_id'] == 0) {
            $tree[] = &$item;
        } elseif (isset($map[$item['parent_id']])) {
            $map[$item['parent_id']]['children'][] = &$item;
        }
    }
    return $tree;
}

function getPage(string $slug): ?array {
    $stmt = getDB()->prepare("SELECT * FROM pages WHERE slug=? AND is_active=1");
    $stmt->execute([$slug]);
    return $stmt->fetch() ?: null;
}

function getBanners(): array {
    return getDB()->query("SELECT * FROM banners WHERE is_active=1 ORDER BY sort_order ASC")->fetchAll();
}

function getHonorees(string $type = 'student', int $limit = 1): array {
    $stmt = getDB()->prepare("SELECT * FROM honorees WHERE type=? AND is_active=1 ORDER BY year DESC LIMIT $limit");
    $stmt->execute([$type]);
    return $stmt->fetchAll();
}

function getGalleryAlbums(int $limit = 12): array {
    return getDB()->prepare("SELECT * FROM gallery_albums WHERE is_active=1 ORDER BY sort_order ASC, album_date DESC LIMIT $limit")->execute() ? getDB()->query("SELECT * FROM gallery_albums WHERE is_active=1 ORDER BY sort_order ASC, album_date DESC LIMIT $limit")->fetchAll() : [];
}

function getAlbumImages(int $albumId, int $limit = 50): array {
    $stmt = getDB()->prepare("SELECT * FROM gallery_images WHERE album_id=? AND is_active=1 ORDER BY sort_order ASC LIMIT $limit");
    $stmt->execute([$albumId]);
    return $stmt->fetchAll();
}

// ── Pagination ─────────────────────────────────────────────
function paginate(string $table, string $where = '1=1', array $params = [], int $perPage = 20): array {
    $page  = max(1, (int)($_GET['paged'] ?? 1));
    $offset= ($page - 1) * $perPage;
    $total = getDB()->prepare("SELECT COUNT(*) FROM $table WHERE $where");
    $total->execute($params);
    $count = (int)$total->fetchColumn();
    return [
        'total'      => $count,
        'pages'      => ceil($count / $perPage),
        'current'    => $page,
        'per_page'   => $perPage,
        'offset'     => $offset,
    ];
}

// ── Date formatting ────────────────────────────────────────
function formatDate(string $date, bool $bangla = false): string {
    if (!$date) return '';
    $ts = strtotime($date);
    if ($bangla || getLang() === 'bn') {
        $months_bn = ['জানুয়ারি','ফেব্রুয়ারি','মার্চ','এপ্রিল','মে','জুন','জুলাই','আগস্ট','সেপ্টেম্বর','অক্টোবর','নভেম্বর','ডিসেম্বর'];
        return date('d', $ts) . ' ' . $months_bn[(int)date('n', $ts) - 1] . ', ' . date('Y', $ts);
    }
    return date('d M, Y', $ts);
}

function banglaNum(string $num): string {
    if (getLang() !== 'bn') return $num;
    return strtr($num, ['0'=>'০','1'=>'১','2'=>'২','3'=>'৩','4'=>'৪','5'=>'৫','6'=>'৬','7'=>'৭','8'=>'৮','9'=>'৯']);
}

// ── Flash Messages ─────────────────────────────────────────
function flash(string $msg, string $type = 'success'): void {
    $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
}

function getFlash(): ?array {
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

// ── Admin Auth ────────────────────────────────────────────
function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        redirect('login.php');
    }
}

function currentUser(): ?array {
    if (!isLoggedIn()) return null;
    static $user = null;
    if ($user === null) {
        $stmt = getDB()->prepare("SELECT * FROM users WHERE id=?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch() ?: null;
    }
    return $user;
}

function can(string $role): bool {
    $u = currentUser();
    if (!$u) return false;
    $roles = ['editor' => 1, 'admin' => 2, 'superadmin' => 3];
    return ($roles[$u['role']] ?? 0) >= ($roles[$role] ?? 99);
}

// ── Truncate text ─────────────────────────────────────────
function excerpt(string $text, int $words = 20): string {
    $text  = strip_tags($text);
    $parts = explode(' ', $text);
    if (count($parts) <= $words) return $text;
    return implode(' ', array_slice($parts, 0, $words)) . '…';
}
