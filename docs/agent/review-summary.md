# Ringkasan Review Arsitektur SIMALAS

Tanggal pemetaan: 2026-10-24. Dokumen ini dimaksudkan sebagai ringkasan mandiri untuk reviewer. Isinya berasal dari pembacaan kode terarah, bukan hasil pengujian runtime.

## 1. Konteks dan gambaran sistem

SIMALAS adalah aplikasi existing untuk pengelolaan absensi laboratorium berbasis fingerprint, dengan portal laboran/dosen, aplikasi mobile mahasiswa, perangkat ESP32, PBL, pelaporan, komunikasi, dan Copilot. Pemilik menegaskan bahwa sistem sudah pernah berjalan dengan data asli, dashboard CONTOH memang disengaja, dan Copilot Gemini sudah tersedia. Karena itu, pemetaan ini tidak merekomendasikan membangun ulang aplikasi.

Backend menggunakan Laravel dengan database relasional dan Eloquent. Route web dan API berbagi front controller serta konfigurasi aplikasi di `bootstrap/app.php`. Frontend staff terutama menggunakan Blade dan JavaScript statis, sementara manifest juga menyediakan React/Vite/Tailwind. Aplikasi mahasiswa berada di `simalas_mobile/` dan menggunakan Flutter. Skeleton firmware ESP32 berada di `komunikasi-mesin/absensi/firmware/`; belum dipastikan apakah sama dengan firmware pada perangkat fisik.

Secara konseptual, browser staff memakai session, Flutter memakai Bearer token, dan mesin memakai API key serta ID perangkat. Semua berakhir pada controller dan aturan bisnis Laravel yang membaca/menulis database. SMTP, Firebase Cloud Messaging, Gemini, dan Reverb merupakan integrasi di sekeliling inti tersebut.

## 2. Modul dan batas akses

Role utama adalah `laboran`, `dosen`, dan `mahasiswa`. Laboran mengelola pengguna, mesin, absensi, laporan, pengumuman, saran, PBL, dan permission. Dosen memperoleh menu berdasarkan permission serta scope mahasiswa/tim. Mahasiswa menggunakan mobile, bukan portal web staff.

Middleware `role`, `perm`, `approved`, `mobile.auth`, dan `mesin.auth` didaftarkan di `bootstrap/app.php`. `PermissionRegistry` menggabungkan default permission dengan tabel `role_permissions`. Namun arsitektur akses tidak hanya bergantung pada permission menu. Query `user_id` membatasi data mobile, `DosenMahasiswaScope` membatasi mahasiswa dosen, `PblTeamService` membatasi pengelolaan tim, percakapan Copilot dibatasi pemiliknya, dan kanal Reverb memeriksa role aktif/approved.

Model data utama mencakup `users`, `role_permissions`, token verifikasi, log approval, `mesin_iot`, `log_absensi`, `absensi_harian`, side-record alfa/tidak-lengkap, `tap_receipts`, proyek dan anggota PBL, announcements/suggestions, push tokens, serta percakapan AI. Migration bersifat incremental dan mengandung legacy; schema aktual belum dibandingkan langsung dengan database.

## 3. Registrasi, aktivasi, dan pengguna

Terdapat dua jalur akun yang berbeda. Pada registrasi mandiri web/mobile, pengguna dibuat belum aktif, menerima OTP email, diverifikasi, lalu masuk antrean persetujuan laboran. `PendingAccountApproval` menentukan akun yang siap diproses dan `AccountApprovalLog` menyimpan jejak tindakan.

Pada import admin, `AccountImportService` melakukan pratinjau sebelum membuat akun. Akun baru berstatus menunggu aktivasi. Pemilik menukar kode aktivasi dengan password, melengkapi profil, dan memverifikasi email. Setelah syarat lengkap, akun aktif tanpa approval tambahan. Jalur ini tidak boleh disamakan dengan registrasi mandiri.

Login web memakai session; mobile hanya mengeluarkan token kepada mahasiswa aktif/approved. Database menyimpan hash token dan middleware memperpanjang idle expiry. `LoginSecurityService` menambahkan throttling, lockout, dan admin unlock. OTP dikirim melalui `AsyncMailer`, yang memakai detached process pada Windows dan fallback after-response sesuai kondisi.

## 4. Absensi dan perangkat

Mesin memanggil `/api/mesin/tap` setelah autentikasi perangkat. `AbsensiTapIdempotency` memvalidasi request dan, bila `event_id` tersedia, menyimpan keputusan dalam `tap_receipts` agar replay aman dan race dapat dibaca ulang. Kompatibilitas firmware lama tanpa `event_id` tetap ada, tetapi tidak memberikan idempotensi.

`AbsensiTapEvaluator` memeriksa akun, enroll, shift, hari libur, status sebelumnya, dan jendela sesi. Tap masuk membuat `log_absensi` serta memperbarui `absensi_harian`; tap pulang melengkapi status harian dan menyelesaikan side-record terkait. Log mentah dan status harian karena itu merupakan dua lapisan data yang saling berelasi, bukan duplikat yang boleh diabaikan.

