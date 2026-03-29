<?php
// ============================================================
//  LocalDrop — PHP Local Network File Manager
//  Place this file in any folder, run with:
//  php -S 0.0.0.0:8080 filemanager.php
//  Then open http://<your-ip>:8080 from any device on LAN
//
//  For large files (required!), start with these php.ini overrides:
//  php -d upload_max_filesize=0 -d post_max_size=0 -d max_execution_time=0 -d max_input_time=-1 -d memory_limit=-1 -S 0.0.0.0:8080 filemanager.php
// ============================================================

define('ROOT_DIR', __DIR__ . '/uploads');
define('APP_NAME', 'LocalDrop');

// Remove all PHP upload/execution limits — disk space is the only limit
ini_set('upload_max_filesize', '0');
ini_set('post_max_size', '0');
ini_set('max_execution_time', '0');
ini_set('max_input_time', '-1');
ini_set('memory_limit', '-1');

// Create uploads directory if it doesn't exist
if (!is_dir(ROOT_DIR)) {
    mkdir(ROOT_DIR, 0755, true);
}

// ── Helpers ──────────────────────────────────────────────────

function sanitizePath(string $path): string {
    $path = str_replace(['..', '//'], '', $path);
    $path = ltrim($path, '/\\');
    return $path;
}

function resolvePath(string $rel): string {
    $safe = sanitizePath($rel);
    // Build the raw path without realpath so non-existent paths work too
    $abs = ROOT_DIR . ($safe !== '' ? '/' . $safe : '');
    // Normalize slashes but don't use realpath (it returns false for missing paths)
    $abs = str_replace('\\', '/', $abs);
    // Security: ensure it stays inside ROOT_DIR
    $rootReal = realpath(ROOT_DIR);
    // Resolve any .. or symlinks only on the existing portion
    $existing = $abs;
    while (!file_exists($existing) && $existing !== dirname($existing)) {
        $existing = dirname($existing);
    }
    $resolvedExisting = realpath($existing);
    if ($resolvedExisting === false || strpos($resolvedExisting, $rootReal) !== 0) {
        return ROOT_DIR;
    }
    return $abs;
}

function humanSize(int $bytes): string {
    $units = ['B','KB','MB','GB','TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < 4) { $bytes /= 1024; $i++; }
    return round($bytes, 1) . ' ' . $units[$i];
}

function fileIcon(string $name): string {
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $map = [
        'pdf'  => '📄', 'doc'  => '📝', 'docx' => '📝', 'txt'  => '📃',
        'xls'  => '📊', 'xlsx' => '📊', 'csv'  => '📊', 'ppt'  => '📋', 'pptx' => '📋',
        'jpg'  => '🖼️',  'jpeg' => '🖼️',  'png'  => '🖼️',  'gif'  => '🖼️',  'webp' => '🖼️',  'svg'  => '🖼️',
        'mp4'  => '🎬', 'mkv'  => '🎬', 'avi'  => '🎬', 'mov'  => '🎬',
        'mp3'  => '🎵', 'wav'  => '🎵', 'flac' => '🎵', 'ogg'  => '🎵',
        'zip'  => '🗜️',  'tar'  => '🗜️',  'gz'   => '🗜️',  'rar'  => '🗜️',  '7z'   => '🗜️',
        'php'  => '⚙️',  'js'   => '⚙️',  'py'   => '⚙️',  'sh'   => '⚙️',  'html' => '⚙️',
    ];
    return $map[$ext] ?? '📁';
}

function breadcrumbs(string $relPath): array {
    $crumbs = [['name' => 'Home', 'path' => '']];
    if ($relPath === '') return $crumbs;
    $parts = explode('/', $relPath);
    $acc   = '';
    foreach ($parts as $p) {
        if ($p === '') continue;
        $acc .= ($acc ? '/' : '') . $p;
        $crumbs[] = ['name' => $p, 'path' => $acc];
    }
    return $crumbs;
}

// ── Action handlers ───────────────────────────────────────────

$action  = $_GET['action']  ?? 'browse';
$relPath = sanitizePath($_GET['path'] ?? '');
$message = '';
$msgType = 'success';

// DOWNLOAD
if ($action === 'download') {
    $file = resolvePath($relPath);
    if (is_file($file)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
        header('Content-Length: ' . filesize($file));
        header('Cache-Control: no-cache');
        readfile($file);
        exit;
    }
}

// DELETE
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $target = resolvePath(sanitizePath($_POST['target'] ?? ''));
    $parent = dirname($target);
    $parentRel = ltrim(str_replace(ROOT_DIR, '', $parent), '/\\');

    if (is_file($target)) {
        unlink($target) ? ($message = 'File deleted.') : ($message = 'Delete failed.') & ($msgType = 'error');
    } elseif (is_dir($target)) {
        // Recursive delete
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iter as $f) { $f->isDir() ? rmdir($f) : unlink($f); }
        rmdir($target) ? ($message = 'Folder deleted.') : ($message = 'Delete failed.') & ($msgType = 'error');
    }
    header('Location: ?path=' . urlencode($parentRel) . '&msg=' . urlencode($message) . '&mt=' . $msgType);
    exit;
}

