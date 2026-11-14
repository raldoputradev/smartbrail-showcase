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
