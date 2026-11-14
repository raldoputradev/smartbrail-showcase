# Histori: Pemetaan Arsitektur Awal

Tanggal: 2026-10-24.

## Ruang lingkup

Pemetaan read-only terhadap root `C:\xampp\htdocs\Simalas`, dilanjutkan dengan pembuatan dokumentasi yang diminta. Tidak ada recovery database, perubahan aplikasi, operasi layanan, test, build, atau panggilan layanan eksternal.

Root bukan repository Git saat diperiksa, sehingga tidak ada commit ID atau baseline status Git. Tidak ditemukan `AGENTS.md` atau `docs/agent/` sebelum tugas ini.

## Kelompok yang ditelusuri

- Manifest PHP/JavaScript/Flutter, `bootstrap/app.php`, route web/API/channel, dan middleware.
- Model serta migration terpilih untuk akun, absensi, PBL, permission, AI, dan penghapusan loker.
- Registrasi/login/OTP/aktivasi/import/approval/permission.
- Mesin/fingerprint/tap/idempotensi/status harian/penutupan/koreksi/rekap/laporan/ekspor.
- PBL, scope dosen, Flutter mobile, pengumuman/saran/notifikasi/email/FCM.
- Copilot UI/SSE/orchestrator/Gemini/tool/privasi/data.
- Scheduler, Reverb, konfigurasi PHPUnit, dan skrip dukungan test terpilih.

Pembacaan tidak mencakup semua baris setiap file. Vendor, dependency generated, data runtime, credential, dump, record pengguna, template fingerprint, dan log aktual tidak dibaca.

## Temuan utama

- Sistem existing memiliki web staff, API mobile, API mesin, Flutter, dan skeleton firmware dengan aturan bisnis bersama di Support/Services.
- Absensi memisahkan log tap, status harian, side-record, dan receipt idempoten.
- PBL mempertahankan model membership baru dan legacy `users.tim`.
- Copilot memiliki function calling dan adapter ke sumber rekap/laporan yang sama, dengan mode baca-saja dan filter privasi.
- Overlay dummy dashboard tidak berlaku sebagai bukti bahwa data operasional lain dummy.
- Reverb hanya wake-up setelah commit; polling tetap menjadi fallback.
- Terdapat area yang perlu verifikasi: command duplikat, kontrak mobile logs, sisa loker, fallback OTP ke log, dan firmware tanpa `event_id`.

## Dokumen yang dibuat

- `AGENTS.md`
- `docs/agent/architecture.md`
- `docs/agent/current-state.md`
- `docs/agent/decisions.md`
- `docs/agent/review-summary.md`
- `docs/agent/modules/accounts-access.md`
- `docs/agent/modules/attendance-iot.md`
- `docs/agent/modules/mobile-pbl-communication.md`
- `docs/agent/modules/copilot.md`
- Catatan histori ini.

## Belum diperiksa/diverifikasi

Status runtime dan database, schema aktual, semua UI/controller/command, versi package terpasang, firmware fisik, serta hasil test/build. Test tidak dijalankan. Skrip `tests/Support/` tertentu bersifat mutatif dan tidak boleh diasumsikan aman.

## Pernyataan perubahan

Hanya dokumentasi pada `AGENTS.md` dan `docs/agent/` yang dibuat. Tidak ada source code aplikasi, dependensi, konfigurasi runtime, database, backup, atau status layanan yang diubah. Tidak ada perubahan Git karena root tidak memiliki repository Git.
