# Kondisi SIMALAS Saat Ini

Tanggal pemetaan: **2026-10-24**.

## Identitas dan ruang

- Root: `C:\xampp\htdocs\Simalas`.
- Root ini **bukan repository Git** saat diperiksa (`git status` dan `git rev-parse HEAD` gagal karena metadata `.git` tidak tersedia). Identitas commit tidak dapat dicatat.
- Tidak ada `AGENTS.md` maupun `docs/agent/` sebelum pemetaan ini. `README.md` masih README bawaan Laravel.
- Dokumentasi lain yang relevan: `docs/REVERB-LOKAL.md`, `docs/AUDIT-COMPOSER-2026-10-09.md`, dan `komunikasi-mesin/absensi/firmware/README.md`.

## Kondisi yang diketahui

### Dari kode

- Backend Laravel, web staff, API mobile, API mesin, database relasional, aplikasi Flutter, dan skeleton firmware ESP32 berada dalam satu root.
- Role utama: `laboran`, `dosen`, `mahasiswa`. Mahasiswa diarahkan ke mobile; laboran/dosen memakai portal web.
- Fitur existing mencakup akun/OTP/aktivasi, import akun, permission, absensi fingerprint, rekap/laporan/ekspor, PBL, pengumuman/saran/notifikasi, Copilot, dan Reverb wake-up snapshot.
- Dashboard mempunyai overlay dummy yang disengaja. Copilot tidak menggunakan overlay tersebut.
- Copilot internal saat ini baca-saja di kode (`AdminAiToolRegistry::READ_ONLY = true`).
- PHPUnit dikonfigurasi memakai SQLite `:memory:`, cache/session array, broadcast null, dan queue sync (`phpunit.xml`). Tidak ada test yang dijalankan dalam pemetaan ini.

### Dari pemilik

- SIMALAS sudah pernah berjalan dengan data asli dan akan dilanjutkan, bukan dibangun ulang.
- Dashboard CONTOH memang disengaja.
- Copilot Gemini tersedia dan dapat digunakan.
- Pengembangan berikutnya harus melalui tugas kecil yang disetujui pemilik.

Informasi pemilik tentang penggunaan historis tidak diperlakukan sebagai hasil pengujian runtime pada sesi ini.

## Pekerjaan terakhir dan validasi

- Dilakukan pemetaan baca-saja terhadap entry point, route, model/migration terpilih, aturan akses, alur absensi, PBL, mobile, notifikasi, integrasi, Copilot, Reverb, dan strategi pengujian.
- Dokumentasi arsitektur dibuat pada 2026-10-24.
- Tidak ada build, test, migration, seeder, repair, pemanggilan provider eksternal, atau perubahan layanan pada tahap dokumentasi ini.
- Bukti pengujian runtime dari chat/dokumen lama tidak dianggap sebagai pengujian yang baru dilakukan.

## Batas recovery database yang masih berlaku

Recovery sebelumnya **dihentikan dan tidak dilanjutkan**.

- Tabel Aria `mysql.db` sebelumnya terkonfirmasi rusak. Repair terarah menghasilkan exit code 0 tetapi output berubah dari `Data records: 3` menjadi `Data records: 0`; ini adalah indikasi yang memerlukan evaluasi, bukan pembuktian isi record tanpa pemeriksaan lanjutan.
- Pemeriksaan pasca-repair tidak dilakukan dalam alur yang dihentikan tersebut.
- Backup offline pra-repair berada di `C:\xampp\mysql-backups\offline-20261024-115019`; jangan diubah atau ditimpa.
- Jangan start server, repair tambahan, menyalin kembali file, atau membuat recovery alternatif hanya berdasarkan dokumentasi ini. Setiap tindakan membutuhkan persetujuan baru dan rencana tersendiri.
- Kondisi database dan kemampuan aplikasi berjalan saat ini **belum diverifikasi** pada tahap pemetaan kode.

## Area yang belum diverifikasi atau belum ditelusuri penuh

- Status queue/scheduler OS, URL aplikasi/HTTP, provider eksternal, dan Reverb application readiness; proses/port awal telah diperiksa pada baseline di bawah.
- Perbandingan schema dibatasi pada 20 tabel inti/pendukung, bukan seluruh schema atau status migration.
- Versi package yang benar-benar terpasang dibandingkan constraint manifest.
- Seluruh detail UI, semua command maintenance/debug, seluruh skrip manual `tests/Support/`, dan kode firmware baris demi baris.
- Apakah skeleton firmware sama dengan firmware yang terpasang pada perangkat fisik.
- Implementasi command yang dipilih Laravel untuk dua class dengan signature `absensi:generate-alfa` yang sama.
- Maksud akses endpoint `GET /api/mobile/logs` dibandingkan komentar route dan batas login mobile.
- Hasil test terkini; test hanya ditemukan dari kode, bukan dieksekusi.

## Usulan pendalaman, bukan pekerjaan yang sudah disetujui

- Konfirmasikan kondisi runtime/database melalui tugas terpisah dengan batas keselamatan eksplisit.
- Tinjau resolusi command duplikat, kompatibilitas legacy PBL, endpoint mobile logs, dan pesan helpdesk lama.
- Tinjau efek samping setiap skrip uji manual sebelum memilihnya; jangan menjalankannya sebagai alternatif otomatis dari PHPUnit.

## Pembaruan arah pengembangan — 2026-10-24

- Pemilik menetapkan SIMALAS fokus pada absensi; smart locker adalah produk terpisah dan tidak dikembalikan. Sisa loker hanya dicatat untuk tugas lain.
- Arah jangka lanjut disetujui: portal web mahasiswa responsif menggantikan Flutter setelah padanan dan transisi terbukti. Portal tersebut **belum diimplementasikan** pada tahap ini.
- PWA adalah tahap lanjutan. Flutter dan API mobile tetap dipertahankan selama transisi; absensi tetap melalui fingerprint, bukan self-service web.
- PostgreSQL hanya boleh dikaji/diuji pada lingkungan terisolasi setelah izin; belum ada migrasi/cutover.
- Copilot tetap baca-saja; peningkatan bantuan, analisis, dan draf baru berupa rencana.
- Pemilik menyatakan perangkat sudah pernah dipasang/diuji dan data berhasil diproses lokal. Pernyataan ini berasal dari pemilik, bukan hasil pengujian perangkat baru pada sesi ini. Kesamaan source firmware tetap belum dikonfirmasi.
- Fokus pengembangan adalah lokal/LAN. Tidak ada pekerjaan VPS, deployment publik, flash firmware, atau perubahan perangkat.
- Root diperiksa ulang dan tetap bukan repository Git. Usulan baseline ada di `development-plan.md`; tidak ada `git init`, staging, commit, atau perubahan `.gitignore` pada tahap ini.

Dokumen rencana baru:

- `development-plan.md` — baseline, pemeriksaan runtime yang diusulkan, tahapan bersyarat, kajian PostgreSQL, dan roadmap Copilot.
- `student-web-transition.md` — inventaris Flutter dan matriks padanan portal mahasiswa.

Semua butir di atas adalah keputusan arah/rencana. Tidak ada fitur baru, test, query, request aplikasi, migrasi, atau perubahan layanan yang dieksekusi pada tahap tersebut. Batas recovery dan backup pada bagian sebelumnya tetap berlaku.

## Baseline runtime lokal terbatas — 2026-10-24 13:48 +07

Pemeriksaan A–C disetujui dan dilakukan tanpa start/stop/restart, request web, login, test, migration, repair, atau perubahan konfigurasi/database.

### Proses dan port

- `mysqld.exe` PID 20152 dari `C:\xampp\mysql\bin\mysqld.exe`; listener `:::3306`.
- Dua proses `httpd.exe` PID 22044 dan 5692 dari binary XAMPP; PID 22044 mendengarkan 80 dan 443 pada IPv4/IPv6. Jumlah proses tidak ditafsirkan sebagai instance ganda.
- Tidak ada listener pada 8080 saat diperiksa; Reverb tidak dinyalakan.
- Command line proses tidak ditampilkan.

### Inisialisasi dan koneksi

- `artisan`, `public/index.php`, `bootstrap/app.php`, `bootstrap/providers.php`, `routes/console.php`, dan `AppServiceProvider` ditinjau sebelum bootstrap. Schedule hanya didaftarkan; provider boot tidak menjalankan mutation/komunikasi otomatis. Tidak ada request/model yang memicu observer.
- PHP CLI default adalah 8.2.12 dan tidak dipakai. Bootstrap/query dijalankan dengan PHP 8.3.35 dari `C:\tools\php83\php.exe`.
- `SELECT 1` berhasil dengan driver `mysql`. Ini membuktikan koneksi dan query minimal saat itu, bukan kesehatan seluruh database atau fungsi aplikasi.

### Metadata yang diverifikasi

20 tabel inti/pendukung ditemukan melalui `information_schema`, tanpa membaca record atau menghitung record:

- `users`, `log_absensi`, `absensi_harian`, `absensi_alfa`, `absensi_tidak_lengkap`, `tap_receipts`;
- `pbl_projects`, `pbl_team_members`, `announcements`, `announcement_reads`, `suggestions`;
- `ai_conversations`, `ai_messages`, `role_permissions`, `mesin_iot`;
- `email_verification_tokens`, `absensi_sesi_settings`, `absensi_kalender_libur`, `program_studi`, `system_settings`.

Primary key, unique index penting, dan relasi utama tersedia, termasuk `tap_receipts.event_id` unik, `absensi_harian(user_id,tanggal,sesi)` unik, `pbl_team_members(pbl_project_id,user_id)` unik, relasi harian ke log/users, PBL ke projects/users, dan AI messages ke conversations.

### Perbedaan ditemukan

- Kolom `users.no_hp` tidak ada, sedangkan migration `2026_10_09_120000_add_no_hp_to_users_table.php` dan `AccountActivation` mengharapkannya.
- `email_verification_tokens.type` adalah `enum('registration','password_reset')`, tidak memuat `account_verify` yang diharapkan migration `2026_10_09_120100_add_account_verify_to_email_verification_tokens_type.php` dan `AccountEmailVerification`.

Verifikasi lanjutan pada 13:57 menunjukkan kedua migration tidak memiliki baris pada repository `migrations`; rinciannya ada pada bagian berikutnya. Tidak ada perbaikan yang dilakukan.

## Verifikasi dua perbedaan migration — 2026-10-24 13:57 +07

- Metode bootstrap dan koneksi default lokal yang sama dengan baseline digunakan melalui PHP 8.3.35; driver tetap `mysql`.
- Tabel repository `migrations` tersedia. Query hanya untuk nama dua migration menghasilkan `MIGRATION_ROWS=[]`, sehingga keduanya **tidak tercatat sebagai sudah berjalan**. `migrate:status` tidak diperlukan karena catatan langsung sudah cukup.
- `2026_10_09_120000_add_no_hp_to_users_table.php`: `up()` melewati penambahan jika kolom sudah ada; jika belum, menambah `no_hp` varchar(20) nullable setelah email melalui Schema Builder tanpa guard driver khusus. Metadata aktual tetap `SCHEMA_NO_HP=null`.
- `2026_10_09_120100_add_account_verify_to_email_verification_tokens_type.php`: berurutan setelah migration `no_hp`; `up()` melewati jika tabel tidak ada, menggunakan `SHOW COLUMNS` pada MySQL dan berhenti bila `account_verify` sudah ada, selain itu menjalankan `ALTER TABLE ... MODIFY`. Driver non-MySQL memakai `enum(...)->change()`. Metadata aktual tetap `enum('registration','password_reset')`.
- Status repository dan schema konsisten dengan perubahan yang belum diterapkan. Tidak ada bukti bahwa migration tercatat berjalan tetapi schema berbeda. Percobaan eksekusi historis, penyebab belum dicatat, atau perubahan manual lain tidak dapat disimpulkan dari bukti ini.

### Dampak potensial, belum diuji melalui request

- `ActivationController::saveProfile` mengisi atribut `no_hp` (`ActivationController.php:151-155`), sementara `AccountActivation::nextStep/complete` mensyaratkannya (`AccountActivation.php:202-225`). Pada schema aktual, penyimpanan profil aktivasi berpotensi gagal karena kolom tidak tersedia.
- `AccountEmailVerification::TYPE` adalah `account_verify`; `send()` menulis nilai tersebut ke `email_verification_tokens.type` (`AccountEmailVerification.php:10,60-69`). Pada enum aktual, insert berpotensi gagal atau tidak valid bergantung pada mode SQL, dan alur verifikasi akun/aktivasi dapat terhambat. Mode SQL runtime dan respons aplikasi tidak diuji.

Tidak ada migration, repair, pembacaan record pengguna/OTP/fingerprint/mysql.*, HTTP request, atau perubahan layanan pada verifikasi ini. Batas recovery tetap berlaku.

### Batas bukti

- Tidak ada isi record, jumlah record pengguna, tabel `mysql.*`, credential, isi `.env`, fingerprint, atau log sensitif yang dibaca.
- Tidak ada HTTP smoke, login, test fungsi, pemeriksaan isi data, status migration, validasi relasi terhadap record, atau pemeriksaan kesehatan InnoDB/Aria.
- Keberhasilan metadata tidak membuktikan aplikasi/aktivasi bekerja atau database sehat sepenuhnya. Backup dan batas recovery pada bagian sebelumnya tetap berlaku.

## Inventaris seluruh migration pending — 2026-10-24 14:08 +07

- PHP 8.3/bootstrap dan koneksi lokal yang sama digunakan untuk membandingkan seluruh nama file migration dengan nama/batch repository.
- Hasil: **58 file migration, 56 catatan repository, 2 pending**. Pending hanya `2026_10_09_120000_add_no_hp_to_users_table` dan `2026_10_09_120100_add_account_verify_to_email_verification_tokens_type`; tidak ada pending lain yang perlu disertakan sebagai paket.
- `no_hp` memerlukan tabel `users`; perubahan enum memerlukan `email_verification_tokens.type`. Keduanya tersedia. Tidak ada dependensi wajib di antara dua migration; ketergabungan hanya pada alur aktivasi.
- `up()` keduanya tidak melakukan backfill/penghapusan data. Keduanya layak diuji terarah dengan path eksplisit pada MariaDB terisolasi, bukan dijalankan pada database operasional.
- `down()` tidak dijadikan jaminan pemulihan: rollback `no_hp` ditolak bila nomor HP terisi, rollback enum ditolak bila ada token `account_verify`. Rencana pemulihan harus membangun ulang lingkungan test dari schema awal, bukan bergantung pada rollback/transaction.

Rencana schema-only, data sintetis, guard koneksi, pengujian preservasi/alur aktivasi, pencegahan komunikasi eksternal, kriteria lulus, dan izin disimpan di `development-plan.md` bagian 7–8.

## Hasil pengujian migration dan aktivasi terisolasi — 2026-10-24 14:22 +07

Pengujian selesai dan seluruh pemeriksaan lulus melalui `tests/Support/run_activation_migration_isolated_test.php` dengan PHP 8.3.35.

- `simalas_activation_test` dibuat setelah dipastikan belum ada; database test dipertahankan dan tidak di-drop.
- 36 base tables disalin schema-only tanpa record, trigger, event, routine, atau referensi database operasional. Tidak ada artefak schema sementara.
- Repository migration test dimulai kosong. Dua target berhasil sebagai batch 1 (`no_hp`) dan batch 2 (`account_verify`), tanpa migration lain.
- `no_hp` menjadi varchar(20) nullable setelah email; enum memuat nilai lama dan `account_verify`; data sintetis/non-target terbukti sama melalui checksum.
- Request internal Laravel membuktikan validasi password/no_hp, penerimaan kode/password kuat, penolakan OTP salah, dan keberhasilan OTP `account_verify`.
- Fixture akhir aktif dan `approved`, sesuai aturan aktivasi import tanpa approval laboran tambahan. Alur approval registrasi mandiri belum diuji.
- Komunikasi eksternal diblokir/di-fake; tidak ada request jaringan, SMTP, Gemini, FCM, reCAPTCHA, atau perangkat.
- Verifikasi akhir read-only menunjukkan operasional tetap tanpa `no_hp`, enum lama, dan nol catatan migration target. Tidak ada perubahan pada database operasional atau backup asli.

Hasil ini hanya membuktikan dua migration dan alur aktivasi pada lingkungan test, bukan kesiapan deployment atau seluruh fungsi aplikasi. Detail metode/hasil ada di `development-plan.md` bagian 8 dan histori pengujian.

## Checkpoint implementasi website/backend — 2026-10-24 15:54 +07

Target aktif: import laboran → aktivasi → portal mahasiswa → informasi/absensi sendiri → rekap staf, dengan API mesin/Flutter tetap tersedia. Tidak ada pekerjaan PostgreSQL, PWA, Copilot, firmware, atau deployment.

- Database operasional teridentifikasi melalui koneksi aktual sebagai `simalas` dengan 36 tabel InnoDB; bukan database test.
- Snapshot kode sebelum implementasi tersimpan dan hash diverifikasi di `C:\xampp\mysql-backups\code-baseline-20261024-155140` (525 file, 10.107.349 byte). Git di-init dan 526 file aman ditinjau staged, tetapi **baseline belum di-commit** karena identitas Git tidak tersedia. Tidak ada identitas yang dibuat atau push/reset/clean.
- `.gitignore` diperkuat untuk secret, dump/backup, data pribadi, log, generated, dan dependency. File harness backup: `tests/Support/backup_operational_verified.php`.
- Backup operasional terverifikasi di `C:\xampp\mysql-backups\operational-20261024-155335` (547.045 byte; SHA-256 `f1005d834c5a4e1e4980674579445d634d319c76c08083ad786da8dd7749c876`). Restore `simalas_restore_20261024_155335` berhasil: 36 definisi tabel dan 36 perbandingan jumlah baris cocok. File credential sementara dihapus; backup/restore tetap privat dan dipertahankan.
- Dua migration diterapkan pada operasional setelah precheck writer/transaksi: `no_hp` batch 48 dan `account_verify` batch 49. `no_hp` varchar(20) nullable dan enum memuat nilai lama + `account_verify`. Jumlah baris seluruh 36 tabel sesuai; hanya repository migration bertambah dua.
- Tidak ada akun mahasiswa asli yang digunakan untuk test aktivasi. Pengembangan importer/portal berikutnya tetap menggunakan fixture terisolasi.

Langkah berikutnya: meninjau dan melengkapi importer existing, kemudian portal mahasiswa. Test browser operasional, self-service attendance, dan perubahan mesin tidak termasuk.

## Checkpoint importer dan aktivasi — 2026-10-24

- `resources/views/admin/users_import_result.blade.php` menambahkan status kode belum diunduh dan kegagalan undangan (tidak ada pengiriman otomatis), tanpa mengubah schema/import flow.
- `tests/Feature/AccountImportActivationTest.php` diperbaiki isolasinya agar SQLite mencerminkan `users.email` nullable pada operasional; assertion snapshot idempotensi, kapitalisasi pesan, dan batas sesi idle 30 menit disesuaikan dengan aturan existing.
- Hasil aktual: `AccountImportActivationTest` **12 tests, 304 assertions — OK**. Cakupan meliputi CSV/XLSX, kontak kosong/leading zero, preview tanpa tulis, duplikasi, apply berulang, permission, sukses sebagian, aktivasi OTP, kode sekali pakai/kedaluwarsa, dan approval.
- Tidak ada akun mahasiswa asli, email/OTP nyata, atau layanan eksternal yang digunakan.

## Checkpoint portal, rekap, dan saran — 2026-10-24 16:19 +07

Implementasi website/backend tahap utama selesai dan seluruh suite lulus.

- Portal mahasiswa tersedia melalui `StudentAuthController` dan `StudentPortalController`: login/logout khusus mahasiswa, beranda, absensi sendiri + filter, profil/foto, tim PBL, notifikasi/read state, dan status enrollment. Payload absensi/PBL/notifikasi menggunakan controller/service mobile existing, bukan database kedua atau overlay demo.
- `resources/views/student/`, `layouts/student.blade.php`, `public/css/student-portal.css`, dan `public/js/student-portal.js` menyediakan UI mobile-first dengan empty/error/loading states. Belum enroll tidak mengunci portal dan dibedakan dari alokasi finger ID.
- Saran mahasiswa menggunakan `suggestions` existing: nomor referensi ID, status, balasan admin, read state, dan batas kepemilikan. Tidak ada schema/lampiran baru.
- `routes/web.php` menambahkan route guest/login dan area `role:mahasiswa`; `/dashboard` dan legacy `/app` mengarah ke portal. Flutter/API mesin/mobile tidak dihapus atau diubah kontraknya.
- Importer diperbarui pada hasil untuk menjelaskan kode belum diunduh dan tidak ada undangan otomatis; CSV/XLSX, preview, kontak kosong, duplikasi, dan apply idempoten lulus.
- Rekap staf/ekspor diverifikasi dengan filter dan metadata yang sama (`summary.total`, `generated_at`, periode, waktu ekspor), tanpa sistem rekap paralel.
- Hasil test terarah: `AccountImportActivationTest` 12/304, `StudentPortalTest` 8/64, `MesinApiAuthTest` 4/6, `AbsensiTapIdempotencyTest` 11/120. Setelah semua perubahan, seluruh suite **74 tests/1033 assertions — OK**.
- File utama: `StudentAuthController.php`, `StudentPortalController.php`, `StudentPortalTest.php`, `AccountImportActivationTest.php`, `routes/web.php`, `resources/views/student/*`, `resources/views/layouts/student.blade.php`, `resources/views/admin/users_import_result.blade.php`, `public/css|js/student-portal.*`, `tests/Support/backup_operational_verified.php`.
- Tidak ada import/aktivasi akun asli, request jaringan, email/OTP nyata, perangkat, deployment, atau perubahan konfigurasi layanan. Browser fisik/iPhone, foto upload aktual, LAN/cookie/HTTPS, dan penggunaan operasional belum diuji.

Langkah berikutnya yang disarankan: UAT browser lokal dengan akun uji yang dibuat admin, mengikuti import → kartu aktivasi → aktivasi → login mahasiswa → rekap/ekspor, tanpa mengubah data asli atau menambah migration.

## Jeda oleh pemilik — 2026-10-24

Pengembangan dihentikan sementara.

- Tetap memakai XAMPP dan koneksi mysql existing ke database simalas.
- PostgreSQL ditunda.
- Jangan menjalankan migration, repair, restore, perubahan konfigurasi,
  atau implementasi lanjutan tanpa persetujuan baru.
- Portal mahasiswa sudah dicoba pemilik: login, pembacaan absensi,
  import, aktivasi, dan OTP email berhasil pada pengujian terbatas.
- Temuan berikutnya: UX template/import dan distribusi aktivasi,
  tampilan portal mahasiswa, serta kasus 403 hasil import.
- Penyebab 403 belum dipastikan.
- Flutter, firmware, dan Copilot tidak diubah lebih lanjut.
- Sebelum melanjutkan, baca checkpoint lalu tanyakan target aktif.
  Jangan mengeksekusi roadmap lama secara otomatis.


## Penyatuan halaman login dan penghapusan registrasi manual web — 2026-11-04

- Halaman login web (`/masuk` / `/login`, `resources/views/auth.blade.php`) disatukan untuk 3 role dengan dropdown `role` berurutan: `Mahasiswa` (`mahasiswa`, default kunjungan umum), `Dosen` (`dosen`), dan `Admin / Laboran` (`laboran`). Label, placeholder, dan teks petunjuk menyesuaikan role terpilih.
- Pemeriksaan kecocokan pilihan dropdown terhadap `$user->role` asli di database dipertahankan (`AuthController::login`), dan pengalihan pasca-login (redirect biasa maupun respons JSON AJAX `public/js/auth-luxury.js`) didasarkan pada role akun asli (`mahasiswa` -> `route('mahasiswa.beranda')`, `dosen` -> `/dosen/dashboard`, `laboran` -> `/admin/dashboard`).
- Pendaftaran manual web dihapus dari tampilan login (`#authTabBar`, `#panelRegister`, `#panelRegResume`, `#panelRegVerify`), route `GET /register` serta `GET /register/verify` dialihkan ke `route('login')`, dan endpoint `POST` registrasi manual web dinonaktifkan melalui `AuthController::rejectManualWebRegistration()`.
- Opsi "Dosen (web)" dihapus dari pengaturan registrasi mandiri di `resources/views/admin/settings.blade.php`; `RegistrationGate` dan `AdminController::updateRegistrationSettings()` mengunci `dosen => false` sementara kontrol `mahasiswa` (APK) tetap dipertahankan.
- URL lama `GET /mahasiswa/masuk` (`route('mahasiswa.login')`) dialihkan ke `route('login', ['role' => 'mahasiswa'])`; `POST /mahasiswa/masuk` mempertahankan kompatibilitas dengan mendelegasikan autentikasi ke `AuthController::login` (`role = mahasiswa`); `POST /mahasiswa/keluar` mendelegasikan logout ke `AuthController::logout`.
- View lama `resources/views/student/auth/login.blade.php` tidak lagi dirender oleh route aktif, namun file tetap dipertahankan di disk sebagai kandidat pembersihan setelah verifikasi browser selesai.
- Tautan pasca-aktivasi di `resources/views/auth/activation.blade.php` diarahkan ke `route('login', ['role' => $role])`, dan `OtpMail` disesuaikan agar tidak lagi membuat signed URL `register.verify` untuk dosen.
- Pengujian terisolasi (`SQLite :memory:`): `StudentPortalTest` (**9 tests, 105 assertions — OK**) dan `AccountImportActivationTest` (**12 tests, 319 assertions — OK**). Verifikasi visual langsung di browser desktop/HP belum dilakukan pada sesi CLI ini.

## Redesign dashboard dan navigasi mahasiswa (Tahap 1) — 2026-11-04

- `resources/views/layouts/student.blade.php`, `resources/views/student/dashboard.blade.php`, `resources/views/student/_status.blade.php`, `public/css/student-portal.css`, dan `public/js/student-portal.js` diperbarui dengan sistem tema terang/gelap (`data-theme="dark"` melalui `dashboard_theme`), logo resmi `branding/aiot-simalas-mark.png`, ikon SVG inline, topbar desktop, dan bottom navigation bar di HP tanpa duplikasi tautan Profil.
- Kartu dan banner fingerprint membedakan secara eksplisit antara alokasi `finger_id` (`ID fingerprint dialokasikan` / `ID belum dialokasikan`) dan keberadaan template 512 byte di server (`Tercatat di sistem` / `Belum terdaftar`), tanpa mengklaim kesiapan perangkat fisik.
- Tata letak beranda disusun menjadi hero identitas mahasiswa, 4 kartu statistik semantik (`Lengkap`, `Tidak lengkap`, `Alfa`, `Template Sidik Jari`), serta grid 2 kolom seimbang (`Aktivitas terbaru` di kiri, `Tim PBL` + `Notifikasi` bertumpuk di kanan).
- Pengujian terisolasi (`SQLite :memory:`): `StudentPortalTest` (**9 tests, 105 assertions — OK**). Pemeriksaan visual langsung di browser desktop dan layar HP perlu dikonfirmasi pengguna.


## Paket akun mahasiswa hasil import (password sementara unik, wajib ganti password, kontak opsional, pemulihan akun) — 2026-11-04

- Migration `database/migrations/2026_10_10_120000_add_must_change_password_to_users_table.php` menambahkan kolom `users.must_change_password` (`boolean`, `default(false)`). **Migration ini belum dijalankan pada database MySQL operasional** sesuai batas persetujuan pemilik.
- Import mahasiswa (`AccountImportService::apply`) membuat akun mahasiswa dengan status `is_active = true`, `approval_status = 'approved'`, `must_change_password = true`, alokasi `finger_id` otomatis (`FingerIdAllocator::allocate`), dan password sementara acak unik (`XXXX-XXXX-XXXX` sekali unduh). Import `dosen` dan `laboran` tetap `approval_status = 'menunggu_aktivasi'`, `is_active = false`, `must_change_password = false`.
- Gate `EnsurePasswordChanged` (`password.changed`) membatasi sesi web dengan `must_change_password = true` hanya ke `GET/POST /mahasiswa/ganti-password`, `POST /logout`, `POST /mahasiswa/keluar`, dan `GET /session/ping`, serta memblokir penerbitan/penggunaan token mobile (`403 PASSWORD_CHANGE_REQUIRED` pada `MobileAuthController::login` dan `EnsureMobileToken`).
- Transisi terkontrol untuk akun mahasiswa import lama (`approval_status = 'menunggu_aktivasi'`) di `AccountActivation::inspectLegacyStudentImportState()` dan `transitionLegacyImportedMahasiswa()`:
  - Hanya berlaku bila log `account_approval_logs` membuktikan asal import (`admin_created` + `activation_code_issued`) tanpa log `registered`, `rejected`, atau `deactivated` serta urutan waktu tidak ambigu.
  - Mahasiswa lama yang belum pernah membuat password pribadi diverifikasi dengan kode sementara yang belum kedaluwarsa, lalu ditransisikan atomik ke `approved` + `is_active = true` + `must_change_password = true` -> `/mahasiswa/ganti-password`.
  - Mahasiswa lama yang sudah sempat membuat password pribadi di tahap 1 `/aktivasi/mulai` (`activation_password_set` tercatat setelah `activation_code_issued`) diverifikasi langsung terhadap password pribadinya (tanpa terblokir TTL kode awal dan menolak kode sementara lama), lalu ditransisikan atomik ke `approved` + `is_active = true` + `must_change_password = false` -> langsung ke `/mahasiswa/beranda` (atau token mobile).
- Pencabutan sesi/token saat reset password sementara oleh admin (`POST /admin/users/{user}/activation-code`, kini diotorisasi dengan `perm:users,update` / `AccountImportService::canManageTemporaryCredentials`):
  - `WebSessionGuard::stampLogin` menyimpan `web_password_user_id` dan `web_password_hash` di session; `EnforceWebSessionTimeout` memeriksa kecocokan hash terhadap record `User` terbaru di setiap request web sehingga sesi web lama langsung gugur di seluruh driver sesi (`array`, `file`, `database`, `cookie`, `redis`), selain penghapusan baris `sessions`, rotasi `remember_token`, dan penghapusan `mobile_api_token`.
- Kontak opsional dan pemulihan akun mandiri:
  - Banner pengingat kontak di `/mahasiswa/beranda` dapat ditutup per user (`student_contact_prompt_dismissed_for = $user->id`) dan dibersihkan saat login/logout agar tidak bocor antar-pengguna di browser yang sama.
  - Mahasiswa dapat mengisi/memperbarui `email` & `no_hp` serta memverifikasi OTP email di `/mahasiswa/profil` (`StudentPortalController::updateContact`, `resendEmailOtp`, `verifyEmailOtp`).
  - Lupa password mandiri (`OTPController::sendForgotPasswordOtp` & `MobileAuthController::forgotPassword`) menolak email yang belum terverifikasi (`email_verified_at === null`).
- Pengujian terisolasi (`SQLite :memory:`): `StudentPortalTest` dan `AccountImportActivationTest` lulus penuh (**26 tests, 527 assertions — OK**).


## Pematangan UI/UX Portal Mahasiswa (Putaran 1: Beranda, Navigasi, Wajib Ganti Password, Profil & Keamanan) — 2026-11-04

- **Beranda & Navigasi (`resources/views/layouts/student.blade.php`, `resources/views/student/dashboard.blade.php`, `public/css/student-portal.css`, `public/js/student-portal.js`):**
  - Header menyembunyikan menu navigasi utama dan menampilkan badge status `Pengamanan Akun` saat mahasiswa berada di halaman wajib ganti password (`is-password-gate`).
  - Kartu `Tim PBL` di beranda disusun dengan baris penuh untuk `Nama Proyek` dan 2 kolom di bawahnya (`Kode Proyek`, `Shift Sesi`) agar nama proyek panjang tidak terpotong atau menyempit.
  - Banner pengingat kontak opsional dilengkapi tag `Opsional` dan penutupan progresif via fetch AJAX (`data-dismiss-banner`) dengan fallback POST standar.
  - Breakpoint navigasi bawah (bottom navigation bar) disesuaikan ke `<= 980px` agar topbar tablet/HP tidak pernah padat atau bertumpuk.
- **Wajib Ganti Password (`resources/views/student/change-password.blade.php`, `app/Http/Controllers/StudentPortalController.php`):**
  - Menampilkan kartu identitas akun yang sedang diamankan, penjelasan penggantian password sementara, tombol tampil/sembunyikan password (`data-toggle-password`), checklist ketentuan `StrongPassword` yang interaktif secara real-time (`data-pwd-rule`), dan indikator kecocokan konfirmasi password (`#passwordMatchHint`).
  - Pada lingkungan `local`, `GET /mahasiswa/ganti-password?preview=1` diizinkan untuk pratinjau visual di browser meskipun `must_change_password` bernilai `false` atau migration MySQL operasional belum dijalankan.
- **Profil & Keamanan (`resources/views/student/profile.blade.php`):**
  - Dikelompokkan menjadi 4 kartu terstruktur: (1) `Identitas & Foto Profil` (`Dapat Diedit`), (2) `Data Akademik & Biometrik` (`Dikelola Admin`), (3) `Kontak Pemulihan (Opsional)` (`Opsional · Dapat Diedit`) yang membedakan status email (`Belum diisi`, `Belum terverifikasi`, `Terverifikasi`) dan menampilkan nomor HP sebagai `Tersimpan`/`Belum diisi` tanpa klaim verifikasi palsu, dilengkapi kotak verifikasi OTP 6 digit & kirim ulang OTP saat email belum terverifikasi, serta (4) `Keamanan & Pemulihan Akun`.
- **Pengujian:** `StudentPortalTest` & `AccountImportActivationTest` (**26 tests, 527 assertions — OK**) pada `SQLite :memory:`.


## Paket pembaruan terpadu Portal Mahasiswa (Layout Sidebar, Beranda, Absensi Saya, Notifikasi Panel/Modal, Bantuan AI & Riwayat Bantuan, Ganti Password Mandiri, dan Aturan Jadwal Mingguan/Kalender 9 SKS) — 2026-11-04

- **Kerangka Layout & Navigasi (`resources/views/layouts/student.blade.php`, `public/css/student-portal.css`, `public/js/student-portal.js`):**
  - Mengganti bottom navigation 6-item dengan sidebar kiri yang dapat diperkecil (collapsible di desktop, off-canvas drawer dengan overlay di layar HP `<= 960px`).
  - Menu utama disederhanakan menjadi 3 item: `Beranda`, `Absensi Saya`, dan `Riwayat Bantuan`. Menu `Tim PBL` dan `Notifikasi` disembunyikan dari navigasi utama dan beranda (tanpa menghapus data, relasi, API, maupun route kompatibilitas `/mahasiswa/tim` dan `/mahasiswa/notifikasi`).
  - Topbar memuat tombol toggle sidebar, judul halaman, toggle tema (`light`/`dark` via `dashboard_theme`), ikon lonceng notifikasi (membuka slide-over panel notifikasi dengan filter `Semua` / `Belum dibaca`, paginasi `GET /mahasiswa/notifikasi/feed`, dan modal detail aksesibel yang menandai baca per item tanpa menandai semua item otomatis saat panel dibuka), serta dropdown profil (`Profil Saya`, `Ganti Password`, `Keluar`).
  - `User::getProfilePhotoUrlAttribute()` memeriksa keberadaan file fisik di disk sebelum mengembalikan URL sehingga path foto yang hilang otomatis fallback ke inisial nama tanpa ikon gambar rusak (`broken image`).
- **Struktur Beranda Mahasiswa (`resources/views/student/dashboard.blade.php`, `StudentPortalController::dashboard`):**
  - Disusun menjadi: (A) `Identitas Mahasiswa` (foto/inisial, nama, NIM, prodi, kelas/angkatan, semester/sesi, status akun & biometrik jujur), (B) `Absensi Hari Ini` (jadwal efektif hari ini, tap masuk/pulang tercatat, status kelengkapan & ketepatan waktu), (C) `Ringkasan Periode` (`Hadir Lengkap`, `Perlu Diperiksa / Tidak Lengkap`, `Alfa` beserta rentang tanggal eksplisit dan sumber kebijakan), (D) `Kalender dan Informasi Akademik` interaktif (klik tanggal menampilkan jadwal efektif dan membedakan agenda informasi/ujian `tetap_wajib` dari `libur`/`batal_wajib` dan `perubahan_jam`/`ubah_jam`), serta (E) `5 Sesi Terjadwal Terakhir`.
- **Redesign Absensi Saya (`resources/views/student/attendance.blade.php`, `StudentPortalController::attendance`):**
  - Desktop menggunakan tabel utama (`Tanggal`, `Jadwal`, `Masuk`, `Pulang`, `Status Kelengkapan & Ketepatan`, `Aksi Rincian`) dan mobile menggunakan kartu per tanggal.
  - Memisahkan secara tegas **Kelengkapan** (`Lengkap`, `Tidak Lengkap`, `Hanya Masuk`, `Alfa`) dari **Ketepatan Waktu** (`Datang lebih awal`, `Tepat waktu`, `Terlambat X menit`, `Pulang lebih awal X menit`, `Pulang sesuai jadwal`, `Melewati jam jadwal` — tidak menggunakan label `"Lembur"` di permukaan mahasiswa).
  - Indikator teknis `slot_lab` (`S1–S10`) ditempatkan di modal rincian dengan penjelasan bahwa itu indikator waktu masuk 55 menitan sesi pagi, bukan 10 kali wajib tap.
  - Menyediakan tombol **Laporkan Masalah Absensi Tanggal Ini** di dalam modal rincian yang mengisi awal tanggal dan referensi status ke alur konfirmasi laporan.
