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

$folderId = isset($_POST['folder']) && $_POST['folder'] !== '' && is_numeric($_POST['folder'])
    ? (int) $_POST['folder']
    : null;

if ($folderId !== null) {
    $folder = getFileById($folderId);
    if (!$folder || !$folder['is_folder']) {
        flash('error', 'Folder tujuan tidak valid.');
        header('Location: index.php');
        exit;
    }
}

$maxSize = (int) (config()['max_upload_size'] ?? 1073741824);
$uploaded = 0;
$skipped = [];
$totalBytes = 0;

if (empty($_FILES['files']['name'][0])) {
    flash('error', 'Tidak ada file yang dipilih.');
    header('Location: index.php?folder=' . ($folderId ?? ''));
    exit;
}

foreach ($_FILES['files']['name'] as $i => $originalName) {
    $errorCode = $_FILES['files']['error'][$i];
    $tmpPath = $_FILES['files']['tmp_name'][$i];
    $size = (int) $_FILES['files']['size'][$i];

    if ($errorCode !== UPLOAD_ERR_OK) {
        $skipped[] = $originalName . ' (error upload ' . $errorCode . ')';
        continue;
    }
    if ($size <= 0) {
        $skipped[] = $originalName . ' (file kosong)';
        continue;
    }
    if ($size > $maxSize) {
        $skipped[] = $originalName . ' (melebihi ' . formatBytes($maxSize) . ')';
        continue;
    }

    $safeOriginal = basename($originalName);
    $relDir = $folderId !== null ? folderRelPath($folderId) : '';
    $dir = ensureFolderPhysicalDir($folderId);
    $storedName = uniqueStoredName($safeOriginal, $relDir);
    $destination = $dir . '/' . basename($storedName);

    if (!move_uploaded_file($tmpPath, $destination)) {
        $skipped[] = $safeOriginal . ' (gagal simpan)';
        continue;
    }

    $mime = function_exists('mime_content_type') ? (mime_content_type($destination) ?: 'application/octet-stream') : 'application/octet-stream';

    $stmt = db()->prepare(
        'INSERT INTO files (parent_id, name, stored_name, is_folder, size, mime_type)
         VALUES (?, ?, ?, 0, ?, ?)'
    );
    $stmt->execute([$folderId, $safeOriginal, $storedName, $size, $mime]);

    $uploaded++;
    $totalBytes += $size;
}

$msg = "Upload selesai: $uploaded file (" . formatBytes($totalBytes) . ')';
if ($skipped) {
    $msg .= ' · Dilewati: ' . implode(', ', array_slice($skipped, 0, 5)) . (count($skipped) > 5 ? '…' : '');
}
flash($uploaded > 0 ? 'success' : 'error', $msg);

header('Location: index.php?folder=' . ($folderId ?? ''));
exit;
