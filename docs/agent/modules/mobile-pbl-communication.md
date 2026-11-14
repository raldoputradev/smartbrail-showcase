# Modul Mobile, PBL, dan Komunikasi

Kembali ke `../architecture.md`. Pemetaan dari kode 2026-10-24; tidak ada pemanggilan mobile/FCM/email.

## 1. Mobile ↔ backend

`main.dart` menjalankan `SimalasApp` dan `AppGate`; state sesi dipulihkan melalui `SessionStore`, lalu `ApiClient` dibuat dengan base URL/token. Bagian UI dipisahkan sebagai `part` screens/widgets, bukan seluruhnya package terpisah.

Kontrak API di `routes/api.php`:

- publik: config/captcha, login, registrasi/OTP/reset, helpdesk;
- `mobile.auth`: profil/foto, logout, push token, absensi sendiri, announcements/notifications, saran, dan PBL.

`EnsureMobileToken` memverifikasi SHA-256 token, status aktif, dan idle expiry; user disimpan sebagai request attribute `mobile_user`. Controller selanjutnya harus tetap membatasi role dan kepemilikan.

`MobileDataController::absensi/absensiSnapshot` memfilter `LogAbsensi` dan `AbsensiHarian` dengan `user_id` pengguna. Announcement memakai audience scope; saran/balasan dibatasi pemilik. `MobileAuthController::login` hanya mengeluarkan token kepada mahasiswa aktif/approved.

**Kontrak yang perlu dikonfirmasi:** komentar `GET /api/mobile/logs` menyebut admin/dosen, tetapi login mobile hanya mahasiswa dan `MesinAbsensiController::mobileLogs` tidak memeriksa role. Ini perbedaan implementasi/dokumentasi, bukan kesimpulan eksploitasi tanpa pengujian.

## 2. PBL: model baru dan legacy

- `pbl_projects`: kode/nama, shift, aktif, `manpro_user_id`, `co_manpro_user_id`; kolom nama staf lama juga masih ada.
- `pbl_team_members`: project + user unik, role ketua/wakil/anggota, assigned_by.
- `users.tim` dan `users.shift`: representasi legacy yang masih digunakan oleh filter absensi, announcement audience, dan mobile.

`PblTeamService` adalah aturan bersama:

- manpro/co-manpro/laboran mengelola struktur;
- ketua dapat menambah/menghapus anggota biasa, bukan mengganti struktur ketua/wakil;
- mahasiswa aktif/approved dapat join proyek aktif;
- operasi membership menyinkronkan `users.tim` dan `users.shift`;
- pembacaan anggota menggabungkan membership baru dengan user legacy berdasarkan nama project.

Admin memakai `Admin/PblProjectController`; dosen memakai `DosenPblController`; mobile mahasiswa/ketua memakai `MobilePblTeamController`. `DosenMahasiswaScope` memetakan proyek manpro/co-manpro ke nama/kode tim untuk query mahasiswa.

Perubahan PBL harus mempertimbangkan kedua representasi. Jangan migration/penghapusan legacy hanya karena model baru tersedia.

## 3. Pengumuman dan read state

`Announcement` memiliki channel pengumuman, PBL, akun, dan kanal loker historis. `scopeActive` memeriksa aktif/kedaluwarsa. `scopeForAudience` membatasi role, target tim/user, dan visibilitas sejak akun aktif. `AnnouncementRead` menyimpan status baca per user.

Web dikelola melalui `AnnouncementController`; mobile melalui `MobileDataController`. Feed mobile menggabungkan pengumuman dan balasan saran dengan pagination.

`AnnouncementObserver` dipanggil saat create/update tertentu dan dapat mengirim FCM kepada penerima aktif melalui `MobilePushService`. `AppServiceProvider` mendaftarkan observer.

## 4. Saran dan notifikasi staff

- Landing publik: `LandingController::storeSuggestion` dengan throttle.
- Dosen: `FeedbackController`.
- Mahasiswa mobile: `MobileDataController::storeSuggestion`.
- Laboran: `Admin/SuggestionController` untuk status, balasan, dan penghapusan.

`Suggestion` menyimpan pemilik, balasan admin, waktu balasan, dan waktu dibaca pengguna. Balasan hanya ditampilkan kepada pemilik.

`StaffNotificationService` membentuk kartu operasional berdasarkan pending OTP/approval, saran, login terkunci, belum enroll, mesin offline, dan balasan feedback. `StaffOpsDismissal` menyimpan dismissal berdasarkan user/key/fingerprint konteks.

## 5. Email, push, dan helpdesk

`AsyncMailer` menangani OTP/status akun dengan detached process/after-response sesuai platform; mailable hanya `OtpMail` dan `AccountStatusMail` dalam inventaris.

`MobilePushService` mendukung FCM legacy atau HTTP v1 dan membaca token dari `mobile_push_tokens`. Konfigurasi dirujuk melalui `config/mobile.php`; nilai/service account tidak dibaca.

Helpdesk publik (`HelpdeskChatController` + `HelpdeskAiService`) berbeda dari Copilot internal: non-stream, FAQ fallback, prompt helpdesk, tanpa tool database Copilot. Teks FAQ masih menyebut loker meskipun schema loker telah dihapus dari absensi; perlu konfirmasi pemilik sebelum mengubah narasi.

## 6. Dependensi lintas modul

- Approval akun menentukan login mobile dan eligibility PBL.
- PBL mengubah tim/shift yang memengaruhi absensi dan audience notifikasi.
- Announcement observer menghubungkan database dengan layanan eksternal FCM.
- Saran masuk dari tiga kanal tetapi dikelola terpusat oleh laboran.
- Versi aplikasi memiliki sumber `pubspec.yaml`, `config/mobile.php`, dan override environment yang perlu dicek saat rilis.
