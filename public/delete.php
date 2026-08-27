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

$returnFolder = isset($_POST['folder']) && $_POST['folder'] !== '' && is_numeric($_POST['folder'])
    ? (int) $_POST['folder']
    : null;

$deletedCount = 0;
$deletedNames = [];
$failed = 0;

$ids = $_POST['ids'] ?? null;
if (is_array($ids) && count($ids) > 0) {
    // Bulk delete
    foreach ($ids as $rawId) {
        if (!is_numeric($rawId)) continue;
        $id = (int) $rawId;
        $row = getFileById($id);
        if ($row) {
            deleteTree($id);
            $deletedCount++;
            $deletedNames[] = $row['name'];
        } else {
            $failed++;
        }
    }
} else {
    // Single delete
    $id = isset($_POST['id']) && is_numeric($_POST['id']) ? (int) $_POST['id'] : null;
    if ($id === null) {
        flash('error', 'ID tidak valid.');
    } else {
        $row = getFileById($id);
        if (!$row) {
            flash('error', 'Item tidak ditemukan.');
        } else {
            deleteTree($id);
            $deletedCount = 1;
            $deletedNames[] = $row['name'];
        }
    }
}

if ($deletedCount > 0) {
    if ($deletedCount === 1) {
        flash('success', '"' . $deletedNames[0] . '" berhasil dihapus.');
    } else {
        flash('success', $deletedCount . ' item berhasil dihapus.');
    }
} elseif ($failed > 0) {
    flash('error', $failed . ' item tidak ditemukan / gagal dihapus.');
}

header('Location: index.php' . ($returnFolder !== null ? '?folder=' . $returnFolder : ''));
exit;
