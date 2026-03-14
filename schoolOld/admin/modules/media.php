<?php
/**
 * Admin Media Library — Upload, process multiple sizes, manage files
 */
$admin_title = 'Media Library';
$msg    = '';
$errors = [];

// ─── Multi-size image processor ───────────────────────────────────────────────
function process_image_upload(array $file, string $context = 'general'): array {
    $upload_dir = APP_ROOT . '/assets/uploads/media/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_images = ['jpg','jpeg','png','gif','webp'];
    $allowed_docs   = ['pdf','doc','docx','xls','xlsx','ppt','pptx','txt'];
    $allowed_videos = ['mp4','webm','avi','mov'];

    $is_image = in_array($ext, $allowed_images);
    $is_doc   = in_array($ext, $allowed_docs);
    $is_video = in_array($ext, $allowed_videos);

    if (!$is_image && !$is_doc && !$is_video) {
        return ['success'=>false,'error'=>"File type .$ext is not allowed."];
    }
    if ($file['size'] > 20*1024*1024) {
        return ['success'=>false,'error'=>'File exceeds 20MB limit.'];
    }

    $base = uniqid() . '_' . preg_replace('/[^a-z0-9]/','_',strtolower(pathinfo($file['name'],PATHINFO_FILENAME)));
    $orig_name = $base . '.' . $ext;
    $orig_path = $upload_dir . $orig_name;

    if (!move_uploaded_file($file['tmp_name'], $orig_path)) {
        return ['success'=>false,'error'=>'Could not save file.'];
    }

    $result = [
        'success'      => true,
        'filename'     => $orig_name,
        'original_name'=> $file['name'],
        'file_path'    => 'media/' . $orig_name,
        'file_size'    => $file['size'],
        'mime_type'    => $file['type'],
        'file_type'    => $is_image ? 'image' : ($is_video ? 'video' : 'document'),
        'thumb_path'   => '',
        'medium_path'  => '',
        'large_path'   => '',
    ];

    // Process image sizes using the resize function
    if ($is_image && function_exists('imagecreatefromjpeg')) {
        // Define sizes based on context
        $sizes = [
            'thumb'  => [200, 200, true],   // thumbnail: 200×200 crop
            'medium' => [600, 400, false],  // medium: 600×400 fit
            'large'  => [1200, 800, false], // large: 1200×800 fit
        ];

        // Special contexts
        if ($context === 'teacher' || $context === 'person') {
            $sizes = [
                'thumb'  => [150, 150, true],  // avatar
                'medium' => [300, 300, true],  // profile
                'large'  => [600, 600, true],  // full
            ];
        } elseif ($context === 'banner' || $context === 'slider') {
            $sizes = [
                'thumb'  => [300, 200, false],
                'medium' => [800, 400, false],
                'large'  => [1400, 600, false],
            ];
        } elseif ($context === 'gallery') {
            $sizes = [
                'thumb'  => [250, 200, true],
                'medium' => [700, 500, false],
                'large'  => [1400, 1000, false],
            ];
        }

        foreach ($sizes as $size_name => [$sw, $sh, $crop]) {
            $sized_name = $base . '_' . $size_name . '.' . $ext;
            $sized_path = $upload_dir . $sized_name;
            if (resize_image($orig_path, $sized_path, $sw, $sh, $crop)) {
                $result[$size_name . '_path'] = 'media/' . $sized_name;
            }
        }
        // Map to standard keys
        $result['thumb_path']  = $result['thumb_path']  ?? '';
        $result['medium_path'] = $result['medium_path'] ?? '';
        $result['large_path']  = $result['large_path']  ?? '';
    }

    return $result;
}

