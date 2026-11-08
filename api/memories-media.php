<?php
require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

Auth::requireLogin();
$manager = new MemoriesManager();
$media = $manager->getMedia((int)($_GET['id'] ?? 0));
if (!$media || !in_array($media['media_type'], ['image', 'video'], true)) {
    http_response_code(404);
    exit;
}

$directory = realpath(MEMORIES_STORAGE_PATH);
$filename = !empty($_GET['thumb']) && $media['media_type'] === 'image'
    ? $media['thumb_path']
    : $media['file_path'];
if (!$directory || !$filename || basename($filename) !== $filename) {
    http_response_code(404);
    exit;
}
$path = realpath($directory . DIRECTORY_SEPARATOR . $filename);
if (!$path || strpos($path, $directory . DIRECTORY_SEPARATOR) !== 0 || !is_file($path)) {
    http_response_code(404);
    exit;
}

$mime = $media['media_type'] === 'image' ? 'image/jpeg' : (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'webm' ? 'video/webm' : 'video/mp4');
header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, private');
header('Accept-Ranges: bytes');
$size = filesize($path);
$start = 0;
$end = $size - 1;
if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $range)) {
    if ($range[1] === '' && $range[2] === '') {
        http_response_code(416);
        header('Content-Range: bytes */' . $size);
        exit;
    }
    if ($range[1] === '') {
        $start = max(0, $size - (int)$range[2]);
    } else {
        $start = (int)$range[1];
    }
    if ($range[2] !== '' && $range[1] !== '') {
        $end = min($end, (int)$range[2]);
    }
    if ($start > $end || $start >= $size) {
        http_response_code(416);
        header('Content-Range: bytes */' . $size);
        exit;
    }
    http_response_code(206);
    header("Content-Range: bytes {$start}-{$end}/{$size}");
}
$length = $end - $start + 1;
header('Content-Length: ' . $length);
$handle = fopen($path, 'rb');
if ($handle === false) {
    http_response_code(404);
    exit;
}
fseek($handle, $start);
while ($length > 0 && !feof($handle)) {
    $chunk = fread($handle, min(8192, $length));
    if ($chunk === false || $chunk === '') {
        break;
    }
    echo $chunk;
    $length -= strlen($chunk);
}
fclose($handle);