// CREATE FOLDER
if ($action === 'mkdir' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawName = trim($_POST['folder_name'] ?? '');
    // Allow letters, numbers, spaces, dashes, underscores, dots, parentheses, brackets
    $newName = preg_replace('/[\/\\\\:*?"<>|]/', '', $rawName); // only strip truly illegal chars
    $newName = trim($newName);
    $newDir  = resolvePath(($relPath !== '' ? $relPath . '/' : '') . $newName);
    if ($newName === '') {
        $message = 'Folder name cannot be empty.';
        $msgType = 'error';
    } elseif (is_dir($newDir)) {
        $message = 'Folder already exists.';
        $msgType = 'warn';
    } elseif (mkdir($newDir, 0755, true)) {
        $message = 'Folder "' . $newName . '" created.';
    } else {
        $message = 'Could not create folder. Check permissions.';
        $msgType = 'error';
    }
    header('Location: ?path=' . urlencode($relPath) . '&msg=' . urlencode($message) . '&mt=' . $msgType);
    exit;
}

// UPLOAD
if ($action === 'upload' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $destDir = resolvePath($relPath);
    $errors  = [];
    $success = 0;

    if (!empty($_FILES['files']['name'][0])) {
        foreach ($_FILES['files']['name'] as $i => $fname) {
            if ($_FILES['files']['error'][$i] !== UPLOAD_ERR_OK) {
                $errors[] = $fname . ': upload error ' . $_FILES['files']['error'][$i];
                continue;
            }
            $safeName = preg_replace('/[^a-zA-Z0-9_\-. ]/', '_', $fname);
            $dest     = $destDir . '/' . $safeName;
            // Avoid overwriting — append number
            if (file_exists($dest)) {
                $info = pathinfo($safeName);
                $c = 1;
                do {
                    $safeName = $info['filename'] . '_' . $c++ . '.' . ($info['extension'] ?? '');
                    $dest = $destDir . '/' . $safeName;
                } while (file_exists($dest));
            }
            move_uploaded_file($_FILES['files']['tmp_name'][$i], $dest) ? $success++ : ($errors[] = $fname . ': move failed');
        }
    }

    $message = $success . ' file(s) uploaded.';
    if ($errors) { $message .= ' Errors: ' . implode('; ', $errors); $msgType = $success ? 'warn' : 'error'; }
    header('Location: ?path=' . urlencode($relPath) . '&msg=' . urlencode($message) . '&mt=' . $msgType);
    exit;
}

// Restore flash message from redirect
if (isset($_GET['msg'])) { $message = $_GET['msg']; $msgType = $_GET['mt'] ?? 'success'; }

// ── Build directory listing ───────────────────────────────────

$currentDir = resolvePath($relPath);
$items = ['dirs' => [], 'files' => []];