`AbsensiSessionCloser` menetapkan alfa bagi mahasiswa tanpa tap pada hari wajib dan tidak-lengkap setelah grace period. Scheduler menjalankan penutupan berkala dan cadangan harian. Staff dapat melakukan koreksi yang juga menyinkronkan side-record dan identitas petugas.

Rekap dibentuk oleh `AbsensiManagementController`; laporan periode dan ranking dibentuk oleh `PeriodicReportBuilder`. Ekspor memakai konteks yang sama. Copilot juga menggunakan adapter terhadap sumber rekap/laporan tersebut, bukan data dashboard dummy.

## 5. PBL dan komunikasi

PBL memakai `pbl_projects` untuk proyek/manpro/co-manpro dan `pbl_team_members` untuk ketua, wakil, serta anggota. Implementasi masih mempertahankan kolom legacy `users.tim`; `PblTeamService` menyinkronkan kolom tersebut dan `users.shift` ketika membership berubah. Pembacaan anggota juga menggabungkan data baru dan legacy. Perubahan modul ini harus menjaga kompatibilitas karena tim dipakai oleh absensi dan notifikasi.

Pengumuman memiliki target role/tim/user, status aktif/kedaluwarsa, dan read state per pengguna. Mobile menggabungkan pengumuman dengan balasan saran dalam feed notifikasi. Saran dapat berasal dari landing, dosen, atau mahasiswa, lalu dikelola laboran. Observer pengumuman dapat mengirim push melalui FCM; notifikasi operasional staff dibentuk terpisah oleh `StaffNotificationService`.

## 6. Copilot

Alur Copilot adalah Blade UI → JavaScript `fetch` pembaca SSE → route admin/dosen → `AdminAiController` → `AdminAiOrchestrator` → `GeminiSseClient`. Gemini dapat meminta function call. `AdminAiToolRegistry` menentukan tool berdasarkan role dan permission; `AdminAiToolRunner` mengeksekusinya terhadap sumber data. Teks dikirim sebagai delta, sedangkan statistik, tabel, grafik, dan tautan dikirim sebagai blok UI. Riwayat disimpan per pengguna.

Copilot saat ini baca-saja. Pencarian langsung nama/NIM dijawab lokal. `CopilotPrivacyFilter` menyamarkan identitas sebelum provider dan `CopilotAbsensiData` memisahkan agregat provider dari blok tabel browser. UI tetap memperingatkan bahwa penyaringan tidak menjamin seluruh data pribadi tertutup. Helpdesk publik adalah implementasi Gemini berbeda dengan FAQ fallback, bukan Copilot internal.

## 7. Dummy, realtime, dan pengujian

`DashboardDemoData` meng-overlay KPI/grafik dashboard bila konfigurasi demo aktif. Query data asli tetap ada sebelum overlay. Rekap dan laporan periodik tidak diubah oleh overlay tersebut. Oleh sebab itu, label CONTOH tidak dapat digunakan untuk menilai kelengkapan fitur lain.

Reverb hanya mengirim wake-up setelah commit ke kanal privat. Browser kemudian mengambil snapshot HTTP. Jika Reverb gagal, sinyal dijeda sementara dan UI kembali ke polling; tap tidak dibatalkan. Event memakai `ShouldBroadcastNow`, sehingga jalur ini tidak memerlukan queue worker.

PHPUnit dikonfigurasi dengan SQLite memori, broadcast null, cache/session array, dan provider Gemini palsu pada test Copilot. Test feature mencakup akun, mesin, idempotensi, broadcast, dan Copilot. Tidak ada test yang dijalankan dalam pemetaan ini. Skrip manual di `tests/Support/` memiliki perilaku berbeda; beberapa menargetkan MySQL `simalas_cleanup_test`, membuat/mengubah fixture, dan menulis log. Skrip tersebut tidak boleh dianggap read-only atau setara PHPUnit.

## 8. Batas bukti dan hal untuk dikonfirmasi

Belum diverifikasi: status layanan/database, schema aktual, versi package terpasang, URL aplikasi, kesiapan provider, firmware fisik, dan hasil test terkini. Recovery database sebelumnya tetap dihentikan; backup tidak disentuh. Pemetaan ini tidak mengubah kode aplikasi, konfigurasi, database, atau layanan.

Temuan untuk didalami, bukan bug pasti: dua command memiliki signature `absensi:generate-alfa` yang sama; komentar `/api/mobile/logs` tidak selaras dengan batas login mobile; dual representation PBL memerlukan kehati-hatian; sisa narasi loker masih ada setelah migration penghapusan; fallback email dapat menulis OTP/email ke log; firmware lama tidak idempoten.

Pemilik perlu mengonfirmasi status loker dalam produk, kontrak endpoint mobile logs, versi firmware aktual, dan waktu pemeriksaan runtime terpisah. Pendalaman berikutnya yang disarankan adalah review resolusi command duplikat, audit scope endpoint, dan inventaris efek samping skrip uji manual—masing-masing melalui tugas kecil yang disetujui, bukan implementasi otomatis.
