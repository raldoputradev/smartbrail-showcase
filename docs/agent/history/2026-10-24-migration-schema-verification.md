# Histori: Verifikasi Dua Perbedaan Migration dan Schema

Waktu: 2026-10-24 13:57 +07.

## Ruang lingkup

Verifikasi read-only untuk `users.no_hp` dan `email_verification_tokens.type`, menggunakan PHP 8.3.35 serta metode bootstrap/koneksi lokal yang sama dengan baseline. Tidak ada migration, repair, HTTP request, login, test, perubahan layanan/schema/data, atau pembacaan record pengguna/OTP/fingerprint/mysql.*.

## Source migration

1. `database/migrations/2026_10_09_120000_add_no_hp_to_users_table.php`
   - `up()` melewati jika `users.no_hp` sudah ada.
   - Jika belum, menambahkan varchar(20) nullable setelah `email` melalui Schema Builder.
   - Tidak ada guard driver khusus.
2. `database/migrations/2026_10_09_120100_add_account_verify_to_email_verification_tokens_type.php`
   - Berurutan setelah migration `no_hp`.
   - `up()` melewati jika tabel tidak ada; pada MySQL memeriksa enum dengan `SHOW COLUMNS` dan berhenti bila nilai sudah ada.
   - Jika nilai belum ada, MySQL menjalankan `ALTER TABLE ... MODIFY`; driver lain memakai `enum(...)->change()`.

## Repository dan schema aktual

- Repository `migrations` tersedia.
- Query terbatas pada kedua nama menghasilkan `MIGRATION_ROWS=[]`: tidak ada catatan keduanya sudah berjalan.
- `SCHEMA_NO_HP=null`.
- `SCHEMA_TOKEN_TYPE=enum('registration','password_reset')`.

Repository dan schema konsisten dengan perubahan yang belum diterapkan. Tidak ada bukti migration tercatat berjalan tetapi schema tidak sesuai. Penyebab historis/percobaan eksekusi tidak terbukti. `migrate:status` tidak diperlukan karena query langsung telah menjawab status kedua nama.

## Dampak potensial dari kode

- `ActivationController::saveProfile` menulis `no_hp`; `AccountActivation::nextStep/complete` mensyaratkannya. Kolom yang tidak ada berpotensi menggagalkan penyelesaian profil aktivasi.
- `AccountEmailVerification` menulis tipe `account_verify`; enum aktual tidak memuatnya. Insert dapat gagal/tidak valid bergantung mode SQL, sehingga verifikasi akun/aktivasi berpotensi terhambat.

Dampak tersebut diturunkan dari kode/schema, bukan dibuktikan melalui request atau eksekusi alur.

## Perubahan

Hanya `docs/agent/current-state.md` diperbarui dan catatan ini dibuat. Tidak ada perubahan source code, migration, schema, data, konfigurasi, layanan, firmware, atau Git. Batas recovery dan backup tetap berlaku.
