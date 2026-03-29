<?php
// ============================================================
//  LocalDrop — PHP Local Network File Manager
//  Place this file in any folder, run with:
//
//  php -d upload_max_filesize=0 -d post_max_size=0 -d max_execution_time=0 -d max_input_time=-1 -d memory_limit=-1 -S 0.0.0.0:8080 filemanager.php
//
//  Then open http://<your-ip>:8080 from any device on LAN
// ============================================================

define('ROOT_DIR', __DIR__ . '/uploads');
define('APP_NAME', 'LocalDrop');

ini_set('upload_max_filesize', '0');
ini_set('post_max_size', '0');
ini_set('max_execution_time', '0');
ini_set('max_input_time', '-1');
ini_set('memory_limit', '-1');

if (!is_dir(ROOT_DIR)) mkdir(ROOT_DIR, 0755, true);

// ── Type helpers ─────────────────────────────────────────────
function getMime(string $file): string {
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $map = [
        'jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif',
        'webp'=>'image/webp','svg'=>'image/svg+xml','bmp'=>'image/bmp','ico'=>'image/x-icon',
        'mp4'=>'video/mp4','webm'=>'video/webm','ogg'=>'video/ogg','mov'=>'video/quicktime',
        'avi'=>'video/x-msvideo','mkv'=>'video/x-matroska','m4v'=>'video/mp4',
        'mp3'=>'audio/mpeg','wav'=>'audio/wav','flac'=>'audio/flac','aac'=>'audio/aac',
        'oga'=>'audio/ogg','m4a'=>'audio/mp4','opus'=>'audio/opus',
        'pdf'=>'application/pdf',
        'txt'=>'text/plain','md'=>'text/plain','csv'=>'text/plain',
        'json'=>'application/json','xml'=>'text/xml',
        'js'=>'text/javascript','css'=>'text/css','php'=>'text/plain',
        'py'=>'text/plain','sh'=>'text/plain','bat'=>'text/plain','log'=>'text/plain',
        'ts'=>'text/plain','jsx'=>'text/plain','tsx'=>'text/plain',
        'c'=>'text/plain','cpp'=>'text/plain','h'=>'text/plain',
        'java'=>'text/plain','rb'=>'text/plain','go'=>'text/plain',
        'sql'=>'text/plain','yaml'=>'text/plain','yml'=>'text/plain',
        'ini'=>'text/plain','html'=>'text/html','htm'=>'text/html',
    ];
    return $map[$ext] ?? 'application/octet-stream';
}
function isImage(string $f): bool { return in_array(strtolower(pathinfo($f,PATHINFO_EXTENSION)),['jpg','jpeg','png','gif','webp','svg','bmp','ico']); }
function isVideo(string $f): bool { return in_array(strtolower(pathinfo($f,PATHINFO_EXTENSION)),['mp4','webm','ogg','mov','avi','mkv','m4v']); }
function isAudio(string $f): bool { return in_array(strtolower(pathinfo($f,PATHINFO_EXTENSION)),['mp3','wav','flac','aac','oga','m4a','opus']); }
function isPdf(string $f):   bool { return strtolower(pathinfo($f,PATHINFO_EXTENSION)) === 'pdf'; }
function isText(string $f):  bool { return in_array(strtolower(pathinfo($f,PATHINFO_EXTENSION)),['txt','md','csv','json','xml','js','css','php','py','sh','bat','log','ini','yaml','yml','html','htm','sql','ts','jsx','tsx','c','cpp','h','java','rb','go','swift','rs','kt']); }
function isPreviewable(string $f): bool { return isImage($f)||isVideo($f)||isAudio($f)||isPdf($f)||isText($f); }
function previewType(string $f): string {
    if (isImage($f)) return 'image';
    if (isVideo($f)) return 'video';
    if (isAudio($f)) return 'audio';
    if (isPdf($f))   return 'pdf';
    if (isText($f))  return 'text';
    return 'none';
}

