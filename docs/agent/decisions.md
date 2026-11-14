# Keputusan dan Batas Kerja SIMALAS

Dokumen ini memisahkan keputusan eksplisit pemilik dari fakta implementasi dan usulan teknis. Alasan historis yang tidak dinyatakan tidak direkonstruksi.

## 1. Keputusan pemilik yang sudah dinyatakan

- SIMALAS adalah project existing yang sudah pernah berjalan dengan data asli; pengembangan dilanjutkan, bukan membangun ulang.
- Data dummy pada dashboard berlabel CONTOH memang disengaja.
- SIMALAS Copilot berbasis Gemini sudah tersedia dan dapat digunakan.
- Pengembangan berikutnya dilakukan melalui tugas kecil yang disetujui pemilik; gambaran arsitektur didahulukan.
- Pemetaan ini hanya untuk dokumentasi: bukan implementasi fitur, refactor, atau pemulihan database.
- Recovery database sebelumnya dihentikan dan tidak boleh dilanjutkan dalam pekerjaan ini.
- Test, migration, seeder, operasi layanan, panggilan provider eksternal, dan pembacaan data sensitif tidak termasuk izin pemetaan.

## 2. Keputusan/constraint yang terverifikasi dari kode

Ini adalah kondisi implementasi, bukan klaim tentang alasan pemilik:

- Portal web mahasiswa telah diimplementasikan pada 2026-10-24; Flutter dan endpoint mobile tetap tersedia (`routes/web.php`, `routes/api.php`).
- Laboran dan dosen memakai session web; mahasiswa memakai Bearer token mobile; mesin memakai API key perangkat.
- Copilot internal saat ini baca-saja melalui `AdminAiToolRegistry::READ_ONLY`.
- Tap mesin baru mendukung idempotensi `event_id`, sementara kompatibilitas firmware lama tanpa `event_id` dipertahankan.
- PBL mendukung membership baru dan legacy `users.tim` secara bersamaan.
- Reverb hanya mengirim wake-up; data tetap dari HTTP snapshot dan polling menjadi fallback.
- Schema loker dihapus oleh migration Oktober 2026, walaupun sisa istilah/kode loker masih ada.
- Overlay dashboard diatur `simalas.dashboard_demo`; nilai runtime tidak dibaca.

## 3. Usulan teknis yang belum disetujui

Berikut adalah bahan evaluasi, bukan backlog yang otomatis boleh dikerjakan:

- Verifikasi resolusi dua class command dengan signature `absensi:generate-alfa` yang sama sebelum mengubah penjadwalan.
- Konfirmasikan kontrak `/api/mobile/logs`: mahasiswa saja atau staf sesuai komentar route.
- Evaluasi strategi jangka panjang untuk dual representation PBL (`pbl_team_members` dan `users.tim`) tanpa merusak filter existing.
- Tinjau fallback `AsyncMailer` yang menulis OTP/email ke log aplikasi.
- Selaraskan teks helpdesk/constant loker dengan keputusan produk terkini sebagai tugas terpisah; belum ada izin penghapusan pada tahap ini.
- Cocokkan dukungan `event_id` pada firmware aktual dan dokumentasi perangkat.
- Periksa konsistensi versi APK pada pubspec, config mobile, override environment, dan artefak rilis.
- Tinjau satu per satu skrip manual `tests/Support/` sebelum penggunaan; jangan menggantinya otomatis dengan test lain.

## 4. Keputusan produk tambahan — tahap rencana 2026-10-24

### Lingkup dan perangkat

- SIMALAS fokus pada absensi. Smart locker sepenuhnya menjadi produk terpisah milik rekan pemilik dan tidak boleh dikembalikan ke SIMALAS.
- Sisa kode/teks loker hanya dicatat untuk tugas terpisah; keputusan ini bukan izin menghapusnya sekarang.
- Pemilik menyatakan firmware sudah pernah dipasang/diuji dan data berhasil diproses lokal. Ini informasi pemilik, bukan pengujian perangkat baru pada sesi ini.
- Kesamaan source firmware di repository dengan firmware fisik masih harus dikonfirmasi bila diperlukan.
- Fokus saat ini adalah lingkungan lokal dan akses HP pada jaringan lokal. Deployment publik, kontak VPS, perubahan tujuan server perangkat, penggantian API key, dan flash firmware tidak termasuk pekerjaan.

### Arah portal mahasiswa

- Arah jangka lanjut yang disetujui adalah mengganti Flutter dengan portal web mahasiswa responsif, karena hambatan unduhan APK dan dukungan iPhone.
- Portal mempunyai halaman login dan pengalaman yang berbeda dari portal laboran/admin/dosen, tetapi tetap memakai backend dan sumber data SIMALAS yang sama; tidak ada database mahasiswa kedua.
- Pemisahan UI/login tidak menggantikan role check, permission, dan kepemilikan data di backend.
- Website responsif adalah tahap pertama; PWA tahap lanjutan.
- Flutter dan endpoint mobile tidak boleh dihapus/dinonaktifkan sampai padanan web yang diperlukan lulus uji dan transisi disetujui.
- Absensi fingerprint tidak diubah menjadi absensi mandiri melalui web tanpa permintaan eksplisit.

