# Modul Akun dan Akses

Kembali ke `../architecture.md`. Pemetaan dari kode 2026-10-24; bukan hasil uji runtime.

## 1. Entry point dan komponen

- Web guest: `routes/web.php` → `AuthController`, `OTPController`, `ActivationController`.
- Mobile: `routes/api.php` prefix `/api/mobile` → `MobileAuthController`, `MobileCaptchaController`.
- Admin: `AdminController` untuk CRUD/approval dan `Admin/AccountImportController` untuk import.
- Aturan bersama: `AccountEmailVerification`, `AccountActivation`, `LoginSecurityService`, `RegistrationGate`, `PendingAccountApproval`, `AccountImportService`, `PermissionRegistry`.
- Penyimpanan: `users`, `email_verification_tokens`, `account_approval_logs`, `role_permissions`; pratinjau import di storage lokal dan kartu aktivasi sementara di cache terenkripsi.

## 2. Registrasi mandiri → OTP → approval

1. Web `POST /register` atau mobile `POST /api/mobile/register` memvalidasi profil, captcha, dan registration gate.
2. User disimpan belum aktif; OTP dan masa berlakunya disimpan di `email_verification_tokens` dengan tipe registrasi.
3. `AsyncMailer::dispatchOtp()` mengirim email tanpa menjadikan queue database sebagai dependensi.
4. Verifikasi mengisi `email_verified_at`; mobile juga mengalokasikan `finger_id` melalui `FingerIdAllocator`.
5. `PendingAccountApproval::query()` memilih mahasiswa/dosen terverifikasi dengan status pending/null.
6. Laboran dengan `users.update` menyetujui/menolak melalui `AdminController`; `AccountApprovalLog` menyimpan jejak dan `AsyncMailer` dapat mengirim status.
7. Middleware `approved` dan pemeriksaan `is_active`/`approval_status` membatasi penggunaan selanjutnya.

Web dan mobile mempunyai implementasi OTP sendiri, bukan satu controller yang sama. `AccountEmailVerification` dipakai untuk verifikasi email akun existing; jangan menganggap semua tipe OTP memakai TTL yang sama: password reset web 10 menit + window reset 30 menit, verifikasi akun 30 menit.

## 3. Import dan aktivasi: alur berbeda dari registrasi

`AccountImportService` menjalankan pratinjau tanpa membuat user → validasi ulang dan lock saat apply → user `menunggu_aktivasi` → kode aktivasi sekali unduh. Akun existing tidak diubah; duplikat ditandai. Kartu aktivasi disimpan sementara terenkripsi di cache dan ditarik sekali.

`AccountActivation` menyimpan hash kode pada kolom password sampai pemilik membuat password. Sesi aktivasi terpisah dari sesi login dan kedaluwarsa setelah idle. Aktivasi selesai hanya jika password, email terverifikasi, dan nomor HP lengkap; kemudian akun menjadi `approved`/`is_active` tanpa approval admin tambahan. Mahasiswa memperoleh `finger_id`.

## 4. Login, sesi, dan reset

- Web `AuthController::login` memeriksa role yang dipilih, status akun, credential, dan `LoginSecurityService`, lalu `Auth::login`, regenerate session, dan `WebSessionGuard::stampLogin`.
- Mobile login hanya menerima mahasiswa aktif/approved. Token plaintext diberikan sekali; database menyimpan SHA-256. `EnsureMobileToken` memperpanjang idle expiry pada setiap request dan menghapus token kedaluwarsa.
- `LoginSecurityService` membatasi IP/ID, mengunci sementara setelah kegagalan berulang, dan dapat meminta admin unlock berdasarkan pola 24 jam.
- Reset password web/mobile memakai OTP tipe `password_reset`; web memerlukan token terverifikasi sebelum update dan menghapus token sesudahnya.

## 5. Permission dan batas data

`role_permissions` unik per role/menu dengan CRUD. `PermissionRegistry::can()` membaca map cache lalu jatuh ke default. Route menambahkan `role:` dan `perm:`; controller tetap memiliki pemeriksaan khusus pada beberapa aksi.

Permission menu bukan pengganti scope:

- Mahasiswa: query mobile berdasarkan `mobile_user`/`user_id`.
- Dosen: `DosenMahasiswaScope` dan `PblTeamService` membatasi tim tertentu.
- Import: actor harus laboran aktif dengan `users.create`, dan payload hanya dapat dibuka actor yang sama.
- Copilot: kepemilikan percakapan dan deklarasi tool mengikuti user sesi, bukan argumen model.

## 6. Keluaran dan dependensi lintas modul

- Akun aktif menentukan akses web/mobile/mesin dan eligibility PBL/absensi.
- `finger_id` menghubungkan akun mahasiswa ke mesin; template enroll tetap terpisah.
- Approval/aktivasi memicu email/log dan memengaruhi notifikasi operasional staff.
- Permission yang sama dipakai route, UI, tool Copilot, dan tautan ekspor.

## 7. Titik perhatian

- `AsyncMailer` memiliki fallback yang menulis OTP dan email ke log; perlu tinjauan keamanan tersendiri, tanpa membuka log aktual.
- Beberapa alur OTP serupa tetapi tidak identik; perubahan harus menguji web dan mobile secara terpisah.
- Schema users dibentuk melalui banyak migration incremental; jangan menyimpulkan kondisi kolom aktual tanpa inspeksi DB yang disetujui.
