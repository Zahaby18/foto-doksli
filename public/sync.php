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

$root = storagePath();
if (!is_dir($root)) {
    flash('error', 'Folder storage tidak ditemukan.');
    header('Location: index.php');
    exit;
}

// Kumpulkan state DB: stored_name yang sudah terdaftar + folder by (parent|name)
$pdo = db();
$filesByStored = [];
$foldersByKey = [];
foreach ($pdo->query('SELECT id, parent_id, name, is_folder, stored_name FROM files') as $row) {
    if ((int) $row['is_folder'] === 1) {
        $key = ($row['parent_id'] ?? '0') . '|' . $row['name'];
        $foldersByKey[$key] = (int) $row['id'];
    } elseif ($row['stored_name'] !== null) {
        $filesByStored[$row['stored_name']] = (int) $row['id'];
    }
}

/**
 * Pastikan folder ada di DB, reuse kalau sudah ada (sama parent + nama).
 */
function ensureFolder(array &$foldersByKey, string $name, ?int $parentId): int
{
    $key = ($parentId ?? '0') . '|' . $name;
    if (isset($foldersByKey[$key])) {
        return $foldersByKey[$key];
    }
    $stmt = db()->prepare('INSERT INTO files (parent_id, name, is_folder) VALUES (?, ?, 1)');
    $stmt->execute([$parentId, $name]);
    $id = (int) db()->lastInsertId();
    $foldersByKey[$key] = $id;
    return $id;
}

/**
 * Scan storage/files rekursif. Subfolder fisik → folder di DB,
 * file fisik → row file dengan parent folder yang sesuai.
 */
function scanStorage(
    string $absDir,
    ?int $parentId,
    string $relPrefix,
    array &$filesByStored,
    array &$foldersByKey,
    int &$added,
    int &$skipped
): void {
    $entries = scandir($absDir);
    if ($entries === false) return;

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..' || $entry === '.gitkeep') continue;

        $absPath = $absDir . '/' . $entry;
        $relPath = $relPrefix === '' ? $entry : $relPrefix . '/' . $entry;

        // Guard path traversal
        if (str_starts_with($entry, '/') || str_contains($entry, '..')) {
            $skipped++;
            continue;
        }

        if (is_dir($absPath)) {
            // Folder fisik → pastikan folder di DB, lalu rekursi
            $folderId = ensureFolder($foldersByKey, $entry, $parentId);
            scanStorage($absPath, $folderId, $relPath, $filesByStored, $foldersByKey, $added, $skipped);
            continue;
        }

        if (!is_file($absPath)) continue;

        if (isset($filesByStored[$relPath])) continue; // sudah terdaftar

        $mime = function_exists('mime_content_type')
            ? (mime_content_type($absPath) ?: 'application/octet-stream')
            : 'application/octet-stream';
        $size = (int) filesize($absPath);

        $stmt = db()->prepare(
            'INSERT INTO files (parent_id, name, stored_name, is_folder, size, mime_type)
             VALUES (?, ?, ?, 0, ?, ?)'
        );
        $stmt->execute([$parentId, $entry, $relPath, $size, $mime]);
        $filesByStored[$relPath] = (int) db()->lastInsertId();
        $added++;
    }
}

$added = 0;
$skipped = 0;
scanStorage($root, null, '', $filesByStored, $foldersByKey, $added, $skipped);

// --- Migrasi: pastikan semua folder DB punya folder fisik ---
foreach ($pdo->query('SELECT id FROM files WHERE is_folder = 1') as $folderRow) {
    ensureFolderPhysicalDir((int) $folderRow['id']);
}

// --- Migrasi: pindahkan file yang masih di root tapi punya parent folder ---
$moved = 0;
$orphan = 0;
$rows = $pdo->query('SELECT id, parent_id, stored_name, name FROM files WHERE is_folder = 0 AND parent_id IS NOT NULL');
foreach ($rows as $row) {
    $rel = $row['stored_name'];
    if ($rel === null || $rel === '') continue;
    $folderRel = folderRelPath((int) $row['parent_id']);
    if ($folderRel === '') continue;
    if (str_starts_with($rel, $folderRel . '/')) continue; // sudah di folder yang benar

    $absOld = $root . '/' . $rel;
    if (!is_file($absOld)) {
        $orphan++; // fisik sudah hilang, biarkan row (diketahui saat download)
        continue;
    }

    $destDir = $root . '/' . $folderRel;
    if (!is_dir($destDir)) @mkdir($destDir, 0775, true);

    $newBase = basename($rel);
    $dest = $destDir . '/' . $newBase;
    if (file_exists($dest)) {
        $stored = uniqueStoredName($row['name'], $folderRel);
        $dest = $root . '/' . $stored;
    }

    if (@rename($absOld, $dest)) {
        $newStored = $folderRel . '/' . basename($dest);
        $upd = $pdo->prepare('UPDATE files SET stored_name = ? WHERE id = ?');
        $upd->execute([$newStored, $row['id']]);
        $moved++;
    }
}

$msgParts = [];
if ($added > 0) {
    $msgParts[] = $added . ' file baru terdaftar dari storage';
}
if ($moved > 0) {
    $msgParts[] = $moved . ' file dipindah ke folder fisiknya';
}
if ($msgParts === []) {
    if ($skipped > 0) {
        flash('error', 'Ada ' . $skipped . ' entri dengan nama tidak aman, dilewati.');
    } else {
        flash('success', 'Tidak ada file baru — storage sudah sinkron.');
    }
} else {
    $msg = implode(', ', $msgParts) . '.';
    if ($orphan > 0) {
        $msg .= ' (' . $orphan . ' file fisik hilang, row dibiarkan)';
    }
    flash('success', $msg);
}

header('Location: index.php');
exit;
