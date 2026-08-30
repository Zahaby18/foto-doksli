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

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

// Bukan HEIC/HEIF → biarkan download.php yang handle (inline untuk gambar biasa)
if (!in_array($ext, ['heic', 'heif'], true)) {
    header('Location: download.php?id=' . (int) $file['id']);
    exit;
}

$path = storagePath() . '/' . $file['stored_name'];

// Guard path traversal
if (str_starts_with($file['stored_name'], '/')
    || str_contains($file['stored_name'], '..')
    || str_contains($file['stored_name'], '\\')) {
    http_response_code(400);
    die('Path tidak valid.');
}

if (!is_file($path)) {
    http_response_code(404);
    die('File fisik tidak ditemukan di server.');
}

// Cache hasil konversi di storage/cache/preview/
$cacheDir = dirname(storagePath()) . '/cache/preview';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0775, true);
}
$mtime = (int) filemtime($path);
$cacheFile = $cacheDir . '/preview_' . (int) $file['id'] . '_' . $mtime . '.jpg';

if (!is_file($cacheFile)) {
    if (!heicToJpeg($path, $cacheFile)) {
        // Konversi gagal (server tidak support HEIC) → fallback download file asli
        header('Location: download.php?id=' . (int) $file['id'] . '&dl=1');
        exit;
    }
}

$size = (int) filesize($cacheFile);
header('Content-Type: image/jpeg');
header('Content-Length: ' . $size);
header('Content-Disposition: inline; filename="' . rawurlencode($file['name']) . '.jpg"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=86400');
readfile($cacheFile);
exit;