- **Aturan Jadwal Mingguan 3 Hari (9 SKS) & Kalender Pengecualian (`app/Support/AbsensiEffectiveSchedule.php`, `AbsensiKebijakan`, `AbsensiLatenessCalculator`, `AbsensiTapEvaluator`, `AbsensiSessionCloser`, `AbsensiManagementController`, `resources/views/admin/absensi_settings.blade.php`):**
  - Migration `database/migrations/2026_10_10_130000_enhance_absensi_schedule_and_calendar.php` menambahkan kolom `tanggal_selesai`, `jenis`, `dampak_absensi`, `jam_masuk_khusus`, `jam_pulang_khusus` pada `absensi_kalender_libur` serta `menit_pulang_cepat` dan `pulang_cepat` pada `log_absensi` dan `absensi_harian` (diuji di `SQLite :memory:`; **belum dijalankan pada MySQL operasional** sesuai batas izin).
  - Mendukung mode `jadwal_mingguan` per hari (default mendukung 3 hari / 9 SKS: Rabu `07:50–17:00`, Kamis `07:50–17:00`, Jumat `07:50–12:00`) beserta `effective_from` (`Tanggal mulai berlaku`) agar riwayat sebelum `effective_from` tetap mengikuti kebijakan lama.
  - Urutan prioritas resolusi: Pengecualian kalender tanggal terkait (`batal_wajib` / `ubah_jam` / `tetap_wajib`) → Jadwal mingguan efektif (jika `>= effective_from`) → Kebijakan sebelum `effective_from`.
  - Tap masuk lebih awal, terlambat, maupun tap pulang sebelum jam jadwal (`pulang_cepat`) tetap dicatat selama sesi buka dan dinilai ketepatan waktunya tanpa ditolak.
  - Dua command `absensi:generate-alfa` disatukan pada `GenerateAbsensiAlfaCommand` (`GenerateAbsensiAlfa` dijadikan alias legacy `absensi:generate-alfa-legacy` yang mendelegasikan ke `AbsensiSessionCloser`).
- **Floating Bantuan AI & Riwayat Bantuan (`POST /mahasiswa/bantuan/ai`, `GET/POST /mahasiswa/saran`, `resources/views/student/suggestions.blade.php`):**
  - Tombol bantuan mengambang di kanan bawah dengan sapaan bergantian halus yang berhenti setelah ditutup/diinteraksikan dan tidak pernah membuka panel otomatis.
  - Memisahkan secara tegas **Tanya AI** (read-only, terisolasi per mahasiswa, tanpa akses tool admin, dan menegaskan tidak mengirim laporan otomatis) dari **Kirim Laporan / Saran** yang mewajibkan langkah konfirmasi eksplisit dan menghasilkan nomor tiket (`Referensi #ID`) yang dipantau di halaman `Riwayat Bantuan`.
- **Ganti Password Mandiri Saat Login (`GET/POST /mahasiswa/ganti-password`, `resources/views/student/change-password.blade.php`):**
  - Mahasiswa yang sudah aktif (`must_change_password = false`) dapat mengganti password kapan saja dari dropdown profil dengan memverifikasi `current_password` + password baru (`StrongPassword`), terpisah dari gate wajib ganti password awal dan alur lupa password (OTP email terverifikasi).

## Pembaruan 2026-11-10 (dari sesi kerja, tercatat agent)

- Paket backend: semantik "menunggu tanggal efektif" untuk mode jadwal_mingguan (`AbsensiEffectiveSchedule::weeklyPending`), flag `jadwal_konsisten` ditampilkan di riwayat/absensi + payload mobile; password sementara mahasiswa berformat `namadepan-XXXXXX`; tautan aktivasi dihapus dari login publik (backend /aktivasi dibatasi dosen/laboran); pemulihan password via tautan email sekali pakai (`PasswordResetLink`, token SHA-256 di email_verification_tokens, TTL 60 mnt) + halaman `/atur-ulang-password/{token}` + `POST /mahasiswa/password/kirim-tautan-reset`.
- Paket frontend: lapisan CSS v3 di student-shell.css (sidebar/topbar/pencarian menu/kartu/stat/kalender/tabel/chat/responsif 720px/340px), indikator "filter aktif" di riwayat absensi, dropdown akun → Profil/Keamanan Akun/Keluar, JS pencarian menu dipindah ke student-shell.js (student-portal.js tidak dimuat), versi asset dibump.
- Test SQLite :memory:: 32 tests/646 assertions lulus (StudentPortalTest + AccountImportActivationTest). Alur reset belum diuji end-to-end manual; verifikasi visual browser menunggu pemilik.
- Menunggu: migration must_change_password & enhance_absensi_schedule_and_calendar di MySQL operasional; tanggal efektif jadwal mingguan ditetapkan pemilik.

## Pembaruan 2026-11-06 (paket penyederhanaan — tercatat agent)

- Registrasi publik: UI & endpoint sudah tertutup (register.* POST menolak via rejectManualWebRegistration). UI approval registrasi dipensiunkan: banner "siap disetujui", bulk-approve, checkbox kolom, opsi filter ready_approve, dan tombol approve/hint persetujuan dihapus dari users.blade.php + users-list-fragment; endpoint approve tetap ada (kompatibilitas), arsip log dipertahankan. Akun pending lama tidak disentuh — tersisa 1 akun mahasiswa status pending (dihitung langsung).
- PBL: tautan navigasi admin & dosen dihapus; route/tim/resolver dipertahankan (data & histori utuh; mahasiswa tanpa tim tetap login/jadwal/tap/rekap).
- Monitoring digabung ke "Mesin Absensi": /admin/monitoring redirect ke admin.mesin.index; kolom "Tap terakhir" ditambah (agregat LogAbsensi per mesin); sidebar: Mesin IoT→Mesin Absensi, "Audit Absensi Mentah"→"Log Tap" dipindah ke grup Operasional IoT; toolbar dashboard disesuaikan.
- Dashboard demo overlay dihapus dari AdminController & DosenController (DashboardDemoData::overlay* tidak lagi dipanggil) — tampilan kini data nyata/nol.
- "Tetap masuk" 30 hari: login remember men-queue cookie remember_until terenkripsi; EnforceWebSessionTimeout memaksa logout viaRemember tanpa/kedaluwarsa penanda; logout menghapusnya; remember_token tetap dirotasi saat reset password.
- Memo per-request ditambahkan: SystemSetting::valueFor, AbsensiSesiSetting::forShift, AbsensiKalenderLibur::entriesForDate (schema check + entri per bulan), MahasiswaShiftResolver — resolve() 30 hari turun 150→5 query.
- Migration must_change_password & enhance_absensi_schedule_and_calendar sudah dijalankan di DB operasional (disetujui pemilik).
- Test: 86/86 feature tests lulus (SQLite :memory:).
- Rencana AI (tahap berikut, belum implementasi): chat satu kolom tanpa sidebar permanen, riwayat via drawer; mahasiswa=bantuan+laporan, dosen=rekap+ekspor, laboran=operasional; backend membatasi akses; aksi ubah wajib pratinjau+konfirmasi.

### Penutupan paket penyederhanaan (2026-11-06)

- Toggle registrasi dihapus total: route, method, kartu di settings.blade.php; RegistrationGate::isOpen() kini selalu false (web+APK tertutup permanen).
- Endpoint admin.users.approve/bulk-approve/reject tidak lagi dirutekan (405); metode controller tersisa tanpa route; URL notifikasi pending diarahkan ke filter approval_status.
- Remember-me: penanda remember_until di-forget saat login tanpa remember (hanya jika klien mengirim marker).
- Test baru: penerbitan marker remember + absennya marker tanpa remember; approve/reject → 404/405; registrasi permanen tertutup. 33/33 lulus; batas: kedaluwarsa 30 hari viaRemember belum disimulasikan (butuh sesi recaller nyata).
- Akun pending lama (1 mahasiswa) tidak disentuh.

### Paket asisten AI (2026-11-06, tahap 1 — tercatat agent)

- Sistem Copilot existing dipakai (AdminAiController melayani laboran+dosen, proposals confirm/cancel, AdminAiOrchestrator/AdminAiToolRegistry, HelpdeskAiService untuk mahasiswa) — tidak ada sistem percakapan kedua.
- UI: sidebar riwayat permanen dihapus → riwayat kini drawer di semua lebar layar (JS mobileMq diperluas; tombol cpCollapse kini menutup drawer; scrim+Esc+inert sudah ada di copilot.js/CSS v3).
- Test: CopilotStreamTest 32/32 lulus. Belum diuji visual; capability/scope dosen-ekspor & tool write belum diaudit penuh — tahap berikutnya.


