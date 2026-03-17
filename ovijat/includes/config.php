<?php
/**
 * OVIJAT GROUP — includes/config.php v2.0
 */
define('DB_HOST',    'localhost');
define('DB_NAME',    'ovijat_db');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');
define('SITE_URL',   'http://localhost/code/ovijat');

//if 
define('BASE_PATH',  dirname(__DIR__));
define('UPLOAD_DIR', BASE_PATH . '/uploads/');
define('UPLOAD_URL', SITE_URL . '/uploads/');

//time zone dhaka
date_default_timezone_set('Asia/Dhaka');

define('IMG_DIMS', [
    'banner'     => ['w'=>1600,'h'=>640, 'crop'=>true],
    'product'    => ['w'=>600, 'h'=>600, 'crop'=>true],
    'logo'       => ['w'=>400, 'h'=>160, 'crop'=>false],
    'management' => ['w'=>400, 'h'=>480, 'crop'=>true],
    'concern'    => ['w'=>300, 'h'=>180, 'crop'=>true],
    'popup'      => ['w'=>800, 'h'=>600, 'crop'=>true],
    'rice'       => ['w'=>700, 'h'=>500, 'crop'=>true],
    'sales'      => ['w'=>300, 'h'=>360, 'crop'=>true],
    'promo'      => ['w'=>800, 'h'=>480, 'crop'=>true],
    'testimonial'=> ['w'=>200, 'h'=>200, 'crop'=>true],
    'partner'    => ['w'=>300, 'h'=>120, 'crop'=>false],
]);
define('ALLOWED_IMG_TYPES', ['image/jpeg','image/jpg','image/png','image/webp','image/gif']);
define('MAX_UPLOAD_SIZE',   10 * 1024 * 1024);

/* ── PDO Singleton ──────────────────────────────────── */
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=".DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            error_log("DB Error: ".$e->getMessage());
            die("<h2>Service temporarily unavailable.</h2>");
        }
    }
    return $pdo;
}

/* ── Settings ───────────────────────────────────────── */
function setting(string $key, string $default = ''): string {
    static $cache = null;
    if ($cache === null) {
        try { $rows = db()->query("SELECT `key`,`value` FROM settings")->fetchAll(); foreach($rows as $r) $cache[$r['key']]=$r['value']; }
        catch(Exception $e){ $cache=[]; }
    }
    return $cache[$key] ?? $default;
}

/* ── Language ───────────────────────────────────────── */
function lang(): string {
    if (isset($_COOKIE['ovijat_lang']) && in_array($_COOKIE['ovijat_lang'],['en','bn'])) return $_COOKIE['ovijat_lang'];
    $default = setting('default_lang','en');
    setcookie('ovijat_lang',$default,time()+(86400*365),'/','',false,false);
    return $default;
}
function t(array $row, string $field): string {
    $key = $field.'_'.lang();
    return htmlspecialchars($row[$key] ?? $row[$field.'_en'] ?? '',ENT_QUOTES,'UTF-8');
}