// Re-use resize function defined in teachers.php via shared include
if (!function_exists('resize_image')) {
    function resize_image(string $src, string $dst, int $w, int $h, bool $crop = false): bool {
        $info = @getimagesize($src);
        if (!$info) return false;
        [$sw, $sh, $type] = [$info[0], $info[1], $info[2]];
        $si = match($type) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($src),
            IMAGETYPE_PNG  => imagecreatefrompng($src),
            IMAGETYPE_GIF  => imagecreatefromgif($src),
            default => false
        };
        if (!$si) return false;
        $di = imagecreatetruecolor($w, $h);
        if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_GIF) {
            imagealphablending($di, false); imagesavealpha($di, true);
            imagefilledrectangle($di, 0, 0, $w, $h, imagecolorallocatealpha($di,0,0,0,127));
        }
        if ($crop) {
            $sr = $sw/$sh; $dr = $w/$h;
            if ($sr > $dr) { $ch=$sh; $cw=(int)($sh*$dr); $sx=(int)(($sw-$cw)/2); $sy=0; }
            else           { $cw=$sw; $ch=(int)($sw/$dr); $sx=0; $sy=(int)(($sh-$ch)/2); }
            imagecopyresampled($di,$si,0,0,$sx,$sy,$w,$h,$cw,$ch);
        } else {
            $sr=$sw/$sh; $dr=$w/$h;
            if ($sr>$dr) { $nw=$w; $nh=(int)($w/$sr); $ox=0; $oy=(int)(($h-$nh)/2); }
            else         { $nh=$h; $nw=(int)($h*$sr); $ox=(int)(($w-$nw)/2); $oy=0; }
            imagefill($di,0,0,imagecolorallocate($di,255,255,255));
            imagecopyresampled($di,$si,$ox,$oy,0,0,$nw,$nh,$sw,$sh);
        }
        $r = match($type) {
            IMAGETYPE_PNG => imagepng($di,$dst,8),
            IMAGETYPE_GIF => imagegif($di,$dst),
            default       => imagejpeg($di,$dst,88)
        };
        imagedestroy($si); imagedestroy($di);
        return $r;
    }
}

// ─── Handle upload POST ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['files'])) {
    $context = $_POST['context'] ?? 'general';
    $alt_text = trim($_POST['alt_text'] ?? '');
    $uploaded = 0;
    $files = $_FILES['files'];

    // Normalize for multiple
    $file_count = is_array($files['name']) ? count($files['name']) : 1;
    for ($i = 0; $i < $file_count; $i++) {
        $single = is_array($files['name']) ? [
            'name'     => $files['name'][$i],
            'type'     => $files['type'][$i],
            'tmp_name' => $files['tmp_name'][$i],
            'error'    => $files['error'][$i],
            'size'     => $files['size'][$i],
        ] : $files;

        if ($single['error'] !== UPLOAD_ERR_OK) continue;

        $up = process_image_upload($single, $context);
        if ($up['success']) {
            db()->prepare("INSERT INTO media_library (filename,original_name,file_path,thumb_path,medium_path,large_path,file_type,file_size,mime_type,alt_text,uploaded_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
               ->execute([$up['filename'],$up['original_name'],$up['file_path'],$up['thumb_path'],$up['medium_path'],$up['large_path'],$up['file_type'],$up['file_size'],$up['mime_type'],$alt_text,$_SESSION['admin_user_id']]);
            $uploaded++;
        } else {
            $errors[] = $up['error'];
        }
    }
    if ($uploaded) $msg = "$uploaded file(s) uploaded and processed successfully.";
}

// Handle delete
if (isset($_GET['delete'])) {
    $mid = (int)$_GET['delete'];
    $row = db()->prepare("SELECT * FROM media_library WHERE id=?"); $row->execute([$mid]); $row = $row->fetch();
    if ($row) {
        foreach (['file_path','thumb_path','medium_path','large_path'] as $f) {
            if (!empty($row[$f])) @unlink(APP_ROOT.'/assets/uploads/'.$row[$f]);
        }
        db()->prepare("DELETE FROM media_library WHERE id=?")->execute([$mid]);
        $msg = 'File deleted.';
    }
}

