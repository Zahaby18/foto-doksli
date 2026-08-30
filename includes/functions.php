<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function storagePath(): string
{
    return rtrim(config()['storage_path'], '/');
}

function formatBytes(int $bytes): string
{
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
}

function getChildren(?int $parentId): array
{
    $stmt = db()->prepare('SELECT * FROM files WHERE parent_id ' . ($parentId === null ? 'IS NULL' : '= ?') . ' ORDER BY is_folder DESC, name ASC');
    if ($parentId === null) {
        $stmt->execute();
    } else {
        $stmt->execute([$parentId]);
    }
    return $stmt->fetchAll();
}

function getFileById(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM files WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function getBreadcrumb(?int $folderId): array
{
    $crumbs = [];
    $current = $folderId;
    while ($current !== null) {
        $row = getFileById($current);
        if (!$row || !$row['is_folder']) break;
        $crumbs[] = ['id' => $row['id'], 'name' => $row['name']];
        $current = $row['parent_id'] === null ? null : (int) $row['parent_id'];
    }
    return array_reverse($crumbs);
}

function ensureSafeFolderName(string $name): string
{
    $name = trim($name);
    $name = str_replace(['/', '\\', "\0"], '', $name);
    $name = preg_replace('/[.\s]+$/', '', $name) ?? '';
    if ($name === '' || $name === '.' || $name === '..') {
        return '';
    }
    if (mb_strlen($name) > 200) {
        $name = mb_substr($name, 0, 200);
    }
    return $name;
}

function uniqueStoredName(string $originalName): string
{
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $base = bin2hex(random_bytes(16));
    $name = $base . ($ext !== '' ? '.' . $ext : '');
    $dir = storagePath();
    while (file_exists($dir . '/' . $name)) {
        $name = bin2hex(random_bytes(16)) . ($ext !== '' ? '.' . $ext : '');
    }
    return $name;
}

function iconFor(array $row): string
{
    if ($row['is_folder']) return '📁';
    $mime = $row['mime_type'] ?? '';
    $name = strtolower($row['name']);
    if (str_starts_with($mime, 'image/')) return '🖼️';
    if ($mime === 'application/pdf') return '📄';
    if (preg_match('/\.(zip|rar|7z|tar|gz)$/', $name)) return '🗜️';
    if (str_starts_with($mime, 'video/')) return '🎬';
    if (str_starts_with($mime, 'audio/')) return '🎵';
    if (str_starts_with($mime, 'text/') || preg_match('/\.(txt|md|log|json|csv)$/', $name)) return '📝';
    if (str_contains($name, '.') === false) return '📄';
    return '📎';
}

function canPreviewInline(string $mime, string $name = ''): bool
{
    // HEIC/HEIF tidak didukung browser → jangan pernah inline
    if ($name !== '') {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (in_array($ext, ['heic', 'heif'], true)) {
            return false;
        }
    }
    // Whitelist format yang bisa dirender browser
    return in_array(strtolower($mime), [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'image/avif', 'image/svg+xml', 'image/bmp',
    ], true);
}

function isPreviewable(array $row): bool
{
    return canPreviewInline($row['mime_type'] ?? '', $row['name'] ?? '');
}

/**
 * Hapus file/folder beserta isinya. Kaskade DB ditangani FK ON DELETE CASCADE,
 * file fisik dikumpulkan dulu lalu di-unlink.
 */
function deleteTree(int $rootId): bool
{
    $pdo = db();

    // BFS kumpulkan semua stored_name di bawah root
    $stored = [];
    $queue = [$rootId];
    while ($queue) {
        $id = array_shift($queue);
        $stmt = $pdo->prepare('SELECT id, is_folder, stored_name FROM files WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) continue;
        if (!$row['is_folder'] && $row['stored_name']) {
            $stored[] = $row['stored_name'];
        }
        $stmt = $pdo->prepare('SELECT id FROM files WHERE parent_id = ?');
        $stmt->execute([$id]);
        foreach ($stmt->fetchAll() as $child) {
            $queue[] = (int) $child['id'];
        }
    }

    $stmt = $pdo->prepare('DELETE FROM files WHERE id = ?');
    $stmt->execute([$rootId]);

    $dir = storagePath();
    foreach ($stored as $name) {
        $path = $dir . '/' . $name;
        if (is_file($path)) {
            @unlink($path);
        }
    }
    return true;
}

// ---------- CSRF ----------
function csrfToken(): string
{
    startSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

function csrfVerify(): void
{
    startSession();
    $sent = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $sent)) {
        http_response_code(419);
        die('CSRF token mismatch. Kembali dan coba lagi.');
    }
}

function flash(?string $type = null, ?string $message = null)
{
    startSession();
    if ($type !== null) {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
        return null;
    }
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}
