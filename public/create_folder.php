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

$parentId = isset($_POST['parent']) && $_POST['parent'] !== '' && is_numeric($_POST['parent'])
    ? (int) $_POST['parent']
    : null;

if ($parentId !== null) {
    $parent = getFileById($parentId);
    if (!$parent || !$parent['is_folder']) {
        flash('error', 'Folder induk tidak valid.');
        header('Location: index.php');
        exit;
    }
}

$name = ensureSafeFolderName($_POST['name'] ?? '');
if ($name === '') {
    flash('error', 'Nama folder tidak valid.');
} else {
    $stmt = db()->prepare('INSERT INTO files (parent_id, name, is_folder) VALUES (?, ?, 1)');
    $stmt->execute([$parentId, $name]);
    flash('success', 'Folder "' . $name . '" dibuat.');
}

header('Location: index.php' . ($parentId !== null ? '?folder=' . $parentId : ''));
exit;
