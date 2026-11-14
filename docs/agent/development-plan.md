# Rencana Pengembangan Bertahap SIMALAS

Status: **rencana untuk persetujuan, bukan izin eksekusi**. Tanggal: 2026-10-24. Keputusan produk dirujuk dari `decisions.md`; batas recovery tetap mengikuti `current-state.md`.

## 1. Urutan dan dependensi

| Tahap | Ruang lingkup | Kriteria selesai yang dapat diperiksa | Risiko dan langkah kembali | Izin sebelum eksekusi |
|---|---|---|---|---|
| 0. Baseline keselamatan | Inventaris sensitif, perkuat aturan ignore, Git baseline, arsip rollback | File sensitif tidak masuk daftar tracking; lockfile/docs ikut; clone/ekstrak cadangan di lokasi baru terverifikasi | Salah tracking dapat membocorkan data. Hentikan sebelum commit; bersihkan daftar, bukan menghapus data | Edit `.gitignore`, `git init/add/commit` lokal; tanpa push |
| 1. Baseline runtime terbatas | Proses/port, koneksi/schema DB, HTTP smoke yang sudah ditinjau | Status pemilik port jelas; `SELECT 1` berhasil jika DB aktif; struktur expected dicatat; URL terpilih sesuai status | Service berhenti → berhenti dan laporkan, tidak start otomatis. GET yang bermutasi dikeluarkan | Menjalankan query/read-only dan request yang disetujui |
| 2A. Jika runtime cukup stabil | Portal mahasiswa responsif secara slice | Login/role aman, data sendiri, fitur minimum pada matriks lulus Android/iPhone; staf/mesin/Flutter tidak berubah | Kembali ke commit sebelum slice; Flutter tetap aktif | Implementasi dan test web disetujui per slice |
| 2B. Jika DB menghambat | Eksperimen PostgreSQL terisolasi | Schema dan fixture sintetis bermigrasi; query/laporan/tap valid; unique race lulus; rollback hanya menghapus lingkungan uji | Jangan sentuh operasional; hapus lingkungan uji setelah persetujuan | Instalasi/server/DB uji dan perintah terkait disetujui |
| 3. Copilot kecil | Bantuan terkurasi, analisis agregat, draf tanpa simpan/kirim | Role/permission lulus, provider hanya backend, data minimum, write tetap ditolak | Nonaktifkan jalur baru/kembali ke fallback lokal | Implementasi tool/prompt/test disetujui |
| 4. Transisi dan tahap lanjut | Paritas tambahan, PWA, keputusan DB/cutover | Pemilik menerima hasil uji dan menyetujui transisi secara eksplisit | Flutter/MariaDB tetap sebagai jalur kembali sampai keputusan baru | Keputusan transisi/cutover terpisah |

Urutan 2A dan 2B **tidak dikunci**. Copilot dapat dikerjakan setelah baseline bila tidak bergantung pada perubahan portal/database. Tidak ada migrasi produksi dalam rencana ini.

## 2. Baseline kode, database, dan rollback

### Yang diusulkan untuk Git

Root belum memiliki Git pada pemeriksaan ulang. `.gitignore` existing mengecualikan `.env`, dependency, `public/build`, log umum, dan cache tertentu; `database/.gitignore` mengecualikan SQLite; mobile mengecualikan build/Dart tool; `komunikasi-mesin/.gitignore` mengecualikan upload lapangan dan `.bak`/log.

Sebelum `git init/add/commit`, tinjau penambahan aturan untuk:

- secret: `.env*` kecuali template yang sudah diperiksa, `auth.json`, private key, service account, credential Firebase;
- dump/backup: `database/backups/`, `database/data/`, `*.sql`, `*.sqlite*`, `*.dump`, `*.bak`, arsip zip backup;
- log: semua log aplikasi/mesin/upload nyata;
- data pribadi: `public/uploads/`, storage private, foto profil/bukti, import nyata, fingerprint/template/citra;
- generated/dependency: `vendor/`, `node_modules/`, build, cache, `.dart_tool/`, hasil Flutter, dan generated config berisi credential.

Tetap lacak source `app/`, `bootstrap/`, `config/`, `routes/`, `resources/`, source assets, migration/factory/test, firmware source/docs, `AGENTS.md`, `docs/agent/`, serta `composer.lock`, `package-lock.json`, dan `simalas_mobile/pubspec.lock`. Tinjau `firebase_options.dart`, template env, dan file JSON sebelum tracking; jangan berasumsi semuanya bebas credential.

