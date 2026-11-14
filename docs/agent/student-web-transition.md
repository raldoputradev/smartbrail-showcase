# Rencana Transisi Flutter → Portal Web Mahasiswa

Status: **matriks perencanaan, bukan implementasi**. Tanggal: 2026-10-24. Arah produk dirujuk dari `decisions.md`; dependensi tahap dari `development-plan.md`.

## 1. Prinsip transisi

- Gunakan backend dan database SIMALAS yang sama; tidak ada database mahasiswa kedua.
- Portal memiliki login/UI tersendiri, tetapi otorisasi tetap di backend.
- Flutter dan endpoint mobile tetap aktif sampai padanan yang disyaratkan lulus dan transisi disetujui.
- Jangan mengubah absensi fingerprint menjadi self-service web.
- Website responsif lebih dahulu; PWA, push browser, dan instalasi home screen kemudian.
- Jangan membuat ulang aturan bisnis bila service/query existing dapat diekstrak dan dipakai bersama.

## 2. Matriks fitur yang ditemukan

“Padanan web” berarti implementasi saat ini, bukan rencana. `mahasiswa.web-disabled` hanya gerbang penonaktifan, bukan portal fungsional.

| Fitur dan tujuan | Implementasi mobile | Backend terkait | Padanan web sekarang | Reuse/perubahan dan batas data | Prioritas | Uji kesetaraan |
|---|---|---|---|---|---|---|
| Login/logout dan idle session | `AuthScreen`, `ApiClient`, `SessionStore`, `HomeShell` di `main.dart` | `/mobile/login`, `/mobile/logout`; `LoginSecurityService`, `EnsureMobileToken` | Login staff ada, tetapi validasi hanya `laboran,dosen` | Buat login web mahasiswa dengan session regenerasi/CSRF/role check; jangan memakai Bearer token sebagai session browser. Hanya akun mahasiswa approved/aktif | Wajib | Akun valid/ditolak/nonaktif/terkunci; idle; mahasiswa tidak dapat membuka `/admin` atau `/dosen` |
| Registrasi, OTP, resume/cancel, approval | `_RegisterWizard`, `_OtpForm`, `_OtpResumeForm` | `/mobile/register*`, `email_verification_tokens`, `AsyncMailer`, `PendingAccountApproval` | Web registrasi hanya dosen; aktivasi/approval admin ada | Reuse endpoint guest atau ekstrak alur bersama; jangan membuat tabel OTP/user baru. Verifikasi email tetap diperlukan sebelum approval | Wajib | Registrasi baru, OTP salah/kedaluwarsa/resume/resend/cancel; status pending sampai admin menyetujui fixture |
| Aktivasi akun import | Respons login dan pengarahan aktivasi | `AccountActivation`, `/aktivasi` | Sudah ada halaman aktivasi umum | Reuse halaman/service; pastikan hasil akhir mengarah ke login mahasiswa, bukan staff. Tidak ada perubahan privilege | Wajib | Kode valid/kedaluwarsa, profil/password/email/HP lengkap, akun aktif dan login ke portal mahasiswa |
| Lupa/reset password | `_ForgotPasswordForm` | `/mobile/forgot-password*`, OTP reset | `OTPController` web sudah ada | Reuse aturan OTP; pilih satu alur guest yang jelas dan hindari duplikasi token. Jangan login otomatis ke area lain | Wajib | OTP valid/salah/kedaluwarsa, reset berhasil, password lama gagal, sesi lain tidak berpindah role |
| Beranda, status akun, aktivitas terbaru | `DashboardPage`, `MhsGopayHomeTop`, `MhsRecentActivityFeed` | `/mobile/me`, absensi snapshot, announcements, notifications | Tidak ada portal mahasiswa; profile umum terpisah | Ekstrak query/read model untuk web. Data hanya `auth()->id()` mahasiswa, bukan ID dari client | Wajib | Nilai ringkasan sama dengan API untuk fixture yang sama; tidak ada data mahasiswa lain |
| Absensi: kalender, ringkasan, riwayat/filter | `StudentAttendancePage`, `MhsHistoryPage`, chart/calendar widgets | `/mobile/me/absensi` dan `/snapshot`; `AbsensiKebijakan` | Belum ada; absensi web hanya staf | Ekstrak pembentukan payload dari `MobileDataController` agar tidak menduplikasi query. Hanya log/status milik sendiri; tidak ada tombol tap mandiri | Wajib | Hadir/tidak-lengkap/alfa/izin/libur, shift, filter tanggal, timezone, empty state; hasil sama dengan API |
| Profil dan verifikasi email | `ProfilePage`, `showEmailVerificationDialog`, `mhs_student_access` | `/mobile/me`, `/me/account-email*`, `/me/photo` | `UserProfileController` membatasi mahasiswa ke profil sendiri dan mendukung foto, tetapi gerbang portal belum ada | Reuse controller/view yang sesuai atau ekstrak service foto. Edit akademik tetap ditolak sebagaimana `MobileAuthController::updateProfile`; jangan menganggap UI Flutter berarti update berhasil | Wajib: profil/email/foto | Profil orang lain 403; foto valid/ditolak; email OTP. Edit akademik tetap 403/tersedia hanya admin |
| PBL: melihat proyek/manpro/anggota | `MhsTeamPage` aktif dari dashboard | `/mobile/me/pbl-team`, `PblTeamService` | Admin/dosen ada; mahasiswa belum | Reuse `PblTeamService` dan scope keanggotaan; dukung representasi legacy `users.tim` | Wajib bila PBL digunakan | Anggota/legacy/kosong; mahasiswa hanya melihat timnya, bukan tim lain |
| PBL: join/kelola anggota | Backend tersedia; UI tim aktif yang diperiksa hanya membaca | `/mobile/pbl/projects`, `/me/pbl/join`, anggota ketua | Admin/dosen dapat mengelola sesuai izin | Reuse `PblTeamService`; bukan syarat minimum jika UI aktif hanya membaca. Jangan mengaktifkan aksi tanpa menguji role ketua | Tahap berikutnya | Join dua tim ditolak; ketua/non-ketua; sinkronisasi `tim`/`shift` dan efeknya pada absensi |
| Pengumuman, notifikasi, read/read-all | Dashboard, `AnnouncementPage`, notification sheets | `/mobile/announcements*`, `/mobile/notifications`, `/suggestions/replies*` | Staff/mobile API ada; mahasiswa web belum | Reuse `Announcement::forAudience` dan kepemilikan suggestion; feed in-app cukup untuk tahap awal | Wajib | Target role/tim/user, expiry, visible-since-joined, read/read-all; tidak dapat menandai item orang lain |
| Saran/masukan | `MhsSuggestionSheet` | `/mobile/suggestions` | Landing dan dosen ada; mahasiswa web belum | Reuse validasi/limiter dan kepemilikan; jangan membuka balasan orang lain | Tahap berikutnya untuk paritas | Submit valid/gagal/throttle; balasan admin hanya pada pemilik |
| Helpdesk/CS AI | Guest/helpdesk sheets | `/mobile/helpdesk/chat` atau `/helpdesk/chat`, `HelpdeskAiService` | Chat publik landing sudah ada | Reuse widget/endpoint; bedakan dari Copilot internal. Tidak perlu tool/personal data | Wajib bila dianggap kanal bantuan utama; selain itu tahap berikutnya | FAQ/fallback/error yang sama; tanpa key di browser dan tanpa mengirim record pribadi |
| Onboarding, coach marks, tema | `OnboardingIntroPage`, `ThemeStore`, showcase | Preferensi lokal | Tidak relevan sebagai backend | Gunakan pola aksesibilitas web, bukan meniru seluruh animasi | Opsional | Keyboard/screen reader, preferensi tema tersimpan tanpa mengubah akun |
| FCM, update APK, probe server | `PushService`, `version_check`, `api_config` | `/mobile/me/push-token`, `/mobile/config` | Khusus aplikasi | Tetap untuk Flutter selama transisi; tidak dipaksakan ke web. Pengganti browser/PWA dievaluasi nanti | Opsional/lanjutan | Flutter tetap bekerja; portal tidak bergantung pada token FCM/APK |

