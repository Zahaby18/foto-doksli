#!/usr/bin/env bash
# Update script — jalankan dari folder site di VPS:
#   ./deploy.sh
set -euo pipefail

SITE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SITE_DIR"

echo "==> git pull origin main"
git pull origin main

echo "==> Memastikan folder storage ada & writable"
mkdir -p storage/files storage/cache/preview
chmod -R 775 storage/files storage/cache

echo "==> Cache opcache PHP CLI dibersihkan (kalau dipakai)"
# php -r 'opcache_reset();' 2>/dev/null || true

echo "==> Selesai. Cek config.php kalau ada perubahan struktur."