### Tiga jenis cadangan berbeda

1. **Cadangan kode:** Git berisi source/docs/lockfile, bukan database atau secret. Verifikasi dengan `git status`, daftar file, pemeriksaan pola sensitif, lalu clone ke direktori baru dan validasi manifest/test yang disetujui.
2. **Cadangan database:** backup offline/logical terpisah. Verifikasi jumlah/ukuran/hash file tanpa menimpa sumber; uji restore hanya ke DB terisolasi dan bandingkan schema/agregat, bukan membuka record pribadi.
3. **Arsip rollback:** snapshot source + dokumentasi versi untuk mengembalikan perubahan aplikasi. Simpan di lokasi baru dengan manifest hash; ekstrak ke folder uji dan bandingkan. Ini bukan pengganti backup DB.

Tidak ada cadangan baru yang dibuat/dipindahkan pada tahap ini. Backup pra-repair existing tidak disentuh.

## 3. Rencana pemeriksaan runtime terbatas

Semua langkah berikut **hanya usulan**. Jika service yang dibutuhkan tidak aktif, berhenti pada temuan; tidak ada start/restart.

### A. Proses dan port

- **Tujuan:** mengetahui MariaDB/Apache/PHP-Reverb yang aktif dan pemilik port tanpa instance ganda.
- **Metode:** PowerShell `Get-CimInstance Win32_Process` difilter `mysqld|mariadbd|httpd|php`; `Get-NetTCPConnection -LocalPort 3306,80,443,8080 -ErrorAction SilentlyContinue`, lalu cocokkan `OwningProcess` dan binary. Baca hanya `my.ini`/Apache config yang relevan, tanpa credential.
- **Efek samping:** query WMI/socket bersifat read-only; tidak mengubah service.
- **Berhasil:** proses, executable, dan listener sesuai; bentrok/duplikat dicatat sebagai temuan, bukan dibunuh.

### B. Koneksi database

- **Tujuan:** membuktikan koneksi dan query minimal, bukan membaca record.
- **Metode:** setelah help/versi PHP yang sesuai diperiksa, bootstrap Laravel dan panggil `DB::connection()->getPdo()` lalu `DB::select('SELECT 1')`, output hanya `DB_CONNECTION_OK` dan driver. Gunakan konfigurasi lokal tanpa mencetaknya.
- **Efek samping:** bootstrap dapat menulis log/cache framework bila ada error; bukan operasi bebas efek samping mutlak. Tidak ada query tabel pengguna.
- **Berhasil:** PDO dan `SELECT 1` berhasil pada database yang dimaksud. Kegagalan dicatat tanpa repair/start.

### C. Struktur database

- **Tujuan:** membandingkan struktur expected dengan aktual dan memahami kondisi pasca-repair tanpa membaca privilege/record.
- **Metode:** periksa ketersediaan `php artisan db:show` melalui help, lalu gunakan output tabel/kolom/index, bukan record. Jalankan `php artisan migrate:status` hanya setelah repository migration dipastikan ada. Untuk evaluasi file Aria, perlu persetujuan terpisah dan server berhenti; gunakan `aria_chk --datadir=... --check --read-only --skip-update-state` hanya pada `mysql\db.MAI`.
- **Efek samping:** Artisan melakukan bootstrap dan dapat logging; `migrate:status` bukan migration tetapi tetap harus ditinjau. `aria_chk` tidak dijalankan bila ada lock/control/log issue.
- **Berhasil:** tabel inti/index/unik/FK terinventaris; perbedaan migration dicatat tanpa memperbaiki. Jumlah record target hanya bila diminta, tanpa isi record.

### D. Smoke test aplikasi

- **Tujuan:** memeriksa front controller, landing/health, dan akses LAN HP.
- **Metode:** mulai dengan `GET /up` dan route yang sudah ditinjau. Gunakan URL XAMPP aktual dan status/header saja. Uji browser Android/iPhone secara manual pada tahap portal.
- **Efek samping:** HTTP GET dapat membaca/mengubah session, cache, log, atau data. Khusus `AuthController::showAuthPage` memanggil prune token OTP, sehingga `/masuk` **tidak dimasukkan smoke read-only default**. Login dapat menulis session/rate limiter/email OTP dan tidak dilakukan pada akun nyata.
- **Berhasil:** status yang diharapkan, tidak ada 5xx, halaman health/landing sesuai konfigurasi. Kegagalan tidak diatasi dengan perubahan otomatis.