## 3. Portal minimum yang diusulkan

### Slice 1 — fondasi dan akun

- Halaman login mahasiswa terpisah, logout, idle session, registrasi/OTP, reset password, dan kelanjutan aktivasi.
- Middleware role mahasiswa, CSRF/session regeneration, rate limiting, dan kepemilikan data dari user sesi.
- Layout responsif dengan navigasi sederhana; bukan layout staff.

### Slice 2 — fungsi inti harian

- Beranda dengan profil/status, aktivitas terbaru, kalender/ringkasan/riwayat absensi sendiri.
- Pengumuman dan notifikasi beserta read state.
- Profil sendiri, verifikasi email, dan upload foto yang valid.
- Tampilan tim PBL read-only bila mahasiswa menggunakan PBL.
- Helpdesk dapat memakai jalur publik existing bila dipilih sebagai bagian minimum.

### Setelah minimum

Saran, join/kelola PBL, penyempurnaan grafik/onboarding/tema, PWA, dan notifikasi browser. Penonaktifan Flutter hanya setelah daftar padanan yang disyaratkan pemilik lulus.

## 4. Kriteria penerimaan minimum

- Nyaman pada browser Android dan Safari iPhone: navigasi sentuh, form/keyboard, orientasi, safe area, tidak bergantung pada hover, dan tidak ada overflow pada lebar HP umum.
- Mahasiswa tidak dapat membuka area staf; laboran/dosen tidak dialihkan ke sesi mahasiswa tanpa aturan eksplisit.
- Dua akun mahasiswa uji tidak dapat melihat/mengubah data satu sama lain melalui URL, ID, filter, atau request langsung.
- Nilai absensi/profil/PBL/notifikasi sama dengan sumber backend untuk fixture yang sama; tidak ada database kedua.
- Edit akademik dan absensi mandiri tidak terbuka secara tidak sengaja. Fingerprint tetap berasal dari perangkat.
- Route staff, API mesin, scheduler, dan endpoint mobile tidak berubah tanpa kebutuhan yang disetujui.
- Flutter tetap berjalan selama transisi; tidak ada penghapusan fitur/endpoint sebagai “cleanup” awal.
- Semua test yang dijalankan memakai data sintetis/isolasi yang sudah ditinjau, dengan bukti hasil eksekusi.

## 5. Pola reuse yang disarankan

- Gunakan `LoginSecurityService`, `AccountActivation`, aturan OTP, model, dan permission yang ada.
- Ekstrak payload absensi mahasiswa dari `MobileDataController` ke read model bersama sebelum membuat tampilan web; jangan menyalin query ke controller baru berulang kali.
- Gunakan `PblTeamService`, `Announcement::forAudience`, dan aturan kepemilikan `Suggestion` secara langsung.
- Gunakan `UserProfileController`/service foto yang relevan, sambil mempertahankan penolakan edit akademik pada API mobile.
- Jangan memakai `EnsureMobileToken` sebagai pengganti session web dan jangan menyimpan token mobile di frontend portal.

Pilihan implementasi spesifik ditetapkan saat slice disetujui; dokumen ini tidak mengunci arsitektur UI baru.