### Asisten AI — fase kapabilitas (2026-11-15)
- Tool baca baru (registry+runner): ruangan_status, pengajuan_pending, petugas_list, kalender_agenda, riwayat_aksi_ai — perm-scoped (ruangan/ruangan_persetujuan/petugas/attendance/dashboard). COPILOT_TOOLS diperluas: +enroll_pending, list_pending_approvals, monitoring_snapshot, log_absensi_cari. Dosen mendapat kalender_agenda (dosen_absensi,view).
- WRITE_TOOLS_ENABLED diperluas (HITL tetap wajib): approve_user, reject_user, bulk_approve_ready, unlock_user_login, toggle_user_active, draft_pengumuman, create_announcement, toggle_announcement, create_ruangan, toggle_mesin, koreksi_absensi_harian, batalkan_alfa, selesai_tidak_lengkap. Tool write baru: create_ruangan (perm ruangan,create; executor bikin kode otomatis bila kosong).
- Proposal kini distream sebagai blok 'proposal' (streamLocal + loop tool Gemini) → tersimpan di meta['blocks'] → UI merender kartu konfirmasi (Setujui/Batalkan via POST admin.ai.confirm/cancel). Dispatch tool Gemini kini pakai copilotToolsFor(role) — dosen tidak lagi ditolak oleh list admin.
- UI AsistenAi merender blok stats/table/links (sebelumnya di-drop) + kartu proposal; blok links memuat href route ekspor nyata (Excel/Word/cetak) = preview-tabel → unduh. Rename/hapus histori + panel Kemampuan & Batasan + maskot baru dari fase sebelumnya.
- Fallback lokal untuk intent tulis: nonaktifkan/aktifkan akun (nama/NIM/#id → resolve User), tolak akun #id, daftarkan ruangan <nama>; intent tanpa target jelas → panduan. Dosen tetap ditolak baca-saja (tool tulis bukan untuk dosen).
- Perintah: /menu (tanpa argumen=daftar topik), /grafik (kosong=polling periode, argumen=chart langsung), /pengguna (kartu stats grand_total+per-role, /pengguna <nama>=tabel cari_pengguna), /impor + tombol paperclip (info belum tersedia+tautan Impor Akun), /baru (klien); palet slash memfilter token perintah pertama. Status kini tahap nyata (Mengirim→Menghubungi Gemini+model→Menunggu→Mengambil data:<tool>→Menyusun→Menampilkan data→mengetik), tanpa teks bergilir palsu.
- Interaksi: blok 'options' (polling chip yang bisa diklik → kirim pesan), perintah /help (daftar kemampuan + opsi) dan /menu <rekap|akun|ruangan|perangkat|laporan|aksi>; kartu aksi beranda kini mengirim /menu (jawaban langsung berupa polling, bukan hanya mengisi prompt).
- Ketahanan: slash command (/help,/menu) dipotong sebelum provider; provider gagal sebelum jawaban terkirim → fallback jawaban lokal (bukan error mentah); rute lokal baru: status ruangan, pengajuan pending, daftar petugas, agenda kalender, log tap, riwayat aksi. UI: tombol Stop saat streaming + Coba lagi.
- Test CopilotStreamTest diperbarui ke kontrak baru (proposal-tanpa-eksekusi + fallback lokal): 37/37 lulus PHP 8.3.
- Belum: impor mahasiswa via chat, preview modal PDF, halaman AI dosen masih Blade admin.ai_assistant, chatbot mahasiswa belum jadi asisten laporan, tuning kecepatan provider.
### Asisten AI — penyelesaian (2026-11-06)

- Scope dosen diterapkan di seluruh jalur data: AbsensiManagementController::scopedMahasiswaQuery (snapshot copilot, rekap, alfa/tidak-lengkap, ekspor /dosen/absensi/export), PeriodicReportBuilder::mahasiswaBaseQuery (/dosen/reports + ekspor Excel/Word/cetak + tool laporan_absensi_periode), CopilotAbsensiData::cariMahasiswa, DosenController::mahasiswaDirectoryQuery (direktori + CSV). Cakupan = tim PBL yang diampu (DosenMahasiswaScope); dosen tanpa pemetaan → hasil kosong (BUKAN semua kelas). BUTUH KONFIGURASI: pemetaan dosen↔kelas/tim belum ada di luar PblProject — saat PBL dinonaktifkan, dosen tanpa PblProject tidak melihat data apa pun; tentukan pemetaan kelas pengganti jika diperlukan.
- Write tool: READ_ONLY tetap, tapi WRITE_TOOLS_ENABLED=['reply_suggestion'] → laboran bisa membalas laporan mahasiswa lewat preview+konfirmasi (status→ditanggapi); aksi tulis lain tetap diblokir. Confirm mengecek status pending, kedaluwarsa, pemilik proposal, dan RBAC.
- UI copilot: sapaan per role + maks 3 contoh (cpConfig.greeting, slice 3); riwayat drawer di semua lebar.
- Test baru: dosen tanpa pemetaan→kosong (snapshot/cari/ekspor), dosen Tim Alfa→hanya timnya (rekap/cari/unduhan), reply_suggestion proposal→confirm pemilik berhasil, pemilik lain ditolak, ulang-konfirmasi ditolak. 90/90 lulus.
- helpAi mahasiswa memakai data diri sendiri saja (sudah ada, terverifikasi kode).

### Pemetaan dosen ↔ kelas (2026-11-06)

- Migration BARU `2026_11_06_130000_create_dosen_kelas_table.php`: tabel `dosen_kelas` (id, user_id FK cascade, kelas varchar(80), timestamps; unique(user_id,kelas), index(kelas)). Hanya tabel baru; tidak mengubah users/PBL/absensi. BELUM dijalankan di DB operasional — menunggu izin pemilik; test :memory: sudah menjalankannya.
- DosenKelasScope (memo per-request attributes, deny-by-default) menggantikan DosenMahasiswaScope/PBL untuk akses operasional dosen: snapshot rekap, PeriodicReportBuilder (dosen.reports + ekspor Excel/Word/cetak + copilot laporan), cari_mahasiswa, direktori+CSV, agregat dashboard (mahasiswaAktifQuery, countAbsensiTaps, distinctMahasiswaHadir). DosenMahasiswaScope tetap untuk kebutuhan PBL/histori — data tidak dihapus.
- Tanpa penugasan → "Belum ada kelas yang ditugaskan" (snapshot kelas_scope_empty+message, blok stats copilot di 4 tool, ekspor redirect+flash error).
- Laboran mengelola pemetaan lewat Manajemen User: kelas_cakupan[] di form Tambah/Edit User (role=dosen → multi-select kelas), sinkron dosen_kelas di storeUser/updateUser; berpindah role menghapus pemetaan.
- Test baru (CopilotStreamTest): dosen tanpa penugasan→kosong+pesan di semua jalur, dosen TI-1A→hanya kelasnya (rekap/cari/unduhan), multi-kelas union, kelas bersama dua dosen, sinkron kelas_cakupan lewat PUT /admin/users. 92/92 lulus.

### Frontend Copilot + form cakupan (2026-11-06)

- Header copilot di-restrukturisasi: ikon kembali | judul+status | aksi [chat baru, riwayat]. cpNewHead & cpNew sama-sama memanggil newChat.
- Drawer riwayat universal kini di aturan dasar .cp-side (fixed+transform), bukan override: rail .is-collapsed, blok v3, dan duplikat drawer mobile DIHAPUS; tweak mobile tersisa di @media 860px; init localStorage cp_side_collapsed dihapus; tombol collapse drawer→Tutup.
- Sapaan per role (cpConfig.greeting) + maks 3 contoh; kosong-state hilang saat chat dimulai (renderEmpty).
- Form cakupan dosen: multi-select diganti daftar checkbox yang bisa dicari (cari+hitung+empty state+penjelasan akses), prefill edit via data-kelas-cakupan; CSS inline di users.blade.php (copilot.css TIDAK dimuat halaman users).
- Test: CopilotStreamTest+AccountImportActivationTest 49/49 lulus. Visual belum dicek browser.

### SMART BRAIL — modul BMS ruangan (2026-11-11)

- Migration BARU `2026_11_11_120000_create_smart_brail_bms.php` — BELUM dijalankan di DB operasional (menunggu izin). Menambah tabel `ruangan` (aturan JSON: hari/jam buka-tutup/max_advance_days=2/max_duration_minutes=120/max_pending_per_team=1 — default dapat diubah), `ruangan_pengajuan` (snapshot pengaju/tim/pembimbing/peserta; status diajukan|menunggu_penerimaan|disetujui|ditolak|dibatalkan|selesai|kedaluwarsa), `ruangan_izin` (per peserta), `ruangan_petugas` (CS non-portal, kredensial hash), `ruangan_access_events`; kolom `mesin_iot.ruangan_id`, `users.is_admin` (default 0 — hak akun lama tak berubah), `users.rfid_uid`, `users.akses_pin` (hashed).
- RuanganBookingService: validasi aturan, maks 1 pending/tim/ruangan, blok jadwal petugas, keputusan+penerimaan pengganti atomik via lockForUpdate, izin per peserta (bukan seluruh tim), cabut izin saat batal/tolak/cabut, statusEfektif() bermakna waktu.
- AksesRuanganService: fingerprint(finger_id)/RFID(rfid_uid)/PIN(akses_pin) → identitas sama → cek izin sama; petugas via jadwal+masa berlaku; lazy-expire izin.
- API perangkat: POST /api/mesin/ruangan/access/{check,event} (mesin.auth + binding ruangan_id dari mesin — room_id klien diabaikan). Event terpisah dari tap absensi; 'buka' = bukti pintu dibuka, beda dari izin/perintah.
- Route web: mahasiswa /mahasiswa/ruangan (ajukan+daftar tim+terima pengganti+batal), dosen /dosen/ruangan (tim bimbingan via manpro/co_manpro), admin /admin/ruangan, /admin/pengajuan, /admin/petugas, POST /admin/mesin/{m}/ruangan (butuh is_admin). Permission baru: ruangan, ruangan_persetujuan, petugas (default laboran aktif via fallback defaults — tanpa tulis DB).
- Frontend: brand Smart BRAIL di layouts/dashboard; sidebar admin +dosen +nav mahasiswa; halaman ruangan menampilkan monitoring kejadian akses terpisah ("Belum terhubung" bila tanpa sumber).
- is_admin pemberian hanya lewat tindakan terotorisasi (belum ada UI toggle) — memisahkan kewenangan sistem/perangkat dari laboran operasional tanpa mengubah hak akun yang ada.
- RuanganBookingTest 19/19 + suite penuh 111/111 lulus (sqlite :memory:). Regresi absensi/cakupan dosen tidak berubah. Visual belum dicek browser.

### Penerapan SMART BRAIL ke simalas (2026-11-11)

- Backup `simalas-pre-smartbrail-20261111-161615.sql` (561KB, 60 statement) → migration `2026_11_11_120000_create_smart_brail_bms.php` DONE (hanya file ini). 5 tabel + 3 kolom terverifikasi ada via Schema check; semua tabel kosong (tanpa data contoh).
- `is_admin`/`rfid_uid`/`akses_pin` DIKELUARKAN dari User::$fillable — tidak bisa dinaikkan via mass-assignment (import/profil/request); hanya `$user->is_admin = true; $user->save()` tindakan terotorisasi. Akun kandidat laboran: id 9 dan 17 — menunggu konfirmasi pemilik.
- Perbaikan konkurensi: assertTidakKonflik/assertSlotBebas kini mengunci SELURUH baris aktif ruangan+tanggal (bukan hanya terputus) — sebelumnya dua persetujuan bersamaan pada 'diajukan' tidak saling mengunci. Terbukti di MariaDB terisolasi (simalas_bms_uji, di-drop setelahnya): proses A menahan FOR UPDATE 3s → setujui() nyata proses B memblokir lalu ditolak konflik; hasil akhir 1 disetujui, 1 tetap diajukan.
- Kontrak perangkat: heartbeat via /api/mesin/heartbeat (mesin.auth generik, semua tipe) sudah ada; telemetri via POST /api/mesin/ruangan/access/event jenis=telemetri → ruangan_access_events. Tidak ada status/sensor yang diarang.
- RuanganBookingTest 19/19 lulus kembali setelah perubahan.
- is_admin diberikan hanya pada user id 17 ("Admin brail", role laboran, aktif/approved) via assignment langsung — tersimpan (fresh=true). User id 9 tidak diubah (is_admin=false). Jalur binding: perm:mesin,update=true + gate is_admin → lolos; login HTTP tidak diuji (kredensial milik pemilik).
- Bug mahasiswa /mahasiswa/ruangan (ditemukan via laravel.log 2026-11-11 16:29): RuanganBookingService::anggotaTim() + snapshot ajukan() dan view student/ruangan memakai kolom name/nim — users memakai nama_lengkap/nitk_nidn. MySQL error 1054; SQLite test lolos palsu karena DQS fallback (kolom hilang → literal "name"). Diperbaiki ke nama_lengkap/nitk_nidn di service+view; snapshot tetap key name/nim (bentuk internal). RuanganBookingTest 19/19.

### Frontend Smart BRAIL + pemisahan Admin/Laboran (2026-11-11)

- Dashboard admin: blok "Smart BRAIL" kondisional $isAdmin — Admin melihat tautan Akun/Perangkat + ringkasan sistem; laboran biasa melihat antrean/pemakaian hari ini/petugas/perangkat (tanpa tautan akun). Data nyata dari RuanganPengajuan/RuanganIzin/RuanganPetugas/MesinIot/RuanganAccessEvent; "belum terhubung" bila tanpa event.
- Backend admin-only tetap ditegakkan di bindMesin (abort_unless is_admin) — bukan sekadar sembunyikan menu; hak lama laboran tak berubah.
- BMS pages redesign: partial bersama partials/bms-styles.blade.php (bms-card/grid/chips/time/people, light/dark, mobile 1 kolom). admin/ruangan = kartu per ruangan (aturan chips, perangkat terikat, kejadian terakhir/belum-terhubung, form aturan inline via details, toggle aktif) + tabel binding mesin↔ruangan (is_admin saja). admin/pengajuan = kartu keputusan (waktu diminta vs pengganti, snapshot tim/pengaju/pembimbing/peserta, alasan+riwayat, aksi setujui/tolak/tawar/cabut). admin/petugas = kartu (identitas, masa berlaku, jadwal chips, toggle status).
- Smoke MariaDB terisolasi (simalas_bms_uji, di-drop): migrate+anggotaTim+ajukan — nama asli terbaca, snapshot benar (membuktikan fix kolom di driver nyata).
- SchemaGuardTest (baru): assert kolom yang dirujuk query BMS ada + name/nim tidak ada + anggotaTim mengembalikan nama_lengkap — mencegah regresi DQS SQLite.
- Test: RuanganBookingTest+SchemaGuardTest+CopilotStreamTest 58/58. Visual browser belum dicek.

### Penutupan frontend + gate admin.only (2026-11-11 lanjutan)

- Middleware `RequireAdmin` (alias `admin.only`, bootstrap/app.php) — 403 untuk laboran tanpa is_admin.
- admin.only diterapkan di routes/web.php pada: users CRUD + import + activation-code regenerate, absensi pengaturan (sesi/kebijakan/tutup-sesi/generate-alfa/libur/prodi), admin.settings, mesin store/update/toggle/regenerate/destroy/bind-ruangan, role-access index/update/reset. Operasional laboran TETAP: pengajuan/ruangan/petugas/mesin.index (status perangkat)/fingerprint/notifications/suggestions/attendance/reports — tidak dicabut.
- Dashboard admin.blade.php: blok Smart BRAIL kondisional $isAdmin; KPI "Pengguna" → tautan users hanya admin (laboran → attendance); kartu feed "Pengguna baru" hanya admin.
- _sidebar admin: grup "Administrasi" (Manajemen User + Pengaturan) hanya render bila is_admin.
- student/ruangan.blade.php ditulis ulang: grid ketersediaan (chips aturan per ruangan) + form (ruangan/tanggal/jam/tujuan/peserta-checkbox tim) + riwayat (chip status, waktu diminta vs pengganti, snapshot, aksi terima/batal); kelas sp-* nyata dari student-shell.css; empty state tanpa tim.
- Fixture test diselaraskan: AccountImportActivationTest+StudentPortalTest admin is_admin=true; CopilotSyntheticData laboran is_admin=1.
- Test baru: admin_only ditolak laboran biasa (users/settings/mesin.store/prodi 403) + operasional tetap OK; admin boleh (users/settings 200, mesin.store lolos gate). PHPUnit (PHP 8.3 /c/tools/php83): 115/115 lulus. php artisan test gagal di PHP 8.2 xampp (PHPUnit butuh ≥8.3) — bukan masalah aplikasi. Visual browser belum dicek.

### Pemisahan 4 role: admin / laboran / dosen / mahasiswa (2026-11-12)

- `User::isAdmin()` = role 'admin' ATAU is_admin (transisi); `isLaboran()` = role laboran ATAU admin (admin tetap operasional). `role_display` admin→'Admin', laboran→'Laboran'.
- `RequireAdmin` memakai `isAdmin()` — lolos untuk role admin maupun flag is_admin selama transisi.
- `PermissionRegistry`: defaults['admin'] = salinan laboran; `can('admin',…)` fallback benar. `seedDefaults()` melewati 'admin' (izin admin dari fallback, bukan baris DB — menjaga migration 2026_05_11_193000 yang memanggil seedDefaults tetap aman di enum lama). `roleLabels`/`roleAccessCards` punya kartu admin.
- Route: grup /admin `role:laboran,admin`; `/dashboard` dispatcher admin→/admin/dashboard; login validasi role += 'admin'; redirect login & OTP match += 'admin'.
- `AccountImportService` canImport/canManageTemporaryCredentials mengizinkan role admin+laboran. `Announcement::scopeForRole` admin melihat semua. `AdminAiToolRegistry` copilotToolsFor/permFor 'admin' = set laboran; runner/orchestrator filter role += 'admin'.
- RolePermissionController roles += 'admin' (halaman kelola akses menampilkan kartu admin dengan semua menu). users.blade: filter/log/select role += 'Admin'; role_permissions.blade deskripsi+menuKeys diperbarui (lockers/dosen_loker dihapus, BMS ditambah).
- Login UI: opsi 'Admin' dipisah dari 'Laboran' (dropdown tetap label saja — role dicocokkan ke DB).
- Migration `2026_11_12_120000_add_admin_role.php` — enum users.role & role_permissions.role += 'admin'; promosi id 17 laboran→admin (idempotent); down() mengembalikan. **BELUM dijalankan di DB operasional — menunggu izin.** Di SQLite test, Laravel rebuild CHECK constraint — 'admin' tersimpan.
- users.is_admin DIPERTAHANKAN (masih dibaca isAdmin() sebagai transisi); tidak ada mass-upgrade laboran.
- Versi PHP: web Apache memakai C:/tools/php83 (8.3.x via httpd-xampp.conf LoadFile php8ts.dll) — cocok composer ^8.3; CLI /c/xampp/php tetap 8.2.12 (gagal untuk phpunit, pakai /c/tools/php83).
- Test: 118/118 PHPUnit. Termasuk: role admin murni lolos admin.only + operasional, login admin→/admin/dashboard, akun admin pilih role lain ditolak, dosen/mahasiswa 403 di area admin & putusan pengajuan, recaller remember-me valid + remember_until kedaluwarsa→logout (tanpa menunggu 30 hari).
- DIJALANKAN di operasional (2026-11-11): backup `simalas-pre-adminrole-20261111-172401.sql` (571KB, 66 statement) → hanya `2026_11_12_120000_add_admin_role` (144ms). Verified: enum kedua tabel = 4 nilai; id 17 role='admin' is_admin=1 aktif/approved; id 9 tetap laboran is_admin=0. Inventaris is_admin: HANYA id 17 — tidak ada laboran lain pemegang flag. role_permissions 'admin' = 0 baris (fallback defaults laboran) → can() efektif semua menu. Login HTTP akun 17 tidak diuji (kredensial pemilik); jalur validasi role 'admin' + redirect terbukti di test.

### Pengamanan autentikasi (2026-11-12)

- Pesan kegagalan login SERAGAM "Tidak dapat masuk. Periksa data login Anda." untuk akun tidak ada/password salah/role salah/nonaktif/pending/ditolak — web (AuthController) dan mobile API (INVALID_CREDENTIALS 401). Pesan throttle/lockout dipertahankan (berlaku per-ID+IP, juga untuk ID tidak terdaftar). Hash::check selalu jalan (dummy hash saat akun null) — timing tidak membedakan keberadaan akun.
- Anti-enumerasi OTP/forgot: OTPController dispatchPasswordResetOtp + cooldown 60s + resetPassword memakai respons sukses generik; resend tetap menampilkan panel verify-otp dengan email input.
- Batas tebakan OTP 6 digit: verify-otp (password_reset) & verify-account-email dibatasi 8 percobaan per email/akun+IP via RateLimiter; habis batas → token dihapus, wajib minta kode baru. Route throttle ditambah: verify-otp.post 10/m, resend-otp 5/m, reset-password 10/m, cancel-password-reset 10/m, account-email 10/m, verify-account-email 15/m, resend-account-verification 5/m, cancel-account-verification 10/m.
- Kunci `login_requires_admin_unlock`: awalnya auto-expire 24 jam — DIREVISI (lihat blok revisi di bawah): flag tidak lagi memblokir, hanya tanda tinjauan; pembersihan >24 jam tetap berlaku.
- Rahasia keluar dari log: AsyncMailer fallback OTP/PasswordResetLink dan SendOtpMailCommand tidak lagi menuliskan kode OTP/URL bertoken.
- SQL (cakupan yang DIPERIKSA — bukan klaim aman menyeluruh): semua pemakaian whereRaw/orderByRaw/selectRaw/DB::raw di app/ ditinjau — nilai dinamis hanya lewat binding `?`/`IN (placeholders)`; filter role/status/login_lock via allowlist in_array; tidak ada orderBy/kolom dari input bebas. Payload SQL diuji pada form login di DB terisolasi (tidak bypass, tidak ada SQLSTATE bocor, data utuh). Query lain di luar jalur login/recovery/pencarian belum diaudit satu per satu.
- Kunci/flag pada login publik (revisi): `login_requires_admin_unlock` = tanda tinjauan admin, BUKAN pemblokir — blokir sementara hanya lewat throttle (IP 20/m, ID 10/m, 5 gagal→15 mnt) dan `login_locked_until`; penonaktifan permanen tetap is_active/approval (manual admin). Flag dibersihkan otomatis >24 jam; alasan rinci ada di log persetujuan/akun admin, bukan respons publik.
- Batas OTP diikat ke record token (bukan IP/email): ganti IP tidak melewati; token baru yang diterbitkan setelah token lama diserang tetap utuh — hanya record yang ditebak yang dihapus saat batas habis. Perbandingan kode pakai hash_equals.
- Test `tests/Feature/AuthSecurityTest` (10 test): pesan seragam 4 kasus, payload SQL (bypass/kebocoran SQLSTATE/perubahan data), manipulasi is_admin/role, sesi regenerate+logout invalidasi, throttle lintas-role pada ID sama, flag admin non-blokir + generik + bersih 24 jam, batas OTP tahan ganti-IP + penghapusan token tepat, token baru bertahan saat token lama diserang, resend-otp anti-enumerasi, 4 role URL langsung. Assertion 'role'→'nitk_nidn' di StudentPortalTest & RuanganBookingTest. Suite: 128/128 lulus.
- Catatan deploy (belum diubah): SESSION_SECURE_COOKIE=env (set true saat HTTPS), http_only=true, same_site=lax; APP_DEBUG pastikan false; recaller/remember_until terenkripsi middleware.

### Redesign shell + beranda mahasiswa SmartBrail (2026-11-12 lanjutan)
- `layouts/student.blade.php`: brand SmartBrail (favicon + mark SVG baru `public/branding/smartbrail-mark.svg`), nav sesuai IA — Beranda, Absensi (riwayat), Ruangan, Kalender, Tim PBL, grup Laporan & Bantuan; dropdown akun Profil/Keamanan/Keluar; pencarian menu topbar fungsional (JS + data JSON + CSS dasar `sp-menusearch` ditambah — sebelumnya markup tanpa style posisi); versi aset di-bump `?v=20261112a`.
- `student/dashboard.blade.php` ditulis ulang: sapaan + CTA Ajukan Ruangan (foto `campus-polybatam.jpg` aset resmi sebagai latar overlay), Absensi hari ini + Ruangan berikutnya (dibedakan "Disetujui" vs "Izin berlaku"; catatan jujur bukan bukti pemakaian), Perlu Perhatian (tawaran pengganti + keputusan ≤7 hari — disembunyikan bila kosong), Kalender ringkas + panel tim kecil, Ringkasan Absensi default "Bulan ini" + pilihan periode, Aktivitas terbaru; status sidik jari jujur tetap ditampilkan.
- `StudentPortalController::ruanganDashboardData()` — 2 query total (pengajuan scoped tim sendiri/pengaju + izin berlaku), tanpa query per tanggal; `ruanganCalendarEvents()` dipakai beranda, `calendarMonth` JSON, dan `calendarPage`.
- `buildMonthCalendar()` menambah `ruangan` per hari; partial kalender punya filter chip Semua/Absensi/Ruangan + penanda `R` (bukan hanya warna); `student-shell.js` menangani filter, tag R, detail ruangan, semua nilai server via `esc()`.
- CSS: sidebar navy via var `--sp-sidebar` + aturan scoped (bukan tumpukan override), komponen `sb-hero`, `sb-next`, `sp-attn`, `sb-team`, `sp-cal__filter/chip`, `sp-cal__tag--room`, `sp-menusearch` dasar; responsif HP.
- Test: `StudentPortalTest` + `RuanganBookingTest` 45/45, suite penuh 128/128 (PHPUnit PHP 8.3); 2 ekspektasi lama disesuaikan (label "Ringkasan Absensi", panel Tim PBL kini tampil dengan scope tim sendiri).
- Belum diverifikasi: visual browser desktop/HP untuk halaman terautentikasi (perlu login mahasiswa nyata); preview hanya menjangkau halaman publik.

### Halaman pengajuan ruangan mahasiswa (2026-11-12 lanjutan)
- `student/ruangan.blade.php` ditulis ulang mengikuti komposisi acuan: hero kampus resmi, kolom kiri kartu ruangan (radio + chip aturan nyata: hari/jam/durasi/H+N) + pemilih waktu (tanggal, chip durasi dari `durasi_opsi` backend — bukan hard-code 1–4 jam, grid slot tersedia/dipesan/petugas/lewat), kolom kanan form dengan stepper Detail→Konfirmasi→Selesai. Setelah kirim: ringkasan + "Menunggu keputusan Laboran" (bukan disetujui). Riwayat memakai timeline Diajukan→Keputusan→Izin dengan label izin Belum mulai/Berlaku s.d./Berakhir/Dicabut.
- `RuanganBookingService::ketersediaanHarian()` — sumber tunggal slot: validitas tanggal (lewat/H+N/hari/nonaktif), penghalang = disetujui+menunggu_penerimaan + jadwal petugas, tanpa identitas pemesan. Slot per jam di dalam jam_buka–tutup, status menghitung durasi penuh.
- Endpoint read-only `GET /mahasiswa/ruangan/ketersediaan` (role mahasiswa, throttle 30/m) untuk sinkron tanpa reload. Konflik tetap dicek ulang backend saat submit (`validateSlot`) dan persetujuan (`assertTidakKonflik` atomik).
- JS `student-shell.js`: satu sumber nilai = input form; kartu ruangan↔select, picker↔input tanggal, chip durasi, klik slot mengisi jam, edit manual jam menghitung ulang durasi, stepper + review + submit-once.
- Sidebar: Ruangan jadi grup bersubmenu (Ajukan Ruangan / Riwayat Pengajuan ?tab=riwayat); logo resmi `branding/smartbrail-logo.jpg` dipakai di brand; versi aset `?v=20261112b`.
- Bug diperbaiki: closure `fn` by-value di `ketersediaanHarian` menjatuhkan penghalang booking — diubah ke closure `use (&$terhalang)`.
- Test: `RuanganBookingTest` 28/28 (+4: otorisasi+tanpa identitas, slot dipesan/petugas/durasi, penolakan aturan tanggal, render stepper+terkirim+timeline). Suite penuh 132/132 (PHP 8.3).
- Belum diverifikasi visual browser pada halaman terautentikasi.

### Empat halaman mahasiswa tambahan (2026-11-12 lanjutan)
- Komponen bersama baru di `student-shell.css`: `.sp-pagehead` (foto `campus-polybatam.jpg` aset resmi sebagai latar area judul yang memudar ke latar halaman, light/dark), `.sp-sidebar__deco` (line-art gedung + gelombang di pojok kiri-bawah sidebar navy, `pointer-events:none`, ikut rail/drawer), plus komponen `sat-*`, `stp-*`, `spp-*`, `sgp-*`.
- `student/attendance.blade.php`: pagehead + kartu Periode Data + 4 statistik (Lengkap/Tidak Lengkap/Alfa/Catatan Waktu — link filter), filter status+bulan tetap, stripe warna status di kolom tanggal tabel & kartu HP, modal rincian + Lapor dipertahankan. **Tanpa tombol Unduh Rekap** — tidak ada route ekspor terotorisasi untuk mahasiswa. Data/penilaian historis tidak dihitung ulang.
- `student/team.blade.php` ditulis ulang: kartu proyek (logo resmi, kode, peran saya, shift, manpro/co-manpro, jumlah anggota), grid/list anggota + pencarian lokal, foto profil atau inisial, tanpa tab Tugas/Dokumen yang belum berfungsi; tanpa tim → arahan jelas.
- `student/profile.blade.php`: header foto+nama di atas pagehead (badge status/email/HP jujur), Identitas Akademik baca-saja terpisah dari Kontak & Pemulihan yang editable, kartu Status Biometrik membedakan ID dialokasikan vs template terekam di server. Foto profil tetap via `profiles.photo.update/destroy`.
- `student/change-password.blade.php`: panel ilustrasi keamanan kiri + form kanan (HP: form diutamakan), tab "Gunakan Password Saat Ini" / "Lupa Password via Email" (mengarah ke `mahasiswa.password.reset-link`), toggle tampil/sembunyi bergaya ikon, indikator kekuatan + checklist sesuai `StrongPassword`, indikator kecocokan konfirmasi, submit-once. Mode `must_change_password` tetap sederhana tanpa password lama.
- Test: suite penuh 132/132 (PHPUnit PHP 8.3). Belum diverifikasi visual browser pada halaman terautentikasi.

### Koreksi paket pengajuan ruangan mahasiswa (2026-11-12, umpan balik visual)
- **Bug waktu diperbaiki**: `type="time"` (locale AM/PM, nilai basi) diganti input teks `HH:MM` 24-jam; `jam_selesai` readonly dihitung dari `jam_mulai + durasi` — tidak bisa menyeberang hari (≥24:00 dikosongkan + pesan error). Satu sumber nilai; `refreshValidity()` menonaktifkan "Lanjutkan" bila tanggal tak tersedia atau slot belum valid; `revalidatePick()` menghapus pilihan basi saat slot dimuat ulang; satu pesan error dekat field (`srTimeError`), tanpa banner ganda.
- **Kartu ruangan**: thumbnail foto (`ruangan.foto`, fallback ikon), kapasitas, fasilitas, chip aturan; badge "Tersedia"/"Tutup pada tanggal pilihan" mengikuti tanggal terpilih (`data-hari` untuk ruangan lain, slot nyata untuk ruangan terpilih).
- **Peserta**: chip terpilih + tombol "Tambah Peserta" membuka modal `srPesertaModal` — pencarian, checkbox anggota tim saja, hitung terpilih, "Pilih semua" dinonaktifkan bila melebihi batas; batas dari `aturan.max_peserta` (fallback kolom `kapasitas`). Validasi backend `ajukan()` menolak peserta > batas dan non-anggota.
- **Profil ruangan di Admin**: `AdminRuanganController::store/update` menerima `kapasitas`, `fasilitas` (teks→array JSON), `foto` (upload tervalidasi jpg/png/webp ≤2MB → `public/uploads/ruangan/`, hapus file lama), `max_peserta`; form admin diperbarui (enctype + field baru + preview foto/fasilitas).
- **Migration `2026_11_12_130000_add_ruangan_profil_fields.php` DIBUAT, BELUM dijalankan** di DB operasional — menambah `ruangan.kapasitas`, `fasilitas` (JSON), `foto`. Berjalan otomatis di test SQLite.
- `sp-pagehead` foto kampus memudar dipakai halaman pengajuan; `.sb-hero` beranda ikut diubah ke overlay terang (light) / gelap memudar (dark). Aset `?v=20261112c`.
- Test: suite penuh 132/132. Visual browser terautentikasi tetap belum diverifikasi.

### Perbaikan shell mahasiswa — sidebar statis, konten scroll (2026-11-12)
- Struktur scroll diubah di sumber: `.sp-shell` kini `height:100dvh; overflow:hidden` (bukan `min-height:100vh` + body scroll); `.sp-sidebar` tidak lagi `position:sticky`; `.sp-workspace` `overflow:hidden`; `.sp-topbar` `position:relative` (bukan sticky — tetap di tempat karena workspace tak scroll); `.sp-main` `overflow-y:auto` = satu-satunya scroll container kanan.
- `.sp-nav` tetap scroll internal dengan `padding-bottom:140px` (dekorasi 132px tidak menutup item terakhir); mode rail `overflow-y:auto` + `title` native pada link nav karena tooltip `::after` terpotong clipping scroll.
- Drawer HP tidak berubah (`position:fixed` + overlay + lock `body.overflow` hanya saat drawer/modal buka); tidak ada JS `window.scroll`/scrollTo — hanya `scrollTop` elemen (chat) dan `scrollIntoView` pencarian menu, keduanya aman di container baru.
- Beranda: `.sp-grid--top/--mid/--bottom` `align-items:start` — panel "Ruangan berikutnya"/"Tim PBL" tak lagi stretch mengikuti tinggi kalender.
- Bug data: kartu Tim PBL menghitung 0 anggota karena `teamMembers()->count()` hanya membaca `pbl_team_members`; sumber digabung via `RuanganBookingService::anggotaTim()` (pbl_team_members + legacy `users.tim`) — kini lewat payload `anggota_count` di `ruanganDashboardData()`, bukan query view.
- Sisa teks "SIMALAS" di halaman mahasiswa diganti "Smart BRAIL" (guide, notifikasi, eyebrow+item login).
- Light mode = baseline (`:root` light, dark opt-in `data-theme`); preferensi pengguna tetap tersimpan.
- Versi aset `?v=20261112d`.
- Test: suite penuh 132/132 (PHPUnit, PHP 8.3 `/c/tools/php83` — PATH `php` default 8.2 tanpa sqlite gagal menjalankan suite). Visual browser belum diverifikasi (halaman terautentikasi butuh login nyata).

### Koreksi scrollbar ganda + light mode + foto header (2026-11-12)
- Keputusan stack: React/Vite hanya dipakai situs publik (`resources/js/public-site/`); area mahasiswa tetap Blade. Migrasi React penuh memerlukan API JSON + transisi paralel — ditunda sebagai keputusan terpisah, bukan diselipkan di paket polish.
- `body.sp-body` kini `height:100dvh; overflow:hidden` — meniadakan scrollbar luar; `.sp-main` satu-satunya scrollbar konten (drawer/modal lock `body.overflow` tetap konsisten).
- Palet light disesuaikan referensi: `--sp-bg:#e9f1fb` (putih kebiruan), border `--sp-border:#d8e4f2`, teks navy `--sp-text:#0e1e3c`, primer `--sp-primary:#1d6ff2`.
- `.sp-pagehead` & `.sb-hero` bukan lagi kartu berborder: margin negatif bleed ke tepi `.sp-main`, foto kampus jadi latar area atas halaman yang memudar ke `--sp-bg` (bukan banner kotak); dark mode overlay gelap memudar setara. Responsif 640px menyesuaikan margin.
- Versi aset `?v=20261112e`. CSS-only round — tidak ada perubahan perilaku; suite terakhir 132/132.
- Belum diverifikasi visual: light mode desktop/HP di halaman terautentikasi.

### Preview terisolasi mahasiswa + koreksi nav (2026-11-12)
- Route dev `GET /dev/preview/mahasiswa/{page}` (gate `app()->isLocal()` → 404 di produksi): merender view aplikasi yang sama via controller asli dengan database fixture SQLite terpisah `storage/app/student-preview.sqlite` (migrasi + seed fixture saat pertama dibuka). Bukan bypass login — `Auth::setUser` in-request saja, tanpa session, tanpa menyentuh DB operasional. Halaman: beranda, absensi, kalender, tim, profil, ganti-password, pengajuan, riwayat.
- Fixture: viewer PREVIEW001 + 11 anggota tim + dosen + laboran, PBL-RE-044, 2 ruangan (aturan+kapasitas+fasilitas), jadwal petugas, 7 pengajuan beragam status + izin aktif, sesi pagi + kebijakan mingguan efektif, ~5 minggu log absensi, 1 pengumuman.
- Nav sidebar diurutkan sesuai referensi: Beranda, Absensi, Ruangan(grup), Kalender, Tim PBL, Laporan & Bantuan(grup) — item/grup diekstrak ke `layouts/partials/student-nav-item|group.blade.php`.
- Batas preview: fetch AJAX ke route terautentikasi nyata (slot ketersediaan `mahasiswa.ruangan.ketersediaan`, kalender bulan) tidak dimuat di preview (tetap menuju login) — grid slot tampil keadaan awal; modal peserta/kalender bulan berjalan (data server-side fixture).
- Test: StudentPortalTest 21/21. Visual menunggu tangkapan user dari preview browser.

### Rebuild frontend mahasiswa → React + TypeScript via Inertia (2026-11-12)
- Keputusan final pemilik: seluruh frontend mahasiswa dibangun ulang React+TS. Integrasi dipilih **Inertia.js** (session+CSRF+permission tetap Laravel; controller mengembalikan `Inertia::render`, bukan API JSON baru). Paket: `inertiajs/inertia-laravel` 3.7, `tightenco/ziggy` 2.6, `ziggy-js` npm.
- Struktur baru: `resources/js/student/` — `main.tsx` (createInertiaApp + bind `window.route`→ziggy), `app.css` (token tema light/dark navy-cyan Tailwind 4), `types.ts`, `lib/` (api/format/utils), `components/ui/*` (button, card, badge, input/select/textarea, dialog, dropdown, checkbox, skeleton, avatar), `components/layout/` (AppShell + nav.ts), `components/{PageHead,MiniCalendar,skeletons}.tsx`, `pages/Student/{Dashboard,Attendance,Calendar,Team,Profile,ChangePassword,RoomRequest}.tsx`.
- `app.blade.php` = root Inertia (@routes + @vite student/main.tsx + pre-hydrate tema). `HandleInertiaRequests` shares `auth.user`, `flash{success,error,status,terkirim}`, `navNotificationState` (via `StudentPortalController::sharedNotificationState` publik).
- Route mahasiswa kini Inertia: beranda, absensi, kalender, tim, profil, ganti-password, ruangan(+tab riwayat). **Transisional masih Blade**: `mahasiswa.panduan`, `mahasiswa.notifikasi`, `mahasiswa.saran` (nav menandai `external` → full page load). Target akhir tetap satu frontend React.
- Shell React: sidebar navy w/ logo resmi + dekorasi line-art/gelombang kiri-bawah, rail collapse sinkron, drawer HP + overlay, topbar (judul, pencarian menu, notif, tema, akun), single scroll `.sb-scroll` kanan, skeleton per-halaman saat navigasi (delay 150ms anti-kilat, shell tetap tampil), `prefers-reduced-motion` dihormati.
- Fitur halaman: dashboard (sapaan, absensi hari ini, ruangan berikutnya, perhatian, mini-kalender, ringkasan, aktivitas), absensi (kartu statistik=filter, filter bar+bulan, tabel desktop/kartu HP, modal rincian+link lapor), kalender (nav bulan via Inertia `?bulan=`, detail hari, legenda), tim (kartu proyek, grid/list+pencarian anggota, state kosong), profil (foto upload/hapus, akademik read-only, kontak+OTP email, biometrik), ganti password (2 kolom, tab password-saat-ini vs reset-email, meter+checklist sesuai `StrongPassword`, mode wajib), pengajuan (kartu ruangan foto/kapasitas/fasilitas, tanggal+durasi, grid slot dari `ketersediaan` dgn skeleton/error-retry, peserta via modal cari+checkbox+batas max_peserta, form→Inertia post, flash terkirim) dan riwayat (list+detail modal, terima-pengganti, batal).
- Data-barang-bukti: `anggota` dimap `{id,nama_lengkap,nitk_nidn,foto}` (accessor `profile_photo_url`); prop `projects` dibuang dari halaman Tim (kebocoran nama proyek lain ke JSON). `RuanganPengajuan` appends `status_efektif`.
- Test: `assertSee` Blade diganti `assertInertia` pada StudentPortalTest & RuanganBookingTest; **suite penuh 132/132** PHP 8.3. `tsc --noEmit` bersih; `npm run build` sukses (chunk per-halaman).
- Preview dev `/dev/preview/mahasiswa/*` kini merender Inertia (fixture SQLite) — semua 8 halaman 200 + komponen benar. **Verifikasi visual browser (screenshot, jaringan lambat, drawer HP) belum dilakukan** — menunggu tangkapan user dari preview `http://127.0.0.1:8000/dev/preview/mahasiswa/beranda`.
- Belum selesai: port guide/notifikasi/saran ke React; skeleton parsial untuk pagination/filter lebih halus; verifikasi visual LIGHT/DARK desktop+HP.

### Perbaikan blank-page Apache (2026-11-11/12)
- **Akar masalah (3 bug bertumpuk)** di `http://localhost/Simalas/public/mahasiswa/beranda`:
  1. `vite.config.js` `base` absolut `/build/` → dynamic chunk diambil dari `http://localhost/build/...` (root) = 404 di deployment subfolder. Fix: `base: ''` (relatif `import.meta.url`) → chunk resolve di `/Simalas/public/build/assets/...`. Juga bekerja di `php artisan serve`.
  2. `resolvePageComponent` mengembalikan fungsi `() => import()` TANPA dipanggil → React menerima async function sebagai komponen → **Minified React error #482** → layar kosong. Fix: resolver async yang memanggil loader + unwrap `.default`.
  3. `HandleInertiaRequests::share` membaca `$request->user()` secara eager → `auth.user: null` untuk request yang user-resolvernya dipasang controller (route preview) → `nama_lengkap` of null. Fix: prop `auth.user`/`navNotificationState` dibuat lazy closure.
- Aset frontend hardcoded `/branding/*` & `/images/*` diperbaiki: helper `lib/asset.ts` (base dari `Ziggy.url`) + CSS var `--sb-campus-img` diset inline di `app.blade.php` via `asset()`. `@routes` menghasilkan `const Ziggy` (bukan `window.Ziggy`) — helper membaca keduanya.
- `RootErrorBoundary` di `main.tsx` memastikan kegagalan render/resolver menampilkan pesan + tombol muat ulang, bukan layar kosong.
- Verifikasi CDP headless di Apache nyata: 8/8 halaman preview render tanpa console error; screenshot desktop 1366px + HP 390px menunjukkan sidebar/topbar/konten/pagehead tampil. Satu-satunya error tersisa = 401 endpoint `ketersediaan` di preview (endpoint terautentikasi — keterbatasan fixture tanpa session, bukan bug; ditangani error-state "Coba Lagi").
- Test: `tsc --noEmit` bersih, `npm run build` sukses, PHPUnit 49/49 (portal+ruangan). Refresh langsung per-URL terverifikasi via `?v=` bust + cache-disabled di CDP.

### Riwayat Laporan → React/Inertia (2026-11-11)
- `GET /mahasiswa/saran` (`StudentPortalController::suggestions`) kini `Inertia::render('Student/ReportHistory')` menggantikan Blade `student.suggestions` (form pembuatan laporan tidak di-port — halaman murni riwayat baca-saja sesuai keputusan pemilik).
- Data tetap model `Suggestion` (sumber chatbot/bantuan); query selalu `where('user_id', auth)`. `?selected={id}` dibatasi `whereKey` pada scope pemilik → fallback item pertama bila bukan milik user; tidak ada data lintas user.
- Server-side: pencarian `q` (subjek/pesan/id), filter `status` (baru/dibaca/ditanggapi), `kategori` (prefix subjek `[Kendala Absensi …]`/`[Akun]`/lainnya → label Kendala Absensi/Bantuan Akun/Saran & Masukan), `tanggal`, pagination `paginate(6)`, statistik ringkasan (total/menunggu/diproses/selesai) dari data nyata.
- Mapping `mapSuggestionRow()` mengekstrak kategori + tanggal absensi dari prefix subjek; timeline tindak lanjut hanya memakai fakta: `created_at`, status, `admin_reply`+`admin_replied_at` (bila ada) — tidak ada progres fiktif.
- React: `pages/Student/ReportHistory.tsx` (hero `.sb-lap-hero` foto kampus, 4 kartu statistik, daftar+cari+filter+pagination kiri, DetailPanel kanan / Dialog HP, skeleton `ReportHistorySkeleton` di `skeletons.tsx`, empty/no-result/error-retry states, `min-w-0` pada grid item mencegah overflow mobile). Nav `Riwayat Laporan` kini internal (bukan `external`), aktif di `/mahasiswa/saran`. POST `baca` (tandai balasan dibaca) tetap berfungsi.
- Preview fixture: `StudentPreviewController` route `laporan` + seed 7 `Suggestion` beragam status/kategori (timestamps di-set via save eksplisit — `created_at`/`updated_at` tidak fillable).
- Test: `test_saran_memiliki_referensi_status_dan_balasan_hanya_untuk_pemilik` ditulis ulang ke `assertInertia` (scoping, `?selected` lintas-pemilik, pencarian, balasan admin) — 21/21 lulus. `tsc` bersih, `npm run build` sukses; diverifikasi CDP headless desktop 1366/HP 390 (pilih detail, dialog mobile, empty state, dark mode, tanpa console error).

### Halaman Login → React/Inertia `Auth/Login` (2026-11-12)
- `GET /masuk` dan `GET /forgot-password` (`AuthController::showAuthPage`) kini merender `Inertia::render('Auth/Login')` menggantikan Blade `auth` untuk panel login/lupa-password; panel OTP/reset/verify/aktivasi tetap Blade. `resources/views/auth.blade.php` tidak lagi dirender route utama login.
- Props: `panel` ('forgot'|null), `selectedRole`, `old` (nitk_nidn/role/remember/identifier), `csrf`, `recaptchaSiteKey` (hanya dikirim bila terkonfigurasi).
- Dropdown "Masuk Sebagai" menampilkan 3 opsi UI: `mahasiswa`, `dosen`, `admin_laboran` (gabungan Admin/Laboran). `AuthController::login` menerima `admin_laboran` dan memetakannya lewat `selectedRoleMatches()` — verifikasi tetap pada `$user->role` asli (`admin`/`laboran`); opsi UI tidak memberi hak akses.
- Form login masih POST klasik (`route('login.post')`) dengan `_token` hidden — kontrak `nitk_nidn`/`password`/`role`/`remember` tidak berubah; redirect per role, `must_change_password`, aktivasi, approval, dan throttling `LoginSecurityService` utuh. Forgot-password POST ke `forgot-password.post` (`identifier` + honeypot `website` + reCAPTCHA lazy saat panel dibuka).
- React: `resources/js/student/pages/Auth/Login.tsx` — komposisi dua sisi sesuai referensi: hero foto kampus + overlay navy/cyan + gelombang, logo SmartBrail, headline, 4 kartu fitur; kartu form putih rounded, input berikon, tombol gradient cyan–biru dengan loading, show/hide password, "Ingat saya", "Lupa password?" (toggle panel inline + route `/forgot-password`), kotak info role-aware + kotak bantuan. CSS `.sb-auth-*` di `app.css`; mobile satu kolom (form fokus) tanpa scroll horizontal.
- `app.blade.php` menjadi root Inertia tunggal untuk seluruh halaman React (login + portal mahasiswa); `@routes` + Vite `base: ''` (chunk relatif) tetap — halaman berfungsi di subfolder Apache `/Simalas/public` dari production build.
- Test: `StudentPortalTest` — assertion Blade lama diperbarui ke `assertInertia` (`component('Auth/Login')`, `selectedRole`) + uji `admin_laboran` (sukses laboran → `/admin/dashboard`, ditolak untuk dosen dengan pesan generik) — **21/21, 447 assertions, lulus**. `AuthSecurityTest`/`MesinApiAuthTest` lulus; `tsc` bersih, `npm run build` sukses.
- Verifikasi CDP headless pada `http://localhost/Simalas/public/masuk` (Apache, production build): screenshot desktop 1366 + HP 390 sesuai referensi; login gagal menampilkan alert "Tidak dapat masuk. Periksa data login Anda." dengan tombol kembali normal (loading berhenti); show/hide password mengubah `type` password↔text; dropdown role mengubah label NIM / NITK / NIDN / Username atau NITK + info box; fokus keyboard OK; `scrollWidth == innerWidth` (tanpa overflow HP); panel lupa password menampilkan reCAPTCHA nyata.
- Belum diverifikasi end-to-end: login sukses tiap role di database operasional (memerlukan kredensial nyata; alur redirect diuji via `StudentPortalTest` dengan akun fixture terisolasi). Blade `auth.blade.php` lama kini orphan untuk panel login/forgot — kandidat pembersihan.

### Widget Asisten SmartBrail (chatbot) → React/Inertia (2026-11-12)
- Widget chatbot mahasiswa dipulihkan di frontend React: `resources/js/student/components/AssistantWidget.tsx` dipasang di `AppShell` — panel dock kanan full-height (desktop, konten menyesuaikan — tidak menutupi), fullscreen `z-50` di HP, launcher lingkaran mengambang saat tertutup, tombol perbesar/kembalikan + tutup di header navy dengan avatar robot + badge Beta.
- Sapaan memakai nama depan user login (`auth.user.nama_lengkap`). Bubble asisten terang, bubble user biru, timestamp tiap pesan, indikator mengetik saat menunggu balasan, `aria-live` pada log.
- Backend yang dipakai kembali (bukan sistem baru): `POST /mahasiswa/bantuan/ai` (`helpAi` — responder lokal deterministik + Gemini bila terkonfigurasi, mengembalikan `suggest_report`+`report_data`), `POST /mahasiswa/saran` (`storeSuggestion` JSON `ticket_ref`), `GET /mahasiswa/ruangan/ketersediaan`, `POST /mahasiswa/ruangan` (`storeMahasiswa` kini menjawab JSON bila `expectsJson` — validasi & `RuanganBookingService::ajukan` tidak berubah).
- Endpoint baru `GET /mahasiswa/bantuan/konteks` (`helpContext`, `mahasiswa.help.context`, throttle 30/menit): jadwal efektif hari ini, tim+anggota (`RuanganBookingService`), daftar ruangan aktif+aturan, 5 pengajuan terakhir tim, 5 laporan terakhir — semuanya discope `user_id`/tim user login.
- Struktur menu sesuai referensi: 6 kartu grid (Absensi, Tim PBL, Ruangan, Panduan, Laporan & Kendala, Lainnya) → submenu relevan dengan data nyata (jadwal hari ini, info tim, status pengajuan, riwayat laporan) atau tautan Inertia ke halaman terkait; tiap level menyediakan "Kembali" + "Menu utama".
- Transaksi terkonfirmasi: laporan (form kategori/tanggal/subjek/uraian → ringkasan → Konfirmasi & Kirim/Ubah/Batal → `ticket_ref` + tautan Riwayat Laporan) dan pengajuan ruangan (pilih ruangan → tanggal → durasi → slot nyata dari `ketersediaan` → peserta tim → tujuan → konfirmasi → POST JSON → status + tautan). Tidak ada penyimpanan tanpa klik konfirmasi eksplisit; tombol double-submit dilindungi `sending`/`done` + throttle backend.
- Keadaan chat tersimpan dalam state komponen — panel tutup/buka dan navigasi internal Inertia mempertahankan percakapan; reset otomatis bila `auth.user.id` berganti (pengaman antar-akun). Tidak ada persistence lintas reload (riwayat tidak dibagikan antar akun).
- Error: kartu error + "Coba lagi" untuk konteks/chat/ketersediaan/pengajuan; pesan & draft input tidak hilang saat gagal.
- Test: `test_konteks_asisten_dan_pengajuan_ruangan_json_terisolasi` di `StudentPortalTest` (sukses JSON, tanpa-tim 422, scoping pengajuan/laporan/anggota antar tim) — suite gabungan `StudentPortalTest`+`RuanganBookingTest`+`AuthSecurityTest` **60/60 lulus, 728 assertions**. `tsc` bersih, `npm run build` sukses.
- Verifikasi CDP headless preview (`/dev/preview/mahasiswa/beranda`, produksi build via Apache identik): launcher→panel, grid 6 menu, submenu Ruangan sesuai referensi, alur form→konfirmasi laporan, pesan bebas→typing→error+retry (endpoint 401 pada fixture preview — wajar, diuji PHPUnit untuk jalur sukses), mobile 390px fullscreen tanpa overflow, expand+tutup→launcher.
- Belum diverifikasi end-to-end di akun operasional: balasan `helpAi`, muatan `konteks`, slot ketersediaan, dan simpan laporan/pengajuan lewat browser nyata (butuh session mahasiswa asli — alur sudah diuji PHPUnit; di preview endpoint mengembalikan 401 sehingga yang tervalidasi visual adalah jalur error/retry).

### Pemilihan slot jam pengajuan ruangan → chips + kartu rentang (2026-11-12)
- `RoomRequest.tsx` (tab Ajukan): dropdown `Durasi` dihapus → chips "Durasi Pemakaian" (`formatDurasiChip`: 30 mnt, 1 Jam, 1½ Jam, 2 Jam, …) dari `durasi_opsi` backend; grid slot kini menampilkan rentang penuh `s.mulai – s.selesai` + status (Tersedia/Pilihan Anda/Dipesan {nama tim}/Jadwal petugas/Sudah lewat), label "Pilih Waktu (durasi X)", legenda 3 warna, tetap digrupkan per sesi + skeleton/error-retry sama.
- Pemilihan menyimpan `jam_mulai`/`jam_selesai` langsung dari slot backend (bukan `tambahMenit` lokal); ringkasan "Waktu" menampilkan `mulai – selesai WIB` dari `slotDipilih`. Ganti ruangan/tanggal/durasi → `setSlot(null)` + muat ulang; slot tak `tersedia` selalu `disabled`. Grid `grid-cols-2 sm:3 md:4` (teks rentang butuh ~105px — `grid-cols-3` di 390px memaksa layout viewport 758px/overflow).
- Backend tidak berubah (`ketersediaanMahasiswa` + `RuanganBookingService::ajukan` memvalidasi ulang saat kirim). Alur chatbot sudah menampilkan `mulai–selesai`.
- Preview ketersediaan nyata: route dev `GET /dev/preview/mahasiswa/api/ketersediaan` (`StudentPreviewController::ketersediaan`, fixture SQLite + service nyata); `HandleInertiaRequests` membagikan `preview.ketersediaan_url` hanya pada route `dev.preview.mahasiswa`, dipakai `RoomRequest` sebagai base URL bila ada — produksi tetap `route('mahasiswa.ruangan.ketersediaan')`.
- Verifikasi CDP di Apache `localhost/Simalas/public/dev/preview/mahasiswa/pengajuan`: chip durasi mengganti set slot (1 Jam→`07:00–08:00`…, 2 Jam→`07:00–09:00`…), klik slot hijau→"Pilihan Anda"+ringkasan `11:00 – 12:00 WIB`, slot `dipesan` menampilkan nama tim pemesan, lewat/petugas terkunci, HP 390px tanpa overflow. Screenshot: `slot_1_desktop.png`, `slot_2_selected.png`, `slot_3_mobile.png`, `slot_4_final.png`.
- Test: `tsc` bersih, `npm run build` sukses, `RuanganBookingTest` 28/28 + `StudentPortalTest` 22/22 (php84 + `extension=pdo_sqlite` — `artisan test` spawn subproses tanpa flag; jalankan `vendor/bin/phpunit` langsung).

### Slot jam tanpa kontrol durasi (2026-11-12, koreksi entri di atas)
- Permintaan pemilik diklarifikasi: durasi **dihilangkan total dari UI**, bukan diubah jadi chips. `RoomRequest.tsx` kini: pilih ruangan → tanggal → grid "Pilih Jam" langsung (`07:00 – 08:00`, …). Konstanta internal `SLOT_MENIT = 60` hanya untuk query `ketersediaan` — backend meng-clamp ke `max_duration_minutes` ruangan yang lebih kecil (slot tetap ditampilkan apa adanya sebagai rentang nyata). `durasi_opsi`, `formatDurasiChip`, state `durasi` dihapus; label "Pilih Waktu (durasi X)" → "Pilih Jam".
- Chatbot (`AssistantWidget.tsx`): langkah "pilih durasi" dihapus — alur menjadi ruangan → tanggal → slot jam (Langkah 3) → peserta (4) → tujuan (5) → konfirmasi. `RoomDraft.durasi` tetap 60 internal untuk query.
- Konfigurasi operasional diverifikasi read-only: `Ruang Meeting` beraturan `{"hari":[1,2],"jam_buka":"07:00","jam_tutup":"22:00","max_advance_days":1,"max_duration_minutes":60}` — menjelaskan (a) "Ruangan tidak tersedia pada hari tersebut" di akun pemilik (Kamis bukan Senin/Selasa — aturan, bukan bug), (b) opsi durasi lama hanya 30 mnt/1 jam (turunan `max_duration_minutes:60`). Tidak ada aturan yang menghalangi slot 1 jam.
- Verifikasi CDP di Apache preview: tanggal→"Pilih Jam" tanpa kontrol durasi, klik `12:00 – 13:00`→"Pilihan Anda"+ringkasan `12:00 – 13:00 WIB`, status lewat/dipesan/tersedia nyata, HP 390px dua kolom tanpa overflow. Screenshot `slot_5_no_durasi.png` + `slot_6_mobile.png`. Test 50/50 (RuanganBooking 28 + StudentPortal 22), `tsc`+build bersih.

### Peminjaman multi-slot ≤5 jam + alasan ketersediaan terdiferensiasi (2026-11-12)
- `ketersediaanHarian`: alasan kini spesifik — nonaktif ("ditutup sementara"), tanggal lewat, di luar batas (menyebut tanggal maks), hari tidak beroperasi ("…pada Kamis, 12 November 2026…"). Field baru: `habis_alasan` (semua dipesan / semua lewat / campuran), `hari_label` (`Senin–Jumat`, `Senin & Selasa`), `tanggal_tersedia_berikutnya` (scan ≤14 hari dari `hari` aturan). `durasi_opsi` dihapus dari respons (tak dipakai lagi).
- `validateSlot`: batas efektif `min(300, max_duration_minutes)` menit (kebijakan maks 5 jam di atas batas ruangan); submit kini juga menolak overlap dengan pengajuan `disetujui`/`menunggu_penerimaan` (sebelumnya hanya saat ACC). `ajukan` dibungkus `DB::transaction` + `lockRowsAktif` → submit bersamaan pada ruangan+tanggal sama terserialisasi. `durasi_opsi` tidak pernah divalidasi — hanya `max_duration_minutes`.
- `RoomRequest.tsx`: seleksi rentang — klik slot awal lalu akhir; semua slot di antaranya harus `tersedia` dan total ≤ `min(5, max_duration_minutes/60)` jam; klik dalam rentang memangkas ke ujung itu, klik slot tunggal menghapus; slot `dipesan`/`petugas`/`lewat` tetap terkunci. Ringkasan `mulai – selesai WIB · N jam`; hint "maks. X jam berurutan". Pesan `!boleh` menampilkan alasan + hari/jam operasional + tanggal berikutnya; `habis_alasan` dipakai untuk banner penuh.
- Chatbot: alur mulai→sampai — pilih "Mulai HH:MM" lalu kartu ujung rentang `mulai – selesai (N jam)` dari slot `tersedia` berurutan (maks `min(5, max/60)`); error memakai `habis_alasan`.
- Fixture preview: meeting `max_duration_minutes`→300, mekanikal `hari`→[1,2]. Screenshot: `slot_7_multi.png` (5 slot terpilih, "10:00 – 15:00 WIB · 5 jam"), `slot_8_tutup.png` (pesan tidak-beroperasi + tanggal berikutnya), `slot_9_mobile.png`.
- Test: `RuanganBookingTest` 31/31 (3 test baru: batas 5 jam walau konfig 480, penolakan bentrok saat submit + slot bersebelahan tetap bisa diajukan tim lain, `habis_alasan` dipesan/lewat + `hari_label`/`tanggal_tersedia_berikutnya`), `StudentPortalTest`+`AuthSecurityTest`+`MesinApiAuthTest` 36/36.
- DITERAPKAN atas konfirmasi pemilik: `Ruang Meeting` (id=1) `max_duration_minutes` 60→300 via tinker terarah — hasil `{"hari":[1,2],"jam_buka":"07:00","jam_tutup":"22:00","max_advance_days":1,"max_duration_minutes":300}`. `hari:[1,2]` tidak diubah — peminjaman tetap hanya Senin/Selasa, maks H+1.

### Restyle Profil + kunci kontak + perbaikan status sidik jari (2026-11-12)
- `Profile.tsx` ditulis ulang mengikuti referensi: tab pill (Informasi Profil / Kontak & Keamanan / Biometrik & Absensi / Preferensi), kartu Identitas Akademik, Kontak & Pemulihan, Keamanan Akun (link `mahasiswa.password.change`), Status Biometrik, Preferensi Tampilan (tema terang/gelap via `dataset.theme` + localStorage). Tab `profil` = grid 2 kolom penuh; tab lain memfilter kartu terkait.
- BUG status sidik jari: frontend membaca `user.finger_template` yang memang `$hidden` di model → selalu false → "belum rekam" walau backend enrolled. Diperbaiki memakai `auth.user.has_fingerprint` (shared prop dari `hasFingerprintEnrolled()`). Akun operasional Rayendra: `finger_id=2`, `enrolled=true` → kini tampil "Sidik Jari Terdaftar".
- Kontak terkunci: email `email_verified_at` terisi → read-only terkunci; `no_hp` tersimpan → read-only terkunci; catatan "ada kendala lapor laboran/admin". Backend `updateContact` menolak perubahan email terverifikasi dan perubahan/penghapusan no_hp tersimpan (session error per-field). Email belum terverifikasi tetap bisa diganti (OTP dikirim ulang). Field kosong tetap bisa diisi.
- Fixture preview viewer kini punya `finger_template` valid (encrypted) agar status terdaftar ter-render.
- Test: `StudentPortalTest` 23/23 (+1 test kunci kontak). Screenshot: `profil_4_ok.png` (desktop), `profil_5_tab_bio.png` (tab Biometrik), `profil_6_mobile.png` (390px).
- Catatan: PHP 8.4 CLI butuh `-d extension=...pdo_sqlite.dll/sqlite3.dll` untuk PHPUnit (pdo_sqlite tak dimuat di php.ini).

### Pemisahan 5 role login (2026-11-12)
- Dropdown "Masuk Sebagai" kini 5 opsi terpisah: **Admin, Laboran, Dosen, Mahasiswa, Pekerja/CS** — opsi gabungan `admin_laboran` dihapus dari UI, whitelist query `?role=`, dan validasi `in:` `AuthController::login`.
- `selectedRoleMatches` kini strict `$selectedRole === $userRole` — memilih "Admin" tidak memberi akses ke akun laboran/dosen/mahasiswa; nilai `admin_laboran` tidak lagi lolos validasi. Blade fallback `auth.blade.php` diselaraskan (5 opsi + hint CS).
- Kewenangan sudah terpisah di backend sebelumnya dan tidak berubah: area `/admin/*` via `role:laboran,admin` + `perm:menu,action` (PermissionRegistry per role) + `admin.only` (RequireAdmin → `isAdmin()`) untuk manajemen user/pengaturan; `/dosen/*` via role dosen; `/mahasiswa/*` role mahasiswa. Redirect: admin/laboran → `/admin/dashboard` (menu difilter perm), dosen → `/dosen/dashboard`, mahasiswa → `/mahasiswa/beranda`.
- **CS belum punya akun portal** — tidak ada user `role='cs'` di DB (petugas ruangan `RuanganPetugas` adalah identitas non-portal untuk mesin). Opsi "Pekerja/CS" ada di dropdown sesuai permintaan; backend menerima nilai `cs` tetapi `selectedRoleMatches` menolak semua akun non-cs → login CS tidak akan pernah berhasil sampai kewenangan/akun CS diputuskan pemilik (perlu keputusan: apakah CS punya portal).
- Test: `StudentPortalTest` 24/24 — uji baru strict-match (laboran+is_admin ditolak via opsi Admin; admin ditolak via opsi Laboran; `admin_laboran` ditolak validasi; `cs` ditolak semua akun) + `lima_role_login_redirect_dan_pembatasan_akses_route` (redirect per role, `/admin/users` 403 untuk laboran, mahasiswa/dosen/laboran 403 lintas route). `AuthSecurityTest` lulus. Screenshot: `login_5role.png`, `login_5role_cs.png`.
- KOREKSI pemilik (2026-11-12): Pekerja/CS BUKAN pengguna portal — identitas non-portal (`RuanganPetugas`, data petugas disinkron website→mesin). Opsi CS dihapus dari dropdown login React + Blade, whitelist `?role=`, dan validasi `in:` `AuthController::login`. Dropdown kini 4 role pengguna: Admin, Laboran, Dosen, Mahasiswa. Nilai `cs`/`admin_laboran` ditolak di validasi (`errors.role`). Pembedaan pengguna (users.role) vs non-pengguna (petugas mesin) dipertahankan.

### Redesign dashboard admin + sidebar navy + halaman data akun per-role (2026-11-12)
- `dashboards/admin.blade.php` ditulis ulang mengikuti referensi pemilik: hero navy ("Selamat datang, {nama}"), 5 kartu KPI (Total Pengguna / Sidik Jari Terdaftar / Ruangan Aktif / Mesin IoT / Pengajuan Pending), baris Tren Aktivitas IoT (multi-line) + Distribusi Pengguna (donut+legenda) + Status Sistem, lalu Live Feed Absensi + Pengajuan Ruangan Terbaru + Aksi Cepat. Chart tetap `SimalasCharts` (buildMultiLineChart/buildDonutChart).
- `AdminController`: `enroll_done` ditambahkan ke `$opsShortcuts` (di samping `enroll_belum`, satu koleksi `$mhsAktif`); `bmsDashboardData()` menambah `pengajuan_terbaru` (5 pengajuan terakhir, eager `ruangan`/`pengaju`) — tidak ada perubahan skema.
- Sidebar (`admin/_sidebar.blade.php`) disusun ulang bergaya referensi: seksi flat "Menu Admin / Operacional Lab / Data & Pengguna / Komunikasi / Laporan & Audit / Administrasi"; "Data & Pengguna" = grup expand (default open) berisi sub-item. Pemeriksaan `P::canCurrent(...)` dan `isAdmin()` tidak berubah — menu tetap difilter permission backend.
- Layout `dashboard.blade.php`: `--bg-sidebar` navy di tema terang & gelap (`#0c1f47` / `#0a1526`); judul seksi/toggle/kartu user disesuaikan warna agar terbaca di navy; brand "SmartBrail — Portal Administración"; ilustrasi line-art kampus ditambahkan di kaki sidebar (SVG inline).
- Halaman data akun per-role: route baru `GET /admin/users/{role}` (`whereIn:mahasiswa|dosen|laboran`, middleware `perm:users,view`+`admin.only` identik) → `AdminController::usersRole()` mem-merge `role` ke request dan memakai ulang `users()`; view `admin/users.blade.php` dibuat role-aware — judul "Data Mahasiswa/Dosen/Laboran", subtitle per role, select role disembunyikan (hidden input), filter Angkatan/Semester hanya di halaman mahasiswa, form toolbar kembali ke URL role page. `/admin/users` tetap ada sebagai "Semua Pengguna".
- Preview dev: `GET /dev/preview/admin/{dashboard|users}` dan `/dev/preview/admin/users/{role}` (hanya `APP_ENV=local`) — fixture SQLite sama, akun `ADM-PREVIEW` (role admin + is_admin) dibuat bila belum ada; `PermissionRegistry::seedDefaults()` dipanggil setiap boot fixture.
- Test: `AuthSecurityTest` 11/11 — test baru memverifikasi ketiga halaman role hanya untuk admin (laboran/dosen/mahasiswa 403), role tidak dikenal → 404/405, halaman mahasiswa hanya menampilkan akun mahasiswa. Screenshot Apache: `adm_1_dash.png` (gelap), `adm_4_light.png` (terang), `adm_2_mhs.png` (Data Mahasiswa), `adm_3_lab.png` (Data Laboran), `adm_5_mobile.png` (390px).
- Belum diverifikasi di akun operasional: tampilan akun admin nyata (user 17) dan laboran (user 9) di `/admin/dashboard` — preview memakai fixture, perilaku view/route identik.

### Dashboard admin dimigrasi ke React + TypeScript via Inertia (2026-11-12)
- Koreksi arah pemilik: frontend admin juga React+TS seperti portal mahasiswa — bukan Blade. `AdminController::dashboard` kini `Inertia::setRootView('app-admin')->render('Dashboard', …)` dengan props terserial eksplisit (stats/line/donut/status_sistem/feed/pengajuan/aksi_cepat/urls + `menu` sidebar). Backend/query/permission tidak berubah.
- App baru `resources/js/admin/` (terpisah dari `student/`): `main.tsx` (pola sama: createInertiaApp + ziggy + error boundary), `app.css` (var `--ad-*`, tema light/dark via `data-theme` + `dashboard_theme` — kunci sama dengan layout Blade admin agar konsisten lintas halaman yang belum dimigrasi), `AdminShell.tsx` (sidebar navy + topbar + burger mobile), `Charts.tsx` (LineChart + DonutChart SVG ringan, tanpa dependensi chart), `pages/Dashboard.tsx`. Root view `app-admin.blade.php`. vite input + alias `@admin` ditambahkan; tsconfig include diperluas.
- Menu sidebar dihitung backend (`adminSidebarMenu`) — `P::canCurrent(perm)` + `isAdmin()`; frontend hanya merender. Label Indonesia: "Operasional Laboratorium", "Portal Admin". Chart period switch (Hari/Minggu/Bulan/Tahun) fetch `admin.dashboard.data` + skeleton/error-retry; feed diberi label jujur "Aktivitas Absensi Terbaru (snapshot)".
- `dashboards/admin.blade.php` dihapus (tak ada referensi); `admin/_sidebar.blade.php` tetap dipakai halaman Blade admin lain (users, mesin, dst.).
- Preview: `/dev/preview/admin/api/dashboard-data` ditambahkan (fixture, method dashboardData nyata); `urls.data` di-override saat `routeIs('dev.preview.*')`.
- Test: CopilotStreamTest disesuaikan (assert `"component":"Dashboard"` + "Portal Admin"); `AuthSecurityTest` 11/11 + `CopilotStreamTest` 37/37 (48 total) + `StudentPortalTest` 24/24. `tsc` bersih, `npm run build` sukses (entry admin `main-DK6OuZ-S.js`).
- Verifikasi Apache (fixture): light `adm_r1.png`, dark `adm_r2_dark.png`, mobile 390px `adm_r3_mobile.png`, ganti periode→Bulan `adm_r4_bulan.png`. Akun operasional nyata belum diverifikasi — minta pemilik membuka `/admin/dashboard`.

### Status 5 role (per 2026-11-12)
- **Admin**: portal Blade + dashboard React; `/admin/*` via `role:laboran,admin`+`perm:*`+`admin.only`. Terverifikasi.
- **Laboran**: dashboard React yang sama (menu difilter perm), tanpa akses manajemen user/peran/perangkat konfigurasi. Terverifikasi test.
- **Dosen**: portal Blade `/dosen/*`. Terverifikasi test (redirect + 403 lintas route).
- **Mahasiswa**: portal React `/mahasiswa/*`. Terverifikasi.
- **Pekerja/CS**: BUKAN pengguna portal (keputusan pemilik) — identitas non-portal `RuanganPetugas` disinkron ke mesin; tidak ada di dropdown login, ditolak di validasi. Tidak ada dashboard/akun.

### Koreksi latar foto kampus dashboard admin (2026-11-12)
- `.ad-hero` tidak lagi kartu/banner (tanpa border, radius, shadow, background sendiri) — diganti `.ad-hero-zone`: pembungkus hero+KPI dengan margin negatif `-20px -22px` sehingga foto penuh dari tepi kiri/kanan area konten mulai tepat di bawah topbar; overlay `::before` bergradasi navy→`var(--ad-bg)` (light) dan navy gelap→`var(--ad-bg)` (dark) memudarkan foto ke background halaman. Kartu KPI tetap kartu di dalam zona. Crop `center 32% / cover` — tidak terdistorsi. Tidak menyentuh backend.
- Verifikasi Apache: `adm_bg1_light.png` (terang, foto menyatu + KPI tetap kartu), `adm_bg2_dark.png` (gelap), `adm_bg3_mobile.png` (390px full-bleed).

### Sidebar admin: grup collapsible + pemisah visual (2026-11-12)
- Struktur menu diubah dari seksi flat menjadi hierarki seksi → grup collapsible → sub-menu sesuai susunan pemilik: Menu Admin (Dashboard, Asisten AI) / Operasional Laboratorium (Absensi & Perangkat: Mesin·Atur·Fingerprint·Log Tap; Manajemen Ruangan: Ruangan·Pengajuan·Petugas) / Data & Pengguna (grup role pages + Semua Pengguna, lalu Akun & Peran) / grup top-level Komunikasi + Laporan & Audit / Administrasi (Pengaturan).
- Backend `AdminController::adminSidebarMenu` kini mengembalikan `sections[] → nodes[]` dengan `kind:'link'|'group'` (group: key/label/icon/active/children) — tetap difilter `P::canCurrent` + `isAdmin`; grup tanpa anak tersembunyi. Badge: Pengumuman (unread), Saran (baru), Antrian (opsCount) dihitung try/catch.
- React `AdminShell`: `NavGroup` (chevron berputar saat open) + `NavLink`; grup berisi halaman aktif selalu terbuka + induk diberi `is-parent-active` (penanda lembut, bukan highlight penuh); hanya tautan aktif yang `is-active`. Persistensi `localStorage` kunci `dashboard_sidebar_group_{key}` — skema sama dengan JS grup Blade → status buka/tutup lintas React↔Blade.
- CSS `app.css`: `.ad-nav-section` (border-top tipis antar seksi), `.ad-nav-sub` (garis vertikal halus penuntun submenu, teks anak lebih kecil), scrollbar sidebar tipis bertema (webkit + scrollbar-color), `border-right` halus sidebar↔konten (border-bottom topbar sudah ada). Blade mirror: `_sidebar.blade.php` struktur identik via `.sidebar-group`/`data-sidebar-group`/`data-active-group`; `.sidebar-sep` ditambah di layout; brand "Portal Admin" (bukan "Administración"); JS grup sudah mendukung (saved 'closed' tak menutup grup aktif).
- Preview dev `admin()`: `?as_route=<name>` mensimulasikan nama route agar penanda aktif menu bekerja dalam fixture (khusus local).
- Verifikasi Apache fixture: `nav_1_dash.png` (Dashboard satu-satunya aktif, semua grup tertutup), `nav_2_child.png` (grup Manajemen Ruangan auto-open, hanya "Pengajuan Ruangan" highlight, induk penanda lembut), `nav_4_blade.png` (Blade: Data Pengguna open + Mahasiswa aktif, persistensi lintas shell), `nav_7_drawer.png` (drawer HP, nav scroll sendiri, Pengaturan terjangkau), `nav_8_dark.png` (dark). Toggle+localStorage diverifikasi via CDP; `tsc`+build bersih; `AuthSecurityTest`+`CopilotStreamTest` 48/48. PHPUnit via `/c/php84/php.exe -d extension_dir=/c/php84/ext -d extension=php_pdo_sqlite.dll vendor/phpunit/phpunit/phpunit` (`artisan test` men-spawn php sistem tanpa sqlite).

### Sidebar admin: accordion + logo SmartBrail baru (2026-11-12)
- **Accordion**: hanya satu grup terbuka. React — state `openKey` diangkat ke `AdminShell` (NavGroup controlled), `useRef` mirror agar klik cepat tidak membaca state basi; saat toggle, SEMUA kunci `dashboard_sidebar_group_*` ditulis ulang (satu 'open', sisanya 'closed') agar sinkron dengan Blade. Blade — `initSidebarGroups` kini menutup semua grup lain saat satu dibuka. Persistensi lintas React↔Blade tetap (skema kunci sama).
- **Logo**: `public/branding/smartbrail-logo.png` (lockup S3+wordmark, 1024×341) menggantikan `aiot-simalas-mark.png` + teks "SmartBrail" di React (`ad-sidebar__logo` h=30px) dan Blade (`sidebar-brand-logo`, h=22px saat collapsed); sub "Portal Admin" tetap dengan garis pemisah kecil.
- Verifikasi Apache fixture: klik Absensi→Ruangan di React hanya 1 grup open (eval DOM+localStorage); klik grup di Blade menutup grup lain; state Blade→React terbawa. `tsc`+build bersih. Screenshot: `acc_4_logo.png` (React), `acc_3_blade.png` (Blade).

### Sidebar admin: logo transparan, animasi accordion, pemisah donut (2026-11-12)
- **Logo**: PNG tempelan punya latar papan-catur abu-abu yang ter-bake (bukan alpha) → teks "Smart" putih hilang. Diproses flood-fill dari tepi (PIL) → 299.668 px latar jadi alpha 0, teks putih/biru interior utuh; di-crop ke konten (866×185). Hasil: `branding/smartbrail-logo.png` transparan, terbaca di navy light&dark.
- **Accordion smooth**: `display:none` diganti animasi `max-height` (0→340/420px) + `opacity` + `margin` + `border-color` 0.42s ease — React `.ad-nav-sub` dan Blade `.sidebar-submenu`; caret .3s. Saat load, bila ada grup aktif kunci 'open' grup lain diabaikan (JS Blade) — maksimal satu grup terbuka; React sudah mendahulukan `activeGroupKey`.
- **Legenda donut**: pemisah vertikal tipis `.ad-donut-sep` antara jumlah dan persen (`12 | 80%`).
- Verifikasi Apache fixture: `fix_2_light.png` (light: logo terbaca, submenu terbuka), `fix_5_blade.png` (Blade: hanya grup aktif terbuka). `tsc`+build bersih.

### Profilo cliccabile + lonceng notifiche funzionante su shell admin (2026-11-12)
- **Backend**: `AdminController::dashboard` aggiunge prop `notif` via `adminNotifications($user)` — sorgente identica a `NotificationComposer` (Blade): `StaffNotificationService::operationalItems/Count` + `Announcement::active()->bellFeed()->forAudience()->unreadBy()` (max 10, con author). Include `urls` (read_all, ops_dismiss, announcements index, notifications). try/catch → zeri.
- **React `AdminShell`**: nuovi componenti `NotifBell` e `UserMenu` (types `AdminNotif`/`NotifOpsItem`/`NotifAnnouncement` in `types.ts`, prop `notif` su ShellProps, Dashboard la passa).
  - `NotifBell`: badge rosso col totale (unread+ops); pannello dropdown con sezione "Antrian operasional" (item cliccabili → POST `notifications.ops.dismiss` con fingerprint, poi navigazione; `ops-suggest` persistente senza dismiss), sezione "Pengumuman" (vuota → stato vuoto; piena → elenco + "Tandai dibaca" → POST `notifications.readAll`), footer link riwayat/antrian. Chiusura: click fuori (mousedown) + Escape.
  - `UserMenu`: carta utente sidebar diventa bottone → popover ancorato sopra (avatar, nome, ruolo, NITK/NIDN, email, "Lihat Profil Lengkap" → `profiles.show`, "Keluar" → form POST `route('logout')`+CSRF — stessa funzione del bottone Logout, che resta). Chiusura click fuori/Escape. Avatar topbar diventa link al profilo.
- **CSS `app.css`**: `.ad-notif*` (pannello 340px, scroll max 56vh), `.ad-usermenu`/`.ad-userpop*` (popover sopra la carta, `ad-in` animato).
- **Blade `layouts/dashboard.blade.php`**: popover profilo già esistente (sidebarProfileToggle → profilePopover) — aggiunta riga azioni con bottone "Keluar" (`.profile-popover-logout`, rosso) che chiama `confirmLogout()` esistente; pannello notif Blade già funzionante (notifToggle/notifPanel/notifOpsList/notifClear + poll `notifications.feed`), invariato.
- **Verificato via Apache+CDP (fixture)**: pannello notif React con 2 ops item reali e badge "2" (`ntf_1_panel.png`), popover utente desktop (`ntf_5_pop1366.png`), pannello dark (`ntf_6_dark.png`), "Keluar" → form POST `/logout`+CSRF (spy form.submit), chiusura click-fuori/Escape, drawer 534-758px, link avatar→`/profiles/{id}`; Blade: pannello ops popolato (`ntf_7_blade.png`), popover con "Keluar" visibile e tema condiviso (`ntf_9_blade_keluar.png`). `tsc`+build puliti; `php -l` AdminController OK; entrambe le pagine preview HTTP 200.
- Non verificato: pannello con annunci non letti (fixture senza annunci admin-target) — logica identica a Blade.

### Halaman Asisten AI admin → React + dropdown profil topbar (2026-11-12)
- `AdminAiController::index`: user non-dosen (admin+laboran) kini dirender `Inertia::render('AsistenAi')` root `app-admin` — dosen tetap Blade `admin/ai_assistant` (cpConfig/copilot.js utuh). Props: `menu`+`notif` (trait bersama), `ai_enabled`, `greeting_name`, `conversations` (maks 40), `actions` (5 kartu aksi terfilter `PermissionRegistry::can`), `examples`, `initial_conversation` (paritas `?c=` Blade), `cp` (stream/conversations/store).
- Trait baru `App\Http\Controllers\Concerns\AdminShellProps` — `adminSidebarMenu()` dan `adminNotifications()` dipindah dari AdminController agar dipakai AdminController & AdminAiController tanpa duplikasi (typo `admin.fingerprint*` → `admin.fingerprint` diperbaiki).
- `resources/js/admin/pages/AsistenAi.tsx`: panel kiri (Obrolan Baru, riwayat grup Hari ini/7 Hari/Lebih lama, kartu tips; drawer + scrim ≤1023px), welcome (robot `images/hero-ai-robot.svg`, sapaan nama akun, 5 kartu aksi pastel, contoh pertanyaan), chat (bubble user/assistant, blok chart SVG + archived_action dari meta), input multiline Enter kirim / Shift+Enter baris baru, stream SSE via fetch (meta/status/delta/block/replay/error/done). Guard baru: content-type non-SSE → error; stream selesai tanpa `done` saat status masih 'streaming' → error (mencegah indikator mengetik tanpa akhir saat sesi habis/redirect login). Catatan privasi kondisional `ai_enabled` (jujur saat provider nonaktif).
- `AdminShell`: `ProfileDropdown` topbar baru (avatar+nama+role+chevron → menu: Profil Saya `profiles.show`, Ganti Password → POST `profile.password.reset-link` dengan catatan hasil inline, Keluar → form POST logout+CSRF); `role="menu"`/`menuitem`, `aria-haspopup/expanded`, Esc + klik luar, tidak terpotong (drop `position:absolute` dalam topbar). `sendLinkForSelf` sudah mendukung admin/laboran/dosen/mahasiswa; route baru `POST /profile/password/reset-link` (auth, throttle 5/mnt).
- Preview: `dev.preview.admin` + page `ai`; `StudentPreviewController::admin` seed 3 percakapan `AiConversation`+`AiMessage` untuk `ADM-PREVIEW` (sekali per fixture); endpoint `GET /dev/preview/admin/ai/conversations/{id}` (`adminAiConversation`, batasi milik ADM-PREVIEW); `cp.conversations` di-override ke URL preview saat `is('dev/preview/*')`.
- Test: CopilotStreamTest diperbarui — helper `inertiaProps()` (payload `<script data-page="app">`), assertion saran→`actions`/`examples`, `initial_conversation` menggantikan cpConfig untuk area admin; regex konten loker/setujui di-scope ke props JSON (Ziggy memuat semua nama route). **37/37 CopilotStream + 11/11 AuthSecurity lulus** (PHP 8.3 `/c/tools/php83`); `tsc`+build bersih.
- Verifikasi Apache fixture: `ai_1_desktop.png` (welcome persis referensi: shell + panel kiri + robot + 5 kartu + contoh + input), `ai_2_chat.png` (riwayat→chat terbuka, bubble benar), `ai_3_profmenu.png` (dropdown Profil Saya/Ganti Password/Keluar), `ai_8_mob_drawer.png` (390px drawer riwayat + scrim), `ai_10_dark.png` (dark). Error path: kirim tanpa sesi → bubble error + "Coba lagi" (bukan typing tanpa akhir). Belum diverifikasi: streaming SSE nyata akun operasional + "Ganti Password" kirim email (butuh SMTP).

### Pembersihan "Akses Anda" + "Akun & Peran" + urutan footer sidebar (2026-11-12)
- **Panel "Akses Anda di IoT SIMALAS" dihapus total** dari `layouts/dashboard.blade.php`: variabel PHP `roleAccessCards`, tombol perisai `#accessToggle` di topbar, modal `#accessModal`, JS `initAccessPanel()`, dan seluruh blok CSS `.access-*` (rule `.sidebar-toggle-btn` yang beririsan dipertahankan). Kelas yatim `access-modal-close` di `reports/periodic.blade.php` dibersihkan (inline style tetap).
- **Halaman "Akun & Peran" dihapus total**: 3 route `admin.role.*` (`GET/POST /admin/role-access`, `POST /admin/role-access/reset`) + import `RolePermissionController` di `routes/web.php`; file `RolePermissionController.php` dan view `admin/role_permissions.blade.php` dihapus; link menu di `AdminShellProps` (React) dan `_sidebar.blade.php` (Blade) dihapus; entri `'shield-check'` pada icon map `AdminShell` dihapus. URL lama kini **404** (GET & POST terverifikasi via Apache).
- **`PermissionRegistry` dirampingkan, keamanan utuh**: method presentasi `roleAccessCards()`, `roleLabels()`, `activeMenuLabels()` (yatim) dan key menu `'role_access'` beserta default-nya dihapus — tidak ada lagi route `perm:role_access`. `menus()/defaults()/seedDefaults()/map()/can()/canCurrent()/clearCache()` tetap — middleware `perm:*`, `admin.only`, policies, dan pembatasan data tidak berubah.
- **CATATAN data tersimpan**: baris `role_permissions` lama (termasuk penyesuaian yang pernah disimpan lewat editor) **masih dibaca** `PermissionRegistry::map()` → tetap memengaruhi akses menu lain. Penghapusan ini hanya menghentikan UI pengeditan; data DB tidak disentuh — reset memerlukan izin pemilik.
- **Footer sidebar diurutkan ulang** (React & Blade): menu (scroll sendiri) → ilustrasi gedung → "by AIOT SIMALAS 2026" → profil akun (popover utuh) → **Logout paling bawah** (POST+CSRF). Blade: `.sidebar-art` `margin-top:auto`, disembunyikan saat sidebar collapsed & `max-height:680px`. React: `max-height:760px` art mengecil 64px, `≤660px` hilang — kontrol akun selalu terjangkau.
- **Test**: `tsc`+build bersih; **RuanganBookingTest+AuthSecurityTest 42/42** (assertion baru: `/admin/role-access` GET+POST+reset → 404), **CopilotStream+AccountImportActivation 49/49** (PHP 8.3). Screenshot: `clean_sidebar_react_light2.png`, `clean_sidebar_blade_light.png` (light), `clean_sidebar_react_light.png` (dark React), `clean_sidebar_blade.png` (dark Blade) di `%Temp%`.

### Halaman Mesin + Monitoring Mesin → React/Inertia (2026-11-12)
- **`MesinIotController::index`** kini render `Inertia 'Mesin'` (root `app-admin`): props `stats` (total/aktif/nonaktif/online/offline/by_type dari heartbeat `ONLINE_THRESHOLD_MINUTES`, BUKAN flag is_active), `mesin` paginasi 15 + `last_tap` agregat `LogAbsensi`, `filters` (q/tipe/status), `type_options`, `can` (create/update/delete admin-only via `PermissionRegistry::canCurrent`), `ruangan_options`, `setup` (flash key sekali-tampil), `threshold_minutes`, `api_base`, `urls`. Filter `status` mendukung online/offline (heartbeat) + aktif/nonaktif (administratif).
- **`AdminController::monitoring`** kini render `Inertia 'MonitoringOperasional'` (root `app-admin`, `perm:monitoring,view`) memakai `buildMonitoringPayload($tab)`; `/monitoring/feed` tetap JSON untuk auto-refresh 15 dtk (menerima `?tab=`). Ekspresi `HOUR()` diperbaiki driver-aware (`strftime` di SQLite — sebelumnya 500 di preview).
- **`Mesin.tsx`** (~599 baris): hero biru + ilustrasi SVG perangkat fingerprint; KPI Total/Online/Offline/Nonaktif (tidak ada "Maintenance" — status itu tidak ada di backend); tabel (nama+ID, tipe, ruangan, status koneksi + heartbeat, tap terakhir, status administratif, aksi); kartu HP ≤900px; panel kanan create/edit/detail (full-screen ≤640px); modal setup API key sekali-tampil (key hanya lewat flash session, tidak pernah di props tabel); konfirmasi toggle/regenerate/hapus.
- **`MonitoringMesin.tsx`** (digantikan `MonitoringOperasional.tsx` 2026-11-13): toolbar LIVE/TERPUTUS + sync terakhir, KPI, bar aktivitas per jam, donut kesehatan armada (`DonutChart` kini menerima `center_label`), tabel status mesin + feed aktivitas, polling `urls.feed` 15 dtk (fetch JSON, indikator stale saat gagal).
- **Menu**: "Mesin Absensi" → "Mesin" + item "Monitoring Operasional" (`AdminShellProps` React & `_sidebar.blade.php` Blade; sebelumnya "Monitoring Mesin"); ikon `activity` ditambah di map AdminShell.
- **Route**: `GET /admin/monitoring` tidak lagi redirect — kini halaman Inertia `perm:monitoring,view`. `update()` menerima `ruangan_id` (admin-only, paritas `bindMesin`). View lama `admin/mesin.blade.php` + `admin/monitoring.blade.php` dihapus (tak ada `view()` tersisa).
- **Preview**: page `mesin`+`monitoring` di `StudentPreviewController::admin` + seed armada fixture 18 mesin (online/offline/nonaktif, pagination) + endpoint `dev.preview.admin.monitoring-feed`; URL feed di-override saat attribute `dev_preview`.
- **Test**: `tsc`+build bersih; **42/42 RuanganBooking+AuthSecurity** (PHP 8.3). Screenshot `%Temp%`: `mesin_3_desktop.png`, `mesin_4_create_panel.png`, `mesin_5_detail.png`, `mesin_12_mon_final.png`, `mesin_8_mobile.png`, `mesin_15_dark.png`.
- **Belum diverifikasi**: submit form nyata (store/update) butuh sesi admin operasional — validasi backend sudah tercakup test; polling feed endpoint asli (`admin.monitoring.feed`) belum diuji dengan login nyata.

### Halaman "Atur Absensi" → React/Inertia (2026-11-12)
- **`AbsensiManagementController::settings`** kini render `Inertia 'AturAbsensi'` (root `app-admin`, tetap `ensureLaboran()` + middleware `perm:settings` + `admin.only`): props `kebijakan` (mode/label/effective_from/jadwal_mingguan ternormalisasi), `hari_nama`, `sesi` (semua field backend + `updated_label`), `slot_lab` (dari `AbsensiLabSlotResolver`), `kalender` (rentang/jenis/dampak/jam khusus), `ringkasan` (hari aktif, slot, agenda aktif/berikut), `riwayat` (timestamp nyata sesi/kalender/kebijakan/heartbeat — bukan log fiksi), `ops` (`AbsensiReadinessReport::build()` lengkap: heartbeat, stats, close_hints, issues, pbl, kamis_ready), `can.update`, `tab`, `urls` (semua route POST/DELETE lama).
- **UI baru** `resources/js/admin/pages/AturAbsensi.tsx` mengikuti referensi: hero biru + ilustrasi kalender/jam inline-SVG, tab bar (Ringkasan / Jadwal & Jam Kerja / Kalender Akademik / Kesiapan Operasional + badge jumlah isu), Ringkasan = 4 kartu status + tabel jadwal mingguan read-only + kartu kalender/slot/riwayat + kolom kanan (Perlu Perhatian, Pengaturan Sesi Pagi/Malam, Kebijakan); tab lain memuat form edit nyata (kebijakan + tabel hari editable, kartu sesi pagi/malam, form kalender + daftar + hapus via konfirmasi, aksi operasional tutup sesi/cadangan alfa/tautan PBL/rekap/fingerprint). `?tab=` dipertahankan via URL. Semua aksi POST/DELETE memakai route lama tanpa perubahan validasi.
- **Blade lama dihapus**: `admin/absensi_settings.blade.php`, `admin/_absensi_ops_panel.blade.php`, `absensi/_pengaturan_absensi_panduan.blade.php` (hanya saling rujuk). `absensi._styles`/`_nav` DIPERTAHANKAN — masih dipakai halaman alfa/tidak_lengkap/rekap/periodic.
- **`HandleInertiaRequests`**: flash `info` kini di-share (dipakai `runCloseSessions` "Belum ada sesi yang perlu ditutup") + key `info` di tipe `SharedProps`.
- **Preview**: `GET /dev/preview/admin/atur-absensi` (+ constraint `where` route); fixture SQLite seed `absensi_sesi_settings` (pagi aktif/malam nonaktif) + 3 entri `absensi_kalender_libur` (libur/ujian/perubahan_jam).
- **Verifikasi**: `php -l` bersih; `tsc`+build bersih; PHPUnit `--filter "Absensi|AuthSecurity|SchemaGuard"` 36/36 lulus (PHP 8.3, sqlite :memory:). Apache preview HTTP 200 untuk `ringkasan|jadwal|kalender|operasional`, props nyata terverifikasi (mode `jadwal_mingguan`, 5 hari aktif Sen–Jum, 10 slot, heartbeat "Nonaktif" jujur karena fixture tanpa scheduler). Screenshot `%Temp%`: `atur_1_desktop.png`, `atur_2_jadwal.png`, `atur_3_kalender.png`, `atur_4_ops.png`, `atur_5_mobile.png` (390px, tanpa overflow), `atur_6_light.png` (light mode).
- **Batas**: submit form nyata (POST sesi/kebijakan/libur/tutup-sesi) belum diuji end-to-end di browser karena preview GET-only; payload diverifikasi field-per-field terhadap aturan `validate()` yang tidak berubah.

### Halaman "Fingerprint" → React/Inertia (2026-11-12)
- **`AdminController::fingerprint`** kini render `Inertia 'Fingerprint'` (root `app-admin`, permission lama utuh). Props dibangun dari `fingerprintState()` yang sudah ada + `fingerprintCharts()`: `stats` (total/enrolled/pending/unassigned/kapasitas/completion), `charts` (donut status, tren 7 hari dari `fingerprint_updated_at`, enroll per kelas, aktivitas mesin dari log sync), `mahasiswa` (paginasi, hanya field aman — `finger_template` TIDAK pernah diserialkan), `filters` (q/status/kelas/tim), `options`, `sync` (mode, burst 20 menit, stats, log paginasi), `can` (update/delete), `tab`, `urls` (assign/reset/clear-enroll/backfill-shift/sync-mode/sync-distribute/snapshot).
- **UI baru** `resources/js/admin/pages/Fingerprint.tsx` mengikuti referensi: hero biru + ilustrasi sensor, tab bar (Ringkasan / Data Mahasiswa / Distribusi Mesin / Sinkronisasi / Log & Riwayat), KPI enroll, donut+tren+distribusi langkah mesin, kontrol mode sync auto/manual + burst, tabel mahasiswa (nama/NIM/kelas-tim/shift/finger_id/status/kesiapan/aksi) ↔ kartu ≤900px, assign Finger ID eksplisit/otomatis, re-enroll (clear-enroll), reset, backfill shift, log sync per mesin — semua POST memakai route lama + modal konfirmasi.
- **Endpoint `fingerprintSnapshot` TETAP merender partials** `admin/_fingerprint_rows` + `_fingerprint_sync_rows` (dipakai juga halaman lain via `absensi._snapshot_boot`) — ketiganya DIPERTAHANKAN. Yang dihapus hanya `admin/fingerprint.blade.php` + `public/css/fingerprint-premium.css` (yatim).
- **Preview**: page `fingerprint` di `StudentPreviewController` (masquerade `admin.fingerprint`) + fixture 12 mahasiswa (6 enrolled/3 pending/3 unassigned termasuk 1 legacy `SENSOR_ENROLLED` via cast Eloquent, 5 log sync) + constraint `where` route.
- **Verifikasi**: `tsc`+build bersih; PHPUnit `AuthSecurity+MesinApiAuth` 15/15 (PHP 8.3). Apache preview 200; props bersih — `finger_template`/`SENSOR_ENROLLED`/blob `eyJpdiI6` = 0 kemunculan; filter `?q=aldo`→1, status filter→7; mobile 390px `scrollWidth=390` (kartu menggantikan tabel via `.ad-fp-desktop`); light+dark OK. Screenshot `%Temp%`: `fp_*_desktop.png`, `fp_9_mobile_mhs.png`, `fp_11_dark.png`.
- **Batas**: submit POST (assign/reset/sync) belum diuji dengan login operasional; endpoint & validasi tidak berubah.

### Halaman "Data Pengguna" → React/Inertia (2026-11-12)
- **`AdminController::users`** kini render `Inertia 'Users'` (root `app-admin`, tetap `perm:users,view` + `admin.only`): props `stats` (+`admin`, `new_30d` = akun dibuat ≤30 hari), `role_chart` (donut komposisi), `users` (paginasi; serialisasi aman — tanpa template/password; `kelas_cakupan` untuk dosen; flag verify/enroll/lock/self), `filters` (+`per_page` 5/10/15/25/50 — baru, clamp whitelist), `options` (prodi, tim PBL, angkatan, kelas dari `MahasiswaProfileOptions`), `alerts` (awaiting_activation/pending_otp/pending_approval/login_locked), `aktivitas` (6 log approval terakhir), `arsip` (log paginasi `log_page` + filter `log_q/log_action/log_role/log_actor`), `log_actors`, `can` (create/update/delete via `PermissionRegistry::canCurrent`), `role_page`, `tab` (`um_tab=arsip` atau filter log aktif), `urls`.
- **`usersRole`** kini `->with('role_page', $role)` pada respons Inertia (sebelumnya `with()` pada View).
- **`AccountImportController::regenerateCode`**: bila header `X-Inertia` → `back()->with('activation_once', [...])` (nama/nitk/jenis/code/expires_at/entry_url); fallback view `admin.activation_code_once` dipertahankan. Flash `activation_once` ditambah di `HandleInertiaRequests`.
- **UI baru** `resources/js/admin/pages/Users.tsx` mengikuti referensi: hero biru + ilustrasi kampus, 5 KPI (Total+Mhs/Dosen/Lab/Admin, persentase real), banner pipeline (OTP/aktivasi/terkunci — klik = filter), tab role (Semua/Mahasiswa/Dosen/Laboran/Admin + Arsip Approval), toolbar lengkap (q/status/verifikasi/persetujuan/login/angkatan/semester), tabel 8 kolom (Pengguna/Identitas/Peran/Akademik/Enroll/Verifikasi/Status/Aksi) ↔ kartu ≤900px, pager + pemilih per-halaman, kolom kanan (donut komposisi + bar enroll, Aksi Cepat, Aktivitas Terbaru), tab Arsip dengan tabel log + filter, modal Tambah/Edit (field mahasiswa lengkap + kelas_cakupan dosen dgn pencarian), modal konfirmasi (toggle/unlock/reset/hapus), modal kode sekali-tampil `activation_once`.
- **Dihapus**: `admin/users.blade.php`, `admin/partials/users-list-fragment.blade.php`, `public/css/users-mgmt-premium.css`. Endpoint fragment `X-UM-Fragment` ikut hilang (kode khusus Blade AJAX). Approve/reject tidak dirender — route memang sudah dinonaktifkan sebelumnya.
- **Verifikasi**: `tsc`+build bersih; PHPUnit `AuthSecurity+AccountImportActivation` **23/23** (assertion `test_halaman_data_akun_per_role_hanya_untuk_admin` diperbarui ke props Inertia + helper `inertiaProps`). Preview 200 untuk index/role page/`?role=admin`/um_tab=arsip; filter q/status/approval/lock/log_action bekerja; mobile 390px `scrollWidth=390`; light+dark OK. Screenshot `%Temp%`: `usr_1_desktop.png`, `usr_2_modal.png`, `usr_3_arsip.png`, `usr_4_light.png`, `usr_5_mobile.png`.
- **Batas**: submit POST/PUT/DELETE nyata & halaman `activation_code_once` fallback belum diuji end-to-end (preview GET-only); halaman Import Akun tetap Blade `admin.users_import` (di luar cakupan).
- **Tinggi tabel sejajar rail kanan** (permintaan pemilik, 2026-11-12): wrapper `ad-usr-desktop` dibatasi JS (`useLayoutEffect` + `ref` tabel & `.ad-usr-side`) — `max-height = bottom rail − top tabel − 56px` (ruang pager), min 280px, dilepas di ≤900px; `overflow-y:auto` → scroll internal, `thead th` sticky bawaan `.ad-table` aktif lagi karena wrap kini kontainer scroll (sticky tidak bekerja di `.ad-panel{overflow:hidden}`). Wrapper arsip `ad-usr-free` tetap bebas (`overflow-y:visible`, thead static). `.ad-table-wrap` global tetap 340px untuk halaman lain. Verifikasi CDP: wrap_bot 1474 vs side_bot 1530, 25 baris scrollable — `usr_10_aligned.png`.

### Monitoring Operasional + Log Tap → React/Inertia (2026-11-13)
- **`/admin/monitoring` kini halaman "Monitoring Operasional"** (`Inertia 'MonitoringOperasional'`, `perm:monitoring,view`): `buildMonitoringPayload($tab)` diperluas — `tabs` dinamis dari tipe `mesin_iot` nyata (Overview + satu tab per tipe + Vision selalu ada), `type_cards` (total/online/offline/nonaktif/ruangan per tipe — tipe baru otomatis jadi kartu+tab baru), `units` gabungan perangkat+ruangan (nama, jenis, lokasi, status, terakhir update, url), `alerts` (mesin aktif offline/belum heartbeat), `live_tiles` (hanya tipe cam/kamera/vision/cctv — tile muncul otomatis saat perangkat vision didaftarkan), `has_vision`, plus metrics/hourly/health/activities terfilter `?tab=` (join `mesin_iot` saat tab spesifik). Feed `?tab=` mengikuti.
- **Bagian referensi yang dilewati**: streaming kamera nyata (belum ada — tile menampilkan status perangkat saja), angka/persen APD (placeholder "Fitur vision belum aktif" — tidak diarang), tab jenis statis (diganti tab dinamis per tipe).
- **`/admin/log/absensi` kini `Inertia 'LogTap'`** (`perm:log_absensi,view`, controller `AdminLogController` kini pakai trait `AdminShellProps`): props `stats` (total/masuk/pulang/unik/perangkat/today/minggu_ini pada SET TERFILTER), `rincian` per tipe (left join tipe mesin, tak dikenal→"Lainnya"), `tren` per jam 06–22 pada rentang, `logs` paginasi (`latest('waktu_tap')`, per_page 10/20/50) dengan serialisasi pengguna/jenis/arah(Masuk|Keluar)/perangkat+ruangan/status Valid/keterangan (Terlambat N mnt · Pulang cepat · Lembur), `filters` (+`jenis`,`arah`,`per_page` baru; q kini juga cari email), `options` (mesin+jenis dinamis). Auto-refresh `router.reload` parsial 15 dtk + badge Real-time/TERPUTUS.
- **`snapshot()`** disederhanakan (tanpa `regions` Blade — satu-satunya konsumen adalah view lama; JSON rows/summary/pagination tetap). Dihapus: `admin/log_absensi.blade.php`, `admin/_log_absensi_rows.blade.php`, `pages/MonitoringMesin.tsx`.
- **Menu**: "Monitoring Mesin" → "Monitoring Operasional" (React + Blade). `AdminShellProps`/`_sidebar` diperbarui.
- **CSS**: `.ad-mon-*`/`.ad-log-*` baru + `.ad-pill--danger`/`.ad-pill--off` (dipakai tapi sebelumnya tak terdefinisi) + `.ad-dot`. Tabel log memakai `ad-usr-free` (tinggi bebas seperti referensi).
- **Preview**: page `log-tap` di `StudentPreviewController` (routeName `admin.log.absensi`) + fixture ~32 tap lintas 12 mahasiswa/2 mesin/arah masuk-pulang + keterangan; `urls.index` sadar `dev_preview` di kedua halaman.
- **Test**: `tsc`+build bersih; **46/46 RuanganBooking+AuthSecurity+MesinApiAuth** (PHP 8.3). Preview Apache 200 untuk semua tab/filter; mobile 390px `scrollWidth=390` kedua halaman; light/dark OK. Screenshot `%Temp%`: `mono_1_desktop.png`, `logt_2_full.png`, `mono_2_mobile.png`, `logt_3_mobile.png`, `mono_3_dark.png`.
- **Belum diverifikasi**: polling feed/snapshot endpoint asli dengan login operasional; tap dari mesin `tipe` non-absensi belum ada di produksi (fixture hanya absensi+ruangan device, tanpa tap).

### Ruangan + Pengajuan Ruangan + Petugas → React/Inertia (2026-11-13)
- **`/admin/ruangan` kini `Inertia 'Ruangan'`** (`perm:ruangan,*` utuh): hero + KPI (Total/Aktif/Nonaktif/Terikat Perangkat), tab **Daftar Ruangan** (tabel foto-thumb/kode/lokasi/kapasitas/chip fasilitas/perangkat/jadwal operasional/status + pencarian + filter status + paginasi 5/10/25 + kartu ≤900px), tab **Binding Perangkat** (daftar mesin + select ruangan → `admin.mesin.bind-ruangan`; tombol hanya untuk `is_admin`, non-admin lihat saja + banner info), tab **Pengaturan Akses** (pilih ruangan → form aturan hari/jam/batas → `admin.ruangan.update`). Panel kanan Tambah/Edit memuat seluruh field validasi lama + upload foto + pratinjau + hapus foto.
- **`/admin/pengajuan` kini `Inertia 'PengajuanRuangan'`** (`perm:ruangan_persetujuan,*` utuh): KPI (Total/Menunggu/Disetujui/Ditolak/Berlangsung), tab status Menunggu(+badge)/Disetujui/Ditolak/Dibatalkan/Semua, list master-detail — kartu kiri (kode `PR-{tahun}-{id:04d}`, judul=tujuan, ruangan/jadwal/peserta, pemohon+tim), panel detail kanan (snapshot, jadwal pengganti, alasan, pemutus) dengan aksi **Setujui/Tolak(alasan)/Tawarkan Pengganti(datetime)/Cabut** → route lama (konflik tetap dicek atomik `RuanganBookingService`), kolom Riwayat Keputusan dari `diputus_*`.
- **`/admin/petugas` kini `Inertia 'Petugas'`** (`perm:petugas,*` utuh): KPI (Total/Aktif/Nonaktif/Akses bulan ini dari `RuanganAccessEvent`), tab **Daftar Petugas** (avatar, ID internal, chip kredensial PIN/RFID/Fingerprint — hash TIDAK pernah diserialkan, ruangan akses, jadwal, berlaku, status), tab **Akses Ruangan** (matriks petugas×ruangan dari `jadwal`), tab **Riwayat Akses** (event terbaru). Form tambah (nama/id_internal/tipe+kredensial/berlaku/jadwal 1 ruangan) & edit (nama/status/berlaku/reset kredensial/multi-jadwal) via panel kanan. Petugas tetap identitas mesin non-portal.
- **Fix bug nyata**: `simpanFoto` sebelumnya menulis file lalu `update()` gagal → foto yatim + foto lama terhapus sebelum DB sukses. Kini foto baru diupload → DB update dalam try/catch → gagal: file baru dihapus + `withErrors` pesan jelas; sukses: baru hapus foto lama. Pesan khusus bila penyebab `Unknown column` pada kolom profil → arahkan migrasi `2026_11_12_130000_add_ruangan_profil_fields` (masih menunggu izin pemilik, BELUM dijalankan di MySQL operasional — `php artisan migrate` diperlukan agar kapasitas/fasilitas/foto tersimpan).
- **Bug base URL diperbaiki**: `rtrim(route(..., 0), '0')` menghasilkan `/admin/pengajuan/0/setujui` (segmen akhir bukan digit) — diganti `route('admin.*.index') . '/'` untuk keempat base aksi + bind_base + update_base.
- **Dihapus**: `admin/ruangan.blade.php`, `admin/pengajuan.blade.php`, `admin/petugas.blade.php` (tak dirujuk lagi). Bukan pengguna portal — `RuanganPetugas` tidak punya login.
- **Preview**: page `ruangan|pengajuan|petugas` + fixture ruangan/petugas/pengajuan beragam status sudah ada; constraint `where` route diperluas.
- **Test**: `tsc`+build bersih; PHPUnit `Ruangan|Petugas|Mesin` **37/37** (PHP 8.3). Preview Apache 200 ketiga halaman + semua tab/filter; mobile 390px `scrollWidth=390`; light+dark OK. Screenshot `%Temp%`: `rg_1_desktop`, `rg_2_binding`, `rg_3_akses`, `rg_4_mobile`, `rg_5_panel`, `pgj_1`, `pgj_2_semua`, `pgj_3_mobile`, `pgj_4_light`, `ptg_1`, `ptg_2_akses`, `ptg_3_light`.
- **Batas**: POST/PUT nyata (store/update/bind/aksi persetujuan/petugas) belum diuji end-to-end (preview GET-only); upload foto tetap gagal sampai migrasi kolom profil dijalankan pemilik.

### Pengumuman + Saran & Masukan → React/Inertia (2026-11-13)
- **Migrasi `2026_11_12_130000_add_ruangan_profil_fields` dijalankan** (izin eksplisit pemilik di thread ini) — batch [54]; kolom `kapasitas`/`fasilitas`/`foto` ruangan kini ada → upload foto ruangan berfungsi; catatan "belum dimigrasi" di blok Ruangan di atas sudah kedaluwarsa.
- **`/admin/pengumuman` kini `Inertia 'Pengumuman'`** (`perm:announcements,*` utuh): hero + KPI (Total/Aktif/Draft/Kedaluwarsa), tab Daftar/Buat/Arsip, tabel (ikon, judul+potongan isi, target, prioritas, status, publikasi, berakhir, aksi lihat/edit/toggle/hapus), filter q+target+prioritas+status+rentang tanggal+per_page, composer create/edit (judul/isi/target/prioritas/berlaku-sampai/aksi draft·publish). `?new=1`/`?id=` lama tetap; invalid → create. `markVisibleAsRead` tetap; POST store/PUT update/POST toggle/DELETE destroy tak berubah.
- **`/admin/saran` kini `Inertia 'SaranMasukan'`** (`perm:suggestions,*` utuh): hero + KPI (Total/Baru/Diproses/Ditanggapi), tab Daftar Laporan(+badge baru)/Statistik & Analisis (tren 6 bulan, distribusi sumber+status, waktu respons), list kode `LPR-{id:04d}` master-detail — panel detail (pelapor+avatar, ubah status select→PATCH update, isi laporan, riwayat penanganan, Balas Laporan→POST reply dengan edit-reply mode, Hapus/Spam→DELETE), filter q+sumber+status+from+to (rentang tanggal ditambahkan ke `filteredQuery`). Seleksi `?id=`/first-row + tandai `baru`→`dibaca` tetap; feed `admin.suggestions.feed` + push-notification reply tak berubah; clear-reply (DELETE `reply.clear`) mengembalikan `ditanggapi`→`dibaca`.
- **Fix preview**: `$selected` ditambah ke `use` closure mapping rows (500 awal); rata-rata waktu respons pindah dari `TIMESTAMPDIFF` (MySQL-only) ke diff PHP — kompatibel SQLite fixture.
- **Dihapus**: `admin/announcements.blade.php`, `admin/suggestions.blade.php` (yatim, tak dirujuk).
- **Preview**: page `pengumuman`/`saran` di `StudentPreviewController` + masquerade `admin.announcements.index`/`admin.suggestions.index`; fixture 7 saran + 1 pengumuman.
- **Test**: `tsc`+build bersih; PHPUnit `AuthSecurityTest|StudentPortalTest|SchemaGuardTest` **37/37** (PHP 8.3). Preview 200 komponen `Pengumuman`/`SaranMasukan`; mobile 390px `scrollWidth=390`; light+dark OK. Screenshot `%Temp%`: `ann_1`, `ann_2_buat`, `ann_5_dark`, `sug_1`, `sug_2_stat`, `sug_3_mobile`.
- **Batas**: submit store/reply/hapus nyata belum diuji end-to-end (preview GET-only); checkbox bulk & "Ekspor" pada referensi dilewati — tak ada endpoint backend.

### Antrian Notifikasi + Pengaturan Sistem → React/Inertia (2026-11-13)
- **`/admin/notifications` kini `Inertia 'AntrianNotifikasi'`** (`perm:notifications,view` utuh): hero foto kampus, KPI (Total/Menunggu/Diproses-hari-ini/Terjadwal), tab Antrian(+badge)/Terjadwal(empty-state)/Riwayat Pengiriman, tabel antrian + filter client-side + panel detail kanan. Sumber antrian dipindah dari derivasi ad-hoc ke `StaffNotificationService::rawOperationalItems` (superset lama: +item belum-enroll, membawa `ops_key`+`fingerprint`). **Aksi nyata**: "Proses & Tindak Lanjuti" → `item.url` (sudah berisi `dismiss_notif=` → middleware `DismissStaffNotifFromQuery` auto-dismiss); "Tolak" → fetch POST `notifications.ops.dismiss` (JSON, CSRF) → dismiss sungguhan; `ops-suggest` non-dismissible (persisten). Riwayat = `AccountApprovalLog` paginasi 10 (`activity_page`).
- **`/admin/settings` kini `Inertia 'Pengaturan'`** (`perm:settings,view` + `admin.only` utuh): tab Umum (Informasi Sistem jujur — env/versi/status + Konfigurasi Umum), Keamanan (postur read-only: sesi `session_guard.absolute_hours`, remember 30 hari, brute-force lock, registrasi tertutup permanen, OTP, audit log), Integrasi (status Terkonfigurasi/Belum untuk mail/Gemini/FCM/reCAPTCHA/sync fingerprint — tanpa nilai kredensial), Tampilan (upload logo), Data & Pemeliharaan (form libur+prodi → route `admin.absensi.*` lama).
- **Branding global baru** (`App\Support\Branding`): `POST /admin/settings` simpan `app_general` {nama_sistem, nama_laboratorium}; `POST /admin/settings/branding` upload logo → `public/branding/app-logo.<ext>` + `SystemSetting app_branding` (perm:settings,update+admin.only); `DELETE` reset. `Branding::logoUrl()` (cache-bust filemtime) dipakai: shared prop Inertia `branding` (AdminShell, AppShell mahasiswa, Login, Team React) + `logoUrlOr()` di `layouts/dashboard`, `layouts/student`, `student/team` → **satu upload mengganti logo di semua portal**. Favicon & cetak laporan tetap bawaan.
- **Dihapus**: `admin/notifications.blade.php`, `admin/settings.blade.php` (yatim; `comm-premium.css` dipertahankan — masih dipakai `feedback/dosen`).
- **Preview**: page `antrian`/`pengaturan` + masquerade `admin.notifications`/`admin.settings`; ops items muncul alami dari fixture (OTP/approval/saran/enroll/mesin offline).
- **Test**: `tsc`+build bersih; PHPUnit `AuthSecurity+SchemaGuard+StudentPortal+AccountImportActivation` **49/49** (PHP 8.3). Preview 200 komponen `AntrianNotifikasi`/`Pengaturan`; mobile 390px `scrollWidth=390`; light+dark OK. Screenshot `%Temp%`: `nq_2_desktop`, `nq_3_riwayat`, `nq_4_mobile`, `set_2_desktop`, `set_3_tampilan`, `set_4_mobile`.
- **Batas**: upload logo/simpan umum/dismiss belum diuji end-to-end (preview GET-only); checkbox bulk, rentang tanggal antrian, dan "Ekspor" pada referensi dilewati (tak ada backend); zona waktu/bahasa/format sengaja read-only (mengikuti config server, bukan diarang bisa diubah).

### Kalender & Agenda + widget agenda dashboard → React/Inertia (2026-11-13)
- **Halaman baru `/admin/kalender` (`admin.kalender`)** — `AbsensiManagementController::kalender` → `Inertia 'KalenderAgenda'` (middleware `perm:attendance,view`; `ensureLaboran` → admin+laboran). Hero kampus, 4 KPI (Agenda Bulan Ini / Jadwal Absensi Aktif N Hari / Hari Libur / Peringatan Nasional), tab bar client-side: **Kalender** (grid bulan Sen–Min, navigasi ‹› + "Hari Ini", chip berwarna per jenis, legenda), **Jadwal Absensi** (tabel mingguan read-only), **Hari Libur / Peringatan Nasional / Agenda Kegiatan** (list per jenis). Rail kanan: kartu Jadwal Absensi (link "Kelola Jadwal Absensi" → Atur Absensi) + Detail Agenda Terpilih (ubah/hapus). Modal Tambah/Ubah Agenda: jenis (agenda/libur/peringatan/ujian/perubahan_jam/informasi), tanggal mulai–selesai (rentang), keterangan, dampak absensi, jam khusus bila `ubah_jam`. `can_update` = `perm:attendance,update` (default laboran+admin true) → tombol aksi disembunyikan bila tak berhak.
- **Route baru**: `POST admin/kalender/agenda` + `DELETE admin/kalender/agenda/{libur}` (middleware `perm:attendance,update`) memakai `storeLibur`/`destroyLibur` lama — validasi identik, `back()` redirect. Route lama `admin.absensi.libur*` (perm:settings,update + admin.only) **tidak diubah** — tab Kalender Akademik di Atur Absensi tetap jalan.
- **Jenis diperluas**: `peringatan` (Peringatan Nasional) + `agenda` (Agenda Kegiatan) ditambah ke validasi `in:`, default dampak `tetap_wajib`, default keterangan; label sudah ada di `StudentPortalController::AGENDA_JENIS_LABELS`.
- **Propagasi ke semua pengguna** — terverifikasi data: entri `absensi_kalender_libur` muncul di kalender mahasiswa via `AbsensiEffectiveSchedule::resolve()` + `buildMonthCalendar()` (5/5 entri fixture terlihat di props `Student/Calendar`). `MiniCalendar` kini menampilkan **dot berwarna per jenis** (libur=merah, peringatan=ungu, ujian=amber, lainnya=biru) di sel hari + legenda "Agenda/info" + "Peringatan" di `Calendar.tsx`; tooltip & detail hari sudah menampilkan `jenis_label` dari backend.
- **Dashboard admin**: kartu "Agenda Kalender Terdekat" di rail kanan (6 entri `tanggal_selesai>=hari ini`, chip jenis + rentang + tanda "hari ini") + tautan "Buka Kalender →"; `urls.kalender` + `agenda_terdekat` di `renderAdminDashboard` via `dashboardAgendaTerdekat()`.
- **Sidebar**: link "Kalender & Agenda" (ikon calendar-days) di grup "Absensi & Perangkat" pada `AdminShellProps` + `_sidebar.blade.php` (perm `attendance`); grup auto-open via `routeIs('admin.kalender')`.
- **Status mesin**: tidak disentuh — `MesinIot`/`last_seen_at`/threshold dan halaman Monitoring tidak diubah; verifikasi visual dashboard tetap "0 online · 14 offline · 4 nonaktif" jujur.
- **Preview**: page `kalender` di `StudentPreviewController` + masquerade `admin.kalender` + `urls.index` sadar preview; fixture kalender diperluas (libur, ujian rentang, perubahan_jam, peringatan, agenda, informasi).
- **Test**: `tsc`+build bersih; PHPUnit `--filter Absensi|Kalender` **24/24** (PHP 8.3 — binary `/c/tools/php83/php.exe`; `php` default 8.2 gagal parse vendor). Preview 200 komponen `KalenderAgenda`; tab list+modal terverifikasi via CDP; mobile 390px `scrollWidth=390`; light+dark OK. Screenshot `%Temp%`: `kal_1_desktop`, `kal_2_mobile`, `kal_4_student`, `kal_5_dash`, `kal_6_modal`, `kal_7_dark`.
- **Batas**: submit POST/DELETE agenda belum diuji end-to-end login operasional (preview GET-only); edit memakai `updateOrCreate` by `tanggal` (kontrak lama — mengubah tanggal saat edit meninggalkan entri lama).

### Revisi modal agenda — terpusat + tandai tanggal + nama bebas (2026-11-13)
- **Fix posisi modal**: keyframe `ad-in` fill-`both` menimpa `transform: translate(-50%,-50%)` pada `.ad-mesin-confirm`/`.ad-mesin-setup` → modal meleset dari tengah. Keyframe baru `ad-in-center` mempertahankan translate — berlaku global ke semua modal halaman lain (Mesin, Users, dll).
- **Tandai tanggal**: form tambah agenda kini memakai mini-kalender klik (multi-tanggal, lintas bulan via ‹›) menggantikan field rentang; tanggal terpilih tampil sebagai chip yang bisa diklik untuk batal; titik kecil menandai tanggal yang sudah punya agenda; tombol simpan menampilkan jumlah tanggal dan terkunci bila kosong. Mode edit tetap field tanggal tunggal + opsional sampai-tanggal (kontrak lama).
- **Backend**: `storeLibur` menerima `tanggal_list[]` (`required_without:tanggal`, max 62, distinct) — satu `updateOrCreate` per tanggal sehingga tiap entri bisa dihapus terpisah; field lama `tanggal`/`tanggal_selesai` tetap didukung untuk halaman Atur Absensi.
- **Nama agenda bebas**: `keterangan` diganti label "Nama Agenda" — input teks bebas + `<datalist>` riwayat nama yang pernah dipakai (dari `props.agenda`), jadi agenda kustom bisa dibuat sendiri dan dipakai ulang; jenis tetap enum backend (warna/label konsisten).
- **Verifikasi**: `tsc`+build bersih; modal terukur terpusat (cx 749≈757); eval klik menandai 3 tanggal → chip "4 Nov × | 11 Nov × | 18 Nov ×". Screenshot `%Temp%\kal_8_modal2.png`. POST `tanggal_list` belum diuji end-to-end (preview GET-only) — payload diverifikasi field-per-field terhadap `validate()`.

### Fix error 500 tandai tanggal + daftar agenda di kalender pengguna (2026-11-13)
- **Root cause 500**: `$payload['tanggal_selesai']` fallback ke `$validated['tanggal']` yang tidak ada saat mode `tanggal_list` → `Undefined array key "tanggal"` (AbsensiManagementController:509). `$dates` kini dihitung lebih dulu dan fallback memakai `$dates->first()`; rule `distinct` pada `tanggal_list.*` dihapus (server sudah `unique()`; duplikat tidak lagi menolak seluruh request).
- **Test baru** `test_kalender_agenda_tandai_tanggal_multi_membuat_entri_terpisah`: POST `/admin/kalender/agenda` dengan `tanggal_list` (termasuk duplikat) → 2 entri terpisah + `tanggal_selesai`=tanggal itu sendiri + terlihat di `AbsensiEffectiveSchedule::resolve()`; tanpa tanggal sama sekali → `assertSessionHasErrors('tanggal')`.
- **"Tanggal muncul bukan agendanya"**: prop `calendar.agendas` backend sudah ada tapi tak pernah dirender — kini ditampilkan: **Beranda mahasiswa** — daftar "Agenda bulan ini" (dot warna per jenis + nama + tanggal, maks 5 + tautan sisa) di bawah MiniCalendar; **halaman Kalender mahasiswa** — daftar agenda bulanan 2 kolom di bawah legenda. `CalendarData.agendas` ditambah ke types.
- **Verifikasi**: PHPUnit `Absensi|Kalender|StudentPortal` **47/47** (PHP 8.3); beranda preview memuat 5 agenda ("AGENDA BULAN INI" + nama + tanggal). Build bersih.
- **Batas**: POST nyata di MySQL operasional belum diuji — error 500 direproduksi lewat log (10:28:39) lalu diuji lewat feature test sqlite yang menulis entri nyata; alur identical di dua koneksi.

### MiniCalendar mahasiswa — agenda jadi chip terlihat (2026-11-13)
- **Keluhan**: sel tanggal hanya menandai agenda dengan titik 1.5px — jenis tak terbaca ("ga nampak beda susah").
- **Perbaikan**: prioritas jenis per sel (libur>peringatan>ujian>perubahan_jam>agenda) — mode penuh: sel 64px, latar tint per jenis (`bg-sb-danger-bg`/`violet`/`warn`/`info-bg` + border) + maks 2 chip nama agenda (8.5px warna per jenis) + "+N lainnya"; compact (Beranda) tetap tint latar + dot 2px bertepi putih.
- **Verifikasi**: `tsc`+build bersih; preview mahasiswa/kalender sel 16「Peringatan Hari Pancasila」ungu、18「Hari Libur Nasional」merah、22/24/29 agenda biru。 Screenshot `%Temp%\kal_10_stufix.png`.

### MiniCalendar mahasiswa — agenda chip langsung di sel tanggal (2026-11-13)
- **Keluhan**: sel tanggal hanya dot 1.5–2px, jenis agenda tak terbaca ("ga nampak beda susah").
- **Perbaikan**: prioritas jenis per sel (libur>peringatan>ujian>perubahan_jam>agenda); mode penuh sel 64px — latar tint per jenis + border + maks 2 chip nama agenda (8.5px, warna per jenis) + "+N lainnya"; compact (Beranda) latar tint + dot 2px ring putih. Daftar "Agenda bulan ini" (nama+tanggal) di bawah MiniCalendar Beranda dan daftar 2 kolom di halaman Kalender.
- **Verifikasi**: `tsc`+build bersih; beranda preview innerText memuat "AGENDA BULAN INI" + 5 entri bernama+tanggal; kalender preview sel 16/18/22/24/29 chip berwarna bernama. Screenshot `%Temp%\kal_9_beranda.png`, `kal_10_stufix.png`.

## Logout 500 → MissingAppKeyException (13 Nov 2026)

- Error "logout error" bukan di controller: POST `/keluar`/`/logout` sudah 302; 500 muncul di GET `/masuk` karena `EncryptCookies` resolve `encrypter` saat `config('app.key')` null.
- Akar: race loading `.env` di bawah Apache threaded (222 error sporadis sejak Mei; `variables_order=GPCS`). Per request kadang env tak termuat → semua route web berisiko 500 acak.
- Perbaikan: `php artisan config:cache` → `bootstrap/cache/config.php` membekukan APP_KEY dsb. Pasca-cache: 0 error baru, `/masuk` 10/10 →200.
- Penting: setiap perubahan `.env` kini WAJIB `php artisan config:cache` ulang (atau `config:clear` saat dev).

## Kalender mahasiswa — layout Detail Tanggal + prioritas (13 Nov 2026)

- `Calendar.tsx` direstruktur jadi 2 kolom (lg): grid kalender + "Agenda Terdekat" di kiri; "Detail Tanggal" (badge hari, N kegiatan, kartu item berprioritas dengan icon+pill+bar warna) dan "Keterangan & Prioritas" (Tinggi/Sedang/Normal/Rutin) di kanan.
- `MiniCalendar` kini mendukung controlled selection (`selected`, `onSelect`, `showDetail`, `hideHeader`) — default lama tetap untuk pemakaian lain (dashboard).
- Prioritas agenda: libur/peringatan/ujian→Tinggi, perubahan_jam/agenda→Sedang, informasi→Normal, absensi wajib→Rutin, booking ruangan→Sedang.
- Fix mobile: `grid-cols-1` eksplisit + `min-w-0` di kolom/sel (sebelumnya kolom auto max-content 430px memotong kolom Min di 390px).

## Dashboard admin — feed memanjang + mini kalender agenda (13 Nov 2026)

- Kartu "Agenda Kalender Terdekat" diganti "Kalender Agenda": grid bulan berjalan (offset Senin), sel bertint per jenis agenda + dot, klik tanggal menampilkan daftar agenda hari itu. Prop baru `kalender_mini` dari `AdminController::dashboardMiniCalendar()` (expand rentang tanggal_clamped ke bulan berjalan).
- Kartu "Aktivitas Absensi Terbaru" kini memanjang menyesuaikan bawah rail kanan: `.ad-panel--fill` + `useLayoutEffect` + ResizeObserver mengukur `rail.bottom - wrap.top` (min 280px, dilepas di ≤1000px).
- CATATAN PENTING: sejak `config:cache`, PHPUnit tidak membaca env phpunit.xml (cache menang). Alur test: `php artisan config:clear` → `php artisan test` → `php artisan config:cache` lagi. Terakhir: 88/88 lulus setelah pola ini.

## AI admin — reliabilitas stream + UX composer (14 Nov 2026)

- **Bug bubble tercampur**: `patchLast` lama menarget pesan terakhir array — pesan UI lain (info lampiran) di tengah stream menelan patch jawaban. Kini target = assistant bubble berstatus `streaming` (dicari dari belakang); bubble lain tak tersentuh.
- **Watchdog**: interval 2s di dalam stream; bila tak ada chunk >60s → abort → bubble error "server berhenti merespons" + tombol Coba lagi. Abort disengaja (stop, ganti percakapan, chat baru) ditandai `abort.byUser` agar tidak dicap error.
- **Busy lock**: composer, tombol lampiran, dan kirim diguard `busy` — kirim kedua mustahil selama request aktif.
- **Paperclip** kini popup kecil (`.ad-ai-attachpop`, 260px, di atas composer) — tidak lagi menyisipkan bubble assistant; tautan ke `cp.import_page`; tombol X tutup.
- **`/pengguna`** dari palet slash mengisi `/pengguna ` + fokus composer; argumen → `cari_pengguna` tabel, kosong → stats.
- **Rute lokal mesin** diperluas ("daftar/laporkan mesin") mengurangi panggilan Gemini untuk intent deterministik.
- Verifikasi: `tsc`+build bersih; `php -l` bersih; CopilotStreamTest 37/37 (537 assertions, PHP 8.3; pola config:clear → test → config:cache).

## Konsistensi tema — layout Blade disamakan ke shell React (14 Nov 2026)

- `layouts/dashboard.blade.php` (dipakai semua halaman Blade: profil, attendance, impor akun, absensi rekap/alfa/tidak-lengkap, PBL, reports, dosen) kini memakai palet React AdminShell:
  - sidebar flat `#0c1f47` (light) / `#0a1526` (dark) — gradien biru + solid `#1d6ff2` dihapus; item aktif `rgba(56,189,248,.16)` + teks cyan `#7dd3fc`; ink `#e8effc`, muted `rgba(232,239,252,.55)`.
  - dark `--bg-main #0b1322`, `--bg-card #111d33`; light `--bg-main #f3f6fb`.
  - font `--font` → 'Plus Jakarta Sans' (Google Fonts ditambah, Inter tetap fallback).
- Dropdown profil topbar disamakan: "Profil Saya / Ganti Password / Keluar" — "Ganti Password" POST `profile.password.reset-link` via form tersembunyi (route sama dengan React); item "Pengaturan" admin tetap ada sebagai tambahan. Footer sidebar "Logout"→"Keluar".
- Verifikasi: `php artisan view:cache` kompilasi bersih; belum dicek visual di browser sesi nyata (preview dev hanya mencakup React).

## Reset password bertema per hak akses (14 Nov 2026)

- `PasswordResetLinkController::showForm` kini meneruskan `accountRole` + `roleLabel` dari pemilik token ke `auth.reset-password`.
- Halaman reset menyesuaikan peran: badge "Akun Admin Laboran / Dosen / Mahasiswa" (icon shield/graduation/user), aksen tombol+fokus+link per role — admin biru `#2563eb→#38bdf8`, dosen violet `#7c3aed→#a78bfa`, mahasiswa hijau `#059669→#34d399`; token invalid tetap netral (merah-oranye).
- Alur tidak berubah: token sekali pakai, revoke sesi, halaman `done` tanpa data akun.
- Verifikasi: `php -l` + `view:cache` bersih; AuthSecurityTest 11/11 (119 assertions).

## Halaman reset password — desain baru ala SmartBrail (14 Nov 2026)

- `auth/reset-password.blade.php` ditulis ulang mengikuti referensi "Ubah Password": hero kiri (logo SmartBrail + foto kampus overlay biru, badge SISTEM LABORATORIUM, fitur Aman/Cepat/Terintegrasi), kartu kanan (ikon lock gradien, judul per role "Ubah Password Admin/Dosen/Mahasiswa", 4-segmen strength bar, indikator "Password sesuai", kotak Ketentuan Password 5 checklist sinkron dengan `StrongPassword`).
- Aksen per role tetap: admin biru, dosen violet, mahasiswa hijau (`body.role-*` CSS vars).
- Semantik token (sudah ada, dikonfirmasi): sukses → `consume()` + revoke sesi → tautan hangus menampilkan "Tautan Tidak Berlaku"; validasi gagal → token tetap hidup, bisa ulangi.
- Verifikasi: `view:cache` bersih; AuthSecurityTest 11/11.

## Eye toggle password — ikon eye ↔ eye-off (14 Nov 2026)

- `auth/reset-password.blade.php`: tombol mata kini swap ikon eye ↔ eye-off via innerHTML + `lucide.createIcons()` (lucide merender <i> jadi <svg>, atribut saja tak cukup).
- `student/change-password.blade.php`: pakai inline SVG — diswap antara markup eye/eye-off; sebelumnya ikon statis.
- `auth.blade.php` (login): sudah benar sejak lama (setAttribute data-lucide + createIcons) — tidak diubah.
- Verifikasi: `view:cache` kompilasi bersih.

## Bug foto ruangan terhapus sendiri (14 Nov 2026)

- Akar: `AdminRuanganController::update` memanggil `hapusFotoLama($ruangan)` SETELAH `$ruangan->update()` — model sudah bermutasi sehingga `$ruangan->foto` = path BARU → file foto baru langsung dihapus dari disk, DB menunjuk file tak ada (gambar pecah di admin & mahasiswa).
- Fix: path lama dicapture ke `$fotoLama` sebelum update; `hapusFotoLama(model)` diganti `hapusFotoPath(?string)` yang menghapus by-path eksplisit.
- Halaman mahasiswa (`RoomRequest`): backend kini mengirim `model.foto_url` (asset URL); TSX sebelumnya memakai `model.foto` mentah → src relatif → 404. Tipe `Ruangan.foto_url?` ditambah.
- Repair data: `RM-MEET` disambung kembali ke file orphan `ruangan-1794534093` (foto pagi ini; file baru terlanjur terhapus bug).
- Verifikasi: `php -l` bersih; tsc+build bersih; RuanganBookingTest 31/31.

## Alur pengajuan ruangan mahasiswa — popup + step tracker (14 Nov 2026)

- Setelah kirim pengajuan, popup sukses (Dialog) muncul: ikon centang, ringkasan ruangan/tanggal/jam/peserta, step tracker, tombol Tutup / Lihat Riwayat (auto-pindah tab riwayat). Kartu flash inline tetap ada setelah popup ditutup.
- Komponen `Stepper` baru: 4 langkah — Dikirim → Persetujuan Laboran → Disetujui → Datang ke Ruangan; status terminal (ditolak/dibatalkan/kedaluwarsa) menampilkan label berakhir, bukan stepper.
- Stepper juga tampil di dialog detail riwayat (`status_efektif`): diajukan/menunggu_penerimaan → langkah 2 aktif; disetujui → langkah 4 aktif; selesai → semua centang.
- Verifikasi: tsc+build bersih.

## Realtime status pengajuan ruangan — Reverb WebSocket (14 Nov 2026)

- **Backend**: event `RuanganPengajuanBerubah` (ShouldBroadcastNow, `broadcastAs = pengajuan.updated`) dipancarkan `RuanganBookingService::siarkan()` setelah transaksi di setujui/tolak/tawarkanPengganti/terimaPengganti/batalkan/cabut — ke channel privat `mahasiswa.{userId}` pengaju + semua peserta. Gagal broadcast ditangkap (log warning) — alur utama tak terganggu saat Reverb mati.
- **Channel auth**: `routes/channels.php` + rule `mahasiswa.{userId}` (id cocok + aktif + approved).
- **Frontend**: `student/lib/live.ts` — klien WS ringan protokol Pusher (port `simalas-live.js`): auth via `/broadcasting/auth`, reconnect backoff, dedup `delivery_id`. `RoomRequest` subscribe `mahasiswa.{id}` → `router.reload({only:['pengajuan']})`; `RiwayatTab` menyimpan `detailId` (bukan objek) sehingga dialog detail ikut segar.
- **Shared prop `reverb`** di `HandleInertiaRequests` (key/host/port/scheme/auth_url dari config broadcasting).
- **WAJIB**: `php artisan reverb:start` harus berjalan agar WS aktif (server dicoba listen di 127.0.0.1:8080). Tanpa server, fitur diam-diam tak realtime — tidak ada fallback polling di halaman ini.
- Verifikasi: `php -l` semua bersih; tsc+build bersih; RuanganBookingTest 31/31; Reverb listen terverifikasi.

## Notifikasi booking transparan + realtime dua arah (14 Nov 2026)

- **Mahasiswa**: `buildStudentNotifications` kini menambah item `kind=pengajuan` dari baris transaksi `ruangan_pengajuan` (diputus_pada tidak null): disetujui ("sudah bisa dipakai"), ditolak, tawaran pengganti, dibatalkan — selalu menyebut nama+role pemutus dari relasi `diputusOleh`. Item `pgjr-*` = pengingat booking disetujui yang mulai ≤3 jam. Read-state via `StaffOpsDismissal` (ops_key `pgj-*`/`pgjr-*`, fingerprint `id:timestamp`); route baru POST `mahasiswa/notifikasi/ops/baca` (`readOpsNotification`, guard prefix) + `readAllAnnouncements` ikut menandai. Dismissal TIDAK mengubah baris pengajuan — audit utuh.
- **Admin/Laboran**: `StaffNotificationService` menambah ops item `ops-pengajuan` ("N pengajuan ruangan menunggu keputusan" → admin.pengajuan.index) — dismissal fingerprint `id:count` sehingga pengajuan baru memunculkan item lagi. Sidebar link "Pengajuan Ruangan" kini badge = jumlah pending aktual (`AdminShellProps`, perm `ruangan_persetujuan`). Cap badge/lonceng diubah 99+ → 9+ (bell + sidebar).
- **Realtime staf**: `RuanganPengajuanBerubah::broadcastOn` menambah channel privat `absensi.staff.laboran` (sudah diautorisasi `isLaboran`); `ajukan()` ikut `siarkan()` → pengajuan baru memping staf. `AdminShell` subscribe via `admin/lib/live.ts` (copy klien student) → `router.reload({only:['notif','menu']})`. Mahasiswa reload kini `['pengajuan','navNotificationState']`.
- **Transparansi frontend**: `indexMahasiswa` mengirim `diputus_oleh_nama`/`diputus_oleh_role`; dialog detail riwayat menampilkan "Diputus oleh {nama} ({role}) · {waktu}". Bell mahasiswa (`AppShell`) diperbaiki ke kunci backend (title/excerpt/time_human/is_read/action_url) — sebelumnya membaca judul/cuplikan yang tidak ada (bug laten).
- Verifikasi: `php -l` semua bersih; tsc+build bersih; PHPUnit `StudentPortalTest|RuanganBookingTest` 56/56 (PHP 8.3 /c/tools/php83, workflow clear→test→cache). Reverb tetap harus jalan untuk ping realtime.
- Belum diverifikasi: perilaku browser ujung-ke-ujung (submit → badge staf bertambah → keputusan → lonceng mahasiswa berisi nama pemutus).

## Lonceng notifikasi admin — solid + per-item dibaca (14 Nov 2026)

- Panel bell React tidak lagi transparan: variabel baru `--ad-pop` (light #fff, dark #101d36 solid) dipakai `.ad-notif__panel` — `--ad-card` dark memang translusen (rgba .72) by design untuk kartu.
- Teks item dipangkas: judul 1 baris ellipsis (`ad-notif__item-t`), body 2 baris line-clamp. Clamp sama diterapkan ke bell Blade `layouts/dashboard`.
- Bell menyala saat ada unread: kelas `has-unread` (warna soft + latar tint) + dot berdenyut (`adNotifPulse`) + ikon bergoyang halus berkala (`adBellTilt`).
- Tandai dibaca per item: tombol check kecil (muncul saat hover) di tiap item ops → `dismissOne` POST ops_dismiss; tiap pengumuman → `readOne` POST `{read_one}/{id}/read` (route `notifications.read` existing; `urls.read_one` base URL baru di AdminShellProps). `ops-suggest` persistent tidak punya tombol. "Tandai dibaca" semua sudah ada dan backend `markAllRead` juga dismiss seluruh ops terlihat.
- Verifikasi: tsc+build bersih; php -l bersih; view:cache OK.

## Popup keputusan ala SweetAlert — halaman Persetujuan Pengajuan (14 Nov 2026)

- Setelah aksi setujui/tolak/tawarkan pengganti/cabut berhasil, muncul modal sukses (bukan hanya banner flash): ikon bulat besar (centang hijau / silang merah), judul ("Pengajuan Disetujui!" dst.), kode+nama pemohon, penjelasan dampak (slot dikunci/notif mahasiswa atas nama Anda), tombol Selesai. Ditangkap via `onDone` callback `post()` — hanya muncul bila POST sukses tanpa error. CSS `.ad-pgj-done*` baru di app.css.
- Verifikasi: tsc+build bersih.

## Fix 500 beranda mahasiswa — closure tanpa `use ($user)` (14 Nov 2026)

- Penyebab: `$pgjItems`/`$reminderItems` map di `buildStudentNotifications` memakai `$user` di dalam closure tanpa `use ($user)` → `Undefined variable $user` → 500 di semua halaman mahasiswa (shared prop `navNotificationState`). Lolos test karena koleksi kosong tak pernah memanggil closure.
- Fix: `use ($user)` ditambah di kedua closure.
- Regresi dicegah lewat 2 test baru di `RuanganBookingTest`: feed notifikasi berisi item `pengajuan` dengan nama+role pemutus (pengaju & peserta), tandai-baca tidak mengubah audit (diputus_oleh/diputus_pada/status utuh), `is_read` berubah; dan `ops-pengajuan` badge count=1 untuk staf.
- Verifikasi: `RuanganBookingTest|StudentPortalTest` 58/58 (PHP 8.3).

## Foto ruangan di kartu dashboard mahasiswa (14 Nov 2026)

- `ruanganDashboardData` menyertakan kolom `foto` pada relasi ruangan dan mengirim `ruangan.foto_url` (asset URL) untuk pengajuan `berikutnya`; kartu "Penggunaan terjadwal" di Dashboard.tsx menampilkan foto ruangan 128px dengan badge kode ruangan. Scope tim (`pbl_project_id`) otomatis — semua anggota tim melihat foto yang sama.
- Verifikasi: tsc+build bersih; php -l bersih.

## Layout dashboard + footer sidebar mahasiswa (14 Nov 2026)

- Kartu "Absensi hari ini" vs "Ruangan berikutnya" kini tinggi sama (grid `items-stretch` + Card `h-full flex-col` + CardContent `flex-1` + tautan bawah `mt-auto`) — tidak ada lagi ruang kosong tak seimbang.
- Sidebar mahasiswa: ilustrasi gedung diangkat 32px, strip footer baru `h-8` di tepi bawah bertuliskan "by AIOT SIMALAS 2026" (selaras sidebar admin); tersembunyi saat rail collapse.
- Verifikasi: tsc+build bersih.

## Asisten mahasiswa — ketahanan konteks + intent pengajuan (14 Nov 2026)

- "Cek Status Pengajuan" dkk. tidak lagi langsung kartu error bila `ctx` null — otomatis memuat konteks dulu (`loadContext` kini return `Konteks|null` + 1 retry internal); error hanya bila fetch benar-benar gagal. Diterapkan ke showPengajuan/showJadwal/showLaporan. Backend `helpContext` terverifikasi sehat via tinker.
- Intent baru di `buildLocalStudentAiReply`: pertanyaan status pengajuan/pinjam/booking dijawab dari DB nyata — status efektif + nama & role pemutus (transparan audit). Pemicu dipersempit (tanpa "ajukan/cara/mau/buat") agar intent membuat booking tidak tertelan.
- Tombol lampiran (paperclip) di composer asisten — klik menjawab "fitur unggah file sedang dalam tahap pengembangan".
- Verifikasi: php -l bersih; tsc+build bersih; PHPUnit 58/58.

## Asisten mahasiswa — konteks tim + tawaran laporan (14 Nov 2026)

- `showTim` kini async + auto-load konteks (`ctx ?? loadContext`) — sebelumnya `ctx` null langsung menjawab "belum tergabung dalam tim PBL" walau user punya tim (Rayendra resolve via legacy `users.tim` → nama proyek; `timMahasiswa` backend terverifikasi benar via tinker).
- `$isIssue` dipersempit: token `absen|tap|finger` dicopot (menelan pertanyaan informatif "Jelaskan ketentuan jam tap…"); typo `masal|masaal` dipertahankan (test `masaalh`). Regex baru masih menangkap lapor/kendala/keluhan/koreksi/alfa/tidak lengkap/belum masuk/tidak tercatat/tidak bisa/gagal/salah.
- Draft laporan tidak lagi otomatis terbuka — `suggest_report` kini menampilkan tawaran ("Mau saya buatkan draft laporan? Tidak ada yang terkirim tanpa persetujuan") dengan opsi Ya/Tidak; `startReport` hanya jalan setelah user klik Ya.
- Verifikasi: php -l bersih; tsc+build bersih; PHPUnit 58/58.

## Asisten mahasiswa — laporan multi-kategori + sidebar Coming Soon (14 Nov 2026)

- `detectIssueKategori()` + `issueReportData()` baru — deteksi kendala toleran typo (lpor/kndala/masal/masaal/gbsa/eror/rusak/bermasalah) dan mengkategorikan: absensi (tap/finger/kehadiran), akun (login/password/email), fasilitas lab → `lainnya`, umum → `saran`. `report_data` kini adaptif — field `tanggal_absensi`/`referensi_absensi` hanya untuk absensi; subjek menyesuaikan topik. Balasan lokal branch-3 juga adaptif (teks "kendala akun/fasilitas lab").
- Teks tawaran frontend ikut kategori ("kendala absensi/akun/fasilitas lab").
- Footer sidebar semua shell: "by AIOT SIMALAS 2026" → "by AIOT SmartBrail 2026" (AppShell, AdminShell, layouts/dashboard).
- Menu sidebar baru "Coming Soon" di bawah Laporan & Bantuan — submenu "Fitur Berikutnya" → route `mahasiswa.coming-soon` (`GET /mahasiswa/segera-hadir`) → halaman Inertia `Student/ComingSoon` (kartu roket + badge "Segera Hadir" + tombol kembali).
- Verifikasi: php -l bersih; tsc+build bersih; route ter-cache; PHPUnit 58/58.

## Asisten & shell mahasiswa — animasi natural (14 Nov 2026)

- Pesan chat masuk dengan animasi slide-fade halus (`.sb-asst-row` keyframe `sb-asst-in`, user sedikit lebih cepat).
- Indikator mengetik diperkaya: 3 titik biru memantul + label "berpikir…" + avatar mini berdenyut; `sendPrompt` menahan minimal 700ms agar respons tidak pop instan.
- Tawaran draft laporan digabung ke bubble balasan yang sama (teks + "Mau saya buatkan draft…?" + tombol Ya/Tidak) — tidak lagi dua gelembung terpisah.
- Lonceng notifikasi mahasiswa: tint biru + goyang berkala saat ada unread, goyang 3x singkat saat jumlah notif bertambah (`sb-bell--justin` via `useRef` prev-count), badge pop tiap perubahan angka.
- Sidebar mahasiswa: akordeon eksklusif — `openGroup` di-lift ke `Sidebar`, membuka satu grup menutup yang lain; animasi buka/tutup memakai `grid-rows-[0fr→1fr]` + opacity 350ms.
- Verifikasi: tsc+build bersih.

## Kalender Akademik Polibatam TA 2026/2027 — diimpor (14 Nov 2026)

- PDF sumber (`Kalender Akademik TA.2026-2027.pdf`) adalah scan 1 halaman tanpa layer teks — dirender via PyMuPDF lalu ditranskripsi manual.
- 62 event diimpor ke `absensi_kalender_libur` (total tabel kini 64 baris) via script idempotent `database/kalender-akademik-import.php` (dapat dijalankan ulang aman — lewati duplikat).
- Pemetaan jenis: `libur` (batal_wajib) untuk hari libur nasional/keagamaan & libur semester; `ujian` untuk ATS/AAS; `informasi` (tetap_wajib) untuk agenda akademik (daftar ulang, publikasi/kunci nilai, PKKMB, wisuda, kuliah perdana, PBL, jalur masuk, dll).
- Constraint UNIQUE `tanggal` (satu event per tanggal mulai) → dua bentrok digabung: 17 Mei 2027 (Idul Adha 17–18 + Waisak 19–20 libur; baris ATS digeser mulai 21 Mei dengan keterangan periode resmi) dan 13 Sep 2027 (Kuliah Perdana + RPL/PSPPI jadi satu keterangan).
- Otomatis tampil di semua kalender pembaca tabel: `/admin/kalender` (admin & laboran — `perm:attendance` terverifikasi view+update untuk laboran), kalender & dashboard mahasiswa, konteks asisten, dan resolver absensi semua role. Terverifikasi: `resolve('2027-03-08','pagi')` → is_wajib=false "Libur: Hari Raya Nyepi".
- Dosen tidak punya halaman kalender sendiri; dampak libur/ujian tetap terbaca lewat status hari di view absensi.

### Navigasi bulan di kalender dashboard (mahasiswa + admin/laboran)
- `MiniCalendar` mahasiswa menerima prop opsional `nav` (`CalNav`: `bulan`, `label`, `busy`, `onPrev/onNext/onPick`) — bar kontrol ‹label› + popover pemilih bulan/tahun (grid 12 bulan + ganti tahun). `shiftMonth()` diekspor dari komponen yang sama.
- Dashboard mahasiswa fetch bulan lain via `GET /mahasiswa/kalender/bulan?bulan=YYYY-MM` (`mahasiswa.calendar.month`, `apiGet`) → state `cal` diganti tanpa reload; pilihan tanggal internal ikut data bulan baru. Halaman penuh `/mahasiswa/kalender` tetap nav `?bulan=` via `router.get`.
- Admin/laboran: `AdminController::dashboardMiniCalendar(?string $bulan)` menerima `kcal` (query dashboard) atau `bulan` (endpoint JSON baru `GET /admin/dashboard/mini-kalender`, `perm:dashboard,view`, route `admin.dashboard.minical`, validasi `YYYY-MM`). Response kini memuat `bulan`, `bulan_label`, `prev`, `next` selain `days`/`selected`.
- `MiniCalView` di `resources/js/admin/pages/Dashboard.tsx` menerima `cal/onNav/busy` — panah ‹ ›, tombol label bulan membuka popover pilih bulan+tahun, loading pulse saat fetch. URL `minical` disuntik via `props.urls`.
- **Batas scope**: dashboard Dosen (Blade `dashboards/dosen.blade.php`) belum punya widget kalender — permintaan "semua user" terpenuhi untuk mahasiswa + admin + laboran; widget kalender Dosen adalah pekerjaan tambahan yang belum dikerjakan.
- **Insiden**: `route('admin.dashboard.minical')` membuat dashboard 500 (`RouteNotFoundException`) karena `bootstrap/cache/routes-v7.php` belum diregenerasi setelah route ditambah. Diperbaiki `php artisan route:cache` (13 Nov 17:35); dashboard kembali 302→login. **Pelajaran**: setiap penambahan route di proyek ini wajib `route:cache` ulang sebelum diserahkan.
- **Verifikasi**: `php -l` bersih; `tsc`+build bersih; `route:list` menampilkan `admin.dashboard.minical`; `route:cache` sukses.

### Verifikasi email jadi sukarela + portal dosen mulai React (13 Nov 2026)
- **Verifikasi email opsional semua role**: login tidak lagi mengeset `email_verification_pending` / mengirim OTP otomatis (`AuthController` ~baris 271) → banner wajib hilang dari semua dashboard. `UserProfileController::show` mengeset flag yang sama via `session()->now()` hanya saat user membuka profilnya sendiri dan email belum terverifikasi → banner+modal OTP tetap bekerja sebagai saran di halaman profil (layout `dashboard.blade.php`, judul banner "disarankan" bila opsional). Endpoint `account-email.post`/`verify-account-email.post` tetap utuh; `pendingAccountVerifyUser` fallback ke `auth()->user()`. `sendLinkForSelf` (reset password mandiri) memerlukan email terisi — UI menyarankan isi email dulu.
- **Mini-kalender dipindah ke trait bersama** `Concerns\DashboardMiniCalendar` (`dashboardMiniCalendar`, `dashboardAgendaTerdekat`, `dashboardMiniCalJson`) — dipakai `AdminController` dan `DosenController`; route baru `dosen.dashboard.minical`.
- **`AdminShellProps` bertambah** `dosenSidebarMenu()` (grup Utama/Rekap/Akademik/Komunikasi/Akun + badge pengumuman) dan `dosenNotifications()` (sama seperti admin tapi URL `dosen.*`). Komponen `MiniCalView` diekstrak ke `resources/js/admin/components/MiniCalView.tsx` (dipakai dashboard admin & dosen).
- **Dashboard & Profil dosen kini React+TS** (`resources/js/admin/pages/DosenDashboard.tsx`, `DosenProfile.tsx`, root `app-admin`, shell `AdminShell` yang sudah mendukung role dosen): hero, KPI (mahasiswa/% hadir/tap/tim PBL), filter mode+kelas+tim+aktivitas via `dosen.dashboard.data`, grafik garis & donat, mini-kalender akademik bernavigasi bulan, agenda terdekat, live feed tap masuk/pulang, pengumuman. Profil: kartu identitas + upload/hapus foto (`profiles.photo.*`), kartu email dengan status verifikasi (aksi mengarah ke `profiles.show` tempat modal OTP bekerja), kirim tautan reset password, tim PBL.
- **Belum dimigrasi**: halaman dosen lain tetap Blade (`dosen.absensi`, `dosen.reports`, `dosen.mahasiswa`, `dosen.ruangan`, `dosen.announcements`, `dosen.notifikasi`, `dosen.feedback`, `dosen.ai` masih `admin.ai_assistant` Blade) — sidebar React menautkan via `<a href>` (full page load, layout Blade visualnya konsisten). Migrasi bertahap berikutnya.
- **Verifikasi**: `php -l` bersih; `tsc`+build bersih; `route:cache` sukses; `route:list` menampilkan `dosen.dashboard.minical`. Render visual belum dicek di browser.

### Rekap absensi dosen → React + realtime Reverb; profil dosen diperkaya (14 Nov 2026)
- **`/dosen/absensi` kini React+TS** (`resources/js/admin/pages/DosenAbsensi.tsx`, root `app-admin`): `AbsensiManagementController::rekap` me-render `Inertia::render('DosenAbsensi')` khusus `isDosen() && ! isLaboran()`; laboran tetap Blade. Payload awal memakai `rekapSnapshotData()` (diekstrak dari `snapshot()`) — endpoint JSON `dosen.absensi.snapshot` tetap mengembalikan `AbsensiSnapshot::envelope` + region HTML untuk polling Blade lama.
- **Realtime Reverb** menggantikan polling: halaman subscribe channel privat `absensi.staff.dosen` via `subscribePrivate` (`live.ts`), event `snapshot.wake` → refetch snapshot HTTP dengan query/filter aktif (server tetap otoritatif; tak ada baris sensitif di payload broadcast). Jaring pengaman 90 dtk hanya berjalan bila WS padam. Badge "Live/Offline" menunjukkan status koneksi.
- **UI profesional**: bar aksi (cari, live badge, refresh, Ekspor/Cetak dengan query aktif), tab Semua/Hadir/Alfa/Tidak Lengkap ber-count, filter kelas/tim/prodi/semester/sesi, donat ringkasan + legend, feed "Tap Masuk Terbaru" realtime, distribusi status ber-bar, tabel detail (NIM/mahasiswa/kelas/prodi/sesi/status-pill/jam masuk+pulang/late-badge/lembur), paginasi ‹ ›. Koreksi hanya via `can_koreksi`; `kelas_scope_empty` tampil sebagai banner amber.
- **Profil dosen diperkaya** (`DosenController::profile`): hero banner gedung `images/campus-polybatam.jpg` + avatar + badge verifikasi; 3 kartu statistik nyata `ad-kpi` (mahasiswa bimbingan, % hadir minggu dari `computeDashboardAnalytics`, tim PBL — tanpa "kelas diampu" karena dosen tidak punya kelas); grid kartu 2 kolom rata-tinggi (Personal, Kontak & Verifikasi, Keamanan, Tim PBL) + Aktivitas Akun full-width dari `approvalLogs` (6 terakhir). CSS `ad-prof-*` di `app.css`.
- **Catatan data**: `users` tidak punya kolom jenis kelamin/tgl lahir/alamat — tidak ditampilkan (tidak dipalsukan); `fakultas` sementara hardcode "Fakultas Ilmu Komputer" hingga kolom ada.
- **Verifikasi**: `php -l` bersih (DosenController, AbsensiManagementController); `tsc` + build bersih; `route:cache` sukses; `route:list` menampilkan `dosen.absensi*` + `dosen.profile`. Render visual & alur broadcast belum dicek di browser/Reverb berjalan.

### Profil dosen kembali ke `profiles.show` + Laporan Periodik → React (14 Nov 2026)
- **`dosen.profile` kini redirect** ke `profiles.show` (halaman profil bersama Blade yang lebih lengkap: hero, data kepegawaian, status akun, proyek PBL, riwayat persetujuan, pintasan). `DosenProfile.tsx` dan stats/aktivitas custom dihapus; CSS `ad-prof-*` boleh dibersihkan nanti bila tak dipakai lagi.
- **Banner "verifikasi email disarankan" dihapus dari halaman profil** — blok `session()->now(email_verification_pending)` di `UserProfileController::show` dicabut. Status "Belum diverifikasi" tetap tampil informatif di kartu Status Akun; endpoint OTP tetap hidup (tidak ada pemaksaan). Field **Nomor HP** (`users.no_hp`) ditambahkan di blok data dosen pada `profiles/show.blade.php`.
- **`/dosen/reports` kini React** (`DosenReports.tsx`): `DosenController::reports()` override trait → Inertia dengan payload `PeriodicReportBuilder` yang sama (filter periode/bulan/custom, kelas, tim; summary; chart per hari; top mesin; aktivitas terbaru; rekap mahasiswa; rankings). UI sesuai mockup: filter bar (periode + bulan/date kondisional + kelas + tim + Terapkan/Reset via `router.get` `preserveState`), tab lokal Ringkasan/Per Mahasiswa/Per Kelas/Per Tim/Per Mesin, 5 KPI `ad-kpi`, stacked bar tren harian (SVG inline), donut komposisi, tabel mahasiswa cari+paginasi klien, agregasi kelas/tim di klien, ekspor Excel/Word/Cetak dengan query aktif. Tidak ada kolom Terlambat karena backend tidak menyediakan (yang ada: hadir/tidak_lengkap/alfa).
- **Temuan scope**: laporan periodik dosen memakai `DosenKelasScope` (kelas yang ditugaskan laboran) — **bukan** tim PBL. Akun dosen tanpa kelas → data 0; ditampilkan banner amber `scope_empty`/`scope_message` (alih-alih mengubah semantik akses). Konsisten dengan rekap harian dan `guardDosenKelasAssignment`.
- **Verifikasi**: `php -l` bersih; `tsc`+build bersih; `route:cache` sukses; tinker → `DosenController::reports` menghasilkan komponen `DosenReports` dengan `scope_empty=true` untuk akun dosen uji (kelas belum ditugaskan). Render visual belum dicek di browser.

### Scope absensi dosen = kelas ∪ tim PBL; sidebar & profil diperbaiki (14 Nov 2026)
- **`DosenKelasScope::applySupervisedMahasiswaQuery()` + `hasSupervision()` baru** — cakupan absensi dosen kini mahasiswa di kelas penugasan UNION mahasiswa di tim PBL (manpro/co-manpro via `DosenMahasiswaScope::timKeysFor`); keduanya kosong → `0=1`. Menggantikan `applyToMahasiswaQuery` (kelas-only) di `PeriodicReportBuilder::mahasiswaBaseQuery`, `AbsensiManagementController::scopedMahasiswaQuery`, 4 titik `DosenController`, `guardDosenKelasAssignment`, `kelas_scope_empty` (rekap), dan `scope_empty` (laporan). `kelasKeysFor`/`applyToMahasiswaQuery` lama dipertahankan untuk UI kelas-spesifik. Terverifikasi: rekap scope Rifqi = 58 mhs (tim KRAI/AGV/PBL-RE), laporan periode Mei–Jul = hadir 18/tidak_lengkap 36/alfa 233.
- **Sidebar dosen diratakan** — submenu hanya "Rekap Absensi" (2 anak); sisanya link top-level: Beranda, Asisten AI, Data Mahasiswa, Pengajuan Ruangan, Pengumuman, Saran & Masukan, Profil Saya — dipisah `ad-nav-divider` (node kind `divider` baru di `SidebarNode`/`AdminShell`). `NavLink` kini punya varian `--top` vs `--child`.
- **Profil (`profiles.show`)** — blok "Kontak & verifikasi" di kartu Status Akun (own profile): form update `no_hp` mandiri via route baru `profiles.contact.update` → `UserProfileController::updateOwnContact` (validasi regex `^[0-9+\-\s()]{8,30}$`); tombol "Verifikasi Email" (`data-email-verify-open`) membuka modal OTP; ikon `?` (`<details>.pf-help`) menjelaskan alur untuk akun baru. Modal tetap dimuat via flag `email_verification_pending` tapi banner strip disupres oleh `@unless(email_verify_optional)` di `dashboard.blade.php` — banner verifikasi hilang total. `email-verify-modal.js` kini mendukung pemicu `data-email-verify-open`.
- **Perapihan** — `DonutChart` tidak lagi menampilkan "1" saat kosong (props `total` dikirim nilai asli); `TrendChart` laporan menampilkan empty-state alih-alih 30 batang kosong.
- **Verifikasi**: `php -l` 8 file bersih; `tsc`+build bersih; `route:cache` sukses; tinker membuktikan scope+laporan berisi data nyata.


### Sidebar dosen ala mockup (2026)
- Menu dosen kini **accordion group** identik di kedua shell: React `AdminShell` (`dosenSidebarMenu`, key `dosen-main/rekap/academic/comm/account` — sama dengan key Blade sehingga state buka/tutup bertahan lintas halaman) dan Blade `dosen._sidebar`.
- `SidebarLink.tag` (chip teks, mis. "Beta" pada Asisten AI) — class `.ad-nav-link__tag` / `.sidebar-link-tag`.
- Item baru "Keamanan Akun" → `profiles.show#status-akun` (anchor `id="status-akun"` pada kartu Status Akun). Item mockup "Proyek PBL/Manajemen Tim/Penilaian PBL" TIDAK ditambahkan: `dosen.pbl.index` sengaja dinonaktifkan dari navigasi dan rute lain belum ada.
- Brand sub role-aware ("Portal Dosen"/"Portal Admin"); footer copy "SmartBrail v2.1.0 · by AIOT 2026" di kedua shell.


### AI dosen diperluas + menu PBL (2026)
- `dosen.ai` kini merender halaman React `AsistenAi` yang sama dengan admin (sidebar+notif dosen via AdminShellProps; `cp` endpoint `dosen.ai.*`; kartu aksi & contoh pertanyaan varian dosen; `import_page` disembunyikan). Route baru: `dosen.ai.conversations.store`, `dosen.ai.confirm`, `dosen.ai.cancel`.
- Tool baru `tim_pbl_saya` (read, perm `dosen_pbl`): daftar tim PBL aktif yang dibimbing + anggota per tim. Scope AI `cari_mahasiswa`/`unassignedNotice` beralih ke `applySupervisedMahasiswaQuery`/`hasSupervision` (union kelas+PBL).
- Perluasan tool dosen (semua read-only, scope `applySupervisedMahasiswaQuery`): `mahasiswa_bimbingan` (`dosen_mahasiswa`, daftar semua mahasiswa bimbingan + count per kelas/tim), `pengajuan_ruangan_saya` (`dosen_pbl`, status pengajuan ruangan tim bimbingan via `RuanganBookingService::timBimbingan`), `list_alfa` + `log_absensi_cari` (shared dengan laboran, difilter `whereHas user` saat pemakai dosen), `list_announcements` (difilter `forAudience($dosen)`). `/help` & prompt sistem dosen diperbarui mencakup tool baru.
- Halaman `/dosen/pbl` kini React `DosenPbl` (Inertia, root `app-admin`): hero + 4 KPI (total proyek/dibimbing/tim/mahasiswa), tab Proyek Saya vs Semua Proyek, kartu proyek ber-foto + badge Manpro/Co-Manpro + progress, grid semua proyek dengan filter status+cari. Metadata ringan (foto, deskripsi, progress, status) via `App\Support\PblProjectMeta` — JSON `storage/app/pbl_meta.json` + file `storage/app/public/pbl/{id}.ext` (tanpa migrasi skema). `POST /dosen/pbl/{pbl}/media` (perm `dosen_pbl,update`, hanya `isManagedBy`) untuk upload foto/edit info. `PblProjectMeta::card()` juga menyertai payload proyek di `MobilePblTeamController` (projects & team) agar foto tampil di sisi mahasiswa. `projectMemberUsers` kini ikut memuat `profile_photo_path` untuk avatar anggota. Kartu proyek membawa `members[]` lengkap (nama/NIM/kelas/foto/role ketua-wakil-anggota) — modal `DetailModal` menampilkan hero foto, fakta Manpro/Co-Manpro/sesi, progress, dan daftar anggota penuh (scroll) untuk semua proyek, bukan hanya milik sendiri; tombol "Kelola tampilan" tetap hanya untuk `is_mine`. CRUD tim untuk manpro: `POST /dosen/pbl` (`dosen.pbl.store`, perm `dosen_pbl,create`) membuat proyek dengan manpro=pembuat; modal detail milik sendiri menyediakan tambah anggota (select dari `unassignedMahasiswa`), set ketua/wakil, dan keluarkan anggota via route `anggota/ketua/wakil/members` lama — otorisasi tetap di `PblTeamService` (`canAssignAnggota`/`managesProject`).
- `menuReply` role-aware: map bersama `rekap`+`laporan`, map laboran (akun/ruangan/perangkat/aksi) vs map dosen (akun=mahasiswa bimbingan, pbl baru, ruangan=pengajuan tim). `/menu perangkat|aksi` untuk dosen fallback ke help. Autocomplete `/` di `AsistenAi.tsx` pakai `DOSEN_COMMANDS` saat `cp.role === 'dosen'` (role dikirim via `cp.role`).
- `cariMahasiswa` ditingkatkan: tokenisasi per kata (tiap token cocok di nama OR NIM), deteksi `di_luar_scope` (akun ada tapi bukan bimbingan → beri tahu tanpa bocorkan data), dan `saranMirip()` fuzzy (similar_text per kata + levenshtein NIM ≤2 untuk kueri ≥4 digit) menampilkan "mungkin maksud Anda" bila 0 cocok. Balasan lokal `cari_mahasiswa` menyebut ketiga kasus.
- `parseChartArgsFromMessage` mendukung nama bulan Indonesia: `grafik absensi juli 2026` → custom 2026-07-01..31, `mei 2026 sampai juli 2026` → rentang lintas bulan. Tombol laporan AI menambah "Preview laporan" (route `*.reports.print`, tab baru) sebelum Excel/Word.
- Sidebar dosen (React+Blade): grup "Proyek PBL" — "Proyek PBL Saya" → `dosen.pbl.index` (diaktifkan kembali atas permintaan pemilik), "Manajemen Tim" & "Penilaian PBL" = `soon` → Swal "masih tahap pengembangan". `SidebarLink.soon`/`tag`; Swal CDN ditambah di `app-admin.blade.php`.

### Dosen: Pengumuman (buat) + Saran & Masukan → React (2026)
- `dosen.announcements` kini merender `DosenPengumuman` (AdminShell): tab Buatan Saya/Diterima, 4 KPI, filter tujuan/status/cari, modal Kirim Pengumuman, panel batasan + aktivitas.
- Route baru `POST /dosen/announcements` (`dosen.announcements.store`, perm `announcements,create`): tujuan dibatasi server-side ke `mahasiswa` (semua) atau `tim:<nama>` yang diverifikasi milik `RuanganBookingService::timBimbingan` (manpro/co-manpro). Prioritas normal/penting/urgent, draft/publish, expires maks 30 hari, kuota 10/hari.
- `AnnouncementObserver::recipientUserIds` diperbaiki: `target_tim` (nama tim) kini memfilter `users.tim` — pengumuman tim tidak lagi push ke semua mahasiswa.
- `dosen.saran` → `DosenSaran` (React): KPI, kartu tiket SM-YYYY-####, status Baru/Dalam Proses/Ditanggapi, tanggapan admin, tombol "Balas tanggapan" (buat saran lanjutan `Re:` via `dosen.feedback.reply`) dan "Tandai dibaca". Route `dosen.feedback.index`/`store`/`read` tidak berubah.
- Sidebar Proyek PBL bertambah "Grup Chat Tim" (tag Soon, React + Blade) — rancangan chat manpro↔ketua↔anggota, belum ada backend; dicatat di decisions.md.
- Belum diverifikasi: alur push notification FCM ke anggota tim (perlu device token aktif).

### Admin: menu Proyek PBL + resolusi anggota tim pengumuman (2026)
- Sidebar admin (React `AdminShellProps` + Blade `admin/_sidebar`) kini punya grup **Proyek PBL** di seksi Data & Pengguna: Semua Proyek (`admin.pbl.index`), Penempatan Mahasiswa (`#penempatan-mhs`), Impor CSV (`#import-csv`). Halaman `admin/pbl` (Blade `admin.pbl_projects`) memang sudah lengkap: CRUD proyek, staff manpro/co-manpro, ketua/wakil, penempatan & clear tim mahasiswa, impor/sinkron CSV — proteksi `perm:users` (admin only).
- Resolusi penerima pengumuman tim diperluas: `Announcement::forAudience` (mahasiswa) dan `AnnouncementObserver::recipientUserIds` kini mengenali anggota lewat `users.tim` = nama proyek **ATAU** record `pbl_team_members` → menutup kasus anggota tanpa `tim` tersinkron. Data saat ini sudah tersinkron semua; clause baru adalah pengaman.

### Admin: Kelola Proyek PBL → React `AdminPbl` (2026)
- `admin.pbl.index` kini merender halaman React `AdminPbl` (AdminShell): hero, 4 KPI (Total/Aktif/Selesai/Anggota), tab **Daftar Proyek · Penetapan Dosen · Monitoring Progress**, filter (status/tahun ajaran/manpro/cari) + toggle tabel↔kartu.
- Drawer wizard 4 langkah (Informasi → Dosen Pembimbing → Pengaturan Tim → Konfirmasi) untuk membuat/mengubah proyek; field tambahan mockup (tahun ajaran, kategori, semester, periode, deskripsi, progress) disimpan di `PblProjectMeta` JSON (tanpa ubah skema). `store`/`update` menerima field meta opsional.
- Modal detail per proyek: anggota lengkap + aksi ketua/wakil/keluarkan (endpoint `admin.pbl.*` lama tetap dipakai), tambah anggota dari `unassignedMahasiswa`. Impor CSV pindah ke modal dalam halaman; submenu Penempatan/Impor dihapus dari sidebar → diganti "Penilaian & Export" + "Grup Chat Tim" (Soon).
- `PblProjectMeta` diperluas: `tahun_ajaran`, `kategori`, `semester`, `periode_mulai`, `periode_selesai`.
- Blade lama `admin.pbl_projects` tidak lagi dipakai index (file dibiarkan); helper `mahasiswaPlacementQuery`/`buildPlacementCards` masih dipakai endpoint `search.json`.

### Fix crash beranda mahasiswa + splash/loading baru (14 Nov 2026)
- Bug: `buildMonthCalendar` mengembalikan `month_value`/`month_label` sementara frontend `CalendarData` mengharapkan `bulan`/`bulan_label` → `cal.bulan` undefined → `nav.bulan.split` crash ("Cannot read properties of undefined (reading 'split')"). Ditambah alias `bulan`, `bulan_label`, `prev`, `next`, `weeks_start` pada return (dashboard, halaman kalender, endpoint `calendar.month` terpengaruh sama).
- Hardening: `user?.nama_lengkap?.split` (Dashboard), `a.nama_lengkap?.split` (RoomRequest).
- Loading baru: splash boot screen branded (logo mark + cincin conic-gradient spin + orb blur + bar shimmer) di `app.blade.php` & `app-admin.blade.php` — muncul instan pra-React, fade-out saat load/fallback 5s. Progress bar Inertia kustom: `includeCSS:false` + gradasi shimmer + peg glow di kedua app.css.

### Popup sambutan login semua role (14 Nov 2026)
- `AuthController` menambah flash `login_welcome` untuk keempat role (mahasiswa kini juga dapat flag, sebelumnya hanya `success`).
- `HandleInertiaRequests` membagikan `flash.login_welcome` via `session()->pull` — tampil sekali lalu terkonsumsi.
- React `AdminShell` (admin/laboran/dosen) dan student `AppShell` (mahasiswa) memunculkan toast SweetAlert2 `Selamat datang, {nama}!` + keterangan peran saat dashboard pertama dimuat; dark-aware. `app.blade.php` kini memuat CDN SweetAlert2 untuk student app. Halaman Blade lama tetap memakai toast `login_success` yang sudah ada di `layouts/dashboard`.

### Status pengajuan ruangan diperketat — bukti kedatangan wajib (15 Nov 2026)
- Keluhan pemilik: pengajuan `disetujui` otomatis jadi "Selesai" begitu `jam_selesai` lewat walau pemohon tidak pernah datang dan mesin tidak melapor.
- `RuanganPengajuan::statusEfektif()` kini: `disetujui`+lewat → `selesai` **hanya bila** `adaKedatanganRuangan()` menemukan `ruangan_access_events` dengan `izin_id` milik izin pengajuan itu dan `hasil='diizinkan'` ATAU `jenis='buka'` dalam jendela mulai−30m s.d. selesai+15m. Tanpa bukti → `kedaluwarsa`. Status terminal (ditolak/dibatalkan/selesai/kedaluwarsa) tak berubah.
- Memo `memoKedatangan` per instance membatasi query tambahan; tidak ada penulis `STATUS_SELESAI` lain di `app/` sehingga perubahan ini satu-satunya jalur "selesai".
- Frontend stepper sudah menangani `kedaluwarsa` → "Alur berakhir — Kedaluwarsa" (tanpa centang "Datang ke Ruangan").
- Uji tinker pada pengajuan #1 (disetujui, lewat, 0 event akses): `statusEfektif` = `kedaluwarsa` — sebelumnya `selesai`.
- Catatan: status efektif tetap komputasi, kolom `status` tersimpan `disetujui` — konsisten dengan desain lazy-expire yang ada.

### Riwayat pengajuan ruangan → kartu grid + filter + pagination (15 Nov 2026)
- `RiwayatTab` di `RoomRequest.tsx` ditulis ulang ala mockup: bar filter (cari nama ruangan, periode semua/7h/30h/bulan ini, ruangan, status), kartu horizontal 2 kolom (foto ruangan `foto_url` atau placeholder gradasi + badge kode, nama + badge status efektif, tanggal/jam/jumlah peserta, tombol "Lihat Detail"), pagination "Menampilkan X–Y dari N" + nomor halaman + pilihan 6/12/24 per halaman. Pagination & filter client-side (data ≤50 baris dari backend).
- Modal detail kini menampilkan **Pemohon** (`pengaju_nama` dari snapshot pengaju/pengaju relation) dan **Tim PBL** (`tim_nama` dari `project` relation); daftar nama peserta dihapus sesuai permintaan — kartu tetap menampilkan jumlah peserta.
- Backend `RuanganPengajuanController::indexMahasiswa` eager-load `project` + `pengaju` dan append `tim_nama`, `pengaju_nama`, `ruangan.foto_url`.

### Kebijakan pengajuan ruangan H-1 + info jelas (15 Nov 2026)
- Aturan baru `min_advance_days` (default **1**) di `Ruangan::aturan()`: pengajuan paling lambat H-{min} sebelum pemakaian — booking untuk hari yang sama ditolak bila min>0. `max_advance_days` tetap batas paling jauh.
- `RuanganBookingService::ajukan` dan `ketersediaanHarian` memvalidasi batas minimum; pesan error eksplisit menyebut tanggal paling cepat. Payload ketersediaan kini memuat `min_advance_days`.
- UI mahasiswa `RoomRequest`: date picker `min`=hari ini+minAdv, `max`=hari ini+maxAdv, teks info "pengajuan paling lambat H-1 · paling jauh H+2" di header kartu, bawah input tanggal, dan blok alasan. Asisten AI (AssistantWidget) mulai menawarkan tanggal dari minAdv (tidak lagi menawarkan "Hari ini" bila min=1); konteks `mahasiswa.help.context` mengirim `min_advance_days`.
- Admin `Ruangan.tsx`: field baru "Min. Pesan di Muka (hari)" di form buat/edit + panel pengaturan akses; `AdminRuanganController` memvalidasi & menyimpan via `aturanDari`.
- Test disesuaikan: `test_status_waktu_selesai_dan_kedaluwarsa` kini menguji aturan bukti akses (tanpa event→kedaluwarsa; dengan event `check`/`diizinkan`→selesai); ruangan `$sore` di test ketersediaan diset `min_advance_days=0` agar skenario "slot hari ini lewat" tetap teruji.

### Chatbot FAB bisa digeser (15 Nov 2026)
- `AssistantWidget`: pointer-drag pada `.sb-asst-fab` (ambang 6px, clamp viewport 8px), posisi disimpan `localStorage('sb-asst-fab-pos')`; klik tetap membuka panel (suppress saat drag), pill sapaan ikut berpindah. Panel chat sendiri tetap di sisi kanan seperti biasa saat dibuka.

### Sidebar mahasiswa — Coming Soon ala admin (15 Nov 2026)
- `navSegera` diganti item nyata: **Penilaian Tim**, **Chat Grup Tim**, **Riwayat Chat Asisten** — semua `soon:true`, badge ungu "(soon)", klik memunculkan SweetAlert "Segera hadir — masih tahap pengembangan" (bukan navigasi halaman). Berlaku juga di hasil MenuSearch. "Riwayat Laporan" tetap item nyata di grup Laporan & Bantuan.

### Sinkronisasi status + profil anggota + navigasi kalender (15 Nov 2026)
- **Fix inkonsistensi status**: kartu "Perlu Perhatian" di beranda mahasiswa memakai `p.status` mentah dan fallback `'Ditolak'` — pengajuan `kedaluwarsa` tampil "Ditolak" sementara riwayat menampilkan "Kedaluwarsa". Badge kini memakai `status_efektif` dengan label peta lengkap (disetujui/ditolak/selesai/kedaluwarsa/dibatalkan/menunggu); `menunggu_penerimaan` tetap label khusus.
- **Profil anggota tim (non-sensitif)**: kartu anggota di `Team.tsx` bisa diklik → dialog berisi avatar besar (ketuk → zoom layar penuh `z-70` dengan tombol tutup), nama, NIM, kelas, peran tim, tim PBL+kode, manpro, co-manpro, shift. Tidak ada data sensitif (kontak/password/fingerprint tidak dikirim ke props).
- **Kalender mahasiswa**: judul bulan kini tombol → popover pilih bulan (navigasi tahun ‹› + grid 12 bulan, klik langsung `?bulan=YYYY-MM` via Inertia preserveState). Tombol "Hari Ini" menutup picker + kembali ke bulan berjalan & menyeleksi tanggal hari ini. "Agenda Terdekat" tetap lingkup bulan yang ditampilkan dengan subjudul menyebut nama bulan; titik prioritas berkedip santai (`sbPrioBlink`) — tinggi 1.1s, sedang 1.9s, normal 2.8s, rutin 4s; hormati `prefers-reduced-motion`.

### Panduan Penggunaan mahasiswa → React + animasi (15 Nov 2026)
- `StudentPortalController::guide` kini `Inertia::render('Student/Guide')` (Blade `student.guide` tidak lagi dipakai; file dibiarkan). Nav item "Panduan Penggunaan" beralih dari `external` ke Inertia — `active` pada `/mahasiswa/panduan`.
- `Guide.tsx`: hero gradasi (badge Pusat Bantuan + ikon BookOpen melayang + blur orbs), pencarian panduan dengan shortcut Ctrl/Cmd+K (Esc membersihkan), panel dua kolom — daftar 6 topik berikon di kiri, detail kanan: badge kategori, kotak Ringkasan, Langkah bernomor stagger, kartu ilustrasi CSS (login form, mesin fingerprint pulse, tabel status absensi, tiket laporan, timeline pengajuan ruangan H-1+tap mesin, email OTP), kotak Ketentuan hijau.
- Animasi: `sbGuidePanelIn` saat ganti topik (key remount), step stagger, mock scale-in, ikon melayang, cincin fingerprint pulse; semua hormati `prefers-reduced-motion`.
- Topik baru "Mengajukan Pemakaian Ruangan" mendokumentasikan aturan H-1 dan bukti kehadiran tap mesin (kedaluwarsa tanpa tap).

### Fix halaman login — teks hilang saat tema gelap (15 Nov 2026)
- **Gejala**: halaman `/login` tampil dengan teks role/title hampir tak terlihat — label "Masuk Sebagai" dan judul "Selamat Datang Kembali" nyaris putih di atas kartu putih.
- **Akar**: semua teks auth memakai variabel `sb-text`/`sb-muted`/`sb-text2`; bila `data-theme="dark"` tersimpan dari sesi sebelumnya, variabel itu berubah jadi warna terang sementara `.sb-auth-card` tetap putih → teks putih di atas putih.
- **Fix**: pin palet light lengkap pada kontainer `.sb-auth` di `app.css` (surface, border, text, primary, warna status) — halaman login selalu terang dan kontras, tidak lagi bergantung pada tema yang tersimpan. Tidak ada perubahan markup/flow login; admin tidak terdampak (admin app tidak memakai `.sb-auth`).
- Validasi: `npm run build` ✓.

### Import Akun admin → React wizard 6 langkah (15 Nov 2026)
- `AccountImportController::index` dan `::show` kini `Inertia::render('AdminImport')` (root `app-admin`) menggantikan Blade `admin.users_import`/`admin.users_import_result` (file Blade dibiarkan, tak dipakai route). Props: shell (menu/notif), `types`+kolom, URL template per jenis (.xlsx/.csv), `ttl_days`, dan pada mode `result`: payload ringkas (token/type/file_name/state/notices/totals + rows dipangkas ke line/identifier/nama/status/reason), `labels`, `id_label`, `can_apply`, `cards_available`, `result_urls` (apply/report/cards).
- `AdminImport.tsx`: stepper 6 langkah (Informasi → Unggah File → Validasi → Hasil Validasi → Konfirmasi → Selesai) ala mockup — hero + 3 kartu info + unduh template per-role (select + xlsx/csv), drop-zone drag&drop dengan validasi ukuran/ekstensi, animasi progres + checklist validasi selama POST preview, KPI + tab filter + tabel ber-paginasi, panel konfirmasi dengan ringkasan & catatan, layar selesai dengan KPI + unduh laporan CSV + form POST kartu aktivasi + tautan import ulang/data pengguna.
- Endpoint existing tidak berubah: `preview` POST (throttle 20/mnt) → redirect `show`; `apply` POST → redirect `show` (state `applied` → langkah 6); `report` GET & `cards` POST tetap unduhan CSV (form native karena Inertia tidak bisa mengunduh file).
- Sidebar: item "Import Akun" baru di grup Data Pengguna (React `AdminShellProps` + Blade `_sidebar`), guard `perm:users,create`, aktif pada `admin.users.import*`.
- Validasi: `php -l` bersih; `tsc` 0 error; build ✓ (chunk AdminImport); `route:cache` + `view:clear` OK. Belum uji upload file nyata di browser.

### Import Akun — tipe "Semua Role" + Petugas/CS + fix warna select (15 Nov 2026)
- **Fix UI**: `<option>` select wizard import kini berlatar `var(--ad-pop)` + teks `var(--ad-text)` — dropdown tidak lagi transparan/sulit dibaca di tema gelap.
- **Tipe `semua` (Semua Role campuran)**: satu file memuat baris mahasiswa/dosen/laboran. Kolom: `id` (generik NIM/NIDN/NITK), `nama_lengkap`, `role` (wajib mahasiswa/dosen/laboran), + kolom mahasiswa opsional (wajib bila role=mahasiswa). `validateRow` menentukan role per baris (`row['role']` + `values['role']`), apply memakai `$rowType` — mahasiswa → aktif+password sementara+finger, dosen/laboran → menunggu aktivasi+kode. `userAttributes` membaca role dari values. `cardsCsv` kini menerima dua URL (login mahasiswa + aktivasi) dan menulis URL per role kartu; header kolom `kode_atau_password_sementara` saat campuran. `reportCsv` menulis role per baris.
- **Tipe `petugas` (Cleaning Service)**: CS **tidak punya akun portal** — import membuat record `ruangan_petugas` (akses lab lewat mesin, tanpa login web). Kolom: `id` (id_internal), `nama_lengkap`, `tipe_kredensial` (pin/rfid/fingerprint), `kredensial` (PIN→bcrypt, RFID→sha256-upper, fingerprint→referensi apa adanya; sama seperti `hashKredensial` petugasStore), `berlaku_mulai`/`berlaku_selesai` opsional. Status aktif, `jadwal=[]` — jadwal ruangan diatur admin di halaman Petugas. Dedup via `id_internal` (file & DB); tidak ada kartu aktivasi.
- `AccountImportTemplate`: `TYPES` += `semua`,`petugas`; label, `identifierColumn='id'`, `columns()` cabang baru, alias `nitk_nidn/id_internal/identitas/id_login`→`id`. Header nim/nidn/nitk tetap ditolak untuk kedua tipe baru.
- Frontend `AdminImport`: `ROLE_DESC` untuk `semua` & `petugas` — dropdown jenis mengambil `props.types` (otomatis memuat tipe baru).
- Validasi: `php -l` semua bersih; tinker preview `petugas` (2 siap, 1 gagal nama kosong) & `semua` (3 siap campur role, 1 gagal role invalid) benar; `tsc` 0 error; build ✓; `route:cache` OK. Belum uji apply end-to-end ke DB.

### Import "Semua Role" — dukung format template multi-sheet user (15 Nov 2026)
- **Format `semua` disamakan dengan template user** (`Template_Import_Akun.xlsx`): kolom identitas terpisah `nim`/`nidn`/`nitk` (isi tepat satu per baris — role disimpulkan otomatis: nim→mahasiswa, nidn→dosen, nitk→laboran; kolom `role` opsional, dicek konsistensinya), `nama_lengkap`, kolom mahasiswa (kelas/tim/angkatan/semester/semester_type/shift/program_studi — wajib untuk baris mahasiswa). Kolom email/no_hp tetap diabaikan (kebijakan aktivasi mandiri) dengan notice.
- **TabularFileReader**: kini menerima workbook multi-sheet — memilih sheet dengan skor header tertinggi (>=2 sel cocok nama kolom yang dikenal) daripada menolak; `read()` menerima daftar `expected` header dari service; `splitHeader` mendeteksi baris header lewat skor kolom (menoleransi baris judul di atas header, seperti baris 1–3 template user) dan memangkas baris kosong di ekor data.
- **mapHeaders**: urutan diperbaiki — kolom resmi dicek dulu baru penolakan nim/nidn/nitk lintas jenis (agar tiga kolom identitas sah untuk tipe `semua`); validasi tambahan: tipe `semua` wajib punya minimal satu kolom identitas.
- **Pengaman**: baris dengan nama diawali "CONTOH" selalu `dilewati` (baris contoh template tidak pernah jadi akun); `tim` dinormalisasi — format "KODE | Nama" dipotong ke nama, dan kode/nama proyek dipetakan ke `PblProject.nama` resmi (cache statis per proses).
- **Terverifikasi pada file asli user**: preview `semua` atas `Template_Import_Akun.xlsx` → sheet IMPORT_AKUN terpilih, header baris 4 terdeteksi, 3 baris CONTOH dilewati, 200 baris kosong ekor dibuang, notice email/no_hp benar. Template unduhan `semua` (.xlsx/.csv) memakai susunan kolom yang sama.
- Validasi: `php -l` bersih; `tsc` 0 error; build ✓; `route:cache` OK.

### Template import per-role + password sementara semua role (15 Nov 2026)

- `AccountImportTemplate` kini membuat **file berbeda per jenis** (`filename()`): `Template_Import_Akun_SmartBrail_{Mahasiswa|Dosen|Laboran|Cleaning_Service|Semua_Role}.xlsx/csv`.
- `.xlsx` multi-sheet baru: **PETUNJUK** (cara pakai, kebijakan password sementara, panduan kolom) + **IMPORT_AKUN** (header + baris CONTOH) + **DAFTAR_PBL** (kode/nama/manpro/co_manpro/shift dari `PblProject` aktif, hanya mahasiswa/semua). Semua sel inlineStr format Teks; header dibekukan.
- `AccountImportController::template()` mengirim daftar proyek ke generator; route `whereIn` sudah mencakup semua tipe.
- **Password sementara untuk semua role portal**: `AccountActivation::issueTemporaryPassword()` (umum, turunan dari alur mahasiswa) dipakai `apply()` untuk mahasiswa/dosen/laboran — akun langsung `approved`+`is_active`+`must_change_password`; `regenerateCode()` di controller juga direluskan ke semua role aktif.
- **Login password sementara dilebarkan ke semua role** di `AuthController` (gate `isMahasiswa()` dihapus dari `usingTempPassword`, fallback check, dan cek kedaluwarsa). Redirect pasca-login: mahasiswa → `mahasiswa.password.change`; lainnya → `account.password.change` (route + controller `AccountPasswordController` + view `auth.force-change-password` baru). `EnsurePasswordChanged` kini role-aware.
- `cardsCsv()` menerima peta `role => URL login` (kolom `password_sementara` + `halaman_login` per role); petugas tidak punya kartu (bukan akun portal).
- **Petugas/CS**: kolom `nitk` diterima sebagai alias `id`; `tipe_kredensial`/`kredensial` opsional — kosong = PIN acak (hash) yang diatur ulang di Petugas Ruangan. File CS pemilik (kolom nitk/nama/email/no_hp/role) terbaca.
- `validateRow`: baris yang hanya mengisi kolom `role` (penanda template) → `dilewati`; kolom `role` kembali opsional-tervalidasi di template single-role.
- Validasi: `php -l` bersih; preview 4 file pemilik → semua baris penanda/CONTOH `dilewati`, 0 gagal; CSV data nyata tanpa email/no_hp → `siap` (mhs/dosen/laboran via `semua`/petugas); `tsc` 0 error; build Vite ✓; `route:cache` OK. `apply` end-to-end ke DB belum diuji.

### Profil & akses mesin: no_hp + kartu RFID + PIN akses semua role (15 Nov 2026)

- Kolom `users.no_hp`, `users.rfid_uid`, `users.akses_pin` (hashed) sudah ada di skema BMS — `AksesRuanganService::resolveIdentitas` sudah menerima fingerprint/rfid/pin untuk user & `ruangan_petugas`. Tidak ada push ke mesin: mesin mengirim kredensial saat tap, backend yang memutuskan — jadi mengisi field di admin = kredensial langsung berlaku.
- **Profil Pengguna** (`profiles/show.blade.php`): field `Nomor HP` kini tampil di blok mahasiswa & laboran (dosen sudah ada); `Kartu Akses (RFID)` + `PIN Akses Ruangan` (Terkonfigurasi/Belum diatur — nilai PIN tak pernah ditampilkan) di ketiga blok role; label "Aplikasi Mobile Mahasiswa" → "Portal Mahasiswa", "Login mobile terakhir" → "Login terakhir".
- **Form admin** (`AdminController::storeUser`/`updateUser` + `Users.tsx`): field `no_hp`, `rfid_uid` (uppercase, unik), `akses_pin` (4–20, Hash::make, kosong=tetap); row payload += `no_hp`,`rfid_uid`,`has_akses_pin`; `can_issue_code` dilebarkan ke semua role aktif-approved (regenerateCode sudah umum). Edit non-mahasiswa tidak lagi men-null-kan `finger_id` — staf boleh punya fingerprint.
- Validasi: `php -l` bersih; `tsc` 0 error; build ✓; `route:cache` + `view:clear` OK.

### Kartu akses otomatis + fix pagination Users (15 Nov 2026)

- `App\Support\KartuAksesAllocator` (pola `FingerIdAllocator`): tiap akun baru otomatis mendapat `rfid_uid` virtual `SBK00001…` (unik, bukan hex murni → tidak mungkin cocok tap kartu fisik). Dipanggil di `storeUser` (semua role) dan `AccountImportService::apply` (semua role portal). Saat kartu fisik diterbitkan, admin mengganti UID via form edit.
- `cekIzin()` tetap sumber keputusan: tap PIN/RFID/fingerprint tanpa `ruangan_izin` aktif → `tidak_ada_izin`/ditolak — PIN tidak bisa membuka ruangan sembarangan.
- Fix pagination Data Pengguna: JSX memakai `ad-mesin-pager__btns` + button polos tetapi CSS hanya ada `__links`/`.ad-mesin-page` → tampil `‹12…5›` mentah. Ditambah CSS `.ad-mesin-pager__btns`/`__ellipsis` selaras tema.
- Validasi: `php -l` bersih; `tsc` 0 error; build ✓.

### Hak akses ruangan per role + backfill kartu akses (15 Nov 2026)

- `AksesRuanganService::cekIzin()`: **admin/laboran/dosen → selalu diizinkan** (`alasan='akses_staf'`); mahasiswa tetap wajib `ruangan_izin` aktif; CS via `cekIzinPetugas` (jadwal + masa berlaku). Semua tap tetap tercatat di `ruangan_access_events` — siapa, kapan, ruangan mana, hasil, alasan.
- Tab Riwayat di `/admin/petugas`: label jenis += tolak/telemetri; `keterangan` kini menerjemahkan kode `alasan` (`alasanAksesLabel` — mis. "Hak akses staf (via PIN)", "Izin sudah kedaluwarsa (via kartu)").
- Backfill `rfid_uid` virtual `SBK00001…` ke 64 akun existing (1 admin, 1 laboran, 1 dosen, 61 mahasiswa) via `KartuAksesAllocator::ensure` — kolom unique menjamin tanpa duplikat.
- Validasi: `php -l` bersih; `tsc` 0 error; build ✓.

### Arsip Approval dihapus + password sementara jadi peringatan non-blocking (15 Nov 2026)

- **Tab "Arsip Approval" dihapus** dari `/admin/users` (React `Users.tsx`): tabel arsip, filter log (`log_q/log_action/log_role/log_actor`), link "Lihat Semua", dan prop backend `arsip`/`log_filters`/`log_actors`/`tab` ikut dicabut dari `AdminController::users()` — query `AccountApprovalLog` arsip tidak lagi dijalankan (feed "Aktivitas Terbaru" 6 item tetap ada). `Users.tsx` sempat rusak saat splice; section "Daftar Pengguna" direkonstruksi penuh (tabel desktop + kartu mobile `ad-usr-mcard` + pager ber-ellipsis). `AccountApprovalLog` tetap ditulis untuk audit — hanya UI arsip yang dihapus.
- **`password.changed` dicabut dari route group `auth`** — pengguna dengan `must_change_password` tidak lagi diblokir ke halaman ganti password; semua fitur bisa dipakai normal. Redirect pertama-login ke halaman ganti password **dipertahankan** (satu kali, mengajak — bukan mengurung). Middleware `EnsurePasswordChanged` masih terdaftar tapi tak terpasang; `must_change_password` tetap di-shared via `HandleInertiaRequests`.
- **Indikator kuning non-blocking**: banner peringatan di `AppShell` mahasiswa ("Anda bebas memakai semua fitur…") + `AdminShell` (admin/laboran/dosen); titik kuning di avatar & item "Keamanan"/"Amankan Akun" menu akun; kartu peringatan di Profil → Keamanan Akun; tautan "Nanti saja — lanjut ke halaman utama" di halaman ganti password staf. Semua hilang otomatis saat flag dibersihkan setelah ganti password.
- Validasi: `php -l` bersih; `tsc` 0 error; build ✓; `route:cache` + `view:clear` OK.

### Asisten mahasiswa lebih kontekstual (15 Nov 2026)

- **Intent baru `buildLocalStudentAiReply`** (mode lokal, dipakai juga sebagai fallback Gemini): tim PBL (resolve `timMahasiswa` nyata — tanpa tim dijelaskan penugasan oleh dosen/laboran + ajakan lapor resmi; dengan tim ditampilkan nama/kode/Manpro/jumlah anggota), cara mengajukan ruangan (langkah + aturan H-1 + syarat tim), akses pintu (nomor kartu akses `rfid_uid` pengguna + aturan izin aktif + audit tap), password sementara/indikator kuning (reassurance bebas aktivitas + langkah ganti + jalur kredensial laboran), kontak bantuan (laporan resmi vs langsung), dan fallback terakhir kini daftar topik yang bisa dibantu alih-alih info jadwal generik.
- **`detectIssueKategori` diperluas**: pemicu menangkap `belum punya/memiliki/terdaftar/ditugaskan/dapat` dan `tidak ada/punya/ditugaskan`; kata `tim|pbl|proyek|penugasan|anggota|manpro` → kategori `akun` sehingga kartu "kirim laporan resmi" ikut muncul untuk keluhan penugasan tim.
- **Prompt Gemini diperkaya fakta akun**: tim PBL (nama/kode/Manpro/anggota atau belum), nomor kartu akses, status `must_change_password`, status sidik jari + ketentuan PBL/ruangan/password — jawaban AI tidak lagi menebak.
- **Widget**: chip "Belum punya tim?" di submenu Tim PBL; kartu `showTim` kosong kini menjelaskan siapa yang menugaskan + tombol "Laporkan ke Laboran".
- Validasi: `php -l` bersih; tinker — 8 skenario pesan menjawab cabang yang benar (kategori `aku belum punya tim` → `akun`); `tsc` 0 error; build ✓.

### Draft laporan chatbot ditulis asisten, bukan kutipan chat (15 Nov 2026)

- `issueReportData` kini menyusun draft per sub-topik: PBL → subjek "Kendala Penugasan Tim PBL" + kalimat pembuka "Saya belum terdaftar di tim PBL mana pun" (bila benar belum punya tim, dicek via `timMahasiswa`); akses/mesin → "Kendala Akses Ruangan"; akun → "Kendala Akun". Uraian berformat: pembuka + kutipan pesan pengguna yang dirapikan (whitespace dinormalisasi, dibatasi 400 char) + kalimat permohonan spesifik ("periksa penugasan/log tap/reset kredensial") — bukan lagi "Saya mengalami kendala: <teks mentah>".
- Validasi: `php -l` bersih; tinker menghasilkan draft ber-subjek dan ber-uraian rapi; build ✓.

### Local assistant "dilatih" — engine intent baru (15 Nov 2026)

- **`App\Support\StudentLocalAssistant` baru** menggantikan responder lama (`buildLocalStudentAiReply` kini delegasi; tetap menerima `?history`). Pipeline:
  1. **Normalisasi slang/typo** — kamus ~70 padanan (`gbsa`→tidak bisa, `blm`→belum, `pw`→password, `gmn`→bagaimana, `msin`→mesin, dll), penggantian per kata utuh setelah strip tanda baca.
  2. **Resolusi follow-up** — pesan ambigu pendek ("terus gmn?", "yang tadi") digabung dengan pertanyaan user terakhir di riwayat lalu di-scoring ulang.
  3. **Scoring intent berbobot** — tabel ~22 intent × kata kunci berbobot (frasa spesifik > kata umum), skor tertinggi ≥3 menang; sapaan/terima-kasih tidak boleh menutupi topik nyata dalam kalimat yang sama.
- **Handler berbasis data nyata**: tim PBL (penugasan/Manpro/anggota), status pengajuan terakhir, daftar ruangan aktif, absensi hari ini/kemarin (tap masuk·pulang·status·menit telat), rekap bulan ini (lengkap/tidak lengkap/alfa), status 3 laporan terakhir + indikasi balasan, 3 pengumuman terbaru, kartu akses `rfid_uid`, `must_change_password`, finger_id, status kunci login. Handler statis: aturan tap/toleransi, cara ajukan (H-1), akses pintu, kontak bantuan, panduan portal, tentang SmartBrail, capabilities.
- Kategori kendala tetap dari `detectIssueKategori` (kartu laporan resmi tetap muncul) dan frasa uji "tidak mengirim laporan" dipertahankan.
- Validasi: `php -l` bersih; tinker — 22 pesan (termasuk slang: "gbsa login nih", "udh absen blm ya", "mana kartu akses ku") + 1 follow-up semuanya masuk intent yang benar; build tidak terpengaruh.

### Penanda balasan laporan di sidebar + notifikasi balasan lebih profesional (15 Nov 2026)

- **`reply_unread_count` baru** di payload `navNotificationState` (jumlah `Suggestion` berbalasan dengan `user_read_reply_at` null). Badge merah tampil di item sidebar **Riwayat Laporan** dan header grup **Laporan & Bantuan** (mode rail → titik pada ikon); hilang otomatis saat mahasiswa membuka detail laporan (`readSuggestionReply` sudah menandai `user_read_reply_at`).
- **Format notifikasi balasan** diubah: judul "Balasan Admin Laboran", kategori "Balasan Laporan", kutipan `Re: <subjek bersih tanpa [tag]> — "<balasan>"`, meta nama pembalas, dan body terpisah rapi (tanggapan vs laporan asli). Judul push mobile senada ("Balasan Admin Laboran" + `Re: subjek`).
- Validasi: `php -l` bersih; `tsc` 0 error; build ✓.

### Pendalaman local assistant — memori masalah & status laporan (15 Nov 2026)

- **Normalisasi akhiran posesif** — kata ≥4 huruf dengan akhiran -ku/-mu/-nya dinormalisasi ke bentuk dasar (`laporanku`→laporan, `pengajuannya`→pengajuan, `jawabannya`→jawaban), sehingga variasi posesif tidak lagi luput ke fallback.
- **Kata kunci status laporan diperluas** — `respons/respon/dijawab/jawaban/balasan/ditanggapi/ditindaklanjuti/tindak lanjut/kelanjutan/hasil/terkirim/diproses/barusan/tadi` kini masuk `report_status`; `pengajuan/disetujui/ditolak/diputuskan` masuk `room_status`.
- **Pembeda niat lampau vs rencana** — "barusan kirim laporan" → status laporan; "mau/buat/laporkan" → intent buat laporan (issue).
- **`rReportStatus` menjawab langsung** — bukan lagi daftar mentah: laporan terbaru dijawab **"Sudah dijawab!"** dengan nama pembalas, waktu, dan kutipan balasan; atau **"Belum dijawab"** dengan status + ekspektasi tindak lanjut. Laporan lama tetap tercantum di bawahnya.
- Validasi: `php -l` bersih; 15+ skenario tinker (slang, posesif, lampau vs rencana) masuk intent yang benar.

### Pelatihan lanjutan local assistant — typo, ambiguitas, frustrasi, learning loop (15 Nov 2026)

- **Toleransi salah ketik** (`fixTypos`) — token ≥4 huruf yang tak dikenal dibandingkan Levenshtein ke kosakata kata kunci (jarak ≤1 untuk 4–6 huruf, ≤2 untuk ≥7): `abensi`→absensi, `pengajuaan`→pengajuan.
- **Klarifikasi ambiguitas** — bila dua intent bersaing (selisih ≤2 dan skor <7), asisten menanyakan topik ("maksud Anda jadwal Anda atau status pengajuan?") via `INTENT_LABELS`, bukan menebak.
- **Intent `frustration`** — "kok lama", "parah", "bukan itu maksudku", "tidak kunjung" → jawaban empatik + status laporan pending nyata + tawaran buat tiket.
- **Learning loop** — pertanyaan yang jatuh ke fallback dicatat ke `storage/logs/assistant-unmatched.log` (`[waktu] u{id}: pertanyaan`) untuk ditinjau saat penambahan intent berikutnya.
- Validasi: `php -l` bersih; skenario typo/frustrasi/ambig tinker masuk intent benar.

### Pelatihan penuh local assistant — multi-intent, intent baru, file pengetahuan, umpan balik (15 Nov 2026)

- **Multi-pertanyaan** — pesan dipisah pada " dan / serta / lalu / sekalian / terus "; bila ≥2 intent berbeda masing-masing skor ≥3, keduanya dijawab berurutan dipisah `— — —`.
- **Intent baru** — `room_location` (lokasi/denah/fasilitas ruangan; nama ruangan spesifik dijawab langsung dari kolom `ruangan.lokasi`+`fasilitas` array) dan `card_lost` (prosedur kartu hilang/rusak: lapor laboran → admin terbitkan pengganti → izin tetap wajib). Aturan konteks: "hilang/kehilangan"+"kartu" selalu → `card_lost`.
- **File pengetahuan** `storage/app/assistant-knowledge.md` — format `## kata kunci | frasa lain` lalu jawaban multi-baris; dimuat ulang otomatis saat file berubah (cache per-mtime). Dipakai hanya bila intent bawaan tidak cocok — admin dapat "melatih" asisten tanpa mengubah kode.
- **Umpan balik per jawaban** — tombol 👍/👎 di bawah tiap balasan AI bebas (`q`+`fb` di Msg); `POST /mahasiswa/bantuan/feedback` (`mahasiswa.help.feedback`) menulis ke `storage/logs/assistant-feedback.log` dengan marker `OK`/`BAD` + pasangan Q/A. Bersama `assistant-unmatched.log` menjadi bahan pelatihan lanjutan.
- Validasi: `php -l` bersih; skenario multi-intent/ruangan/kartu/knowledge tinker benar; `tsc` 0 error; build ✓; `route:cache` OK.

### Pelatihan penuh local assistant — multi-intent, intent baru, file pengetahuan, umpan balik (15 Nov 2026)

- **Multi-pertanyaan** — pesan dipisah pada " dan / serta / lalu / sekalian / terus "; bila ≥2 intent berbeda masing-masing skor ≥3, keduanya dijawab berurutan dipisah `— — —`.
- **Intent baru** — `room_location` (lokasi/denah/fasilitas; nama ruangan spesifik dijawab langsung dari `ruangan.lokasi` + `fasilitas`) dan `card_lost` (prosedur kartu hilang/rusak). Aturan konteks: "hilang/kehilangan"+"kartu" selalu → `card_lost`.
- **File pengetahuan** `storage/app/assistant-knowledge.md` — format `## kata kunci | frasa lain` lalu jawaban multi-baris; dimuat ulang otomatis saat file berubah (cache per-mtime). Dipakai hanya bila intent bawaan tidak cocok — admin dapat "melatih" asisten tanpa mengubah kode.
- **Umpan balik per jawaban** — tombol 👍/👎 di bawah balasan AI bebas; `POST /mahasiswa/bantuan/feedback` (`mahasiswa.help.feedback`) menulis `OK`/`BAD` + pasangan Q/A ke `storage/logs/assistant-feedback.log`. Bersama `assistant-unmatched.log` jadi bahan pelatihan lanjutan.
- Validasi: `php -l` bersih; skenario tinker benar; `tsc` 0 error; build ✓; `route:cache` OK.

### Umpan balik 3 tingkat + pelabelan topik (15 Nov 2026)

- Widget: tombol 😞 buruk / 😐 cukup / 😄 sangat puas di bawah balasan AI; pesan terima kasih berbeda per tingkat.
- `helpFeedback` kini memvalidasi `level` (bad|ok|great) dan menulis `topik=<intent>` di `assistant-feedback.log` via `StudentLocalAssistant::topicOf()` (API publik baru) — jadi penilaian bisa dianalisis per topik (laporan, akses, kendala, dst) untuk pelatihan lanjutan.
- Validasi: `php -l` bersih; `tsc` 0 error; build ✓; `route:cache` OK.

### Koreksi agent dari uji nyata + ketahanan Gemini (15 Nov 2026)

- **Merge follow-up lebih selektif** — pesan yang sudah punya topik nyata tidak lagi digabung ke pertanyaan sebelumnya ("kalau cek pengajuan lain bisa?" → room_status, bukan capabilities).
- **Anti false-positive fuzzy** — `PROTECTED_WORDS`: kata fungsi umum (gimana, bagaimana, kenapa, kapan, dst) tidak pernah "dikoreksi" — memperbaiki bug `gimana`→`dimana` yang salah masuk room_location.
- **`room_howto` butuh konteks ruangan** — 'ajukan'/'mengajukan'/'pinjam' diturunkan bobotnya; frasa penuh ('ajukan ruangan', 'pengajuan ruangan') yang berbobot tinggi — "ajukan laporan" tidak lagi salah masuk cara pinjam ruangan.
- **Circuit breaker Gemini** (`HelpdeskAiService`) — setelah 1 kegagalan, `helpdesk_gemini_down` di-cache 10 menit; request berikutnya langsung ke jalur lokal tanpa menunggu timeout 5 dtk × multi-model (penyebab kartu "Gagal menghubungi asisten"). Sukses → flag dihapus.
- **Sanitasi API key di log** — pesan error cURL memuat `?key=`; kini diganti `***` sebelum ditulis.
- Knowledge ditambah: SLA respons laporan ("berapa lama laporan dibalas").
- Validasi: `php -l` bersih; skenario tinker terkoreksi semua.

### Lampiran foto laporan + Dashboard Pelatihan AI (15 Nov 2026)

- **Migrasi disetujui & dijalankan** — kolom `suggestions.lampiran` (path di disk `local`) ditambah; `Suggestion::$fillable` diperbarui.
- **Upload staged** — `POST /mahasiswa/bantuan/lampiran` (`mahasiswa.help.lampiran`): JPG/PNG/WebP ≤3MB → `lampiran-tmp/u{id}/{token}`, token dikirim ulang via `lampiran_token` di `storeSuggestion` → file dipindah ke `lampiran-saran/{id}.{ext}` dan path disimpan di kolom. Token tervalidasi regex + hanya milik pengguna.
- **Serve terproteksi** — `GET /mahasiswa/saran/{suggestion}/lampiran` (pemilik saja) dan `GET /admin/suggestions/{suggestion}/lampiran` (perm `suggestions,view`). `mapSuggestionRow` + payload `selected` admin membawa `lampiran_url`.
- **Widget** — `LampiranPicker` di kartu laporan (pilih → upload → chip nama + hapus); ikut saat konfirmasi; klip global kini mengarahkan ke alur laporan.
- **Tampilan** — thumbnail di Riwayat Laporan mahasiswa dan detail `SaranMasukan` admin.
- **Pelatihan AI (`/admin/ai/training`, menu "Pelatihan AI")** — ringkasan pertanyaan tak terjawab (frekuensi), umpan balik 😞/😐/😄 per topik, daftar jawaban buruk terbaru (Q↔A), dan editor `assistant-knowledge.md` langsung dari UI (perm `ai_assistant,update`).
- Validasi: `php -l` bersih; migrasi DONE; `tsc` 0 error; build ✓; `route:cache` OK. Upload end-to-end belum diuji manual.

### Kalender & Agenda (2026-11-14, tambahan)

- Kolom `prioritas` di `absensi_kalender_libur`: rendah|normal|penting|mendesak (default `normal`), migrasi sudah dijalankan.
- Edit agenda kini memakai PATCH `admin/kalender/agenda/{libur}` (updateLibur) — bukan lagi POST/updateOrCreate, sehingga ganti tanggal tidak membuat duplikat.
- Form tambah/ubah punya pilihan Prioritas + multi-tanggal (tanggal_list) atau rentang; chip kalender/list menampilkan garis warna prioritas + legenda.
- Sidebar admin: ikon menu mendapat aksen warna deterministik per item (`data-accent` 0-9 di AdminShell + CSS `resources/js/admin/app.css`); item aktif tetap `is-active`.
- Validasi: `php -l`, `tsc` 0 error, `npm run build` OK, `route:cache` OK. CRUD create/update/delete prioritas belum diuji manual lewat UI.

### Lampiran Asisten AI Copilot (2026-11-14)

- `POST admin|dosen/ai/chat/attachment` (- Klik thumbnail lampiran membuka lightbox di tempat (bukan tab baru); footer chat dikoreksi (tidak lagi 'Baca-saja'); intent troubleshooting upload ditambah di Copilot (`localFallback`) dan asisten mahasiswa (`upload_help`).
- `GET ai/chat/attachment/{id}` (owner-only) menyajikan lampiran; gelembung chat menampilkan thumbnail gambar nyata (bukan teks 📎) baik untuk pesan baru maupun riwayat (meta.attachments menyimpan id+name+image). Bug `meta?->attachments` pada array cast — penyebab error 500 'Koneksi terputus' — diperbaiki.
perm ai_assistant,view): upload staged JPG/PNG/WebP + TXT/MD/CSV/LOG maks 4MB ke `storage/app/ai-lampiran/{user_id}/{uuid}`.
- `stream` menerima `attachments[]` (maks 3, id tervalidasi milik user, mime dicek ulang dari isi). Gambar → `inlineData` base64 ke Gemini (vision); file teks → disuntik sebagai part teks (maks 6k char). Nama lampiran disimpan di `AiMessage.meta`.
- Fallback lokal: lampiran tetap diterima dengan catatan jujur (gambar butuh AI online). Privasi: teks tetap disamarkan; gambar dikirim apa adanya ke provider — jangan lampirkan data sensitif.
- UI: tombol klip, pemilih file, dan paste screenshot langsung (Ctrl+V di kolom pesan — dinamai `Screenshot HH.MM.png`) → pemilih file (maks 3), chip preview "Mengunggah…", nama lampiran tampil di gelembung user ("📎 nama").
- Validasi: php -l, tsc 0 error, build OK, route:cache OK. Analisis gambar end-to-end BELUM diuji (Gemini sempat 429/503 — coba saat provider stabil).

### Pelatihan AI v2 + kemampuan lampiran Copilot (2026-11-14)

- Halaman `admin.ai.training` dibangun ulang: hero banner, 4 kartu statistik, sub-nav (Data Pelatihan, Data Respons, Umpan Balik & Evaluasi, File Pengetahuan, Topik & Kategori, Pengaturan), daftar respons berfilter (cari/topik/penilaian) + panel detail.
- `trainingLogs` kini mengembalikan `respons` (semua entri feedback log, maks 80). Aksi nyata: Edit Respons → draft di editor pengetahuan; Jadikan Data Pelatihan → append `## q` + jawaban ke `assistant-knowledge.md`; Hapus → `POST ai/training/respons/hapus` menghapus baris log yang cocok (perm ai_assistant,update).
- Copilot admin/dosen/laboran diajari kemampuan lampiran: system prompt Gemini menyatakan bisa membaca gambar/file lewat ikon klip; `localFallback` menjawab benar untuk pertanyaan "bisa baca png/jpg?" walau provider mati. Jawaban "tidak bisa" di screenshot lama berasal dari Gemini yang belum tahu fitur — sudah diperbaiki di dua jalur.
- Catatan deploy: fitur lampiran tidak butuh layanan tambahan — hanya kunci Gemini + disk `local` yang sama.
- Validasi: php -l, tsc 0 error, build OK, route:cache OK, jawaban lokal diuji tinker. Halaman belum dicek visual di browser; analisis gambar end-to-end belum diuji.

### Rebrand laporan + ketahanan Copilot (2026-11-14)

- Semua cetak/ekspor absensi: "SIMALAS"→"SmartBrail", logo `public/images/logo-smartbrail.png` (salinan dari branding/) — `reports_print.blade.php`, `SimalasExcelExportLayout` (Excel), `RekapAbsensiExportHtml` (Rekap Harian), Word export di `HandlesPeriodicReports`.
- `reports_print`: toolbar baru (Excel/Word/PDF jelas), Detail Aktivitas disusun ulang 6 kolom (Mahasiswa+NIM, Kelas·Tim, chip status berwarna, waktu d M Y + H:i terpisah).
- Halaman "Laporan Periodik" (blade `reports/periodic`) adalah halaman laporan aktif — bukan sisa tak terpakai; sidebar menunjuk ke sana.
- Copilot: circuit breaker `admin-ai-provider-down` (10 mnt) — saat Gemini 429/timeout, pesan berikutnya langsung mode lokal (tool laporan tetap jalan). Jika provider mati setelah kartu terkirim, jawaban ditandai "terpotong" alih-alih error keras.
- Validasi: php -l semua file, view:clear OK. Tampilan cetak/Excel belum dicek visual.


### Laporan Periodik — desain ulang + ekspor mengikuti template SmartBrail (16 Nov 2026)
- `reports/periodic.blade.php` direstrukturisasi ke layout mockup pemilik: hero gradien + panel "Unduh Laporan" (Excel/PDF/Word + tautan Rekap Harian), kartu "Filter Laporan" (periode kini `<select>` + bulan/dari-sampai kondisional; `lpTogglePeriode` diperbarui membaca select), 5 kartu KPI berikon (menambah "Mahasiswa dalam Laporan"), kartu Tren dengan toggle Grafik|Tabel (tabel harian dirender server dari `$chart`), legenda donut menampilkan hitungan+persen, dan kartu "Rekap per Mahasiswa (Dalam Laporan)" dengan pencarian klien + tombol unduh. Bagian rekor/aktivitas/modal tetap.
- `HandlesPeriodicReports::exportReportExcel` ditulis ulang sebagai **SpreadsheetML multi-sheet** sesuai `SmartBrail_Laporan_Periodik_Absensi.xlsx`: PETUNJUK, RINGKASAN, REKAP_MAHASISWA (kolom NIM/Nama/Kelas/Tim/Hadir/TL/Alfa/Total Sesi/% Hadir/Catatan), REKAP_HARIAN, DETAIL_ABSENSI (dari `detailRows`, maks 250 baris). File tetap `.xls` tapi kini berisi 5 worksheet nyata.
- `exportReportWord` direstrukturisasi sesuai `Template_Laporan_Absensi_SmartBrail.docx`: 01 INFORMASI LAPORAN (periode, tanggal cetak, mode, filter kelas/tim, disusun oleh penyusun login, diperiksa oleh), 02 RINGKASAN KEHADIRAN (5 metrik + catatan sesi), 03 REKAPITULASI PER MAHASISWA (+ kolom Aktivitas Terakhir). Blok rekor di Word dihapus agar sesuai template.
- Rute dosen (`dosen.reports*`) memakai view & ekspor yang sama — dosen tetap dapat unduh rekap tersaring kelas/tim; guard `guardDosenKelasAssignment` tidak berubah.
- Verifikasi: `php -l` OK, `view:clear`/`route:cache` OK, kompilasi blade `view('reports.periodic')` OK. Belum diverifikasi: render visual halaman baru, struktur sheet hasil unduhan Excel/Word di Excel/Word asli, alur unduh dosen.

### Penyelarasan sidebar Blade ↔ React + polesan periodik (16 Nov 2026)
- `layouts/dashboard.blade.php`: metrik sidebar Blade disamakan dengan AdminShell React — link 8×10px radius 9 font 13/600 op .88, ikon lucide svg 17px, group-toggle transparan non-uppercase font 13/600, submenu indent 19px+border kiri, section-title 10px/800, sep 9px 10px. Ditambah **aksen warna ikon `data-accent` 0–9** (palet sama persis React) lewat JS hash label ×31 mod 10 di layout — tanpa mengubah markup `_sidebar.blade.php`. Mode collapsed tetap jalan (svg 20px).
- `reports/periodic.blade.php`: `.lpx-filter__grid` diubah grid→flex-wrap (field Periode/Kelas/Tim sejajar andal).
- Verifikasi: kedua blade terkompilasi, `view:clear` OK. Belum dicek visual di browser.

### Laporan Periodik admin/laboran → React (16 Nov 2026)
- `HandlesPeriodicReports::reports()` kini merender halaman React **`LaporanPeriodik`** (root `app-admin`, props `menu`/`notif`/`report`/`urls`) untuk panel admin & laboran; blade `reports.periodic` tidak lagi dipakai untuk index (print/Word/Excel tetap berfungsi). Dosen tetap memakai `DosenReports` yang sudah React sebelumnya — alur satu pintu laporan kini semuanya React.
- `resources/js/admin/pages/LaporanPeriodik.tsx`: hero banner (campus-polybatam + overlay) dengan kartu "Periode Laporan" (rentang + durasi hari), kartu Filter (periode/kelas/tim + bulan/custom kondisional → `router.get`), 5 KPI gradien (cakupan, dalam laporan, hadir/TL/alfa + persen), Tren Kehadiran Harian dengan toggle Grafik (LineChart SVG 3 seri) ↔ Tabel, donut Komposisi + legenda hitungan+persen + top mesin, dan "Rekap per Mahasiswa (Dalam Laporan)" — pencarian, dropdown Unduh Data (Excel/Word/Cetak-PDF membawa query filter), header tabel berkelompok "Rekap Absensi (Sesi)", paginasi 10/halaman. Data `students` = `studentRecapsActive`.
- CSS `lpr-*` di `admin/app.css` (hero, kartu, KPI gradien, segmented, tabel, dropdown, pager); tipe `LaporanPeriodikProps` di `types.ts`.
- Belum diverifikasi visual di browser; modal detail mahasiswa per baris tidak diporting (nama tampil teks biasa).

### Rekap Harian admin/laboran → React (16 Nov 2026)
- `AbsensiManagementController::rekap()` kini merender halaman React **`RekapHarian`** (root `app-admin`) untuk admin & laboran; dosen tetap `DosenAbsensi`. `resolveRekapFilter` kini menerima `?date=YYYY-MM-DD` (default hari ini) sehingga pemilih tanggal berfungsi; `rekapState` menyimpan `allRows` (tanpa paginasi) untuk tabel klien.
- `resources/js/admin/pages/RekapHarian.tsx`: hero "Laporan Absensi Harian" + kartu Tanggal Laporan (label tanggal + rentang jam sesi), Filter Data (tanggal/kelas/tim), 5 KPI gradien (Total, Hadir, Terlambat, Tidak Lengkap=TL+masuk, Alfa=alfa+belum), donut Distribusi 5 kategori, bar chart "Tren Kehadiran per Sesi" (dari `sesi_trend` — tap masuk per slot_lab, server-side) dengan toggle Grafik/Tabel, feed Aktivitas Terbaru (6 tap terakhir), dan "Rekap Mahasiswa" — chip status (Semua/Hadir/Terlambat/Tidak Lengkap/Alfa/Tidak Wajib), pencarian, tombol Excel+PDF membawa `?date/kelas/tim`, kolom Status/Masuk/Pulang/Durasi, paginasi klien 10/halaman.
- View blade `admin.attendance` kini yatim untuk alur rekap (masih ada di disk).
- Belum diverifikasi visual; koreksi manual per baris tidak diporting ke halaman baru.

### Rekap Harian — realtime + UI stabil (16 Nov 2026)
- Endpoint JSON baru `GET /admin/attendance/live` (`admin.attendance.live`, `rekapLive`, middleware `perm:attendance,view`): mengembalikan `{synced_at, snapshot}` — summary + seluruh baris + tap_masuk + sesi_trend lewat `rekapLivePayload` (dipakai juga render awal). Admin diizinkan (guard `ensureStaffAbsensi` tidak dipakai; perm route menjaga).
- `RekapHarian.tsx`: snapshot kini state lokal; **polling fetch 15 detik** ke `urls.live` (diskip saat tab hidden) → mahasiswa yang tap muncul otomatis tanpa refresh; indikator "● Live · jam sinkron" di toolbar. Tombol Excel/PDF diganti satu dropdown **Export Laporan** (Excel + Cetak/PDF). Tabel rekap diberi kotak tinggi tetap 430px + thead sticky → layout tak bergeser saat ganti tab/halaman.
- Status mahasiswa tetap keputusan backend (`AbsensiTapEvaluator`/job): halaman hanya menampilkan label berbeda (Hadir, Terlambat, Masuk=baru tap masuk, Tidak Lengkap, Alfa, Belum Tap, Tidak Wajib) — tidak ada penilaian status di sisi klien.
- Test: php -l OK, tsc 0 error, build ✓, route:cache OK. Belum dicek visual/polling di browser.

### Dashboard — feed absensi memenuhi kartu (16 Nov 2026)
- `Dashboard.tsx`: tabel "Aktivitas Absensi Terbaru" diganti daftar flex `ad-feedlist` — setiap baris `flex:1` sehingga daftar mengembang merata memenuhi kartu sampai bawah rail kanan (tidak ada lagi ruang kosong di bawah baris terakhir); head sticky grid dengan lebar kolom konsisten. JS pengukur `feedWrapRef`/`railRef` tetap dipakai.
- Test: tsc/build OK. Belum dicek visual.

## Update 2026-11 — Fingerprint multi-role + rename grup Perangkat

- Grup sidebar "Absensi & Perangkat" diganti **"Perangkat"** (`AdminShellProps` + `_sidebar.blade.php`); key grup tetap `absensi-perangkat`.
- Fingerprint diperluas dari mahasiswa-only ke semua role portal: `FingerprintSyncService::FP_ROLES = [mahasiswa, laboran, dosen, admin]`.
  - `enrolledQuery($roles)` berparameter; `fetchForSync`/`buildStatusPayload` memakai `enrolledQuery(null)` = semua role → template staf ikut tersinkron ke mesin.
  - `countEnrolled`/`countPendingEnroll` digeneralisasi (method lama `*Mahasiswa` dipertahankan sebagai alias).
  - Halaman `admin.fingerprint` mendapat filter `role` (default mahasiswa); statistik/donut/trend mengikuti role terpilih; kelas/tim/shift & backfill-shift hanya untuk mahasiswa.
  - `assignFingerId`/`resetFingerId`/`clearFingerprintEnroll` kini menerima semua `FP_ROLES`; `MahasiswaShiftResolver` hanya dipanggil untuk mahasiswa.
  - `MesinFingerprintController` (check, enrollConfirm, deleteEnroll, list, findByFingerId) memakai `FP_ROLES` — staf bisa enroll & tap di mesin seperti mahasiswa.
- Kartu akses: `users.rfid_uid` + `akses_pin` SUDAH ada untuk semua role (auto-alokasi `KartuAksesAllocator` saat buat/import/approve; editable di form Pengguna). CS/petugas non-portal sudah punya `RuanganPetugas` (PIN/RFID/fingerprint + jadwal per-ruangan).
- Otorisasi ruangan TIDAK berubah: `AksesRuanganService::cekIzin` → admin/laboran/dosen = `akses_staf` (bebas semua ruangan — "laboran semua ruangan" sudah tercakup); mahasiswa wajib izin aktif; petugas mengikuti jadwal. Kredensial fisik (PIN/RFID/fingerprint) tidak bisa bypass izin server.

## Update 2026-11 — Kredensial akses per role (kartu + PIN)

- **Seri kartu per role**: `KartuAksesAllocator::PREFIXES` — SBM mahasiswa, SBD dosen, SBL laboran, SBA admin (SBK lama tetap valid). `ensure()` memilih prefix dari `role`.
- **PIN mahasiswa = PIN per-izin** (baru): kolom `ruangan_izin.akses_pin_enc` (terenkripsi, migrasi `2026_11_20_100000_add_akses_pin_enc_to_ruangan_izin` — JALANKAN `php artisan migrate`). `RuanganBookingService::terbitkanIzin` membuat PIN 6 digit unik per izin; `AksesRuanganService::resolveIdentitas('pin')` juga mencari PIN izin aktif → identitas pemiliknya; `cekIzin` tetap wajib diverifikasi (PIN tidak bypass izin). PIN mati saat izin dicabut/kedaluwarsa.
- **PIN staf** (admin/laboran/dosen): tetap permanen di `users.akses_pin`, kini bisa **diganti sendiri** lewat profil (`POST /profile/akses-pin`, `UserProfileController::updateOwnAccessPin`, form di `profiles/show.blade.php`). Form pengguna admin tidak lagi menampilkan/menyimpan akses_pin untuk mahasiswa.
- **Mahasiswa lihat kartunya**: profil portal React (`Student/Profile`) kini menampilkan nomor seri kartu (`rfid_uid`) + daftar PIN izin aktif beserta ruangan & masa berlaku (`StudentPortalController::profile` props `kartuAkses`, `izinPins`).
- CS/petugas tidak berubah: `RuanganPetugas` (PIN/RFID/fingerprint + jadwal) tetap identitas non-portal.

## Update 2026-11 — Laboran lihat pengguna + import masuk tim PBL

- **Kelola tim PBL**: `admin.pbl.*` route memakai `perm:users,*` (tanpa admin.only) — admin & laboran bisa akses; sidebar "Proyek PBL" kini tampil untuk keduanya. Dosen mengelola tim bimbingannya lewat `dosen.pbl.index`.
- **Laboran lihat pengguna**: GET `admin.users` + `admin.users.role` tidak lagi `admin.only` (perm:users,view saja) — laboran dapat melihat daftar pengguna read-only; flag `can.create/update/delete` di halaman users dipaksa `isAdmin()` sehingga tombol mutasi tersembunyi dan route mutasi/import tetap admin.only. Seksi "Data & Pengguna" sidebar kini gated `canCurrent('users')` (bukan isAdmin); link Import Akun tetap admin-only.
- **Import → keanggotaan PBL**: `AccountImportService` kini menautkan `tim` hasil import ke `PblProject` (nama/kode) → `PblTeamMember` role anggota + sinkron `users.tim`/`shift`. Jika nama tim belum ada sebagai proyek → reason baris menjelaskan "buat proyeknya di menu Proyek PBL lalu assign ulang".

## Update 2026-11 — Toggle rail sidebar di AdminShell React

- Tombol garis-3 (`ad-burger`, ikon Menu) di topbar AdminShell kini tampil juga di desktop: <1024px = buka drawer mobile, ≥1024px = toggle mode rail (sidebar menyempit 78px, ikon saja).
- Persistensi memakai key `dashboard_sidebar_collapsed` yang sama dengan sidebar Blade — status bertahan lintas navigasi React ↔ Blade.
- Di mode rail: label/teks/art/copy/submenu disembunyikan via CSS; klik ikon grup submenu membuka kembali sidebar penuh + grupnya sekaligus.
- Cakupan toggle collapse kini lengkap: AdminShell React (baru), AppShell mahasiswa React (Menu), layouts/dashboard.blade (edge toggle), layouts/student.blade (spSidebarToggle).

## Update 2026-11 — Modal PIN akses ruangan di profil staf

- Field "PIN Akses Ruangan" (admin/laboran & dosen) didesain ulang: status Aktif/Belum + deskripsi fungsi + tombol "Bikin/Atur Ulang PIN" membuka modal — menggantikan form inline kecil.
- Modal punya 2 mode: "Ketik Sendiri" (PIN + konfirmasi, tepat 6 digit) dan "Buat Otomatis" (acak 6 digit, tampil sekali, tombol acak ulang). Hanya input mode aktif yang tersubmit (lainnya disabled).
- Validasi backend `profiles.access-pin.update` diperketat: `digits:6` (sebelumnya 4–20). PIN lama non-6-digit tetap valid (hash).
- CSS modal/field di `public/css/profile-premium.css` (link dibump ?v=8).

## Update 2026-11 — Halaman Program Mesin + sketch Arduino

- Route baru `GET /admin/mesin/panduan` (perm:mesin,view) → `PanduanMesin.tsx` (Inertia/app-admin): kredensial koneksi (base URL + header X-API-KEY), referensi endpoint per kelompok (umum/absensi/pintu), perbedaan jalur program (absensi=update, pintu=baru), tabel unduh sketch per mesin, checklist uji keputusan akses. Sidebar grup Perangkat dapat link "Program Mesin" (React + Blade, icon file-code).
- Route `GET /admin/mesin/{mesin}/sketch` mengunduh .ino dari `App\Support\MesinSketchBuilder`: tipe absensi → template update (heartbeat loop + konfigurasi ulang, tap/enroll tak berubah); tipe lain → sketch pintu lengkap (WiFi+heartbeat+RFID RC522 → access/check → relay → access/event; stub opsional PIN keypad & fingerprint UART). id_mesin & SERVER_BASE terisi otomatis; MESIN_API_KEY selalu placeholder.
- Status Online dashboard tidak diubah — benar menampilkan 0/3 Offline karena belum ada heartbeat; begitu firmware heartbeat ≤5 menit, status jadi Online.

## Update 2026-11 — Program Mesin master-detail (mockup profesional)

- `PanduanMesin.tsx` ditulis ulang mengikuti mockup: hero + chip Total/Online/Offline, 4 tab halaman (Program Mesin / Endpoint API / Template Sketch / Panduan Integrasi), layout master-detail — kiri daftar mesin dengan pencarian + dot status, kanan detail dengan sub-tab Konfigurasi / Endpoint API / Sketch-Firmware / Log & Riwayat.
- Konfigurasi: kartu Informasi Mesin (tipe/lokasi/status/terakhir aktif/aktivitas) + Kredensial & Koneksi (base URL copyable, API key masked permanen + catatan regenerate, interval heartbeat, tombol Uji Koneksi → JSON `admin.mesin.conn` mengukur latensi + status heartbeat).
- Riwayat per mesin di-load server-side: absensi → 8 tap terakhir (nama+j enis+waktu), pintu → 8 access event (nama+hasil+waktu). Endpoint list per tipe mesin expandable payload.
- File CSS `pm-*` di app.css diganti total (tidak pakai bootstrap).

## Update 2026-11 — Label fitur BARU/SOON di sidebar semua portal

- `App\Support\FeatureBadge` (baru): registry 9 fitur + SystemSetting `feature_badges` {enabled, until, items}. State per fitur: new→"Baru" (berdenyut, otomatis mati setelah tanggal `until` = akhir semester berjalan), soon→"Soon" (klik popup segera-hadir), off.
- Pengaturan → tab Tampilan → kartu "Label Fitur di Sidebar": master toggle, tanggal kedaluwarsa BARU, per-fitur select Baru/Soon/Mati. Simpan ke `admin.settings.feature-badges` (perm:settings,update + admin.only).
- Diterapkan di: AdminShell React (attrs di $link + tag 'Baru' glow emerald), _sidebar admin/dosen blade (sidebar-link-tag--new + Pelatihan AI ditambah di blade), student.blade nav (item 'feature' + .sp-tag), student React AppShell (nav item 'feature' + shared prop `features` dari HandleInertiaRequests).
- Default baru: asisten-ai, pelatihan-ai, program-mesin, pbl-tim, pengajuan-ruangan = BARU; penilaian-pbl, grup-chat-tim, manajemen-tim, riwayat-chat = SOON.

## Update 2026-11 — Lampiran gambar di chat Asisten SmartBrail (mahasiswa)

- Widget chat mahasiswa: tombol paperclip membuka file picker (JPG/PNG/WebP maks 3 MB) dan input teks menerima paste screenshot (Ctrl+V). Gambar di-upload staged lewat endpoint lampiran yang sama (`mahasiswa.help.lampiran` → lampiran-tmp/{uid}/token), chip pratinjau di atas input, bisa dikirim tanpa teks.
- `mahasiswa.help.ai` menerima `image_token` opsional; message boleh kosong jika ada gambar. `HelpdeskAiService::reply($msg,$history,$image)` menambah part `inline_data` (base64) ke request Gemini → asisten menganalisis isi screenshot (error, tampilan portal, kartu).
- Fallback tanpa Gemini: balasan lokal mengakui gambar diterima + sarankan lampirkan sebagai foto bukti laporan. Bila suggest_report aktif, token gambar otomatis dibawa ke draft laporan (lampiran_token) — foto chat langsung jadi bukti laporan resmi.
- File: HelpdeskAiService, StudentPortalController (helpAi), AssistantWidget.tsx, student/app.css.
- Verifikasi: tsc 0 error, build OK; belum diuji end-to-end dengan Gemini aktif.

## Update 2026-11 — Gemini vision aktif untuk lampiran chat mahasiswa

- GEMINI_API_KEY terkonfigurasi (model gemini-flash-latest + fallback) → jalur vision di helpAi aktif di produksi.
- Pagar prompt diperkuat (aturan 9-10): asisten wajib jujur bila gambar buram/tak relevan/ragu — tawarkan laporan resmi, dilarang mengarang diagnosis; batas topik portal + data mahasiswa sendiri; tolak permintaan di luar konteks; tidak pernah meminta/menampilkan password/PIN/OTP.
- Timeout request vision dinaikkan 5→15 dtk (payload base64 gambar lebih berat); circuit breaker helpdesk_gemini_down tetap menjaga jika Gemini gagal.
- Fallback lokal tetap menawarkan draft laporan berlampiran saat gambar dikirim tapi analisis tidak tersedia.
- Verifikasi: php -l bersih; belum diuji end-to-end terhadap API Gemini nyata.

## Update 2026-11 — Groq vision sebagai fallback gratis analisis gambar

- HelpdeskAiService::reply kini berlapis: Gemini (utama) → Groq vision (khusus request bergambar, model meta-llama/llama-4-scout-17b-16e-instruct, endpoint OpenAI-compatible api.groq.com) → FAQ lokal. Gemini catch tidak lagi langsung return agar fallback bisa dicoba.
- Config baru services.groq.api_key (env GROQ_API_KEY) + services.groq.vision_model (env GROQ_VISION_MODEL). helpAi menerima source 'groq' sebagai mode ai_assistant. Teks tanpa gambar tidak berubah — tetap Gemini/FAQ.
- Aktivasi: user buat akun Groq gratis → GROQ_API_KEY di .env → config:cache. Tanpa key, perilaku sama seperti sebelumnya.
- Verifikasi: php -l bersih, config:cache OK; belum diuji request Groq nyata (key belum terpasang).

## Update 2026-11 — Fallback AI gratis: Groq (teks) + vision_fallback generik (gambar)

- GROQ_API_KEY terpasang di .env (setelah blok GEMINI), config:cache sudah dijalankan. Terverifikasi: POST chat/completions model openai/gpt-oss-120b → 200.
- Catatan: Groq sudah TIDAK punya model vision (llama-4-scout dihapus, /models hanya teks/whisper) → Groq dipakai sebagai fallback TEKS saat Gemini 429/down; jauh lebih baik daripada FAQ statis.
- Analisis gambar memakai services.vision_fallback — endpoint OpenAI-compatible generik: default Ollama lokal (http://127.0.0.1:11434/v1, model qwen2.5vl:3b, connect timeout 2s, timeout 45s). Belum ada Ollama di mesin → gagal cepat lalu fallback laporan. Bisa juga diarahkan ke OpenRouter via env VISION_FALLBACK_BASE_URL/API_KEY/MODEL.
- helpAi mengakui source gemini/groq/vision_fallback sebagai mode ai_assistant.
- Aktivasi vision lokal: install Ollama + `ollama pull qwen2.5vl:3b` → langsung jalan tanpa konfigurasi.

## Update 2026-11 — Kill-switch lampiran asisten AI (semua portal)

- `AI_ATTACHMENTS_ENABLED=false` di .env → services.ai.attachments. Saat false:
  - Copilot admin/dosen/laboran: tombol paperclip disembunyikan, paste gambar diabaikan (props cp.attachments_enabled), uploadAttachment → 422, stream melewati resolveAttachments.
  - Widget mahasiswa: tombol lampiran disembunyikan, paste screenshot nonaktif (shared prop ai_attachments via HandleInertiaRequests); helpAi dengan image_token menjawab ramah bahwa fitur dimatikan + arahkan laporan resmi.
- Lampiran foto bukti di LAPORAN RESMI (mahasiswa.help.lampiran/suggestion) TIDAK ikut mati — itu fitur laporan, bukan AI vision.
- Hidupkan kembali: set AI_ATTACHMENTS_ENABLED=true + config:cache.
- Verifikasi: php -l bersih, config:cache OK, tsc 0 error, build ✓.

## Update 2026-11 — Logo BRAIL diterapkan sebagai logo aplikasi

- Logo BRAIL (PNG) dipasang sebagai logo kustom resmi: public/branding/app-logo.png + SystemSetting app_branding.logo_path → otomatis dipakai semua titik Branding::logoUrl/logoUrlOr (sidebar admin/dosen/laboran React+Blade, student shell, login, reset password, laporan cetak).
- Favicon + splash mark diganti branding/brail-mark.png di app.blade, app-admin.blade, layouts/student.blade (tipe image/png).
- Hardcode aiot-simalas-* di auth.blade, activation, force-change-password, dashboard.blade, reports_print kini lewat logoUrlOr → ikut logo kustom. Video transisi login aiot-simalas-logo-anim.mp4 diganti <img> brail-mark (JS query 'video.js-logo-anim' aman/no-op).
- Reset ke logo bawaan tetap bisa lewat Pengaturan → Tampilan → Reset Logo.
- Verifikasi: logoUrl() resolve OK, view:cache sukses; belum dicek visual.

## Update 2026-11 — Logo SB + wordmark "SmartBrail" bergaya logo di semua shell

- app-logo.png & brail-mark.png diganti logo SB biru (bukan badge BRAIL); cache-bust filemtime otomatis.
- Wordmark "SmartBrail" (Branding::appName) kini tampil di sebelah logo dengan style logo: italic 800 + gradient cyan→blue — AdminShell React (ad-sidebar__brand-name, ikut hilang di rail), dashboard.blade (sidebar-brand-name), student.blade (sp-brand__text strong, css bump v=20261114b), AppShell mahasiswa (gradient Tailwind), Login.tsx.
- auth.blade: semua teks "AIoT SIMALAS" (title, hero flip, mobile alt, transisi) diganti Branding::appName().
- Verifikasi: tsc 0 error, build ✓, view:cache OK.

## Update 2026-11 — Penanda angka di judul grup sidebar (semua portal)

- AdminShell React (admin/laboran/dosen): NavGroup menjumlahkan badge anak → chip merah di judul grup, terlihat walau grup tertutup.
- Blade dashboard (admin+dosen): JS menjumlahkan .sidebar-link-badge tampil per .sidebar-group → span.sidebar-group-badge disisipkan sebelum caret; MutationObserver ikut refresh saat badge berubah dinamis; tetap muncul di mode rail.
- Student React sudah punya groupBadge (balasan saran) sejak awal — tidak diubah. Student blade nav tidak punya badge item → tidak ada yang ditandai.
- Verifikasi: tsc 0 error, build ✓, view:cache OK.

## Update 2026-11 — Label portal per-role + menu Akun di sidebar staf

- Brand-sub sidebar kini mengikuti role: Portal Admin/Laboran/Dosen/Staf — AdminShell React, dashboard.blade, app-admin splash (dinamis via auth role).
- Menu "Akun" baru di akhir sidebar admin-menu (admin + laboran, React + Blade): link "Profil Saya" → profiles.show. Dosen sudah punya grup Akun (Profil Saya + Keamanan Akun); mahasiswa sudah punya profil di nav-nya.
- Verifikasi: php -l bersih, tsc 0 error, build ✓, view:cache + route:cache OK.
 - 2026-11-14 (lanjutan): Mode rail/collapse sidebar kini menjaga indikator tetap terlihat — badge angka, tag BARU/SOON, dan badge grup berubah jadi titik kecil di sudut ikon (React AdminShell, Blade dashboard admin/dosen, dan mahasiswa sp-rail) alih-alih hilang total. Logo sidebar tidak lagi menyusut saat rail (34px tetap). - 2026-11-14 (lanjutan): Toggle sidebar Blade dashboard (edge pill chevron) diganti hamburger garis-3 di topbar-kiri — konsisten dengan React AdminShell & student. Ikon tetap menu, state via dashboard_sidebar_collapsed; burger disembunyikan di mobile (sidebar off-canvas tidak dipakai). - 2026-11-14 (lanjutan): Audit PBL — tabel pbl_activity_logs (append-only, no delete endpoint), PblActivityLogger di semua aksi admin+dosen PBL; endpoint GET pbl/{pbl}/aktivitas (admin perm users,view; dosen dibatasi isManagedBy). Profil ringkas GET /profiles/{user}/ringkas (non-sensitif; mhs hanya setim/diri). UI: nama anggota+manpro/co-manpro di DetailModal PBL (admin & dosen) jadi tombol → MemberProfilePopup; seksi Riwayat Aktivitas via ActivityTimeline (dosen hanya proyek miliknya). Fix: popup profil pakai route() Ziggy (app jalan di subdirektori — path relatif /profiles/... gagal). Admin/laboran kini bisa pasang foto+detail tampilan proyek via POST admin/pbl/{pbl}/media (perm users,update) + MediaModal di AdminPbl — teraudit. Presence online (khusus admin): users.last_seen_at disentuh EnforceWebSessionTimeout (maks 1x/60 dtk); User::isOnline(5mnt)+last_seen_label; halaman Users admin = KPI Online Sekarang + dot hijau di avatar (laboran tidak melihat field tsb); ringkas profil sertakan online/last_seen hanya untuk viewer admin. Presence: mobile app ikut disentuh via EnsureMobileToken (piggyback save token_expires, throttle 60 dtk); halaman profil penuh tampilkan badge Online sekarang / Aktif X lalu khusus viewer admin (presence prop); field Login terakhir di profil = mobile_last_login_at, bukan presence.
## Update 2026-11 — Heartbeat presence, Pusat Kendali, notifikasi realtime, pembersihan loker

- **Heartbeat**: `session-idle-guard.js` kini ping `/session/ping` tiap 2,5 menit saat tab terlihat (bukan hanya saat aktivitas mouse). React AdminShell & AppShell mahasiswa punya interval ping sendiri (~3 menit, hanya document.visible). Student Blade sudah ping 12 dtk. Menyentuh `last_seen_at` lewat `EnforceWebSessionTimeout` — tidak menambah query baru, tidak mengubah semantik idle/absolute session.
- **Pusat Kendali** (`admin.pusat-kendali`, perm admin-only): gabung online sekarang, mesin offline/down, antrian approval, log akses ruangan ditolak, aktivitas PBL terakhir — satu halaman + feed polling ringan. `MonitoringOperasional` tetap untuk detail mesin.
- **Realtime**: Reverb sudah terpasang (port 8080, HTTP). Event baru `NotifikasiBaru` broadcast ke kanal `mahasiswa.{id}` + staf; di-hook ke pengumuman, balasan saran, saran masuk, perubahan pengajuan ruangan. AdminShell & AppShell subscribe kanal masing-masing → refresh badge/menu tanpa reload. Jatuh ke polling bila Reverb mati (graceful).
- **Pembersihan loker** (disetujui pemilik): semua UI/teks/cabang mati dihapus — DosenController+types (seri/dropdown loker), DashboardStatLabels/DemoData, periodic.blade+reports_print+student-detail partial, PeriodicReportBuilder+HandlesPeriodicReports (validasi `aktivitas` kini semua,absensi), PeriodicStudentDetailBuilder, HelpdeskAiService FAQ, AdminAi services (wantsLoker, ternary chart, deskripsi tool, key summary), RegisterMesinCommand (opsi loker), FingerprintReadinessCommand, simalas-charts.js (dataset y1 loker), panel-premium.css selectors, lockers-premium.css (file dihapus), dashboards/dosen.blade.php + dosen/_pbl_notice.blade.php (view mati dihapus), public-site JSX (About/Features/Hero/HowItWorks/TickerBand/PhoneMockup/Faq/ChatWidget/CtaBanner/DownloadPage — "loker"→"akses ruangan"), UserProfileController recentLoker, profiles/show teks, meta landing/download.
- **Dipertahankan sengaja**: `MesinIot::where('tipe','!=','loker')` di semua query (penjaga data mesin loker historis), `Announcement::CHANNEL_NOTIFIKASI_LOKER` + `isLockerNotification()` (feed lama), `MachineAccessPolicy` config 'loker', `MesinIotController`/`MesinFingerprintController` penolakan tipe loker, `AdminAiOrchestrator` respons penolakan "loker bukan bagian aplikasi", migrasi historis `log_loker`/`locker_state`.
- Verifikasi: php -l bersih semua file, tsc 0 error, `npm run build` ✓, view:cache + route:cache OK.
- Catatan: Reverb berjalan via `php artisan reverb:start` (background) — perlu dijalankan saat server start agar realtime hidup; tanpa itu fallback polling tetap berfungsi.

## Update 2026-11 — Git baseline + GitHub terhubung

- gh CLI terpasang (winget), login akun `raldoputradev` via device flow (scope repo).
- Repo dibuat: `smartbrail` private (origin) + `smartbrail-showcase` public (remote `showcase`). Commit `1e324ea chore: baseline SmartBrail` di-push ke keduanya (526 file).
- Identitas git lokal: noreply GitHub. Ruleset protect-main 403 (perlu GitHub Pro untuk private) — proteksi praktis lewat model Fork+PR.
- CONTRIBUTING.md + .github/pull_request_template.md ditambahkan (aturan fork→PR, prefix commit, larangan secrets).
- Keputusan lengkap: decisions.md #12.

## Update 2026-11 — GitHub dual-repo + redesain landing publik

- Git baseline: `smartbrail` (private/origin) + `smartbrail-showcase` (public/showcase). gh CLI login `raldoputradev`. CONTRIBUTING + PR template + .gitignore (scripts/ & nul dikecualikan — berisi kredensial produksi). README showcase + galeri 10 screenshot di .github/screenshots/.
- Catch-up commit menyinkronkan seluruh pekerjaan S7 ke dua repo (baseline awal ternyata snapshot lama).
- Landing publik (React public-site) di-redesign penuh ke brand SmartBrail: tema rose/orange → biru/cyan; semua teks AIoT SIMALAS → SmartBrail; fokus konten bergeser dari loker+absensi ke absensi biometrik + akses ruangan berbasis izin + PBL + portal multi-peran + notifikasi realtime + asisten AI (FlipWords, Hero, TickerBand, About, Features +icon Users/Sparkle, HowItWorks 10 langkah baru, Faq +portal mahasiswa web, ChatWidget, Navbar/Footer/CtaBanner/TrustBadges/SocialContact/InstallGuide/DownloadPage, meta landing+download blade).
- LandingController: logoMark→brail-mark.png, logoLockup→Branding::logoUrl(), statistik mesin difilter tipe!=loker. HelpdeskAiService: semua teks brand → SmartBrail + jawaban portal mahasiswa web diperbarui.
- Popup sukses auto-dismiss 4,5dtk: dialog pengajuan mahasiswa (RoomRequest) + modal hasil aksi admin (PengajuanRuangan).
- Verifikasi: tsc 0 error, build ✓, view:cache OK, GET / → 200 konten SmartBrail.

## 2026-11-15 — Web Push (VAPID) semua role

- Paket `minishlink/web-push` v11; konfigurasi `services.webpush` dari env `VAPID_SUBJECT/PUBLIC_KEY/PRIVATE_KEY` (private key hanya di `.env` server).
- Tabel `push_subscriptions` (user_id, endpoint unik, keys p256dh/auth, encoding, user_agent).
- Endpoint auth `/push/vapid-key`, `/push/subscribe`, `/push/unsubscribe` — semua role.
- `App\Services\WebPushService::sendToUsers()` fail-soft; subscription 404/410 dihapus.
- Dipanggil dari: `RuanganBookingService::siarkan` (pemohon+peserta; staf saat status `diajukan`), `AnnouncementObserver` (pengumuman), `SuggestionController::pushSuggestionReply`, `StudentPortalController` saran baru → staf.
- Service worker `public/sw-push.js`; helper `public/js/webpush.js` (window.SbPush).
- UI toggle: `partials/push-toggle.blade.php` (halaman notifikasi mahasiswa + dosen), komponen `PushToggle` React di `AntrianNotifikasi` (admin/laboran).
- Belum diverifikasi end-to-end di browser; VAPID key produksi diisi saat deploy.
