# SIMALAS — Panduan Agent

SIMALAS adalah aplikasi existing; kembangkan melalui tugas kecil yang disetujui pemilik, bukan membangun ulang.

## Urutan baca

1. `docs/agent/current-state.md` — kondisi terakhir, batas operasional, dan hal yang belum diverifikasi.
2. `docs/agent/architecture.md` — peta sistem, modul, data, dan alur utama.
3. `docs/agent/decisions.md` — keputusan pemilik versus usulan teknis yang belum disetujui.
4. Rincian sesuai tugas di `docs/agent/modules/`.
5. `docs/agent/review-summary.md` — ringkasan mandiri untuk reviewer.
6. `docs/agent/history/` — riwayat pemetaan/perubahan dokumentasi, bukan pengganti kode.

Kode dan konfigurasi aktual tetap menjadi sumber kebenaran implementasi. Dokumen lama dapat kedaluwarsa; periksa kode relevan sebelum mengubahnya.

## Aturan keselamatan

- Jangan membaca `.env`, private key, credential, dump database, record pengguna, template/citra fingerprint, atau log yang berpotensi memuat data pribadi tanpa izin eksplisit.
- Jangan menampilkan API key, password, OTP, token, atau isi backup. Gunakan nama kunci konfigurasi, bukan nilainya.
- Jangan menjalankan migration, seeder, repair/recovery, reset database, queue worker, scheduler, deployment, atau perintah operasional tanpa persetujuan spesifik.
- Jangan memanggil Gemini, email, Firebase, perangkat IoT, atau layanan eksternal dari pemeriksaan lokal tanpa persetujuan.
- Jangan menjalankan test secara otomatis. Tinjau `phpunit.xml`, test target, fixture, dan efek sampingnya terlebih dahulu. Skrip di `tests/Support/` tidak setara dengan PHPUnit dan dapat mengubah database/log.
- Jangan mengubah backup. Jangan menghapus atau mengganti `mysql/data`, file InnoDB/Aria, control file, atau log database.
- Hindari membaca massal `vendor/`, `node_modules/`, `public/build/`, cache, dan generated output. Gunakan manifest/lockfile untuk dependensi.
- Jangan melakukan perubahan Git tanpa permintaan pemilik. Root ini tidak memiliki metadata Git saat pemetaan 2026-10-24.
- Recovery database sebelumnya dihentikan. Jangan melanjutkannya berdasarkan dokumen ini; lihat batas di `current-state.md`.

## Sebelum bekerja

- Tentukan lingkup kecil dan persetujuan pemilik.
- Baca route/entry point, pemanggil, model/support, dan test yang relevan; jangan mengandalkan daftar file saja.
- Periksa apakah ada representasi legacy dan implementasi baru yang sama-sama masih digunakan, khususnya PBL dan migration historis.
- Jangan menganggap data berlabel CONTOH berarti fitur belum selesai. Overlay dashboard disengaja dan terpisah dari banyak alur data operasional.
- Bedakan dengan eksplisit: ditemukan dari kode, informasi pemilik, sudah dieksekusi/diuji, dan belum diverifikasi.

## Setelah pekerjaan bermakna

- Perbarui `current-state.md` dengan perubahan nyata dan validasi yang benar-benar dilakukan.
- Perbarui `architecture.md` atau dokumen modul bila hubungan komponen berubah.
- Tambahkan keputusan pemilik baru ke `decisions.md`; jangan mengubah usulan menjadi keputusan.
- Tambahkan catatan singkat di `docs/agent/history/` untuk perubahan arsitektur atau pemetaan besar.
- Jangan menduplikasi rincian yang sama ke semua dokumen; tautkan ke dokumen utama.