Jika perlu write test, gunakan SQLite `:memory:` atau DB uji terpisah berisi fixture sintetis setelah test dan efek samping ditinjau. Jangan gunakan database operasional atau skrip `tests/Support/` yang mutatif tanpa persetujuan khusus.

## 4. Kajian awal PostgreSQL

### Temuan yang sudah diverifikasi

- `AbsensiTapIdempotency::isUniqueViolation` mengenali SQLSTATE `23000` dan pesan MySQL/SQLite. PostgreSQL unique violation umumnya `23505`; perlu penyesuaian dan uji race/replay.
- Beberapa migration memakai `ALTER TABLE ... MODIFY` khusus MySQL: email nullable, tipe `waktu_tap`, dan enum status. Sebagian dijaga `getDriverName()==='mysql'` sehingga menjadi no-op pada PostgreSQL; schema hasil akhir dapat berbeda.
- Enum, JSON/JSON path, UUID, boolean, timestamp/date, foreign key, composite unique/index, dan `lockForUpdate` perlu divalidasi pada driver PostgreSQL, bukan diasumsikan dari SQLite.
- `updateOrCreate`/`updateOrInsert` Eloquent digunakan luas. SQL laporan yang diperiksa memakai `SUM(CASE ...)`, `COUNT`, `MAX`, `LOWER`, dan `TRIM` yang relatif portabel, tetapi hasil/tipe tetap perlu dibandingkan.
- Nama index/constraint dan operasi `dropUnique/dropIndex` perlu diperiksa karena batas penamaan PostgreSQL berbeda.
- Migration historis loker dan backfill ikut terbawa; jangan memilih/menghapusnya tanpa strategi schema canonical.

Belum diaudit: semua raw SQL, seluruh migration, perbandingan collation/sort, performa index, dan data aktual. Ini bukan audit global.

### Eksperimen terisolasi yang diusulkan

1. Buat PostgreSQL server/database uji terpisah setelah izin, tanpa data operasional.
2. Jalankan migration pada schema kosong dan catat kegagalan/no-op. Susun perbedaan tipe, null/default, enum/check, index, FK, dan constraint.
3. Gunakan fixture sintetis yang sama untuk MariaDB dan PostgreSQL. Jangan export/import data pengguna.
4. Validasi jumlah tabel/schema dan hasil agregat, relasi PBL, scope mahasiswa, rekap/laporan, serta status harian.
5. Uji tap pertama, replay `event_id`, payload conflict, dan dua request konkuren; pastikan hanya satu keputusan/log dan SQLSTATE ditangani.
6. Jalankan test Copilot/provider palsu dan test web yang relevan setelah ditinjau; SQLite saja tidak cukup.
7. Rollback eksperimen cukup mematikan/menghapus lingkungan uji setelah persetujuan. Produksi dan backup tetap utuh. Cutover memerlukan rencana lain.

Keberhasilan koneksi saja bukan keberhasilan eksperimen. PostgreSQL tidak dianggap otomatis menyelesaikan kerusakan MariaDB atau masalah aplikasi.

## 5. Roadmap kecil Copilot

Kemampuan existing yang tidak perlu diusulkan ulang: rekap/daftar/laporan, cari mahasiswa, chart, hitung user, attention/ops summary, status mesin, SSE/riwayat/replay, role permission, dan filter privasi. Draft/write tool sudah memiliki metadata tetapi dinonaktifkan.

1. **Bantuan penggunaan terkurasi**  
   Skenario: “Bagaimana mengoreksi absensi?” atau “Di mana melihat tim PBL?” Jawaban berasal dari panduan singkat milik project dan tautan menu sesuai role. Ini bantuan dokumentasi, bukan membaca record. Jangan mencampur FAQ helpdesk publik yang masih mengandung loker.
2. **Analisis operasional agregat**  
   Skenario: “Apa yang perlu perhatian minggu ini dan mengapa?” Gunakan tool existing dan, bila perlu, tool read-only pembanding periode/agregat. Provider menerima angka minimum, bukan daftar identitas/template/log mentah. Ini pembacaan data, bukan tindakan.
3. **Draf pesan/pengumuman tanpa eksekusi**  
   Skenario: laboran meminta draf pengingat tap pulang. Hasil hanya preview dalam chat; tidak memakai write tool, tidak menyimpan/publish/mengirim. Simpan/publish nanti memerlukan desain izin dan konfirmasi baru.