// ── Path helpers ─────────────────────────────────────────────
function sanitizePath(string $path): string {
    $path = str_replace(['..','//'], '', $path);
    return ltrim($path, '/\\');
}
function resolvePath(string $rel): string {
    $safe = sanitizePath($rel);
    $abs  = ROOT_DIR . ($safe !== '' ? '/' . $safe : '');
    $abs  = str_replace('\\', '/', $abs);
    $rootReal = realpath(ROOT_DIR);
    $existing = $abs;
    while (!file_exists($existing) && $existing !== dirname($existing)) {
        $existing = dirname($existing);
    }
    $resolvedExisting = realpath($existing);
    if ($resolvedExisting === false || strpos($resolvedExisting, $rootReal) !== 0) return ROOT_DIR;
    return $abs;
}
function humanSize(int $bytes): string {
    $units = ['B','KB','MB','GB','TB']; $i = 0;
    while ($bytes >= 1024 && $i < 4) { $bytes /= 1024; $i++; }
    return round($bytes,1).' '.$units[$i];
}
function fileIcon(string $name): string {
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $map = [
        'pdf'=>'📄','doc'=>'📝','docx'=>'📝','txt'=>'📃','md'=>'📃',
        'xls'=>'📊','xlsx'=>'📊','csv'=>'📊','ppt'=>'📋','pptx'=>'📋',
        'jpg'=>'🖼️','jpeg'=>'🖼️','png'=>'🖼️','gif'=>'🖼️','webp'=>'🖼️','svg'=>'🖼️','bmp'=>'🖼️',
        'mp4'=>'🎬','mkv'=>'🎬','avi'=>'🎬','mov'=>'🎬','webm'=>'🎬','m4v'=>'🎬',
        'mp3'=>'🎵','wav'=>'🎵','flac'=>'🎵','ogg'=>'🎵','aac'=>'🎵','m4a'=>'🎵',
        'zip'=>'🗜️','tar'=>'🗜️','gz'=>'🗜️','rar'=>'🗜️','7z'=>'🗜️',
        'php'=>'⚙️','js'=>'⚙️','py'=>'⚙️','sh'=>'⚙️','html'=>'⚙️','css'=>'⚙️',
        'json'=>'⚙️','xml'=>'⚙️','sql'=>'⚙️','ts'=>'⚙️',
    ];
    return $map[$ext] ?? '📁';
}
function breadcrumbs(string $relPath): array {
    $crumbs = [['name'=>'Home','path'=>'']];
    if ($relPath === '') return $crumbs;
    $parts = explode('/', $relPath); $acc = '';
    foreach ($parts as $p) {
        if ($p === '') continue;
        $acc .= ($acc ? '/' : '').$p;
        $crumbs[] = ['name'=>$p,'path'=>$acc];
    }
    return $crumbs;
}

// ── Actions ───────────────────────────────────────────────────
$action  = $_GET['action']  ?? 'browse';
$relPath = sanitizePath($_GET['path'] ?? '');
$message = ''; $msgType = 'success';

// STREAM — supports HTTP Range requests for video seeking
if ($action === 'stream') {
    $file = resolvePath($relPath);
    if (!is_file($file)) { http_response_code(404); exit('Not found'); }
    $mime  = getMime($file);
    $size  = filesize($file);
    $start = 0; $end = $size - 1;
    header('Accept-Ranges: bytes');
    header('Content-Type: ' . $mime);
    header('Cache-Control: public, max-age=3600');
    if (isset($_SERVER['HTTP_RANGE'])) {
        preg_match('/bytes=(\d+)-(\d*)/', $_SERVER['HTTP_RANGE'], $m);
        $start = (int)$m[1];
        $end   = isset($m[2]) && $m[2] !== '' ? (int)$m[2] : $size - 1;
        $end   = min($end, $size - 1);
        http_response_code(206);
        header("Content-Range: bytes $start-$end/$size");
        header('Content-Length: '.($end - $start + 1));
    } else {
        header('Content-Length: '.$size);
    }
    $fp = fopen($file, 'rb'); fseek($fp, $start);
    $remaining = $end - $start + 1;
    while ($remaining > 0 && !feof($fp)) {
        $chunk = min(1024*256, $remaining);
        echo fread($fp, $chunk);
        $remaining -= $chunk; flush();
    }
    fclose($fp); exit;
}

// DOWNLOAD
if ($action === 'download') {
    $file = resolvePath($relPath);
    if (is_file($file)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.basename($file).'"');
        header('Content-Length: '.filesize($file));
        header('Cache-Control: no-cache');
        readfile($file); exit;
    }
}

// DELETE
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $target    = resolvePath(sanitizePath($_POST['target'] ?? ''));
    $parent    = dirname($target);
    $parentRel = ltrim(str_replace(str_replace('\\','/',ROOT_DIR), '', str_replace('\\','/',$parent)), '/\\');
    if (is_file($target)) {
        unlink($target) ? ($message='File deleted.') : ($message='Delete failed.')&($msgType='error');
    } elseif (is_dir($target)) {
        $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iter as $f) { $f->isDir() ? rmdir($f) : unlink($f); }
        rmdir($target) ? ($message='Folder deleted.') : ($message='Delete failed.')&($msgType='error');
    }
    header('Location: ?path='.urlencode($parentRel).'&msg='.urlencode($message).'&mt='.$msgType); exit;
}

