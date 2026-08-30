<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$folderId = isset($_GET['folder']) && is_numeric($_GET['folder']) ? (int) $_GET['folder'] : null;
$currentFolder = $folderId !== null ? getFileById($folderId) : null;

// Validasi: folder harus ada & benar-benar folder
if ($folderId !== null && (!$currentFolder || !$currentFolder['is_folder'])) {
    header('Location: index.php');
    exit;
}

$children = getChildren($folderId);
$breadcrumb = getBreadcrumb($folderId);
$flash = flash();

$totalFiles = 0;
$totalSize = 0;
foreach ($children as $child) {
    if (!$child['is_folder']) {
        $totalFiles++;
        $totalSize += (int) $child['size'];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $folderId !== null ? e($currentFolder['name']) . ' — ' : '' ?>Foto Doksli</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header class="topbar">
        <div class="topbar-inner">
            <div class="brand">📁 Foto Doksli</div>
            <div class="topbar-right">
                <span class="user-chip">👤 <?= e($_SESSION['username'] ?? 'user') ?></span>
                <a href="logout.php" class="btn btn-ghost btn-sm">Logout</a>
            </div>
        </div>
    </header>

    <main class="container">
        <?php if ($flash): ?>
            <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
        <?php endif; ?>

        <!-- Breadcrumb -->
        <nav class="breadcrumb">
            <a href="index.php" class="crumb<?= $folderId === null ? ' active' : '' ?>">Beranda</a>
            <?php foreach ($breadcrumb as $crumb): ?>
                <span class="crumb-sep">/</span>
                <a href="index.php?folder=<?= (int) $crumb['id'] ?>" class="crumb<?= $crumb['id'] === $folderId ? ' active' : '' ?>"><?= e($crumb['name']) ?></a>
            <?php endforeach; ?>
        </nav>

        <!-- Upload & New Folder -->
        <div class="action-bar">
            <form method="post" action="upload.php" enctype="multipart/form-data" class="upload-form" id="upload-form">
                <input type="hidden" name="folder" value="<?= $folderId ?? '' ?>">
                <?= csrfField() ?>
                <label class="btn btn-primary file-btn">
                    📤 Pilih file…
                    <input type="file" name="files[]" multiple hidden>
                </label>
                <button type="submit" class="btn btn-accent" id="upload-btn" disabled>Upload</button>
                <span class="upload-hint" id="upload-hint"></span>
            </form>
            <form method="post" action="create_folder.php" class="folder-form">
                <input type="hidden" name="parent" value="<?= $folderId ?? '' ?>">
                <?= csrfField() ?>
                <input type="text" name="name" placeholder="Nama folder baru…" required>
                <button type="submit" class="btn btn-ghost">＋ Buat Folder</button>
            </form>
            <form method="post" action="sync.php" class="sync-form" title="Daftarkan file yang diupload langsung ke storage/ (mis. via File Manager)">
                <?= csrfField() ?>
                <button type="submit" class="btn btn-ghost">🔄 Sync Storage</button>
            </form>
        </div>

        <div class="storage-info">
            <?= count($children) ?> item · <?= $totalFiles ?> file · <?= formatBytes($totalSize) ?>
        </div>

        <!-- Bulk delete bar -->
        <form method="post" action="delete.php" id="bulk-form">
            <input type="hidden" name="folder" value="<?= $folderId ?? '' ?>">
            <?= csrfField() ?>
            <div class="bulk-bar">
                <label class="check-all-label"><input type="checkbox" id="check-all"> Pilih semua</label>
                <span class="bulk-count" id="bulk-count">0 terpilih</span>
                <button type="submit" class="btn btn-danger btn-sm" id="bulk-delete-btn" disabled>🗑️ Hapus terpilih</button>
            </div>
        </form>

        <!-- File list -->
        <?php if (count($children) === 0): ?>
            <div class="empty-state">
                <div class="empty-icon">🗂️</div>
                <p>Folder ini kosong. Upload file atau buat folder baru.</p>
            </div>
        <?php else: ?>
            <div class="file-list">
                <?php foreach ($children as $child): ?>
                    <?php if ($child['is_folder']): ?>
                        <div class="file-row folder-row">
                            <input type="checkbox" class="row-check" value="<?= (int) $child['id'] ?>" data-name="<?= e($child['name']) ?>" aria-label="Pilih <?= e($child['name']) ?>">
                            <a class="file-main" href="index.php?folder=<?= (int) $child['id'] ?>">
                                <span class="file-icon"><?= iconFor($child) ?></span>
                                <span class="file-name"><?= e($child['name']) ?></span>
                            </a>
                            <span class="file-meta">Folder</span>
                            <div class="file-actions">
                                <form method="post" action="delete.php" class="inline-form" data-confirm="Hapus folder '<?= e($child['name']) ?>' beserta semua isinya?">
                                    <input type="hidden" name="id" value="<?= (int) $child['id'] ?>">
                                    <input type="hidden" name="folder" value="<?= $folderId ?? '' ?>">
                                    <?= csrfField() ?>
                                    <button type="submit" class="btn btn-danger btn-sm" title="Hapus">🗑️</button>
                                </form>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php $canPreview = isPreviewable($child); ?>
                        <?php
                            $isHeic = in_array(strtolower(pathinfo($child['name'], PATHINFO_EXTENSION)), ['heic', 'heif'], true);
                            $previewHref = $isHeic
                                ? 'preview.php?id=' . (int) $child['id']
                                : 'download.php?id=' . (int) $child['id'];
                        ?>
                        <div class="file-row">
                            <input type="checkbox" class="row-check" value="<?= (int) $child['id'] ?>" data-name="<?= e($child['name']) ?>" aria-label="Pilih <?= e($child['name']) ?>">
                            <?php if ($canPreview): ?>
                                <a class="file-main" href="<?= $previewHref ?>" data-preview="1" data-name="<?= e($child['name']) ?>">
                                    <img class="file-thumb" src="thumb.php?id=<?= (int) $child['id'] ?>" alt="" loading="lazy">
                                    <span class="file-name"><?= e($child['name']) ?></span>
                                </a>
                            <?php else: ?>
                                <a class="file-main" href="download.php?id=<?= (int) $child['id'] ?>&dl=1">
                                    <span class="file-icon"><?= iconFor($child) ?></span>
                                    <span class="file-name"><?= e($child['name']) ?></span>
                                </a>
                            <?php endif; ?>
                            <span class="file-meta"><?= formatBytes((int) $child['size']) ?> · <?= date('d M Y', strtotime($child['created_at'])) ?></span>
                            <div class="file-actions">
                                <a class="btn btn-ghost btn-sm" href="download.php?id=<?= (int) $child['id'] ?>&dl=1" title="Download">⬇️</a>
                                <form method="post" action="delete.php" class="inline-form" data-confirm="Hapus file '<?= e($child['name']) ?>'?">
                                    <input type="hidden" name="id" value="<?= (int) $child['id'] ?>">
                                    <input type="hidden" name="folder" value="<?= $folderId ?? '' ?>">
                                    <?= csrfField() ?>
                                    <button type="submit" class="btn btn-danger btn-sm" title="Hapus">🗑️</button>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <!-- Image preview modal -->
    <div class="modal-overlay" id="preview-overlay" hidden>
        <div class="modal">
            <div class="modal-head">
                <span class="modal-title" id="preview-name"></span>
                <span class="modal-counter" id="preview-counter"></span>
                <button type="button" class="modal-close" id="preview-close" aria-label="Tutup">✕</button>
            </div>
            <div class="modal-body">
                <button type="button" class="modal-nav modal-nav-left" id="preview-prev" aria-label="Sebelumnya">‹</button>
                <img id="preview-img" src="" alt="Preview">
                <button type="button" class="modal-nav modal-nav-right" id="preview-next" aria-label="Berikutnya">›</button>
            </div>
        </div>
    </div>

    <script src="assets/js/app.js"></script>
</body>
</html>
