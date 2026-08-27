# 📁 Foto Doksli

Personal file storage (Google Drive mini) — single user, PHP murni + MySQL.

## Struktur

```
foto-doksli/
├── config.example.php   # template config → copy ke config.php
├── config.php           # kredensial DB (TIDAK di-commit, ada di .gitignore)
├── includes/            # logic (db, auth, functions) — di luar web root
├── public/              # DOCUMENT ROOT — satu-satunya yang exposed
│   ├── index.php        # file browser
│   ├── login.php
│   ├── logout.php
│   ├── upload.php
│   ├── download.php     # serve file via session check (bukan URL langsung)
│   ├── delete.php
│   ├── create_folder.php
│   └── assets/
├── sql/schema.sql       # buat tabel + seed user
├── storage/files/       # file fisik (di luar web root)
└── deploy.sh            # update flow: git pull + perbaikan permission
```

## Deploy di CloudPanel (sekali saja)

1. **Site**: CloudPanel → Add Website → PHP → domain `foto.azdevs.my.id`.
2. **Document Root**: arahkan ke folder **`public/`** (jangan root repo).
3. **Git clone** repo ke folder site:
   ```bash
   cd /home/<user>/htdocs/foto.azdevs.my.id
   git clone https://github.com/Zahaby18/foto-doksli.git .
   ```
4. **Database**: CloudPanel → Databases → Create (mis. `foto_doksli`), buat user + password.
5. **Config**:
   ```bash
   cp config.example.php config.php
   nano config.php   # isi host/name/user/pass dari langkah 4
   ```
6. **Schema + seed user** (username `shania`, password `shaniapassword`):
   ```bash
   mysql -u <user> -p <dbname> < sql/schema.sql
   ```
   (atau import `sql/schema.sql` lewat phpMyAdmin / Database Manager CloudPanel)
7. **Permission storage**:
   ```bash
   mkdir -p storage/files && chmod -R 775 storage/files
   ```
   User PHP di CloudPanel (`www-data`) harus bisa tulis folder ini.
8. **SSL**: CloudPanel → Site → SSL → Let's Encrypt → issue cert.
9. **PHP settings** (CloudPanel → Site → PHP Settings) naikkan:
   - `upload_max_filesize` → `1024M` (atau sesuai kebutuhan)
   - `post_max_size` → `1025M`
   - `max_execution_time` → `300`

## Update / pull (tiap ada versi baru)

```bash
cd /home/<user>/htdocs/foto.azdevs.my.id
./deploy.sh
```

Kalau update mengubah struktur DB, ada file `.sql` baru di folder `sql/` — import manual sekali.

## Keamanan

- Login single user dengan bcrypt + rate-limit (5x gagal → lock 5 menit).
- File fisik di `storage/files` **di luar** web root `public/`; download cuma lewat `download.php` yang wajib session.
- Semua form pakai CSRF token.
- File di-serve dengan `X-Content-Type-Options: nosniff` + dukungan HTTP Range (preview lancar).