if (is_dir($currentDir)) {
    foreach (scandir($currentDir) as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $full    = $currentDir . '/' . $entry;
        $entRel  = ($relPath ? $relPath . '/' : '') . $entry;
        $isDir   = is_dir($full);
        $data    = [
            'name'    => $entry,
            'rel'     => $entRel,
            'mtime'   => filemtime($full),
            'size'    => $isDir ? 0 : filesize($full),
        ];
        $isDir ? $items['dirs'][] = $data : $items['files'][] = $data;
    }
    usort($items['dirs'],  fn($a,$b) => strcasecmp($a['name'], $b['name']));
    usort($items['files'], fn($a,$b) => strcasecmp($a['name'], $b['name']));
}

$crumbs = breadcrumbs($relPath);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= APP_NAME ?> — <?= htmlspecialchars($relPath ?: 'Home') ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600&family=Syne:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
  :root {
    --bg:       #0d0f14;
    --surface:  #161921;
    --surface2: #1e222d;
    --border:   #2a2f3d;
    --accent:   #4fffb0;
    --accent2:  #ff6b6b;
    --accent3:  #ffc94a;
    --text:     #e8ecf5;
    --muted:    #636b82;
    --radius:   10px;
    --mono:     'JetBrains Mono', monospace;
    --sans:     'Syne', sans-serif;
  }

  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    background: var(--bg);
    color: var(--text);
    font-family: var(--sans);
    min-height: 100vh;
    overflow-x: hidden;
  }

  /* ── HEADER ── */
  .header {
    background: var(--surface);
    border-bottom: 1px solid var(--border);
    padding: 0 2rem;
    display: flex;
    align-items: center;
    gap: 2rem;
    height: 60px;
    position: sticky;
    top: 0;
    z-index: 100;
  }
  .logo {
    font-size: 1.25rem;
    font-weight: 800;
    letter-spacing: -0.03em;
    color: var(--accent);
    text-decoration: none;
    white-space: nowrap;
  }
  .logo span { color: var(--muted); }
  .header-right { margin-left: auto; display: flex; align-items: center; gap: .75rem; }
  .server-badge {
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: 6px;
    padding: .25rem .75rem;
    font-family: var(--mono);
    font-size: .7rem;
    color: var(--muted);
  }
  .server-badge strong { color: var(--accent); }

  /* ── MAIN LAYOUT ── */
  .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }

  /* ── BREADCRUMBS ── */
  .breadcrumbs {
    display: flex;
    align-items: center;
    gap: .35rem;
    flex-wrap: wrap;
    font-size: .8rem;
    font-family: var(--mono);
    margin-bottom: 1.5rem;
    color: var(--muted);
  }
  .breadcrumbs a {
    color: var(--text);
    text-decoration: none;
    padding: .2rem .5rem;
    border-radius: 5px;
    transition: background .15s;
  }
  .breadcrumbs a:hover { background: var(--surface2); }
  .breadcrumbs .sep { color: var(--border); }
  .breadcrumbs .current { color: var(--accent); }

  /* ── FLASH MESSAGE ── */
  .flash {
    padding: .75rem 1.25rem;
    border-radius: var(--radius);
    margin-bottom: 1.5rem;
    font-size: .85rem;
    font-family: var(--mono);
    border-left: 3px solid;
    animation: slideIn .25s ease;
  }
  .flash.success { background: #0a1f16; border-color: var(--accent); color: var(--accent); }
  .flash.error   { background: #1f0a0a; border-color: var(--accent2); color: var(--accent2); }
  .flash.warn    { background: #1f180a; border-color: var(--accent3); color: var(--accent3); }

  /* ── TOOLBAR ── */
  .toolbar {
    display: flex;
    gap: .75rem;
    flex-wrap: wrap;
    margin-bottom: 1.5rem;
    align-items: flex-start;
  }

  /* Upload zone */
  .upload-zone {
    flex: 1;
    min-width: 260px;
    border: 2px dashed var(--border);
    border-radius: var(--radius);
    padding: 1.25rem 1.5rem;
    text-align: center;
    cursor: pointer;
    transition: border-color .2s, background .2s;
    position: relative;
    background: var(--surface);
  }
  .upload-zone:hover, .upload-zone.drag { border-color: var(--accent); background: #0a1f16; }
  .upload-zone input[type=file] { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; }
  .upload-zone .uz-icon { font-size: 1.8rem; margin-bottom: .3rem; }
  .upload-zone .uz-label { font-size: .8rem; color: var(--muted); }
  .upload-zone .uz-label strong { color: var(--accent); }
  .upload-zone .file-list { margin-top: .5rem; font-size: .75rem; font-family: var(--mono); color: var(--text); text-align: left; }

  /* New folder */
  .folder-form {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 1.25rem 1.5rem;
    display: flex;
    flex-direction: column;
    gap: .6rem;
    min-width: 200px;
  }
  .folder-form label { font-size: .75rem; color: var(--muted); font-family: var(--mono); }
  .folder-form input[type=text] {
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: 6px;
    padding: .45rem .75rem;
    color: var(--text);
    font-family: var(--mono);
    font-size: .85rem;
    outline: none;
    transition: border-color .2s;
    width: 100%;
  }
  .folder-form input[type=text]:focus { border-color: var(--accent); }

  /* Buttons */
  .btn {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    padding: .45rem 1rem;
    border-radius: 6px;
    font-family: var(--sans);
    font-size: .8rem;
    font-weight: 600;
    cursor: pointer;
    border: 1px solid transparent;
    transition: all .15s;
    text-decoration: none;
  }
  .btn-primary { background: var(--accent); color: #0d0f14; border-color: var(--accent); }
  .btn-primary:hover { background: #38e897; }
  .btn-ghost   { background: transparent; color: var(--muted); border-color: var(--border); }
  .btn-ghost:hover { border-color: var(--text); color: var(--text); }
  .btn-danger  { background: transparent; color: var(--accent2); border-color: #3d1a1a; }
  .btn-danger:hover { background: #1f0a0a; }
  .btn-sm { padding: .3rem .7rem; font-size: .75rem; }

  /* Upload submit button */
  #uploadBtn { display: none; }

  /* ── FILE TABLE ── */
  .file-table-wrap {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    overflow: hidden;
  }
  .file-table-header {
    display: grid;
    grid-template-columns: 2rem 1fr 120px 160px 140px;
    padding: .6rem 1rem;
    font-size: .7rem;
    font-family: var(--mono);
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: .06em;
    border-bottom: 1px solid var(--border);
    background: var(--surface2);
  }
  .file-row {
    display: grid;
    grid-template-columns: 2rem 1fr 120px 160px 140px;
    padding: .6rem 1rem;
    border-bottom: 1px solid var(--border);
    align-items: center;
    font-size: .85rem;
    transition: background .1s;
  }
  .file-row:last-child { border-bottom: none; }
  .file-row:hover { background: var(--surface2); }
  .file-row .icon { font-size: 1rem; }
  .file-row .name a { color: var(--text); text-decoration: none; font-weight: 600; }
  .file-row .name a:hover { color: var(--accent); }
  .file-row .size, .file-row .date { font-family: var(--mono); font-size: .75rem; color: var(--muted); }
  .file-row .actions { display: flex; gap: .4rem; justify-content: flex-end; }

  .empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: var(--muted);
  }
  .empty-state .empty-icon { font-size: 3rem; margin-bottom: 1rem; }
  .empty-state p { font-size: .85rem; font-family: var(--mono); }

  /* ── DELETE MODAL ── */
  .modal-overlay {
    display: none;
    position: fixed; inset: 0;
    background: rgba(0,0,0,.7);
    z-index: 200;
    align-items: center; justify-content: center;
  }
  .modal-overlay.open { display: flex; animation: fadeIn .15s ease; }
  .modal {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 2rem;
    max-width: 380px;
    width: 90%;
    animation: slideIn .2s ease;
  }
  .modal h3 { font-size: 1.1rem; margin-bottom: .5rem; }
  .modal p  { font-size: .85rem; color: var(--muted); margin-bottom: 1.5rem; font-family: var(--mono); word-break: break-all; }
  .modal .modal-actions { display: flex; gap: .75rem; justify-content: flex-end; }

  /* ── UPLOAD PROGRESS ── */
  .progress-bar-wrap {
    display: none;
    height: 4px;
    background: var(--border);
    border-radius: 2px;
    margin-top: .75rem;
    overflow: hidden;
  }
  .progress-bar {
    height: 100%;
    width: 0%;
    background: var(--accent);
    transition: width .1s;
    border-radius: 2px;
  }

  /* ── ANIMATIONS ── */
  @keyframes slideIn { from { opacity:0; transform:translateY(-8px); } to { opacity:1; transform:none; } }
  @keyframes fadeIn  { from { opacity:0; } to { opacity:1; } }

  /* ── RESPONSIVE ── */
  @media (max-width: 700px) {
    .file-table-header { grid-template-columns: 2rem 1fr 100px; }
    .file-table-header .col-size, .file-table-header .col-date { display: none; }
    .file-row { grid-template-columns: 2rem 1fr 100px; }
    .file-row .size, .file-row .date { display: none; }
    .container { padding: 1rem; }
    .header { padding: 0 1rem; }
  }
</style>
</head>
<body>

<header class="header">
  <a href="?" class="logo"><?= APP_NAME ?><span>/</span></a>
  <div class="header-right">
    <div class="server-badge">
      Storage: <strong><?= htmlspecialchars(basename(ROOT_DIR)) ?></strong>
    </div>
  </div>
</header>

<div class="container">

  <!-- Breadcrumbs -->
  <nav class="breadcrumbs">
    <?php foreach ($crumbs as $i => $c): ?>
      <?php if ($i > 0): ?><span class="sep">/</span><?php endif; ?>
      <?php if ($i === count($crumbs)-1): ?>
        <span class="current"><?= htmlspecialchars($c['name']) ?></span>
      <?php else: ?>
        <a href="?path=<?= urlencode($c['path']) ?>"><?= htmlspecialchars($c['name']) ?></a>
      <?php endif; ?>
    <?php endforeach; ?>
  </nav>

  <!-- Flash -->
  <?php if ($message): ?>
    <div class="flash <?= htmlspecialchars($msgType) ?>"><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>

  <!-- Toolbar -->
  <div class="toolbar">

    <!-- Upload zone -->
    <form id="uploadForm" method="POST" action="?action=upload&path=<?= urlencode($relPath) ?>" enctype="multipart/form-data" style="flex:1;min-width:260px;">
      <div class="upload-zone" id="dropZone">
        <input type="file" name="files[]" id="fileInput" multiple>
        <div class="uz-icon">⬆️</div>
        <div class="uz-label">Drag & drop files or <strong>click to browse</strong></div>
        <div class="file-list" id="fileList"></div>
        <div class="progress-bar-wrap" id="progressWrap"><div class="progress-bar" id="progressBar"></div></div>
      </div>
      <div style="margin-top:.6rem;">
        <button type="submit" id="uploadBtn" class="btn btn-primary">⬆ Upload Files</button>
      </div>
    </form>

    <!-- New folder -->
    <form class="folder-form" method="POST" action="?action=mkdir&path=<?= urlencode($relPath) ?>">
      <label>New Folder</label>
      <input type="text" name="folder_name" placeholder="folder-name" required>
      <button type="submit" class="btn btn-ghost">＋ Create</button>
    </form>

  </div>

  <!-- File listing -->
  <div class="file-table-wrap">
    <div class="file-table-header">
      <div></div>
      <div>Name</div>
      <div class="col-size">Size</div>
      <div class="col-date">Modified</div>
      <div style="text-align:right;">Actions</div>
    </div>

    <?php
    $allItems = array_merge($items['dirs'], $items['files']);
    if (empty($allItems)):
    ?>
      <div class="empty-state">
        <div class="empty-icon">📂</div>
        <p>This folder is empty. Upload some files!</p>
      </div>
    <?php else: foreach ($allItems as $item):
        $isDir = is_dir(ROOT_DIR . '/' . $item['rel']);
    ?>
      <div class="file-row">
        <div class="icon"><?= $isDir ? '📁' : fileIcon($item['name']) ?></div>
        <div class="name">
          <?php if ($isDir): ?>
            <a href="?path=<?= urlencode($item['rel']) ?>"><?= htmlspecialchars($item['name']) ?>/</a>
          <?php else: ?>
            <a href="?action=download&path=<?= urlencode($item['rel']) ?>" title="Download"><?= htmlspecialchars($item['name']) ?></a>
          <?php endif; ?>
        </div>
        <div class="size"><?= $isDir ? '—' : humanSize($item['size']) ?></div>
        <div class="date"><?= date('Y-m-d H:i', $item['mtime']) ?></div>
        <div class="actions">
          <?php if (!$isDir): ?>
            <a href="?action=download&path=<?= urlencode($item['rel']) ?>" class="btn btn-ghost btn-sm" title="Download">↓</a>
          <?php endif; ?>
          <button class="btn btn-danger btn-sm"
            onclick="confirmDelete(<?= json_encode($item['rel']) ?>, <?= json_encode($item['name']) ?>)">✕</button>
        </div>
      </div>
    <?php endforeach; endif; ?>
  </div>

</div>

<!-- Delete modal -->
<div class="modal-overlay" id="deleteModal">
  <div class="modal">
    <h3>Delete item?</h3>
    <p id="deleteTargetName"></p>
    <form method="POST" id="deleteForm" action="?action=delete">
      <input type="hidden" name="target" id="deleteTargetInput">
      <div class="modal-actions">
        <button type="button" class="btn btn-ghost" onclick="closeModal()">Cancel</button>
        <button type="submit" class="btn btn-danger">Delete</button>
      </div>
    </form>
  </div>
</div>

<script>
// ── Drag & drop ──────────────────────────────────────────────
const dropZone   = document.getElementById('dropZone');
const fileInput  = document.getElementById('fileInput');
const fileList   = document.getElementById('fileList');
const uploadBtn  = document.getElementById('uploadBtn');
const uploadForm = document.getElementById('uploadForm');
const progressWrap = document.getElementById('progressWrap');
const progressBar  = document.getElementById('progressBar');

function updateFileList() {
  const files = fileInput.files;
  if (files.length === 0) { fileList.innerHTML = ''; uploadBtn.style.display = 'none'; return; }
  const names = Array.from(files).map(f => `<div>• ${f.name} (${(f.size/1024/1024).toFixed(1)} MB)</div>`).join('');
  fileList.innerHTML = names;
  uploadBtn.style.display = 'inline-flex';
}

fileInput.addEventListener('change', updateFileList);

['dragenter','dragover'].forEach(e => dropZone.addEventListener(e, ev => {
  ev.preventDefault(); dropZone.classList.add('drag');
}));
['dragleave','drop'].forEach(e => dropZone.addEventListener(e, () => dropZone.classList.remove('drag')));

dropZone.addEventListener('drop', ev => {
  ev.preventDefault();
  const dt = ev.dataTransfer;
  if (dt.files.length) {
    fileInput.files = dt.files;
    updateFileList();
  }
});

// ── AJAX upload with progress ────────────────────────────────
uploadForm.addEventListener('submit', function(e) {
  e.preventDefault();
  const formData = new FormData(this);
  const xhr = new XMLHttpRequest();
  progressWrap.style.display = 'block';

  xhr.upload.addEventListener('progress', ev => {
    if (ev.lengthComputable) {
      progressBar.style.width = (ev.loaded / ev.total * 100) + '%';
    }
  });

  xhr.addEventListener('load', () => { window.location.href = xhr.responseURL; });
  xhr.addEventListener('error', () => { alert('Upload failed. Check server logs.'); progressWrap.style.display = 'none'; });

  xhr.open('POST', this.action);
  xhr.send(formData);
});

// ── Delete modal ─────────────────────────────────────────────
function confirmDelete(rel, name) {
  document.getElementById('deleteTargetName').textContent = name;
  document.getElementById('deleteTargetInput').value = rel;
  document.getElementById('deleteModal').classList.add('open');
}
function closeModal() {
  document.getElementById('deleteModal').classList.remove('open');
}
document.getElementById('deleteModal').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});

// ── Auto-dismiss flash ───────────────────────────────────────
const flash = document.querySelector('.flash');
if (flash) setTimeout(() => flash.style.display = 'none', 5000);
</script>
</body>
</html>