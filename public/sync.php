<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

csrfVerify();

$dir = storagePath();
if (!is_dir($dir)) {
    flash('error', 'Folder storage tidak ditemukan.');
    header('Location: index.php');
    exit;
}

// Kumpulkan stored_name yang sudah terdaftar di DB
$existing = [];
foreach (db()->query('SELECT stored_name FROM files WHERE stored_name IS NOT NULL') as $row) {
    $existing[$row['stored_name']] = true;
}

$entries = scandir($dir);
$added = 0;
$skipped = [];

foreach ($entries as $entry) {
    if ($entry === '.' || $entry === '..' || $entry === '.gitkeep') continue;

    $path = $dir . '/' . $entry;
    if (!is_file($path)) continue; // abaikan subfolder (storage harus flat)

    if (isset($existing[$entry])) continue; // sudah terdaftar

    // Tolak nama berbahaya (path traversal)
    if (str_contains($entry, '/') || str_contains($entry, '\\') || $entry === '..') {
        $skipped[] = $entry;
        continue;
    }

    $mime = function_exists('mime_content_type')
        ? (mime_content_type($path) ?: 'application/octet-stream')
        : 'application/octet-stream';
    $size = (int) filesize($path);

    $stmt = db()->prepare(
        'INSERT INTO files (parent_id, name, stored_name, is_folder, size, mime_type)
         VALUES (NULL, ?, ?, 0, ?, ?)'
    );
    $stmt->execute([$entry, $entry, $size, $mime]);
    $existing[$entry] = true;
    $added++;
}

if ($added > 0) {
    flash('success', $added . ' file baru terdaftar dari storage.');
} elseif ($skipped) {
    flash('error', 'Ada ' . count($skipped) . ' file dengan nama tidak aman, dilewati.');
} else {
    flash('success', 'Tidak ada file baru — storage sudah sinkron.');
}

header('Location: index.php');
exit;