Rincian rencana, bukan implementasi yang sudah tersedia: `student-web-transition.md`.

### Database

- PostgreSQL boleh dikaji sebagai calon pengganti MariaDB. Ini bukan izin migrasi/cutover, instalasi, export/import, atau perubahan konfigurasi.
- PostgreSQL tidak diasumsikan menghilangkan seluruh error. Kondisi pasca-repair dan penyebab gangguan tetap perlu diverifikasi.
- Backup pra-repair tetap dipertahankan; tidak ada repair tambahan atau kelanjutan recovery lama.

### Copilot

- Copilot boleh direncanakan menjadi asisten yang lebih berguna: bantuan penggunaan, analisis operasional, draf pengumuman/pesan, dan percakapan kontekstual.
- Mode baca-saja dipertahankan untuk tahap sekarang. Write action, perubahan data, dan pengiriman komunikasi memerlukan desain izin serta konfirmasi terpisah.
- Provider saat ini disebut pemilik sebagai Gemini API gratis; konfigurasi aktual belum diverifikasi.
- Jangan memperluas pengiriman data pribadi, menaruh secret pada frontend, atau membaca/menampilkan credential.

## 5. Target jangka lanjut versus izin implementasi

Target berikut adalah arah, bukan fitur yang sudah tersedia atau izin eksekusi:

- Portal web mahasiswa responsif, kemudian PWA bila padanan web terbukti.
- Eksperimen PostgreSQL pada lingkungan/database terisolasi setelah persetujuan.
- Peningkatan Copilot baca-saja secara bertahap.
- Baseline kode dengan Git dan cadangan rollback yang dapat diverifikasi.

Rencana dan dependensi: `development-plan.md`.

## 6. Usulan teknis yang tetap belum disetujui

- Pembersihan sisa kode/teks loker sebagai tugas terpisah.
- Resolusi command duplikat, kontrak `/api/mobile/logs`, strategi legacy PBL, fallback OTP ke log, dan konsistensi versi rilis.
- Perubahan write action Copilot atau pengiriman komunikasi melalui Copilot.
- Migrasi/cutover database, penonaktifan Flutter, atau perubahan absensi menjadi self-service web.

## 7. Pertanyaan yang masih terbuka

- Apakah `/api/mobile/logs` masih digunakan client tertentu, dan role apa yang seharusnya dilayani?
- Apakah firmware fisik berasal dari skeleton repository dan apakah sudah mengirim `event_id`?
- Kapan pemilik menyetujui pemeriksaan runtime terbatas dan pembentukan baseline Git?

Tidak ada jawaban yang diasumsikan dalam dokumen arsitektur.


## 8. Keputusan halaman login gabungan dan registrasi manual web — 2026-11-04

- Satu halaman login web gabungan (`/masuk`) digunakan untuk `Mahasiswa`, `Dosen`, dan `Admin / Laboran` (urutan dropdown tersebut, default `Mahasiswa`), menggantikan halaman login mahasiswa terpisah.
- Pendaftaran mandiri via web untuk admin/laboran dan dosen dihapus dari UI, pengaturan admin, dan dinonaktifkan pada endpoint backend; akun dibuat melalui import admin dan alur aktivasi kode sekali pakai (`/aktivasi`).
- Registrasi mandiri mahasiswa via APK mobile (`POST /api/mobile/register` dan pengaturan `RegistrationGate` untuk `mahasiswa`) tetap dipertahankan di luar cakupan perubahan ini.
- Nama route `mahasiswa.login` dipertahankan untuk kompatibilitas (`GET /mahasiswa/masuk` redirect ke `/masuk?role=mahasiswa`, `POST /mahasiswa/masuk` delegasi ke autentikasi gabungan). File `resources/views/student/auth/login.blade.php` dipertahankan sementara sebagai kandidat pembersihan pasca-verifikasi.


## 9. Keputusan alur akun mahasiswa hasil import — 2026-11-04

- Alur akun mahasiswa hasil import disederhanakan menjadi: import oleh admin/laboran → mahasiswa login dengan NIM + password sementara unik (`XXXX-XXXX-XXXX`) → wajib membuat password pribadi baru (`/mahasiswa/ganti-password`) → langsung masuk ke beranda (`/mahasiswa/beranda`) → pengisian/verifikasi email & nomor HP bersifat opsional dengan pengingat di beranda yang dapat ditutup per sesi.
- Status wajib ganti password disimpan secara eksplisit melalui kolom `users.must_change_password` (`boolean`, `default(false)`), bukan diinferensikan dari log untuk akun aktif biasa.
- Pemulihan password mandiri via OTP email hanya diizinkan bagi akun yang sudah memiliki `email` terverifikasi (`email_verified_at !== null`); mahasiswa yang belum mengisi/memverifikasi email melakukan reset password melalui admin/laboran (`POST /admin/users/{user}/activation-code` dengan izin `users.update`).
- Alur aktivasi `/aktivasi` untuk `dosen` dan `laboran` hasil import tetap dipertahankan.