/* ── Image Processing ───────────────────────────────── */
function processUploadedImage(array $file, string $section, string $subdir, string $oldFile=''): string|false {
    if($file['error'] !== UPLOAD_ERR_OK) return false;
    if($file['size'] > MAX_UPLOAD_SIZE){ $_SESSION['flash']=['msg'=>'File too large (max 10MB).','type'=>'error']; return false; }
    $mime = mime_content_type($file['tmp_name']);
    if(!in_array($mime,ALLOWED_IMG_TYPES)){ $_SESSION['flash']=['msg'=>'Invalid image type.','type'=>'error']; return false; }
    $dims=$_dims=IMG_DIMS[$section]??['w'=>600,'h'=>600,'crop'=>true];
    $tw=$dims['w']; $th=$dims['h']; $doCrop=$dims['crop'];
    $src = match($mime){
        'image/jpeg','image/jpg'=>imagecreatefromjpeg($file['tmp_name']),
        'image/png'             =>imagecreatefrompng($file['tmp_name']),
        'image/webp'            =>imagecreatefromwebp($file['tmp_name']),
        'image/gif'             =>imagecreatefromgif($file['tmp_name']),
        default=>false
    };
    if(!$src){ $_SESSION['flash']=['msg'=>'Could not process image.','type'=>'error']; return false; }
    [$sw,$sh]=[imagesx($src),imagesy($src)];
    if($doCrop){
        $srcRatio=$sw/$sh; $tgtRatio=$tw/$th;
        if($srcRatio>$tgtRatio){$cropH=$sh;$cropW=(int)($sh*$tgtRatio);$cropX=(int)(($sw-$cropW)/2);$cropY=0;}
        else{$cropW=$sw;$cropH=(int)($sw/$tgtRatio);$cropX=0;$cropY=(int)(($sh-$cropH)/2);}
        $canvas=imagecreatetruecolor($tw,$th);
        imagecopyresampled($canvas,$src,0,0,$cropX,$cropY,$tw,$th,$cropW,$cropH);
    } else {
        $ratio=min($tw/$sw,$th/$sh); $nw=(int)($sw*$ratio); $nh=(int)($sh*$ratio);
        $canvas=imagecreatetruecolor($nw,$nh);
        imagefill($canvas,0,0,imagecolorallocate($canvas,255,255,255));
        imagecopyresampled($canvas,$src,0,0,0,0,$nw,$nh,$sw,$sh);
    }
    imagedestroy($src);
    $newName=uniqid('img_',true).'.webp';
    $destDir=UPLOAD_DIR.$subdir.'/';
    if(!is_dir($destDir)) mkdir($destDir,0755,true);
    $saved=imagewebp($canvas,$destDir.$newName,85);
    imagedestroy($canvas);
    if(!$saved){ $_SESSION['flash']=['msg'=>'Failed to save image.','type'=>'error']; return false; }
    if($oldFile && file_exists($destDir.$oldFile)) @unlink($destDir.$oldFile);
    return $newName;
}

function processUploadedFile(array $file, string $subdir, string $oldFile='', array $allowed=['application/pdf']): string|false {
    if($file['error'] !== UPLOAD_ERR_OK) return false;
    if($file['size'] > MAX_UPLOAD_SIZE){ flash('File too large (max 10MB).','error'); return false; }
    $mime = mime_content_type($file['tmp_name']);
    if(!in_array($mime, $allowed)){ flash('Invalid file type. Only PDF allowed.','error'); return false; }
    
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $newName = uniqid('doc_', true) . '.' . $ext;
    $destDir = UPLOAD_DIR . $subdir . '/';
    if(!is_dir($destDir)) mkdir($destDir, 0755, true);
    
    if(move_uploaded_file($file['tmp_name'], $destDir . $newName)){
        if($oldFile && file_exists($destDir . $oldFile)) @unlink($destDir . $oldFile);
        return $newName;
    }
    return false;
}

/* ── Security ───────────────────────────────────────── */
function e(string $str): string { return htmlspecialchars($str,ENT_QUOTES|ENT_HTML5,'UTF-8'); }
function csrf_token(): string { if(empty($_SESSION['csrf_token'])) $_SESSION['csrf_token']=bin2hex(random_bytes(32)); return $_SESSION['csrf_token']; }
function csrf_verify(?string $token = null): bool { 
    $t = $token ?? $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? ''; 
    return !empty($t) && hash_equals($_SESSION['csrf_token'] ?? '', $t); 
}
function redirect(string $url): never { header("Location: $url"); exit; }

