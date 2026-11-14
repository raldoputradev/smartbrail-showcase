# Histori: Keputusan Produk dan Rencana Pengembangan

Tanggal: 2026-10-24.

## Ruang lingkup

Tahap ini melanjutkan dokumentasi arsitektur tanpa mengulang pemetaan seluruh repository. Hanya dokumen existing dan kode yang dibutuhkan untuk transisi mahasiswa, baseline, PostgreSQL, dan Copilot yang dibaca. Tidak ada runtime check, query, request, test, build, migration, repair, atau perubahan layanan.

## Keputusan pemilik yang dicatat

- SIMALAS fokus absensi; smart locker produk terpisah dan tidak dikembalikan.
- Portal web mahasiswa responsif menjadi arah pengganti Flutter setelah padanan dan transisi lulus; PWA kemudian.
- Backend/database yang sama dipertahankan; Flutter dan endpoint mobile belum dinonaktifkan; absensi tidak menjadi self-service web.
- Pengembangan berfokus lokal/LAN, tanpa VPS/deployment/flash/perubahan perangkat.
- PostgreSQL hanya untuk kajian/eksperimen terisolasi, bukan migrasi/cutover.
- Copilot diperluas secara bertahap tetapi tetap baca-saja dan tidak memperluas data pribadi ke provider.

Informasi bahwa perangkat sudah pernah bekerja berasal dari pemilik, bukan pengujian baru.

## Verifikasi tambahan

- Root tetap bukan repository Git; `.gitignore` root/database/mobile/mesin ditinjau tanpa membaca secret.
- Flutter aktif memiliki login/registrasi/OTP/reset, dashboard, absensi, profil/foto/email, PBL view, pengumuman/notifikasi, saran, helpdesk, pengaturan dan integrasi push/update.
- Edit akademik dan delete foto memiliki jalur UI, tetapi backend mobile menolaknya; rencana tidak menganggap fungsi tersebut berhasil.
- Web login/registrasi saat ini untuk staff/dosen; aktivasi dan profile controller sebagian dapat digunakan kembali.
- Risiko PostgreSQL utama: SQLSTATE unique violation dan migration `ALTER TABLE ... MODIFY` khusus MySQL/no-op pada driver lain. Enum/JSON/UUID/index/FK/lock dan laporan perlu eksperimen terpisah.
- Copilot memanggil Gemini dari backend; JS hanya memanggil route aplikasi. Tool rekap/analisis/riwayat/role/privasi sudah ada dan tidak diusulkan ulang.

## Dokumen yang diubah/dibuat

- Diperbarui: `docs/agent/decisions.md`, `docs/agent/current-state.md`.
- Dibuat: `docs/agent/development-plan.md`, `docs/agent/student-web-transition.md`.
- Dibuat: catatan histori ini.

## Belum diverifikasi

Status runtime/database, schema aktual, konfigurasi provider, hasil test/build, firmware fisik/source equivalence, dan seluruh raw SQL/migration. Rencana HTTP smoke secara eksplisit mengecualikan `/masuk` secara default karena `AuthController::showAuthPage` dapat memprune token OTP; GET tidak dianggap otomatis bebas efek samping.

## Pernyataan perubahan

Hanya dokumentasi yang diizinkan yang berubah. Tidak ada source code aplikasi, dependensi, `.gitignore`, konfigurasi runtime, database, firmware, backup, atau status layanan yang diubah. Tidak ada Git init/stage/commit/push. Recovery lama tetap dihentikan.