// CREATE FOLDER
if ($action === 'mkdir' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawName = trim($_POST['folder_name'] ?? '');
    $newName = trim(preg_replace('/[\/\\\\:*?"<>|]/', '', $rawName));
    $newDir  = resolvePath(($relPath !== '' ? $relPath.'/' : '').$newName);
    if ($newName === '') {
        $message = 'Folder name cannot be empty.'; $msgType = 'error';
    } elseif (is_dir($newDir)) {
        $message = 'Folder already exists.'; $msgType = 'warn';
    } elseif (mkdir($newDir, 0755, true)) {
        $message = 'Folder "'.$newName.'" created.';
    } else {
        $message = 'Could not create folder. Check permissions.'; $msgType = 'error';
    }
    header('Location: ?path='.urlencode($relPath).'&msg='.urlencode($message).'&mt='.$msgType); exit;
}

// UPLOAD
if ($action === 'upload' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $destDir = resolvePath($relPath); $errors = []; $success = 0;
    if (!empty($_FILES['files']['name'][0])) {
        foreach ($_FILES['files']['name'] as $i => $fname) {
            if ($_FILES['files']['error'][$i] !== UPLOAD_ERR_OK) { $errors[] = $fname.': error '.$_FILES['files']['error'][$i]; continue; }
            $safeName = preg_replace('/[^a-zA-Z0-9_\-. ]/', '_', $fname);
            $dest = $destDir.'/'.$safeName;
            if (file_exists($dest)) {
                $info = pathinfo($safeName); $c = 1;
                do { $safeName = $info['filename'].'_'.$c++.'.'.($info['extension']??''); $dest = $destDir.'/'.$safeName; } while (file_exists($dest));
            }
            move_uploaded_file($_FILES['files']['tmp_name'][$i], $dest) ? $success++ : ($errors[]=$fname.': move failed');
        }
    }
    $message = $success.' file(s) uploaded.';
    if ($errors) { $message .= ' Errors: '.implode('; ',$errors); $msgType = $success ? 'warn' : 'error'; }
    header('Location: ?path='.urlencode($relPath).'&msg='.urlencode($message).'&mt='.$msgType); exit;
}

if (isset($_GET['msg'])) { $message = $_GET['msg']; $msgType = $_GET['mt'] ?? 'success'; }

// ── Directory listing ─────────────────────────────────────────
$currentDir = resolvePath($relPath);
$items = ['dirs'=>[],'files'=>[]];
if (is_dir($currentDir)) {
    foreach (scandir($currentDir) as $entry) {
        if ($entry==='.'||$entry==='..') continue;
        $full   = $currentDir.'/'.$entry;
        $entRel = ($relPath ? $relPath.'/' : '').$entry;
        $isDir  = is_dir($full);
        $data   = ['name'=>$entry,'rel'=>$entRel,'mtime'=>filemtime($full),'size'=>$isDir?0:filesize($full)];
        $isDir ? $items['dirs'][] = $data : $items['files'][] = $data;
    }
    usort($items['dirs'],  fn($a,$b)=>strcasecmp($a['name'],$b['name']));
    usort($items['files'], fn($a,$b)=>strcasecmp($a['name'],$b['name']));
}

$crumbs = breadcrumbs($relPath);
$previewableFiles = array_values(array_filter($items['files'], fn($f)=>isPreviewable($f['name'])));
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
  --bg:#0d0f14; --surface:#161921; --surface2:#1e222d; --border:#2a2f3d;
  --accent:#4fffb0; --accent2:#ff6b6b; --accent3:#ffc94a;
  --text:#e8ecf5; --muted:#636b82; --radius:10px;
  --mono:'JetBrains Mono',monospace; --sans:'Syne',sans-serif;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{background:var(--bg);color:var(--text);font-family:var(--sans);min-height:100vh;overflow-x:hidden}

/* HEADER */
.header{background:var(--surface);border-bottom:1px solid var(--border);padding:0 2rem;display:flex;align-items:center;gap:2rem;height:60px;position:sticky;top:0;z-index:100}
.logo{font-size:1.25rem;font-weight:800;letter-spacing:-.03em;color:var(--accent);text-decoration:none;white-space:nowrap}
.logo span{color:var(--muted)}
.header-right{margin-left:auto;display:flex;align-items:center;gap:.75rem}
.server-badge{background:var(--surface2);border:1px solid var(--border);border-radius:6px;padding:.25rem .75rem;font-family:var(--mono);font-size:.7rem;color:var(--muted)}
.server-badge strong{color:var(--accent)}

