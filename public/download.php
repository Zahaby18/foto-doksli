<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    die('Parameter tidak valid.');
}

$file = getFileById((int) $_GET['id']);
if (!$file || $file['is_folder'] || !$file['stored_name']) {
    http_response_code(404);
    die('File tidak ditemukan.');
}

$path = storagePath() . '/' . $file['stored_name'];
if (!is_file($path)) {
    http_response_code(404);
    die('File fisik tidak ditemukan di server.');
}

$mime = $file['mime_type'] ?: 'application/octet-stream';
$size = (int) $file['size'];
$name = $file['name'];

// ?dl=1 → paksa download sebagai attachment
$forceDownload = isset($_GET['dl']) && $_GET['dl'] === '1';
$disposition = $forceDownload ? 'attachment' : (canPreviewInline($mime) ? 'inline' : 'attachment');

header('Content-Type: ' . $mime);
header('Content-Length: ' . $size);
header('Content-Disposition: ' . $disposition . '; filename="' . rawurlencode($name) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=0, must-revalidate');

// Support range request (biar preview gambar/pdf lancar)
if (isset($_SERVER['HTTP_RANGE'])) {
    $fp = fopen($path, 'rb');
    if ($fp === false) {
        http_response_code(500);
        exit;
    }
    $start = 0;
    $end = $size - 1;
    if (preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m) && $m[1] !== '' && $m[2] !== '') {
        $start = (int) $m[1];
        $end = (int) $m[2];
    } elseif (preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m) && $m[1] !== '') {
        $start = (int) $m[1];
    } elseif (preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m) && $m[2] !== '') {
        $start = $size - (int) $m[2];
    }
    if ($start > $end || $start >= $size) {
        http_response_code(416);
        header('Content-Range: bytes */' . $size);
        exit;
    }
    http_response_code(206);
    header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
    header('Content-Length: ' . ($end - $start + 1));
    fseek($fp, $start);
    $left = $end - $start + 1;
    while ($left > 0 && !feof($fp)) {
        $chunk = fread($fp, min(8192, $left));
        if ($chunk === false) break;
        echo $chunk;
        $left -= strlen($chunk);
        flush();
    }
    fclose($fp);
    exit;
}

readfile($path);
exit;