/* ── Translation ────────────────────────────────────── */
function __ (string $en, string $bn): string {
    return lang() === 'bn' ? $bn : $en;
}
function L(string $key): string {
    static $strings = null;
    if ($strings === null) $strings = require __DIR__ . '/lang_strings.php';
    $lang = lang();
    return $strings[$key][$lang] ?? $strings[$key]['en'] ?? $key;
}
function flash(string $msg, string $type='success'): void { $_SESSION['flash']=['msg'=>$msg,'type'=>$type]; }
function getFlash(): array { $f=$_SESSION['flash']??null; unset($_SESSION['flash']); return $f??[]; }
function sanitizeText(string $input): string { return strip_tags(trim($input)); }
function paginate(int $total,int $perPage,int $current): array { $pages=(int)ceil($total/$perPage); return ['total'=>$total,'pages'=>$pages,'current'=>$current,'offset'=>($current-1)*$perPage,'limit'=>$perPage]; }
function imgUrl(string $file,string $subdir,string $placeholder='product'): string {
    if($file && file_exists(UPLOAD_DIR.$subdir.'/'.$file)) return UPLOAD_URL.$subdir.'/'.$file;
    $colors=['product'=>'d8f3dc','banner'=>'1a3d2e','management'=>'d8f3dc','concern'=>'fdf6e3','rice'=>'f0faf3','promo'=>'1a3d2e'];
    $bg=$colors[$placeholder]??'f4f4f4';
    $svg='<svg xmlns="http://www.w3.org/2000/svg" width="600" height="400" viewBox="0 0 600 400"><rect width="600" height="400" fill="#'.$bg.'"/><text x="300" y="205" text-anchor="middle" font-family="sans-serif" font-size="16" fill="#aaa">Ovijat Group</text></svg>';
    return 'data:image/svg+xml;base64,'.base64_encode($svg);
}

/* ── Active Ticker ───────────────────────────────────── */
function getActiveTicker(): array {
    if(!setting('ticker_enabled','1')) return [];
    $today=date('Y-m-d');
    try { return db()->query("SELECT * FROM ticker_items WHERE active=1 AND (start_date IS NULL OR start_date<='$today') AND (end_date IS NULL OR end_date>='$today') ORDER BY sort_order")->fetchAll(); }
    catch(Exception $e){ return []; }
}

/* ── Active Popup ────────────────────────────────────── */
function getActivePopup(): ?array {
    $today=date('Y-m-d');
    try { return db()->query("SELECT * FROM event_popups WHERE active=1 AND start_date<='$today' AND end_date>='$today' LIMIT 1")->fetch()?:null; }
    catch(Exception $e){ return null; }
}

/* ── Action Log ──────────────────────────────────────── */
function logAction(string $action, string $details=''): void {
    try {
        db()->prepare("INSERT INTO action_logs (admin_id,admin_user,action,details,ip) VALUES (?,?,?,?,?)")
            ->execute([$_SESSION['admin_id']??null,$_SESSION['admin_user']??null,$action,$details,$_SERVER['REMOTE_ADDR']??null]);
    } catch(Exception $e){}
}

/* ── IP Geolocation & Helpline ───────────────────────── */
function getCountryCode(): string {
    if (!empty($_SESSION['user_country'])) return $_SESSION['user_country'];
    
    $ip = $_SERVER['REMOTE_ADDR'] === '::1' ? '103.145.128.0' : $_SERVER['REMOTE_ADDR']; // Default to BD IP for localhost testing
    try {
        $ctx = stream_context_create(['http' => ['timeout' => 2]]);
        $res = @file_get_contents("http://ip-api.com/json/{$ip}?fields=countryCode", false, $ctx);
        $data = json_decode($res, true);
        $_SESSION['user_country'] = $data['countryCode'] ?? 'BD';
    } catch (Exception $e) {
        $_SESSION['user_country'] = 'BD';
    }
    return $_SESSION['user_country'];
}

function getDynamicHelpline(): string {
    $isBD = (getCountryCode() === 'BD');
    $key = $isBD ? 'helpline_bd' : 'helpline_intl';
    $default = setting('helpline', '09647000025');
    return setting($key, $default);
}
function logVisitor(): void {
    try {
        $page = ($_SERVER['QUERY_STRING']??'') ? '/?'.($_SERVER['QUERY_STRING']) : '/';
        $ref  = $_SERVER['HTTP_REFERER'] ?? null;
        $ua   = mb_substr($_SERVER['HTTP_USER_AGENT']??'',0,500);
        $ip   = $_SERVER['REMOTE_ADDR'] ?? null;
        // Only log once per session page
        $key  = 'vl_'.md5($ip.$page.date('Ymdh'));
        if(empty($_SESSION[$key])){
            $_SESSION[$key]=1;
            db()->prepare("INSERT INTO visitor_logs (ip,page,referrer,user_agent) VALUES (?,?,?,?)")->execute([$ip,$page,$ref,$ua]);
        }
    } catch(Exception $e){}
}