Batas: backend memegang key (`AdminAiOrchestrator`/`GeminiSseClient`); frontend hanya memanggil route SSE dan tidak memuat key. Pertahankan `READ_ONLY`, tool per role, redaction, dan peringatan privasi. Biaya/kuota gratis hanya informasi pemilik yang belum diverifikasi; fallback model dan hingga enam tool round dapat memperbesar konsumsi. Pertahankan rate limit dan evaluasi jumlah panggilan, bukan hanya tarif.

## 6. Rekomendasi awal yang sudah dilaksanakan

Baseline runtime non-invasif A–C dan inventaris migration telah dilakukan pada 2026-10-24. Hasil aktual dicatat di `current-state.md`; bagian ini dipertahankan sebagai penanda urutan, bukan hasil test fungsi.

## 7. Inventaris migration dan dependensi target

Perbandingan 2026-10-24 menemukan **58 file migration, 56 catatan repository, dan hanya 2 pending**:

| Migration | Perubahan dari kode | Objek/dependensi wajib | Data dan relevansi |
|---|---|---|---|
| `2026_10_09_120000_add_no_hp_to_users_table.php` | Tambah `no_hp` varchar(20) nullable setelah email jika belum ada | `users` harus ada; tidak bergantung pada pending lain | `up()` tidak backfill/menghapus data. Diperlukan profil dan penyelesaian aktivasi |
| `2026_10_09_120100_add_account_verify_to_email_verification_tokens_type.php` | Tambah nilai `account_verify` pada enum token | `email_verification_tokens` dan kolom `type` harus ada; MySQL memakai ALTER, driver lain `change()` | `up()` tidak backfill/menghapus token lama. Diperlukan pengiriman/verifikasi OTP akun |

Tidak ada dependensi migration wajib di antara keduanya. Keduanya saling melengkapi hanya pada **alur aktivasi**: profil membutuhkan `no_hp`, OTP akun membutuhkan enum baru. Semua tabel prasyarat tersedia. Tidak ada pending tidak terkait yang perlu disertakan.

Keduanya layak diuji sebagai perubahan terarah dengan path eksplisit, bukan paket `migrate` seluruh pending pada database operasional.

## 8. Rencana pengujian MariaDB terisolasi

Status: **rencana telah dieksekusi dan lulus pada 2026-10-24 14:22 +07**. Harness: `tests/Support/run_activation_migration_isolated_test.php` (tidak masuk suite biasa).

### Hasil eksekusi

- `simalas_activation_test` dibuat setelah nama dipastikan belum ada. Identitas lokal/driver `mysql` dan `SELECT DATABASE()` dijaga; koneksi Laravel proses diarahkan khusus ke test tanpa mengubah `.env`/konfigurasi operasional.
- Sebanyak 36 base tables disalin melalui `SHOW CREATE TABLE` tanpa record. Tidak ada trigger/event/routine atau referensi database operasional pada schema. Tidak ada artefak schema sementara yang perlu dihapus.
- Repository migration test dimulai **kosong**, sehingga hasilnya tidak menyesatkan. Setelah dua path eksplisit, hanya ada `2026_10_09_120000_add_no_hp_to_users_table` batch 1 dan `2026_10_09_120100_add_account_verify_to_email_verification_tokens_type` batch 2.
- `no_hp` menjadi varchar(20) nullable tepat setelah email. Enum memuat `registration`, `password_reset`, dan `account_verify`. Checksum user/token sintetis dan nilai non-target sama sebelum/sesudah migration.
- Request internal Laravel membuktikan password lemah ditolak, password kuat dan kode diterima, nomor HP invalid ditolak lalu valid, OTP salah tidak mengaktifkan akun, dan OTP `account_verify` yang benar menyelesaikan aktivasi.
- Status akhir fixture `is_active=true` dan `approval_status=approved`, sesuai aturan aktivasi import yang tidak memerlukan approval laboran tambahan. Approval registrasi mandiri tidak termasuk cakupan uji.
- `Mail::fake`, notification/queue/bus fake, HTTP client fake + stray prevention, session/cache array, broadcast null, dan provider nonaktif digunakan. Tidak ada request jaringan, SMTP, Gemini, FCM, reCAPTCHA, atau perangkat.
- Database test dipertahankan sesuai izin. Verifikasi akhir read-only pada operasional tetap menunjukkan `no_hp=null`, enum lama, dan nol catatan migration target; tidak ada penerapan ke operasional.

