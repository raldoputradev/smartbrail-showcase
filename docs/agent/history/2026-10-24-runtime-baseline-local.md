# Histori: Baseline Runtime Lokal Terbatas A–C

Waktu pemeriksaan: 2026-10-24 13:48 +07.

## Izin dan batas

Pemeriksaan lokal read-only untuk proses/port, koneksi database, dan metadata inti. Tidak ada start/stop/restart, HTTP request, login, test/build, scheduler/queue worker, migration/seeder, repair, perubahan privilege, atau akses layanan eksternal/perangkat.

Tidak ada `.env`, credential, isi record, jumlah record pengguna, tabel `mysql.*`, dump, fingerprint, atau log sensitif yang dibaca/ditampilkan.

## Metode

1. WMI/CIM memeriksa proses `mysqld|mariadbd|httpd|php` tanpa command line; `Get-NetTCPConnection` memeriksa listener 80, 443, 3306, dan 8080 beserta PID.
2. Meninjau `artisan`, `public/index.php`, `bootstrap/app.php`, `bootstrap/providers.php`, `routes/console.php`, dan `AppServiceProvider` sebelum bootstrap. Tidak ada schedule/mutation/komunikasi otomatis pada jalur yang dipakai.
3. Menggunakan PHP 8.3.35 untuk bootstrap dan PDO. `SELECT 1` dijalankan tanpa output konfigurasi/credential.
4. Membaca `information_schema` untuk 15 tabel inti, lalu 5 tabel pendukung akun/sesi setelah ditemukan perbedaan terkait aktivasi.

## Hasil

- MariaDB XAMPP aktif PID 20152 dan listen pada 3306.
- Apache memiliki dua proses; listener 80/443 pada PID 22044. Tidak ada listener Reverb 8080.
- `SELECT 1` berhasil dengan driver `mysql`.
- Seluruh 20 tabel inti/pendukung yang dipilih ditemukan. Kolom, tipe, primary/unique index, dan relasi utama berhasil dicatat.
- `tap_receipts.event_id`, unique harian/PBL, dan relasi utama absensi/PBL/AI/komunikasi sesuai dengan kebutuhan pengembangan yang ditinjau.

## Perbedaan

- `users.no_hp` tidak ditemukan meskipun diharapkan migration dan aktivasi.
- Enum `email_verification_tokens.type` tidak memuat `account_verify`.

Penyebab/status migration belum diverifikasi; tidak ada perubahan yang dilakukan.

## Batas dan perubahan

Koneksi/metadata tidak membuktikan database sehat sepenuhnya atau alur aplikasi bekerja. Belum ada HTTP smoke, test fungsi, status migration, validasi isi/relasi data, pemeriksaan InnoDB/Aria, atau validasi provider. Riwayat insiden dan batas recovery tetap berlaku.

Hanya `docs/agent/current-state.md` yang diperbarui dan catatan histori ini yang dibuat. Source code, dependensi, konfigurasi, database, firmware, layanan, dan file Git tidak diubah.
