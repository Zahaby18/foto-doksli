<?php
// config.example.php
// Copy file ini jadi config.php lalu isi kredensial database asli.
// config.php JANGAN di-commit ke git (sudah ada di .gitignore).

return [
    'db' => [
        'host'     => '127.0.0.1',
        'name'     => 'foto_doksli',
        'user'     => 'db_user_dari_cloudpanel',
        'pass'     => 'db_password_dari_cloudpanel',
        'charset'  => 'utf8mb4',
    ],
    // Folder fisik tempat file disimpan.
    // Default: satu level di atas folder public/ (di luar web root).
    'storage_path' => __DIR__ . '/storage/files',
    // Batas ukuran upload per file (bytes). 1073741824 = 1GB.
    // Pastikan juga upload_max_filesize & post_max_size di php.ini/CloudPanel disesuaikan.
    'max_upload_size' => 1073741824,
];