/* LAYOUT */
.container{max-width:1200px;margin:0 auto;padding:2rem}

/* BREADCRUMBS */
.breadcrumbs{display:flex;align-items:center;gap:.35rem;flex-wrap:wrap;font-size:.8rem;font-family:var(--mono);margin-bottom:1.5rem;color:var(--muted)}
.breadcrumbs a{color:var(--text);text-decoration:none;padding:.2rem .5rem;border-radius:5px;transition:background .15s}
.breadcrumbs a:hover{background:var(--surface2)}
.breadcrumbs .sep{color:var(--border)}
.breadcrumbs .current{color:var(--accent)}

/* FLASH */
.flash{padding:.75rem 1.25rem;border-radius:var(--radius);margin-bottom:1.5rem;font-size:.85rem;font-family:var(--mono);border-left:3px solid;animation:slideIn .25s ease}
.flash.success{background:#0a1f16;border-color:var(--accent);color:var(--accent)}
.flash.error{background:#1f0a0a;border-color:var(--accent2);color:var(--accent2)}
.flash.warn{background:#1f180a;border-color:var(--accent3);color:var(--accent3)}

/* TOOLBAR */
.toolbar{display:flex;gap:.75rem;flex-wrap:wrap;margin-bottom:1.5rem;align-items:flex-start}
.upload-zone{flex:1;min-width:260px;border:2px dashed var(--border);border-radius:var(--radius);padding:1.25rem 1.5rem;text-align:center;cursor:pointer;transition:border-color .2s,background .2s;position:relative;background:var(--surface)}
.upload-zone:hover,.upload-zone.drag{border-color:var(--accent);background:#0a1f16}
.upload-zone input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%}
.upload-zone .uz-icon{font-size:1.8rem;margin-bottom:.3rem}
.upload-zone .uz-label{font-size:.8rem;color:var(--muted)}
.upload-zone .uz-label strong{color:var(--accent)}
.upload-zone .file-list{margin-top:.5rem;font-size:.75rem;font-family:var(--mono);color:var(--text);text-align:left}
.progress-bar-wrap{display:none;height:4px;background:var(--border);border-radius:2px;margin-top:.75rem;overflow:hidden}
.progress-bar{height:100%;width:0%;background:var(--accent);transition:width .1s;border-radius:2px}
.folder-form{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:1.25rem 1.5rem;display:flex;flex-direction:column;gap:.6rem;min-width:200px}
.folder-form label{font-size:.75rem;color:var(--muted);font-family:var(--mono)}
.folder-form input[type=text]{background:var(--surface2);border:1px solid var(--border);border-radius:6px;padding:.45rem .75rem;color:var(--text);font-family:var(--mono);font-size:.85rem;outline:none;transition:border-color .2s;width:100%}
.folder-form input[type=text]:focus{border-color:var(--accent)}

/* BUTTONS */
.btn{display:inline-flex;align-items:center;gap:.4rem;padding:.45rem 1rem;border-radius:6px;font-family:var(--sans);font-size:.8rem;font-weight:600;cursor:pointer;border:1px solid transparent;transition:all .15s;text-decoration:none}
.btn-primary{background:var(--accent);color:#0d0f14;border-color:var(--accent)}
.btn-primary:hover{background:#38e897}
.btn-ghost{background:transparent;color:var(--muted);border-color:var(--border)}
.btn-ghost:hover{border-color:var(--text);color:var(--text)}
.btn-danger{background:transparent;color:var(--accent2);border-color:#3d1a1a}
.btn-danger:hover{background:#1f0a0a}
.btn-preview{background:transparent;color:var(--accent);border-color:#0a2a1a}
.btn-preview:hover{background:#0a1f16}
.btn-sm{padding:.3rem .7rem;font-size:.75rem}
#uploadBtn{display:none}

/* FILE TABLE */
.file-table-wrap{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden}
.file-table-header{display:grid;grid-template-columns:2rem 1fr 120px 160px 175px;padding:.6rem 1rem;font-size:.7rem;font-family:var(--mono);color:var(--muted);text-transform:uppercase;letter-spacing:.06em;border-bottom:1px solid var(--border);background:var(--surface2)}
.file-row{display:grid;grid-template-columns:2rem 1fr 120px 160px 175px;padding:.6rem 1rem;border-bottom:1px solid var(--border);align-items:center;font-size:.85rem;transition:background .1s}
.file-row:last-child{border-bottom:none}
.file-row:hover{background:var(--surface2)}
.file-row .icon{font-size:1rem}
.file-row .name a{color:var(--text);text-decoration:none;font-weight:600}
.file-row .name a:hover{color:var(--accent)}
.file-row .size,.file-row .date{font-family:var(--mono);font-size:.75rem;color:var(--muted)}
.file-row .actions{display:flex;gap:.4rem;justify-content:flex-end}
.empty-state{text-align:center;padding:4rem 2rem;color:var(--muted)}
.empty-state .empty-icon{font-size:3rem;margin-bottom:1rem}
.empty-state p{font-size:.85rem;font-family:var(--mono)}

/* DELETE MODAL */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:200;align-items:center;justify-content:center}
.modal-overlay.open{display:flex;animation:fadeIn .15s ease}
.modal{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:2rem;max-width:380px;width:90%;animation:slideIn .2s ease}
.modal h3{font-size:1.1rem;margin-bottom:.5rem}
.modal p{font-size:.85rem;color:var(--muted);margin-bottom:1.5rem;font-family:var(--mono);word-break:break-all}
.modal .modal-actions{display:flex;gap:.75rem;justify-content:flex-end}

/* ═══════════════════════════════
   PREVIEW LIGHTBOX
═══════════════════════════════ */
.lightbox{display:none;position:fixed;inset:0;background:rgba(5,7,10,.95);z-index:300;flex-direction:column}
.lightbox.open{display:flex;animation:fadeIn .2s ease}

.lb-topbar{display:flex;align-items:center;gap:1rem;padding:.65rem 1.25rem;background:var(--surface);border-bottom:1px solid var(--border);flex-shrink:0;min-height:52px}
.lb-title{font-family:var(--mono);font-size:.82rem;color:var(--text);flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.lb-badge{font-family:var(--mono);font-size:.65rem;padding:.15rem .5rem;border-radius:4px;text-transform:uppercase;letter-spacing:.08em;flex-shrink:0}
.lb-badge.image{background:#0a1a2a;color:#6ac8ff}
.lb-badge.video{background:#1a0a2a;color:#b06aff}
.lb-badge.audio{background:#0a1f16;color:var(--accent)}
.lb-badge.pdf{background:#1f100a;color:#ff9a6a}
.lb-badge.text{background:#1a1a0a;color:#ffe06a}
.lb-topbar-actions{display:flex;align-items:center;gap:.5rem;flex-shrink:0}
.lb-nav-btn{background:var(--surface2);border:1px solid var(--border);color:var(--text);border-radius:6px;padding:.28rem .7rem;font-family:var(--mono);font-size:.78rem;cursor:pointer;transition:all .15s;white-space:nowrap}
.lb-nav-btn:hover:not(:disabled){border-color:var(--accent);color:var(--accent)}
.lb-nav-btn:disabled{opacity:.35;cursor:default}
.lb-counter{font-family:var(--mono);font-size:.75rem;color:var(--muted);white-space:nowrap}
.lb-close-btn{background:transparent;border:1px solid #3d1a1a;color:var(--accent2);border-radius:6px;padding:.28rem .7rem;font-family:var(--mono);font-size:.78rem;cursor:pointer;transition:all .15s;white-space:nowrap}
.lb-close-btn:hover{background:#1f0a0a}

.lb-body{flex:1;overflow:hidden;display:flex;align-items:center;justify-content:center;padding:1.5rem;position:relative}

/* Image preview */
.lb-img{max-width:100%;max-height:100%;object-fit:contain;border-radius:8px;animation:fadeIn .2s ease;cursor:zoom-in}
.lb-img.zoomed{cursor:zoom-out;transform:scale(2);transform-origin:var(--ox,50%) var(--oy,50%)}

/* Video */
.lb-video{max-width:100%;max-height:100%;border-radius:8px;background:#000;outline:none;width:100%}

/* Audio */
.lb-audio-wrap{display:flex;flex-direction:column;align-items:center;gap:1.5rem;width:min(480px,90vw)}
.lb-audio-disc{width:160px;height:160px;background:linear-gradient(135deg,var(--surface2),var(--surface));border-radius:50%;border:3px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:3.5rem;box-shadow:0 0 40px rgba(79,255,176,.1)}
.lb-audio-disc.spinning{animation:spin 6s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
.lb-audio-name{font-family:var(--mono);font-size:.8rem;color:var(--muted);text-align:center;word-break:break-all}
audio.lb-audio{width:100%;border-radius:8px}

/* PDF */
iframe.lb-pdf{width:100%;height:100%;border:none;border-radius:8px;background:#fff}

/* Text */
.lb-text-wrap{width:100%;height:100%;overflow:auto;background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:1.5rem}
.lb-text-wrap pre{font-family:var(--mono);font-size:.8rem;color:var(--text);white-space:pre-wrap;word-break:break-word;line-height:1.7}

/* Loading */
.lb-loading{color:var(--muted);font-family:var(--mono);font-size:.9rem;display:flex;align-items:center;gap:.75rem}
.lb-loading::before{content:'';width:18px;height:18px;border:2px solid var(--border);border-top-color:var(--accent);border-radius:50%;animation:spin .8s linear infinite;flex-shrink:0}

/* Arrow buttons */
.lb-arrow{position:absolute;top:50%;transform:translateY(-50%);background:rgba(0,0,0,.5);border:1px solid var(--border);color:var(--text);border-radius:50%;width:44px;height:44px;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:1.4rem;transition:all .15s;z-index:10;line-height:1;user-select:none}
.lb-arrow:hover{background:var(--surface);border-color:var(--accent);color:var(--accent)}
.lb-arrow.lb-prev{left:.75rem}
.lb-arrow.lb-next{right:.75rem}
.lb-arrow[hidden]{display:none}

/* ANIMATIONS */
@keyframes slideIn{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:none}}
@keyframes fadeIn{from{opacity:0}to{opacity:1}}

/* RESPONSIVE */
@media(max-width:700px){
  .file-table-header{grid-template-columns:2rem 1fr 110px}
  .file-table-header .col-size,.file-table-header .col-date{display:none}
  .file-row{grid-template-columns:2rem 1fr 110px}
  .file-row .size,.file-row .date{display:none}
  .container{padding:1rem}
  .header{padding:0 1rem}
  .lb-arrow{display:none}
  .lb-topbar{flex-wrap:wrap;gap:.5rem}
}
</style>
</head>
<body>

<header class="header">
  <a href="?" class="logo"><?= APP_NAME ?><span>/</span></a>
  <div class="header-right">
    <div class="server-badge">Storage: <strong><?= htmlspecialchars(basename(ROOT_DIR)) ?></strong></div>
  </div>
</header>

<div class="container">

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

  <?php if ($message): ?>
    <div class="flash <?= htmlspecialchars($msgType) ?>"><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>

  <div class="toolbar">
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
    <form class="folder-form" method="POST" action="?action=mkdir&path=<?= urlencode($relPath) ?>">
      <label>New Folder</label>
      <input type="text" name="folder_name" placeholder="folder-name" required>
      <button type="submit" class="btn btn-ghost">＋ Create</button>
    </form>
  </div>

  <div class="file-table-wrap">
    <div class="file-table-header">
      <div></div><div>Name</div>
      <div class="col-size">Size</div>
      <div class="col-date">Modified</div>
      <div style="text-align:right">Actions</div>
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
        $isDir     = is_dir(ROOT_DIR.'/'.$item['rel']);
        $canPrev   = !$isDir && isPreviewable($item['name']);
        $pvIdx     = -1;
        if ($canPrev) {
            foreach ($previewableFiles as $pi => $pf) {
                if ($pf['rel'] === $item['rel']) { $pvIdx = $pi; break; }
            }
        }
    ?>
      <div class="file-row">
        <div class="icon"><?= $isDir ? '📁' : fileIcon($item['name']) ?></div>
        <div class="name">
          <?php if ($isDir): ?>
            <a href="?path=<?= urlencode($item['rel']) ?>"><?= htmlspecialchars($item['name']) ?>/</a>
          <?php elseif ($canPrev): ?>
            <a href="#" onclick="openPreview(<?= $pvIdx ?>);return false;"><?= htmlspecialchars($item['name']) ?></a>
          <?php else: ?>
            <a href="?action=download&path=<?= urlencode($item['rel']) ?>"><?= htmlspecialchars($item['name']) ?></a>
          <?php endif; ?>
        </div>
        <div class="size"><?= $isDir ? '—' : humanSize($item['size']) ?></div>
        <div class="date"><?= date('Y-m-d H:i', $item['mtime']) ?></div>
        <div class="actions">
          <?php if ($canPrev): ?>
            <button class="btn btn-preview btn-sm" onclick="openPreview(<?= $pvIdx ?>)" title="Preview">👁</button>
          <?php endif; ?>
          <?php if (!$isDir): ?>
            <a href="?action=download&path=<?= urlencode($item['rel']) ?>" class="btn btn-ghost btn-sm" title="Download">↓</a>
          <?php endif; ?>
          <button class="btn btn-danger btn-sm" onclick="confirmDelete(<?= json_encode($item['rel']) ?>,<?= json_encode($item['name']) ?>)">✕</button>
        </div>
      </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<!-- Delete Modal -->
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

<!-- Preview Lightbox -->
<div class="lightbox" id="lightbox">
  <div class="lb-topbar">
    <span class="lb-title" id="lbTitle"></span>
    <span class="lb-badge" id="lbBadge"></span>
    <div class="lb-topbar-actions">
      <button class="lb-nav-btn" id="lbPrevBtn" onclick="lbNav(-1)">◀ Prev</button>
      <span class="lb-counter" id="lbCounter"></span>
      <button class="lb-nav-btn" id="lbNextBtn" onclick="lbNav(1)">Next ▶</button>
      <a id="lbDownload" href="#" class="btn btn-ghost btn-sm">↓ Save</a>
      <button class="lb-close-btn" onclick="closeLightbox()">✕ Close</button>
    </div>
  </div>
  <div class="lb-body" id="lbBody">
    <div class="lb-loading">Loading…</div>
  </div>
  <button class="lb-arrow lb-prev" id="lbArrowPrev" onclick="lbNav(-1)" hidden>‹</button>
  <button class="lb-arrow lb-next" id="lbArrowNext" onclick="lbNav(1)"  hidden>›</button>
</div>

<script>
const PREVIEW_FILES = <?= json_encode(array_map(fn($f) => [
  'name' => $f['name'],
  'rel'  => $f['rel'],
  'type' => previewType($f['name']),
], $previewableFiles)) ?>;

let lbIndex = 0;

const streamUrl   = rel => '?action=stream&path='   + encodeURIComponent(rel);
const downloadUrl = rel => '?action=download&path=' + encodeURIComponent(rel);

function openPreview(idx) {
  lbIndex = idx;
  document.getElementById('lightbox').classList.add('open');
  document.body.style.overflow = 'hidden';
  renderPreview();
}

function closeLightbox() {
  stopMedia();
  document.getElementById('lightbox').classList.remove('open');
  document.body.style.overflow = '';
  document.getElementById('lbBody').innerHTML = '<div class="lb-loading">Loading…</div>';
}

function stopMedia() {
  const body = document.getElementById('lbBody');
  body.querySelectorAll('video,audio').forEach(m => { m.pause(); m.src = ''; });
}

function lbNav(dir) {
  stopMedia();
  lbIndex = (lbIndex + dir + PREVIEW_FILES.length) % PREVIEW_FILES.length;
  renderPreview();
}

function renderPreview() {
  if (!PREVIEW_FILES.length) return;
  const f    = PREVIEW_FILES[lbIndex];
  const body = document.getElementById('lbBody');
  const url  = streamUrl(f.rel);
  const many = PREVIEW_FILES.length > 1;

  document.getElementById('lbTitle').textContent   = f.name;
  document.getElementById('lbCounter').textContent = (lbIndex+1) + ' / ' + PREVIEW_FILES.length;
  document.getElementById('lbDownload').href        = downloadUrl(f.rel);

  const badge = document.getElementById('lbBadge');
  badge.textContent = f.type.toUpperCase();
  badge.className   = 'lb-badge ' + f.type;

  document.getElementById('lbPrevBtn').disabled = !many;
  document.getElementById('lbNextBtn').disabled = !many;
  document.getElementById('lbArrowPrev').hidden = !many;
  document.getElementById('lbArrowNext').hidden = !many;

  body.innerHTML = '<div class="lb-loading">Loading…</div>';

  if (f.type === 'image') {
    const img = new Image();
    img.className = 'lb-img';
    img.onload = () => { body.innerHTML = ''; body.appendChild(img); };
    img.onerror = () => { body.innerHTML = '<div class="lb-loading">Failed to load image.</div>'; };
    img.src = url;
    // Click to zoom
    img.addEventListener('click', function(e) {
      if (this.classList.contains('zoomed')) {
        this.classList.remove('zoomed');
      } else {
        const rect = this.getBoundingClientRect();
        this.style.setProperty('--ox', ((e.clientX - rect.left) / rect.width * 100) + '%');
        this.style.setProperty('--oy', ((e.clientY - rect.top)  / rect.height * 100) + '%');
        this.classList.add('zoomed');
      }
    });

  } else if (f.type === 'video') {
    body.innerHTML = '';
    const vid = document.createElement('video');
    vid.className = 'lb-video'; vid.controls = true; vid.autoplay = true; vid.preload = 'metadata';
    const src = document.createElement('source'); src.src = url;
    vid.appendChild(src); body.appendChild(vid);

  } else if (f.type === 'audio') {
    body.innerHTML = '';
    const wrap = document.createElement('div'); wrap.className = 'lb-audio-wrap';
    const disc = document.createElement('div'); disc.className = 'lb-audio-disc'; disc.textContent = '🎵';
    const aud  = document.createElement('audio'); aud.className = 'lb-audio'; aud.controls = true; aud.autoplay = true; aud.src = url;
    aud.addEventListener('play',  () => disc.classList.add('spinning'));
    aud.addEventListener('pause', () => disc.classList.remove('spinning'));
    aud.addEventListener('ended', () => disc.classList.remove('spinning'));
    const name = document.createElement('div'); name.className = 'lb-audio-name'; name.textContent = f.name;
    wrap.append(disc, aud, name); body.appendChild(wrap);

  } else if (f.type === 'pdf') {
    body.innerHTML = '';
    const ifr = document.createElement('iframe'); ifr.className = 'lb-pdf'; ifr.src = url;
    body.appendChild(ifr);

  } else if (f.type === 'text') {
    fetch(url)
      .then(r => r.text())
      .then(text => {
        body.innerHTML = '';
        const wrap = document.createElement('div'); wrap.className = 'lb-text-wrap';
        const pre  = document.createElement('pre'); pre.textContent = text;
        wrap.appendChild(pre); body.appendChild(wrap);
      })
      .catch(() => { body.innerHTML = '<div class="lb-loading">Failed to load file.</div>'; });
  }
}

// Keyboard shortcuts
document.addEventListener('keydown', e => {
  if (!document.getElementById('lightbox').classList.contains('open')) return;
  if (e.key === 'Escape')     closeLightbox();
  if (e.key === 'ArrowRight' || e.key === 'l') lbNav(1);
  if (e.key === 'ArrowLeft'  || e.key === 'h') lbNav(-1);
});

// ── Upload ────────────────────────────────────────────────────
const dropZone = document.getElementById('dropZone');
const fileInput = document.getElementById('fileInput');
const fileList  = document.getElementById('fileList');
const uploadBtn = document.getElementById('uploadBtn');
const uploadForm = document.getElementById('uploadForm');
const progressWrap = document.getElementById('progressWrap');
const progressBar  = document.getElementById('progressBar');

function updateFileList() {
  const files = fileInput.files;
  if (!files.length) { fileList.innerHTML=''; uploadBtn.style.display='none'; return; }
  fileList.innerHTML = Array.from(files).map(f=>`<div>• ${f.name} (${(f.size/1024/1024).toFixed(1)} MB)</div>`).join('');
  uploadBtn.style.display = 'inline-flex';
}
fileInput.addEventListener('change', updateFileList);
['dragenter','dragover'].forEach(e => dropZone.addEventListener(e, ev=>{ev.preventDefault();dropZone.classList.add('drag');}));
['dragleave','drop'].forEach(e => dropZone.addEventListener(e, ()=>dropZone.classList.remove('drag')));
dropZone.addEventListener('drop', ev=>{ev.preventDefault(); if(ev.dataTransfer.files.length){fileInput.files=ev.dataTransfer.files;updateFileList();}});
uploadForm.addEventListener('submit', function(e) {
  e.preventDefault();
  const xhr = new XMLHttpRequest();
  progressWrap.style.display = 'block';
  xhr.upload.addEventListener('progress', ev=>{if(ev.lengthComputable) progressBar.style.width=(ev.loaded/ev.total*100)+'%';});
  xhr.addEventListener('load', ()=>{ window.location.href=xhr.responseURL; });
  xhr.addEventListener('error', ()=>{ alert('Upload failed.'); progressWrap.style.display='none'; });
  xhr.open('POST', this.action);
  xhr.send(new FormData(this));
});

// ── Delete modal ──────────────────────────────────────────────
function confirmDelete(rel, name) {
  document.getElementById('deleteTargetName').textContent = name;
  document.getElementById('deleteTargetInput').value = rel;
  document.getElementById('deleteModal').classList.add('open');
}
function closeModal() { document.getElementById('deleteModal').classList.remove('open'); }
document.getElementById('deleteModal').addEventListener('click', function(e){if(e.target===this)closeModal();});

// ── Flash ──────────────────────────────────────────────────────
const flash = document.querySelector('.flash');
if (flash) setTimeout(()=>flash.style.display='none', 5000);
</script>
</body>
</html>