// Handle alt text update (AJAX)
if (isset($_POST['update_alt'])) {
    db()->prepare("UPDATE media_library SET alt_text=? WHERE id=?")
       ->execute([trim($_POST['alt_text']), (int)$_POST['media_id']]);
    echo json_encode(['success'=>true]);
    exit;
}

// Fetch media
$filter = preg_replace('/[^a-z]/', '', $_GET['type'] ?? '');
$where  = $filter ? "WHERE file_type=?" : "";
$params = $filter ? [$filter] : [];
$per = 24;
$pg  = max(1,(int)($_GET['p']??1));
$total = db()->prepare("SELECT COUNT(*) FROM media_library $where"); $total->execute($params); $total = $total->fetchColumn();
$pag = paginate($total, $per, $pg);
$media_s = db()->prepare("SELECT * FROM media_library $where ORDER BY created_at DESC LIMIT {$pag['per_page']} OFFSET {$pag['offset']}");
$media_s->execute($params);
$media_list = $media_s->fetchAll();
?>

<?php if ($msg): ?><div class="alert alert-success">✅ <?= h($msg) ?></div><?php endif; ?>
<?php foreach ($errors as $e): ?><div class="alert alert-error">⚠️ <?= h($e) ?></div><?php endforeach; ?>

<!-- Upload Panel -->
<div class="panel">
  <div class="panel-header">
    <div class="panel-title">⬆️ Upload Files</div>
  </div>
  <div class="panel-body">
    <form method="POST" enctype="multipart/form-data" id="uploadForm">
      <div class="form-row col-3">
        <div class="form-group">
          <label class="form-label">Upload Context (determines resize dimensions)</label>
          <select name="context" class="form-control form-select">
            <option value="general">General (600×400, 200×200 thumb)</option>
            <option value="gallery">Gallery (700×500, 250×200 thumb)</option>
            <option value="banner">Banner/Slider (1400×600, 800×400)</option>
            <option value="teacher">Teacher/Person (300×300 square crop)</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Alt Text (for accessibility)</label>
          <input type="text" name="alt_text" class="form-control" placeholder="Describe the image">
        </div>
        <div class="form-group" style="align-self:end">
          <button type="submit" class="btn btn-primary btn-block">⬆️ Upload</button>
        </div>
      </div>
      <!-- Drop zone -->
      <div class="upload-zone" id="dropZone" onclick="document.getElementById('fileInput').click()">
        <div style="font-size:2.5rem;margin-bottom:8px">📁</div>
        <div style="font-weight:600;margin-bottom:4px">Click or drag & drop files here</div>
        <div style="font-size:.82rem;color:var(--muted)">Supports: JPG, PNG, GIF, WEBP, PDF, DOC, MP4 (max 20MB each)</div>
        <div id="fileList" style="margin-top:12px;font-size:.82rem;color:var(--primary)"></div>
      </div>
      <input type="file" name="files[]" id="fileInput" multiple accept="image/*,.pdf,.doc,.docx,.mp4,.webm" style="display:none"
             onchange="showFileList(this)">
    </form>
  </div>
</div>

