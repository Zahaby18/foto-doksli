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

$id = isset($_POST['id']) && is_numeric($_POST['id']) ? (int) $_POST['id'] : null;
$returnFolder = isset($_POST['folder']) && $_POST['folder'] !== '' && is_numeric($_POST['folder'])
    ? (int) $_POST['folder']
    : null;

if ($id === null) {
    flash('error', 'ID tidak valid.');
} else {
    $row = getFileById($id);
    if (!$row) {
        flash('error', 'Item tidak ditemukan.');
    } else {
        deleteTree($id);
        flash('success', '"' . $row['name'] . '" berhasil dihapus.');
    }
}

header('Location: index.php' . ($returnFolder !== null ? '?folder=' . $returnFolder : ''));
exit;
