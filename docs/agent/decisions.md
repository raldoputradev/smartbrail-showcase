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

- Mahasiswa diarahkan ke aplikasi Flutter; portal web mahasiswa dinonaktifkan (`routes/web.php`).
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
