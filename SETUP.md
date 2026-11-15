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
cd C:/xampp/htdocs
git clone https://github.com/raldoputradev/smartbrail.git Simalas
cd Simalas
```

> Clone dengan nama folder **`Simalas`** (huruf besar-kecil sama) — dengan begitu
> `APP_URL` di `.env` dari ketua tim langsung cocok dan tidak perlu diedit.

Saat diminta login, pakai akun GitHub kamu (password = **Personal Access Token**,
bukan password akun — buat di GitHub → Settings → Developer settings → Tokens → scope `repo`).

## 3. Install dependency

```bash
composer install
npm install
```

Kalau `composer install` gagal soal versi PHP, lihat Troubleshooting #1.

## 4. Paket privat dari ketua tim (minta via WA/email — JANGAN di-commit)

Tiga hal ini tidak ada di repo dan wajib diminta ke ketua tim:

| Item | Bentuk | Wajib? |
|---|---|---|
| `.env` | file | **Ya** — semua kredensial + `APP_KEY` asli |
| Database | file dump `.sql` | **Ya** — seluruh data aplikasi |
| `storage/app/` | folder zip (foto/upload PBL, dsb.) | Disarankan — tanpa ini foto lama broken |

> **PENTING — jangan jalankan `php artisan key:generate`** kalau kamu pakai
> `.env` dari ketua tim. `APP_KEY` asli dipakai untuk mendekripsi data
> terenkripsi di database (mis. PIN akses ruangan). Key baru = data itu
> tidak bisa dibaca lagi.
>
> Kalau foldermu bukan `Simalas`, sesuaikan `APP_URL` di `.env`, mis.
> `APP_URL=http://localhost/<nama-folder>/public`.

## 5. Database

```bash
# 1. Buka XAMPP Control Panel → Start Apache + MySQL
# 2. Buka http://localhost/phpmyadmin → New → nama database: simalas → Create
# 3. Klik database "simalas" → tab Import → pilih simalas.sql dari ketua tim → Go
```

`DB_*` di `.env` dari ketua tim sudah cocok dengan standar XAMPP
(`DB_DATABASE=simalas`, user `root`) — tidak perlu diubah selama MySQL-mu
memakai setting bawaan XAMPP.

Alternatif cepat tanpa dump (struktur saja, data kosong):

```bash
php artisan migrate --seed   # hanya kalau diinstruksikan — tanya dulu
```

## 6. Build & jalankan

```bash
npm run build                # compile frontend sekali (wajib pertama kali)
php artisan storage:link     # symlink folder upload — wajib sekali
```

Lalu cukup nyalakan **Apache + MySQL di XAMPP** dan buka:

```
http://localhost/Simalas/public
```

(`php artisan serve` di `http://localhost:8000` juga bisa, tapi jalur XAMPP
di atas sesuai cara tim menjalankannya.)

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