<!-- Media Library -->
<div class="panel">
  <div class="panel-header">
    <div class="panel-title">🖼️ Media Library (<?= number_format($total) ?> files)</div>
    <div style="display:flex;gap:8px">
      <a href="/admin/?action=media" class="btn btn-sm <?= !$filter?'btn-primary':'btn-secondary' ?>">All</a>
      <a href="/admin/?action=media&type=image" class="btn btn-sm <?= $filter==='image'?'btn-primary':'btn-secondary' ?>">Images</a>
      <a href="/admin/?action=media&type=document" class="btn btn-sm <?= $filter==='document'?'btn-primary':'btn-secondary' ?>">Docs</a>
      <a href="/admin/?action=media&type=video" class="btn btn-sm <?= $filter==='video'?'btn-primary':'btn-secondary' ?>">Videos</a>
    </div>
  </div>
  <div class="panel-body">
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px">
      <?php foreach ($media_list as $m): ?>
      <div style="background:var(--bg);border:1px solid var(--border);border-radius:8px;overflow:hidden;position:relative" class="media-item">
        <!-- Preview -->
        <?php if ($m['file_type'] === 'image'): ?>
        <div style="height:120px;overflow:hidden;cursor:pointer"
             onclick="copyUrl('<?= upload_url($m['file_path']) ?>')">
          <img src="<?= upload_url($m['thumb_path'] ?: $m['file_path']) ?>"
               alt="<?= h($m['alt_text']) ?>"
               style="width:100%;height:100%;object-fit:cover" loading="lazy">
        </div>
        <?php elseif ($m['file_type'] === 'video'): ?>
        <div style="height:120px;background:#1a1a2e;display:flex;align-items:center;justify-content:center;font-size:2.5rem">🎬</div>
        <?php else: ?>
        <div style="height:120px;background:#f8fafc;display:flex;align-items:center;justify-content:center;font-size:2.5rem">
          <?= str_ends_with($m['filename'],'.pdf')?'📄':'📎' ?>
        </div>
        <?php endif; ?>

        <div style="padding:8px">
          <div style="font-size:.72rem;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-bottom:4px" title="<?= h($m['original_name']) ?>">
            <?= h(mb_substr($m['original_name'],0,22)) ?>
          </div>
          <div style="font-size:.68rem;color:var(--muted)"><?= number_format($m['file_size']/1024,1) ?>KB</div>
          <?php if (!empty($m['thumb_path']) && !empty($m['medium_path'])): ?>
          <div style="font-size:.65rem;color:var(--primary);margin-top:2px">✅ Processed</div>
          <?php endif; ?>
          <div style="display:flex;gap:4px;margin-top:6px">
            <button onclick="copyUrl('<?= upload_url($m['file_path']) ?>')" class="btn btn-xs btn-secondary" title="Copy URL">📋</button>
            <a href="<?= upload_url($m['file_path']) ?>" target="_blank" class="btn btn-xs btn-success" title="View">👁️</a>
            <a href="/admin/?action=media&delete=<?= (int)$m['id'] ?>" class="btn btn-xs btn-danger" onclick="return confirm('Delete this file?')" title="Delete">🗑️</a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (empty($media_list)): ?>
      <div style="grid-column:1/-1;text-align:center;padding:40px;color:var(--muted)">No files uploaded yet. Use the form above to add files.</div>
      <?php endif; ?>
    </div>
    <?php if ($pag['pages'] > 1): ?>
    <div class="pagination" style="margin-top:20px">
      <?php for($i=1;$i<=$pag['pages'];$i++): ?>
      <a href="/admin/?action=media&p=<?= $i ?><?= $filter?"&type=$filter":'' ?>" class="page-btn <?= $i===$pag['current']?'active':'' ?>"><?= $i ?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<div id="copyToast" style="position:fixed;bottom:24px;right:24px;background:#1a1a2e;color:#fff;padding:10px 18px;border-radius:8px;font-size:.88rem;display:none;z-index:999">URL copied to clipboard!</div>

<script>
function copyUrl(url) {
  navigator.clipboard.writeText(url).then(() => {
    const toast = document.getElementById('copyToast');
    toast.style.display = 'block';
    setTimeout(() => toast.style.display = 'none', 2000);
  });
}

function showFileList(input) {
  const list = document.getElementById('fileList');
  if (input.files.length) {
    list.textContent = Array.from(input.files).map(f => f.name + ' (' + (f.size/1024).toFixed(1) + 'KB)').join('\n');
  }
}

// Drag and drop
const zone = document.getElementById('dropZone');
const fi = document.getElementById('fileInput');
zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag-over'); });
zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
zone.addEventListener('drop', e => {
  e.preventDefault();
  zone.classList.remove('drag-over');
  fi.files = e.dataTransfer.files;
  showFileList(fi);
});
</script>
