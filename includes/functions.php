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

function uniqueStoredName(string $originalName, string $subdir = ''): string
{
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $base = bin2hex(random_bytes(16));
    $name = $base . ($ext !== '' ? '.' . $ext : '');
    $dir = storagePath();
    if ($subdir !== '') {
        $dir .= '/' . $subdir;
    }
    while (file_exists($dir . '/' . $name)) {
        $name = bin2hex(random_bytes(16)) . ($ext !== '' ? '.' . $ext : '');
    }
    return ($subdir !== '' ? $subdir . '/' : '') . $name;
}

/**
 * Path relatif folder dari root storage, mis. "Tugas/Kuliah" ("" untuk root).
 */
function folderRelPath(?int $folderId): string
{
    $parts = [];
    $current = $folderId;
    $guard = 0;
    while ($current !== null && $guard++ < 100) {
        $row = getFileById($current);
        if (!$row || !$row['is_folder']) break;
        $parts[] = $row['name'];
        $current = $row['parent_id'] === null ? null : (int) $row['parent_id'];
    }
    return implode('/', array_reverse($parts));
}

/**
 * Pastikan folder fisik ada di disk sesuai struktur DB. Buat kalau belum ada.
 * Mengembalikan path absolut folder fisik (root storage untuk parent NULL).
 */
function ensureFolderPhysicalDir(?int $folderId): string
{
    $root = storagePath();
    $rel = folderRelPath($folderId);
    $dir = $rel === '' ? $root : $root . '/' . $rel;
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
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
    // HEIC/HEIF bisa di-preview via preview.php (konversi server ke JPEG)
    $ext = strtolower(pathinfo($row['name'] ?? '', PATHINFO_EXTENSION));
    if (in_array($ext, ['heic', 'heif'], true)) {
        return true;
    }
    return canPreviewInline($row['mime_type'] ?? '', $row['name'] ?? '');
}

/**
 * Konversi HEIC/HEIF ke JPEG. Coba Imagick dulu, fallback ImageMagick CLI.
 * Mengembalikan true kalau file JPEG berhasil dibuat.
 */
function heicToJpeg(string $srcPath, string $dstPath): bool
{
    // Jalur 1: PHP Imagick (kalau tersedia + support HEIC)
    if (class_exists('Imagick')) {
        try {
            $img = new Imagick($srcPath);
            if ($img->getNumberImages() === 0) {
                $img->clear();
                return false;
            }
            $img->setIteratorIndex(0); // frame utama, hindari file -0/-1
            $img->setImageFormat('jpeg');
            $img->setImageCompressionQuality(88);
            $img->writeImage($dstPath);
            $img->clear();
            return is_file($dstPath) && filesize($dstPath) > 0;
        } catch (Throwable $e) {
            // lanjut ke jalur CLI
        }
    }

    // Jalur 2: ImageMagick CLI (convert / magick).
    // [0] memaksa frame utama — HEIC sering punya thumbnail tambahan.
    $binary = trim((string) @shell_exec('command -v convert 2>/dev/null'));
    if ($binary === '') {
        $binary = trim((string) @shell_exec('command -v magick 2>/dev/null'));
    }
    if ($binary !== '') {
        $cmd = escapeshellarg($binary)
            . ' ' . escapeshellarg($srcPath . '[0]')
            . ' -quality 88 '
            . escapeshellarg($dstPath)
            . ' 2>/dev/null';
        exec($cmd, $out, $code);
        return $code === 0 && is_file($dstPath) && filesize($dstPath) > 0;
    }

    return false;
}

/**
 * Hapus file/folder beserta isinya. Kaskade DB ditangani FK ON DELETE CASCADE,
 * file fisik dikumpulkan dulu lalu di-unlink, folder fisik ikut dibersihkan.
 */
function deleteTree(int $rootId): bool
{
    $pdo = db();

    // BFS kumpulkan semua stored_name + folder fisik di bawah root
    $stored = [];
    $folderRels = [];
    $queue = [$rootId];
    while ($queue) {
        $id = array_shift($queue);
        $stmt = $pdo->prepare('SELECT id, is_folder, stored_name FROM files WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) continue;
        if (!$row['is_folder'] && $row['stored_name']) {
            $stored[] = $row['stored_name'];
        } elseif ($row['is_folder']) {
            $rel = folderRelPath((int) $row['id']);
            if ($rel !== '') {
                $folderRels[] = $rel;
            }
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

    // Hapus folder fisik dari yang paling dalam (kalau sudah kosong)
    usort($folderRels, function (string $a, string $b): int {
        return substr_count($b, '/') <=> substr_count($a, '/');
    });
    foreach ($folderRels as $rel) {
        $path = $dir . '/' . $rel;
        if (is_dir($path)) {
            @rmdir($path);
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
