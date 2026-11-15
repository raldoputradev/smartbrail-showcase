# Setup SmartBrail di Laptop Lokal

Panduan langkah-demi-langkah untuk menjalankan proyek ini di Windows.
Ikuti urut dari atas — kalau ada error, cek bagian **Troubleshooting** di bawah.

> Butuh akses? Minta ketua tim menambahkan akun GitHub kamu sebagai
> **collaborator** di repo `smartbrail` dulu — tanpa itu `git clone` gagal.

## 1. Prasyarat (install sekali)

| Tools | Versi | Cek di terminal |
|---|---|---|
| Windows 10/11 + terminal | PowerShell atau **Git Bash** (disarankan) | — |
| PHP | **8.3+** (via XAMPP 8.3 atau standalone) | `php -v` |
| Composer | 2.x | `composer --version` |
| Node.js + npm | LTS 20/22/24 | `node -v` & `npm -v` |
| Git | terbaru | `git --version` |
| MySQL | via XAMPP (bawaan) | via phpMyAdmin |

Download:

- XAMPP: <https://www.apachefriends.org> (pilih yang PHP **8.3** — lihat catatan PHP di Troubleshooting)
- Composer: <https://getcomposer.org/Composer-Setup.exe>
- Node.js LTS: <https://nodejs.org>
- Git for Windows (termasuk Git Bash): <https://git-scm.com/download/win>

Semua perintah di bawah dijalankan di **Git Bash** atau PowerShell — perintahnya sama.

## 2. Clone repo

```bash
cd C:/xampp/htdocs          # atau folder kerja kamu
git clone https://github.com/raldoputradev/smartbrail.git
cd smartbrail
```

Saat diminta login, pakai akun GitHub kamu (password = **Personal Access Token**,
bukan password akun — buat di GitHub → Settings → Developer settings → Tokens → scope `repo`).

## 3. Install dependency

```bash
composer install
npm install
```

Kalau `composer install` gagal soal versi PHP, lihat Troubleshooting #1.

## 4. File `.env`

`.env` **tidak ada di repo** (sengaja, berisi rahasia). Dua sumber:

- Minta file `.env` yang sudah jadi ke **ketua tim** (lewat WA/email — jangan commit), atau
- `cp .env.example .env` lalu `php artisan key:generate` — tapi fitur yang butuh
  API key eksternal (Gemini, dst) tidak akan jalan tanpa key asli.

## 5. Database

```bash
# Buat database kosong di phpMyAdmin (http://localhost/phpmyadmin)
# lalu import file dump .sql dari ketua tim ke database itu.
# Sesuaikan DB_* di .env dengan nama database + user MySQL kamu.
```

Alternatif cepat tanpa dump (struktur saja, data kosong):

```bash
php artisan migrate --seed   # hanya kalau diinstruksikan — tanya dulu
```

## 6. Build & jalankan

```bash
npm run build                # compile frontend sekali (wajib pertama kali)
php artisan serve            # buka http://localhost:8000
```

Untuk development frontend dengan hot-reload:

```bash
npm run dev                  # biarkan jalan di terminal terpisah
```

Opsional — notifikasi realtime (Reverb WebSocket):

```bash
php artisan reverb:start     # terminal terpisah; tanpa ini notifikasi pakai polling (tetap jalan)
```

## 7. Alur kerja harian

```bash
git pull origin main                  # selalu mulai dari kode terbaru
git checkout -b feat/nama-fitur       # branch baru per tugas
# ... coding ...
npm run build                         # pastikan frontend compile
git add -A && git commit -m "feat: deskripsi singkat"
git push -u origin feat/nama-fitur
```

Lalu buka repo di GitHub → **Compare & pull request** → assign ketua tim sebagai
reviewer → tunggu di-merge. **Jangan push langsung ke `main`.**

Detail aturan branch/commit: lihat `CONTRIBUTING.md`.

## Troubleshooting

**#1 `composer install` error: "requires php ^8.3"**
`composer.json` menargetkan PHP 8.3+, tapi mesin dev utama jalan di 8.2.
Kalau PHP kamu 8.2 dan tidak bisa upgrade, pakai:

```bash
composer install --ignore-platform-reqs
```

**#2 Landing page / route error 405**
Route cache basi — bersihkan:

```bash
php artisan route:clear && php artisan config:clear && php artisan view:clear
```

**#3 Perubahan frontend tidak muncul**
`npm run build` ulang, lalu **Ctrl+F5** di browser (hard refresh).

**#4 Warning "Module openssl is already loaded"**
Warning bawaan XAMPP — abaikan, tidak berpengaruh.

**#5 `npm run dev` error / port bentrok**
Tutup terminal dev lain; atau jalankan `npm run build` saja (tanpa dev server).

**#6 Login bisa tapi fitur AI/notifikasi mati**
`.env` kamu belum punya key asli — minta `.env` lengkap ke ketua tim.