## 10. Keputusan pembaruan Portal Mahasiswa & aturan jadwal absensi 3 hari (9 SKS) — 2026-11-04

- Kerangka navigasi portal mahasiswa menggunakan sidebar kiri yang dapat diperkecil (drawer di mobile) dengan 3 menu utama (`Beranda`, `Absensi Saya`, `Riwayat Bantuan`). Menu `Tim PBL` disembunyikan sementara dari navigasi dan beranda tanpa menghapus data/fungsi backend PBL.
- Notifikasi diakses dari ikon lonceng topbar (panel slide-over + modal detail aksesibel yang menandai baca per item saat dibuka) tanpa menu navigasi utama terpisah.
- Jadwal absensi mendukung aturan mingguan per hari (termasuk skema 3 hari / 9 SKS: Rabu `07:50–17:00`, Kamis `07:50–17:00`, Jumat `07:50–12:00`) dengan tanggal efektif (`effective_from`) agar riwayat sebelum tanggal tersebut tidak berubah otomatis, serta kalender pengecualian (`libur`/`batal_wajib`, `perubahan_jam`/`ubah_jam`, dan `ujian`/`informasi` dengan dampak `tetap_wajib` yang tidak otomatis membatalkan kewajiban absensi).
- Tap awal, terlambat, dan pulang lebih awal (`pulang_cepat`) tetap dicatat oleh backend; penilaian **Kelengkapan** (`Lengkap`, `Tidak Lengkap`, `Alfa`) dipisahkan dari **Ketepatan Waktu**, dan tap pulang melewati jam jadwal ditampilkan sebagai `"Melewati jam jadwal"` (bukan `"Lembur"`) pada permukaan mahasiswa.
- Indikator `slot_lab` (`10 x 55 menit`) dipertahankan sebagai informasi sekunder waktu masuk sesi pagi (bukan 10 kali wajib tap); rincian jumlah slot khusus hari Jumat (`07:50–12:00`) dapat dikonfirmasi pemilik tanpa menghambat jadwal efektif harian.
- Chat AI bantuan mahasiswa bersifat informatif/read-only dan terpisah tegas dari pengiriman laporan resmi yang memerlukan konfirmasi mahasiswa dan menghasilkan nomor referensi tiket (`#ID`).
- Mahasiswa yang sudah login dapat mengganti password secara mandiri dengan verifikasi `current_password` tanpa perlu persetujuan admin atau OTP.

## 11. Rencana (disetujui sebagai konsep): Grup Chat Tim PBL — 2026
- Pemilik menyetujui grup chat tim PBL sebagai **rancangan/improvement** dulu, bukan implementasi.
- Peserta yang direncanakan: manpro/co-manpro, ketua tim, wakil, dan anggota tim.
- Saat ini hanya item sidebar "Grup Chat Tim" bertanda `Soon` yang memunculkan SweetAlert.
- Bila diimplementasikan kelak: backend realtime (Reverb), pesan per proyek PBL, otorisasi via PblTeamService/managesProject; jangan bypass hak manpro.

## 12. Git baseline + workflow GitHub dua repo — 2026-11-14

- Project disimpan di GitHub atas akun `raldoputradev` dengan dua repo terpisah:
  - `raldoputradev/smartbrail` (**private**) = repo kerja; seluruh development + histori detail di sini.
  - `raldoputradev/smartbrail-showcase` (**public**) = etalase untuk CV/HR; disinkron dari `main` per milestone atas permintaan pemilik.
- Model kolaborasi (dipilih pemilik): **Fork + PR ketat** — anggota tim fork repo private, membuka PR kembali; hanya pemilik yang merge ke `main`. (Ruleset wajib-review tidak tersedia di tier gratis untuk repo private; model fork membuat main secara teknis tidak bisa ditulis anggota.)
- Identitas commit lokal: `Rayendra Aldo Putra <raldoputradev@users.noreply.github.com>` (noreply GitHub agar email asli tidak tersebar di repo public).
- Atribusi AI: commit oleh Devin di repo private boleh membawa penanda `Co-Authored-By: Devin`; di repo **public** penanda dibersihkan agar histori tampil atas nama pemilik (keputusan eksplisit pemilik).
- Konvensi commit: prefix `feat:`/`fix:`/`ui:`/`docs:`/`chore:` Bahasa Indonesia. Panduan tim di `CONTRIBUTING.md`, template PR di `.github/pull_request_template.md`.
- Remote lokal: `origin` → private, `showcase` → public. Baseline commit pertama: `chore: baseline SmartBrail` (tanpa trailer Devin karena snapshot milik pemilik).
- Keamanan: `.gitignore` mengecualikan `.env`, `vendor`, `node_modules`, `public/build`, `storage/*.key`; audit pre-push menemukan 0 secrets ter-stage.