Rencana awal dan batas berikut tetap berlaku sebagai dokumentasi metode. Keberhasilan ini bukan bukti seluruh aplikasi/semua alur bebas masalah dan bukan izin deployment.

### Schema awal dan isolasi

1. Gunakan MariaDB lokal yang sama agar perilaku enum sesuai, bukan PostgreSQL atau SQLite.
2. Bentuk database baru, misalnya `simalas_activation_test`, berisi **schema-only** dari database aplikasi saat ini tanpa record. Gunakan ekspor metadata schema yang disetujui; jangan menyalin record pengguna/OTP/fingerprint.
3. Verifikasi kondisi awal: `users.no_hp` tidak ada dan enum token hanya `registration,password_reset`. Repository migration pada salinan tidak perlu diisi data operasional; dua target dipanggil dengan path eksplisit.
4. Gunakan konfigurasi proses khusus test, bukan perubahan `.env`. Sebelum setiap tulis, kode wajib menolak jika driver bukan `mysql`, host bukan lokal, atau `SELECT DATABASE()` bukan `simalas_activation_test`. `DB::purge()`/koneksi terpisah mencegah penggunaan koneksi default operasional.
5. Gunakan PHP 8.3 dan jangan mengubah Apache, PATH, service, atau database sumber.

### Data sintetis dan penerapan terarah

- Buat akun aktivasi sintetis beserta log aktivasi yang diperlukan, user pendukung, dan token lama sintetis. Jangan gunakan identitas nyata.
- Simpan fingerprint non-rahasia dari kolom yang tidak menjadi target dan nilai token lama untuk perbandingan sebelum/sesudah.
- Jalankan migration pertama dengan path persis `2026_10_09_120000_add_no_hp_to_users_table.php`, lalu migration kedua dengan path persis `2026_10_09_120100_add_account_verify_to_email_verification_tokens_type.php`. Jangan menjalankan file lain.

### Pengujian struktur, preservasi, dan alur

- Pastikan `no_hp` menjadi varchar(20) nullable setelah email dan enum memuat `account_verify`.
- Pastikan akun/token sintetis sebelum migration tetap ada dan kolom/nilai non-target tidak berubah; `up()` tidak melakukan backfill.
- Jalankan alur aktivasi dengan fixture `menunggu_aktivasi`: kode → password → profil email/no_hp → OTP `account_verify` → verifikasi → akun aktif/approved. Periksa `no_hp`, email terverifikasi, dan finger ID sintetis sesuai aturan.
- Gunakan HTTP kernel internal/Feature Test hanya setelah efek samping test ditinjau; jangan request jaringan. Bila menguji direct service, jangan mengklaim cakupan HTTP penuh.
- Uji ulang penerapan yang sama hanya untuk memastikan guard tidak menggandakan perubahan; jangan mengulang pada database operasional.

### Pencegahan komunikasi eksternal

- Gunakan `APP_ENV=testing`, mail array/`Mail::fake`, broadcast null, queue sync, Gemini/FCM nonaktif, dan DNS check aktivasi false.
- Blokir pemanggilan provider pada harness; keberhasilan hanya dihitung jika tidak ada email/HTTP eksternal nyata. OTP hanya dibaca dari database test sintetis bila diperlukan.

### Kriteria lulus dan pemulihan

Lulus bila dua perubahan schema tepat, data sintetis lama terpreservasi, alur aktivasi selesai, hanya dua migration tercatat pada database test, dan tidak ada koneksi/tulis ke database operasional atau provider.

`down()` bukan jaminan pemulihan: migration `no_hp` menolak rollback jika ada nomor HP terisi; migration enum menolak rollback jika ada token `account_verify`. DDL/transaction juga tidak dijadikan jaminan. Pemulihan adalah membuang dan membuat ulang **hanya database test** dari schema awal/fixture, setelah verifikasi nama database. Jangan restore ke operasional dan jangan mengubah backup asli.

Jika gagal, berhenti, catat error tersanitasi, dan pulihkan lingkungan test saja. Izin yang diperlukan: membuat database test, ekspor/import schema-only tanpa record, menulis fixture sintetis, menjalankan dua migration path eksplisit pada test, dan menjalankan test aktivasi dengan komunikasi eksternal dimatikan.
