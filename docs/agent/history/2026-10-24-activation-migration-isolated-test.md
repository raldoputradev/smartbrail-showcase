# Histori: Pengujian Migration dan Aktivasi pada MariaDB Terisolasi

Waktu selesai: 2026-10-24 14:22 +07.

## Identitas lingkungan

- PHP 8.3.35, driver `mysql`, host lokal, database `simalas_activation_test`.
- Nama database diperiksa sebelum dibuat; belum ada database dengan nama tersebut.
- Guard memeriksa `SELECT DATABASE()` dan koneksi Laravel khusus `activation_test` pada fase tulis. Konfigurasi file/default operasional tidak diubah.
- Database test dipertahankan setelah sukses. Tidak ada credential pada harness/output.

## Harness

Dibuat hanya `tests/Support/run_activation_migration_isolated_test.php`, tidak masuk suite biasa. Sintaks PHP lolos sebelum eksekusi. Harness tidak memuat credential atau data pribadi.

## Schema dan migration

- 36 base tables disalin dengan `SHOW CREATE TABLE` tanpa record; tidak ada trigger/event/routine atau referensi database operasional. Tidak ada artefak schema sementara.
- Repository migration test dimulai kosong.
- `2026_10_09_120000_add_no_hp_to_users_table` berhasil, batch 1.
- `2026_10_09_120100_add_account_verify_to_email_verification_tokens_type` berhasil, batch 2.
- Tidak ada migration lain yang tercatat.

`no_hp` menjadi varchar(20) nullable tepat setelah email. Enum token memuat `registration`, `password_reset`, dan `account_verify`. Checksum fixture dan nilai non-target sama sebelum/sesudah, sehingga data sintetis terpreservasi.

## Aktivasi melalui request internal Laravel

- Password lemah ditolak tanpa perubahan akun.
- Kode aktivasi dan password kuat diterima.
- Nomor HP invalid ditolak; profil valid tersimpan.
- OTP `account_verify` dibuat; pengiriman email hanya `Mail::fake`.
- OTP salah tidak mengaktifkan akun.
- OTP benar menyelesaikan aktivasi: akun aktif, email terverifikasi, `finger_id` teralokasi, dan log `activation_completed` tersedia.
- Status akhir `approved` sesuai aturan aktivasi import tanpa approval laboran tambahan. Approval registrasi mandiri tidak termasuk pengujian.

## Isolasi efek samping

`APP_ENV=testing`, mail/notification/queue/bus fake, HTTP client fake dan stray prevention, session/cache array, broadcast null, Gemini/FCM nonaktif, dan DNS check aktivasi false digunakan. Tidak ada request jaringan, SMTP, reCAPTCHA, provider, atau perangkat.

## Verifikasi operasional dan batas

Verifikasi read-only setelah test menunjukkan operasional tetap:

- `users.no_hp` tidak ada;
- enum token tetap `registration,password_reset`;
- nol catatan dua migration target.

Tidak ada penulisan ke database operasional atau backup asli oleh prosedur ini. Tidak ada perubahan source aplikasi, migration existing, konfigurasi, dependency, layanan, firmware, atau Git. Perubahan file hanya harness dan dokumentasi yang diizinkan.

## Belum diuji

Deployment ke operasional, seluruh migration terhadap data operasional, approval registrasi mandiri, HTTP melalui Apache/browser, konkurresi, performa, dan seluruh fungsi aplikasi. Keberhasilan test bukan izin menerapkan migration ke operasional.

## Rekomendasi berikutnya

Penerapan dua migration ke operasional hanya melalui tugas deployment terpisah setelah backup operasional baru yang terverifikasi, persetujuan eksplisit, guard database, dan verifikasi pasca-perubahan. Risikonya mencakup DDL/lock dan kegagalan aktivasi bila penerapan berbeda dari test; test database saat ini tidak dihapus tanpa persetujuan.
